<?php

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Models\Order;
use App\Models\Invoice;
use App\Models\PaymentTransaction;
use App\Models\PaymentMethod;
use App\Support\Facades\Auth;
use App\Support\Facades\Session;
use App\Validation\Validator;

class PaymentController
{
    private $orderModel;
    private $invoiceModel;
    private $paymentTransactionModel;
    private $paymentMethodModel;

    public function __construct()
    {
        $this->orderModel = new Order();
        $this->invoiceModel = new Invoice();
        $this->paymentTransactionModel = new PaymentTransaction();
        $this->paymentMethodModel = new PaymentMethod();
    }

    /**
     * Display payment interface for an order
     */
    public function index(Request $request, int $orderId): Response
    {
        // Check permissions
        if (!Auth::hasPermission('payments_process')) {
            return Response::error('Unauthorized access', 403);
        }

        $order = $this->orderModel->findWithDetails($orderId);
        if (!$order) {
            return Response::error('الطلب غير موجود', 404);
        }

        // Check if order is ready for payment
        if (!in_array($order->status, ['ready', 'served'])) {
            return Response::error('الطلب غير جاهز للدفع', 422);
        }

        // Get or create invoice
        $invoice = $this->invoiceModel->findByOrderId($orderId);
        if (!$invoice) {
            $invoiceId = $this->createInvoice($order);
            $invoice = $this->invoiceModel->find($invoiceId);
        }

        // Get available payment methods
        $paymentMethods = $this->paymentMethodModel->getActiveMethods();

        // Get existing payment transactions
        $transactions = $this->paymentTransactionModel->getByInvoiceId($invoice->id);

        $data = [
            'order' => $order,
            'invoice' => $invoice,
            'payment_methods' => $paymentMethods,
            'transactions' => $transactions,
            'remaining_amount' => $invoice->total_amount - $this->calculatePaidAmount($invoice->id),
            'user' => Auth::user()
        ];

        return Response::view('payment.index', $data);
    }

    /**
     * Process payment for an order
     */
    public function processPayment(Request $request, int $orderId): Response
    {
        // Check permissions
        if (!Auth::hasPermission('payments_process')) {
            return Response::error('Unauthorized access', 403);
        }

        $validator = new Validator($request->all(), [
            'payment_method_id' => 'required|exists:payment_methods,id',
            'amount' => 'required|numeric|min:0.01',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500'
        ]);

        if (!$validator->passes()) {
            return Response::error($validator->errors(), 422);
        }

        $order = $this->orderModel->find($orderId);
        if (!$order) {
            return Response::error('الطلب غير موجود', 404);
        }

        $invoice = $this->invoiceModel->findByOrderId($orderId);
        if (!$invoice) {
            return Response::error('الفاتورة غير موجودة', 404);
        }

        $paymentMethod = $this->paymentMethodModel->find($request->input('payment_method_id'));
        if (!$paymentMethod || !$paymentMethod->is_active) {
            return Response::error('طريقة الدفع غير متاحة', 422);
        }

        $amount = $request->input('amount');
        $referenceNumber = $request->input('reference_number');
        $notes = $request->input('notes');

        // Check if amount is valid
        $remainingAmount = $invoice->total_amount - $this->calculatePaidAmount($invoice->id);
        if ($amount > $remainingAmount + 0.01) { // Allow 0.01 SAR tolerance
            return Response::error('المبلغ المدفوع أكبر من المبلغ المطلوب', 422);
        }

        // Start transaction
        try {
            $this->db->beginTransaction();

            // Create payment transaction
            $transactionData = [
                'invoice_id' => $invoice->id,
                'payment_method_id' => $paymentMethod->id,
                'amount' => $amount,
                'reference_number' => $referenceNumber,
                'status' => 'completed',
                'notes' => $notes,
                'processed_by' => Auth::id(),
                'processed_at' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s')
            ];

            $transactionId = $this->paymentTransactionModel->create($transactionData);

            // Check if invoice is fully paid
            $newPaidAmount = $this->calculatePaidAmount($invoice->id);
            if ($newPaidAmount >= $invoice->total_amount) {
                // Mark invoice as paid
                $this->invoiceModel->update($invoice->id, [
                    'status' => 'paid',
                    'paid_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);

                // Update order status
                $this->orderModel->update($orderId, [
                    'status' => 'paid',
                    'paid_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);

                // Free the table
                if ($order->table_id) {
                    $tableModel = new \App\Models\Table();
                    $tableModel->update($order->table_id, [
                        'status' => 'available',
                        'current_order_id' => null
                    ]);
                }

                $paymentComplete = true;
            } else {
                $paymentComplete = false;
            }

            $this->db->commit();

            return Response::json([
                'success' => true,
                'message' => 'تم تسجيل الدفعة بنجاح',
                'transaction_id' => $transactionId,
                'payment_complete' => $paymentComplete,
                'remaining_amount' => $invoice->total_amount - $newPaidAmount
            ]);

        } catch (\Exception $e) {
            $this->db->rollBack();
            return Response::error('فشل في معالجة الدفعة', 500);
        }
    }

    /**
     * Refund payment transaction
     */
    public function refund(Request $request, int $transactionId): Response
    {
        // Check permissions
        if (!Auth::hasPermission('payments_refund')) {
            return Response::error('Unauthorized access', 403);
        }

        $validator = new Validator($request->all(), [
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'required|string|max:500'
        ]);

        if (!$validator->passes()) {
            return Response::error($validator->errors(), 422);
        }

        $transaction = $this->paymentTransactionModel->find($transactionId);
        if (!$transaction) {
            return Response::error('المعاملة غير موجودة', 404);
        }

        if ($transaction->status !== 'completed') {
            return Response::error('لا يمكن استرداد هذه المعاملة', 422);
        }

        if ($transaction->amount < $request->input('amount')) {
            return Response::error('مبلغ الاسترداد أكبر من المبلغ المدفوع', 422);
        }

        try {
            $this->db->beginTransaction();

            // Create refund transaction
            $refundData = [
                'invoice_id' => $transaction->invoice_id,
                'payment_method_id' => $transaction->payment_method_id,
                'amount' => -$request->input('amount'), // Negative for refund
                'reference_number' => 'REF_' . $transaction->reference_number,
                'status' => 'refunded',
                'notes' => 'استرداد: ' . $request->input('reason'),
                'processed_by' => Auth::id(),
                'processed_at' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s')
            ];

            $refundId = $this->paymentTransactionModel->create($refundData);

            // Update original transaction status if fully refunded
            $newRefundAmount = $this->getRefundAmount($transactionId) + $request->input('amount');
            if ($newRefundAmount >= $transaction->amount) {
                $this->paymentTransactionModel->update($transactionId, [
                    'status' => 'refunded',
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
            }

            // Check if invoice needs status update
            $invoice = $this->invoiceModel->find($transaction->invoice_id);
            $paidAmount = $this->calculatePaidAmount($invoice->id);
            
            if ($paidAmount < $invoice->total_amount) {
                $this->invoiceModel->update($invoice->id, [
                    'status' => 'partial',
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
            }

            $this->db->commit();

            return Response::json([
                'success' => true,
                'message' => 'تم استرداد المبلغ بنجاح',
                'refund_id' => $refundId
            ]);

        } catch (\Exception $e) {
            $this->db->rollBack();
            return Response::error('فشل في استرداد المبلغ', 500);
        }
    }

    /**
     * Split payment between multiple methods
     */
    public function splitPayment(Request $request, int $orderId): Response
    {
        // Check permissions
        if (!Auth::hasPermission('payments_process')) {
            return Response::error('Unauthorized access', 403);
        }

        $validator = new Validator($request->all(), [
            'payments' => 'required|array|min:1',
            'payments.*.payment_method_id' => 'required|exists:payment_methods,id',
            'payments.*.amount' => 'required|numeric|min:0.01'
        ]);

        if (!$validator->passes()) {
            return Response::error($validator->errors(), 422);
        }

        $order = $this->orderModel->find($orderId);
        if (!$order) {
            return Response::error('الطلب غير موجود', 404);
        }

        $invoice = $this->invoiceModel->findByOrderId($orderId);
        if (!$invoice) {
            return Response::error('الفاتورة غير موجودة', 404);
        }

        $payments = $request->input('payments');
        $totalAmount = array_sum(array_column($payments, 'amount'));
        $remainingAmount = $invoice->total_amount - $this->calculatePaidAmount($invoice->id);

        if (abs($totalAmount - $remainingAmount) > 0.01) {
            return Response::error('مجموع المبالغ لا يساوي المبلغ المطلوب', 422);
        }

        try {
            $this->db->beginTransaction();

            $transactionIds = [];

            foreach ($payments as $payment) {
                $transactionData = [
                    'invoice_id' => $invoice->id,
                    'payment_method_id' => $payment['payment_method_id'],
                    'amount' => $payment['amount'],
                    'status' => 'completed',
                    'processed_by' => Auth::id(),
                    'processed_at' => date('Y-m-d H:i:s'),
                    'created_at' => date('Y-m-d H:i:s')
                ];

                $transactionId = $this->paymentTransactionModel->create($transactionData);
                $transactionIds[] = $transactionId;
            }

            // Check if invoice is fully paid
            $newPaidAmount = $this->calculatePaidAmount($invoice->id);
            if ($newPaidAmount >= $invoice->total_amount) {
                $this->invoiceModel->update($invoice->id, [
                    'status' => 'paid',
                    'paid_at' => date('Y-m-d H:i:s')
                ]);

                $this->orderModel->update($orderId, [
                    'status' => 'paid',
                    'paid_at' => date('Y-m-d H:i:s')
                ]);
            }

            $this->db->commit();

            return Response::json([
                'success' => true,
                'message' => 'تم تقسيم الدفعة بنجاح',
                'transaction_ids' => $transactionIds
            ]);

        } catch (\Exception $e) {
            $this->db->rollBack();
            return Response::error('فشل في تقسيم الدفعة', 500);
        }
    }

    /**
     * Apply discount to invoice
     */
    public function applyDiscount(Request $request, int $orderId): Response
    {
        // Check permissions
        if (!Auth::hasPermission('payments_discount')) {
            return Response::error('Unauthorized access', 403);
        }

        $validator = new Validator($request->all(), [
            'discount_type' => 'required|in:percentage,fixed',
            'discount_value' => 'required|numeric|min:0',
            'reason' => 'required|string|max:200'
        ]);

        if (!$validator->passes()) {
            return Response::error($validator->errors(), 422);
        }

        $order = $this->orderModel->find($orderId);
        if (!$order) {
            return Response::error('الطلب غير موجود', 404);
        }

        $invoice = $this->invoiceModel->findByOrderId($orderId);
        if (!$invoice) {
            return Response::error('الفاتورة غير موجودة', 404);
        }

        // Check if invoice already has discounts
        if ($invoice->discount_amount > 0) {
            return Response::error('تم تطبيق خصم مسبقاً على هذه الفاتورة', 422);
        }

        $discountType = $request->input('discount_type');
        $discountValue = $request->input('discount_value');
        $reason = $request->input('reason');

        $discountAmount = 0;
        if ($discountType === 'percentage') {
            if ($discountValue > 100) {
                return Response::error('نسبة الخصم لا يمكن أن تكون أكبر من 100%', 422);
            }
            $discountAmount = ($invoice->subtotal * $discountValue) / 100;
        } else {
            $discountAmount = $discountValue;
        }

        if ($discountAmount >= $invoice->subtotal) {
            return Response::error('قيمة الخصم أكبر من المبلغ المطلوب', 422);
        }

        // Update invoice with discount
        $newSubtotal = $invoice->subtotal - $discountAmount;
        $newTaxAmount = $newSubtotal * 0.15;
        $newTotalAmount = $newSubtotal + $newTaxAmount;

        $this->invoiceModel->update($invoice->id, [
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'discount_amount' => $discountAmount,
            'discount_reason' => $reason,
            'subtotal' => $newSubtotal,
            'tax_amount' => $newTaxAmount,
            'total_amount' => $newTotalAmount,
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        return Response::json([
            'success' => true,
            'message' => 'تم تطبيق الخصم بنجاح',
            'new_total' => $newTotalAmount,
            'discount_amount' => $discountAmount
        ]);
    }

    /**
     * Print payment receipt
     */
    public function printReceipt(Request $request, int $transactionId): Response
    {
        // Check permissions
        if (!Auth::hasPermission('payments_print')) {
            return Response::error('Unauthorized access', 403);
        }

        $transaction = $this->paymentTransactionModel->findWithDetails($transactionId);
        if (!$transaction) {
            return Response::error('المعاملة غير موجودة', 404);
        }

        $data = [
            'transaction' => $transaction,
            'invoice' => $this->invoiceModel->find($transaction->invoice_id),
            'order' => $this->orderModel->find($transaction->invoice->order_id),
            'payment_method' => $this->paymentMethodModel->find($transaction->payment_method_id),
            'print_time' => date('Y-m-d H:i:s')
        ];

        return Response::view('payment.receipt', $data);
    }

    /**
     * Get payment history for an order
     */
    public function getPaymentHistory(Request $request, int $orderId): Response
    {
        // Check permissions
        if (!Auth::hasPermission('payments_view')) {
            return Response::error('Unauthorized access', 403);
        }

        $order = $this->orderModel->find($orderId);
        if (!$order) {
            return Response::error('الطلب غير موجود', 404);
        }

        $invoice = $this->invoiceModel->findByOrderId($orderId);
        if (!$invoice) {
            return Response::json(['payments' => []]);
        }

        $payments = $this->paymentTransactionModel->getByInvoiceId($invoice->id);

        return Response::json([
            'success' => true,
            'payments' => $payments,
            'invoice_total' => $invoice->total_amount,
            'paid_amount' => $this->calculatePaidAmount($invoice->id),
            'remaining_amount' => $invoice->total_amount - $this->calculatePaidAmount($invoice->id)
        ]);
    }

    /**
     * Create invoice for order
     */
    private function createInvoice($order): int
    {
        $subtotal = $order->subtotal;
        $taxAmount = $order->tax_amount;
        $totalAmount = $order->total_amount;

        $invoiceData = [
            'order_id' => $order->id,
            'invoice_number' => $this->generateInvoiceNumber(),
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s')
        ];

        return $this->invoiceModel->create($invoiceData);
    }

    /**
     * Generate unique invoice number
     */
    private function generateInvoiceNumber(): string
    {
        $prefix = 'INV';
        $year = date('Y');
        $month = date('m');
        $day = date('d');
        
        // Get the last invoice number for today
        $lastInvoice = $this->invoiceModel->getLastInvoiceForToday();
        
        if ($lastInvoice) {
            // Extract sequence number from last invoice
            $parts = explode('-', $lastInvoice->invoice_number);
            $sequence = (int) end($parts) + 1;
        } else {
            $sequence = 1;
        }

        return sprintf('%s-%s%s%s-%04d', $prefix, $year, $month, $day, $sequence);
    }

    /**
     * Calculate total paid amount for invoice
     */
    private function calculatePaidAmount(int $invoiceId): float
    {
        return $this->paymentTransactionModel->getTotalPaidAmount($invoiceId);
    }

    /**
     * Get total refund amount for transaction
     */
    private function getRefundAmount(int $transactionId): float
    {
        return $this->paymentTransactionModel->getRefundAmount($transactionId);
    }

    /**
     * Get dashboard payment statistics
     */
    public function getPaymentStats(Request $request): Response
    {
        // Check permissions
        if (!Auth::hasPermission('payments_view')) {
            return Response::error('Unauthorized access', 403);
        }

        $period = $request->input('period', 'today');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $stats = $this->paymentTransactionModel->getPaymentStatistics($period, $dateFrom, $dateTo);

        return Response::json([
            'success' => true,
            'statistics' => $stats
        ]);
    }
}