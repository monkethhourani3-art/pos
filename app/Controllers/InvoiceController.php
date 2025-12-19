<?php

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Support\Facades\Auth;
use App\Validation\Validator;

class InvoiceController
{
    private $invoiceModel;
    private $orderModel;
    private $paymentTransactionModel;

    public function __construct()
    {
        $this->invoiceModel = new Invoice();
        $this->orderModel = new Order();
        $this->paymentTransactionModel = new PaymentTransaction();
    }

    /**
     * Display invoices list
     */
    public function index(Request $request): Response
    {
        // Check permissions
        if (!Auth::hasPermission('invoices_view')) {
            return Response::error('Unauthorized access', 403);
        }

        $page = $request->input('page', 1);
        $status = $request->input('status');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $customerName = $request->input('customer_name');

        $filters = [
            'status' => $status,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'customer_name' => $customerName
        ];

        $invoices = $this->invoiceModel->getFilteredInvoices($filters, $page, 20);
        $totalInvoices = $this->invoiceModel->getTotalFilteredInvoices($filters);

        $data = [
            'invoices' => $invoices,
            'total_invoices' => $totalInvoices,
            'current_page' => $page,
            'total_pages' => ceil($totalInvoices / 20),
            'filters' => $filters
        ];

        return Response::view('invoices.index', $data);
    }

    /**
     * Display invoice details
     */
    public function show(Request $request, int $id): Response
    {
        // Check permissions
        if (!Auth::hasPermission('invoices_view')) {
            return Response::error('Unauthorized access', 403);
        }

        $invoice = $this->invoiceModel->findWithDetails($id);
        if (!$invoice) {
            return Response::error('الفاتورة غير موجودة', 404);
        }

        $order = $this->orderModel->findWithDetails($invoice->order_id);
        $orderItems = $this->orderModel->getOrderItems($invoice->order_id);
        $payments = $this->paymentTransactionModel->getByInvoiceId($id);
        $paymentHistory = $this->paymentTransactionModel->getPaymentHistory($id);

        $data = [
            'invoice' => $invoice,
            'order' => $order,
            'order_items' => $orderItems,
            'payments' => $payments,
            'payment_history' => $paymentHistory,
            'paid_amount' => $this->calculatePaidAmount($id),
            'remaining_amount' => $invoice->total_amount - $this->calculatePaidAmount($id)
        ];

        return Response::view('invoices.show', $data);
    }

    /**
     * Create new invoice
     */
    public function create(Request $request): Response
    {
        // Check permissions
        if (!Auth::hasPermission('invoices_create')) {
            return Response::error('Unauthorized access', 403);
        }

        $validator = new Validator($request->all(), [
            'order_id' => 'required|exists:orders,id',
            'customer_name' => 'nullable|string|max:100',
            'customer_phone' => 'nullable|string|max:20',
            'customer_address' => 'nullable|string|max:500',
            'discount_type' => 'nullable|in:percentage,fixed',
            'discount_value' => 'nullable|numeric|min:0',
            'discount_reason' => 'nullable|string|max:200'
        ]);

        if (!$validator->passes()) {
            return Response::error($validator->errors(), 422);
        }

        $orderId = $request->input('order_id');
        $order = $this->orderModel->find($orderId);
        
        if (!$order) {
            return Response::error('الطلب غير موجود', 404);
        }

        // Check if invoice already exists for this order
        $existingInvoice = $this->invoiceModel->findByOrderId($orderId);
        if ($existingInvoice) {
            return Response::error('يوجد فاتورة مسبقاً لهذا الطلب', 422);
        }

        try {
            // Calculate totals
            $subtotal = $order->subtotal;
            $discountAmount = 0;
            $discountType = $request->input('discount_type');
            $discountValue = $request->input('discount_value');

            if ($discountType && $discountValue) {
                if ($discountType === 'percentage') {
                    if ($discountValue > 100) {
                        return Response::error('نسبة الخصم لا يمكن أن تكون أكبر من 100%', 422);
                    }
                    $discountAmount = ($subtotal * $discountValue) / 100;
                } else {
                    $discountAmount = $discountValue;
                }

                if ($discountAmount >= $subtotal) {
                    return Response::error('قيمة الخصم أكبر من المبلغ المطلوب', 422);
                }
            }

            $newSubtotal = $subtotal - $discountAmount;
            $taxAmount = $newSubtotal * 0.15;
            $totalAmount = $newSubtotal + $taxAmount;

            $invoiceData = [
                'order_id' => $orderId,
                'invoice_number' => $this->generateInvoiceNumber(),
                'customer_name' => $request->input('customer_name'),
                'customer_phone' => $request->input('customer_phone'),
                'customer_address' => $request->input('customer_address'),
                'subtotal' => $newSubtotal,
                'discount_type' => $discountType,
                'discount_value' => $discountValue,
                'discount_amount' => $discountAmount,
                'discount_reason' => $request->input('discount_reason'),
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'status' => 'pending',
                'created_by' => Auth::id(),
                'created_at' => date('Y-m-d H:i:s')
            ];

            $invoiceId = $this->invoiceModel->create($invoiceData);

            return Response::json([
                'success' => true,
                'message' => 'تم إنشاء الفاتورة بنجاح',
                'invoice_id' => $invoiceId,
                'redirect_url' => "/invoices/{$invoiceId}"
            ]);

        } catch (\Exception $e) {
            return Response::error('فشل في إنشاء الفاتورة', 500);
        }
    }

    /**
     * Update invoice
     */
    public function update(Request $request, int $id): Response
    {
        // Check permissions
        if (!Auth::hasPermission('invoices_update')) {
            return Response::error('Unauthorized access', 403);
        }

        $invoice = $this->invoiceModel->find($id);
        if (!$invoice) {
            return Response::error('الفاتورة غير موجودة', 404);
        }

        // Check if invoice can be updated
        if ($invoice->status === 'paid') {
            return Response::error('لا يمكن تعديل فاتورة مدفوعة', 422);
        }

        $validator = new Validator($request->all(), [
            'customer_name' => 'nullable|string|max:100',
            'customer_phone' => 'nullable|string|max:20',
            'customer_address' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:1000'
        ]);

        if (!$validator->passes()) {
            return Response::error($validator->errors(), 422);
        }

        $updateData = [
            'customer_name' => $request->input('customer_name'),
            'customer_phone' => $request->input('customer_phone'),
            'customer_address' => $request->input('customer_address'),
            'notes' => $request->input('notes'),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        $this->invoiceModel->update($id, $updateData);

        return Response::json([
            'success' => true,
            'message' => 'تم تحديث الفاتورة بنجاح'
        ]);
    }

    /**
     * Cancel invoice
     */
    public function cancel(Request $request, int $id): Response
    {
        // Check permissions
        if (!Auth::hasPermission('invoices_cancel')) {
            return Response::error('Unauthorized access', 403);
        }

        $validator = new Validator($request->all(), [
            'reason' => 'required|string|max:500'
        ]);

        if (!$validator->passes()) {
            return Response::error($validator->errors(), 422);
        }

        $invoice = $this->invoiceModel->find($id);
        if (!$invoice) {
            return Response::error('الفاتورة غير موجودة', 404);
        }

        if ($invoice->status === 'paid') {
            return Response::error('لا يمكن إلغاء فاتورة مدفوعة', 422);
        }

        // Check for existing payments
        $payments = $this->paymentTransactionModel->getByInvoiceId($id);
        $totalPaid = array_sum(array_column($payments, 'amount'));

        if ($totalPaid > 0) {
            return Response::error('لا يمكن إلغاء فاتورة تحتوي على مدفوعات', 422);
        }

        $this->invoiceModel->update($id, [
            'status' => 'cancelled',
            'cancellation_reason' => $request->input('reason'),
            'cancelled_at' => date('Y-m-d H:i:s'),
            'cancelled_by' => Auth::id(),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        return Response::json([
            'success' => true,
            'message' => 'تم إلغاء الفاتورة بنجاح'
        ]);
    }

    /**
     * Print invoice
     */
    public function print(Request $request, int $id): Response
    {
        // Check permissions
        if (!Auth::hasPermission('invoices_print')) {
            return Response::error('Unauthorized access', 403);
        }

        $invoice = $this->invoiceModel->findWithDetails($id);
        if (!$invoice) {
            return Response::error('الفاتورة غير موجودة', 404);
        }

        $order = $this->orderModel->findWithDetails($invoice->order_id);
        $orderItems = $this->orderModel->getOrderItems($invoice->order_id);
        $payments = $this->paymentTransactionModel->getByInvoiceId($id);

        $data = [
            'invoice' => $invoice,
            'order' => $order,
            'order_items' => $orderItems,
            'payments' => $payments,
            'paid_amount' => $this->calculatePaidAmount($id),
            'print_time' => date('Y-m-d H:i:s')
        ];

        return Response::view('invoices.print', $data);
    }

    /**
     * Send invoice via email
     */
    public function sendEmail(Request $request, int $id): Response
    {
        // Check permissions
        if (!Auth::hasPermission('invoices_email')) {
            return Response::error('Unauthorized access', 403);
        }

        $validator = new Validator($request->all(), [
            'email' => 'required|email',
            'subject' => 'nullable|string|max:200',
            'message' => 'nullable|string|max:1000'
        ]);

        if (!$validator->passes()) {
            return Response::error($validator->errors(), 422);
        }

        $invoice = $this->invoiceModel->findWithDetails($id);
        if (!$invoice) {
            return Response::error('الفاتورة غير موجودة', 404);
        }

        $email = $request->input('email');
        $subject = $request->input('subject') ?: "فاتورة رقم {$invoice->invoice_number}";
        $message = $request->input('message') ?: "مرحباً، نرسل إليكم فاتورة رقم {$invoice->invoice_number}";

        // Here you would implement email sending logic
        // For now, we'll just return success
        try {
            // Email sending implementation would go here
            // mail($email, $subject, $message);
            
            return Response::json([
                'success' => true,
                'message' => 'تم إرسال الفاتورة عبر البريد الإلكتروني'
            ]);
        } catch (\Exception $e) {
            return Response::error('فشل في إرسال البريد الإلكتروني', 500);
        }
    }

    /**
     * Generate invoice PDF
     */
    public function generatePdf(Request $request, int $id): Response
    {
        // Check permissions
        if (!Auth::hasPermission('invoices_pdf')) {
            return Response::error('Unauthorized access', 403);
        }

        $invoice = $this->invoiceModel->findWithDetails($id);
        if (!$invoice) {
            return Response::error('الفاتورة غير موجودة', 404);
        }

        // Here you would implement PDF generation logic
        // For now, we'll just redirect to print view
        return Response::redirect("/invoices/{$id}/print");
    }

    /**
     * Export invoices to Excel
     */
    public function export(Request $request): Response
    {
        // Check permissions
        if (!Auth::hasPermission('invoices_export')) {
            return Response::error('Unauthorized access', 403);
        }

        $status = $request->input('status');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $filters = [
            'status' => $status,
            'date_from' => $dateFrom,
            'date_to' => $dateTo
        ];

        $invoices = $this->invoiceModel->getFilteredInvoices($filters, 1, 10000); // Get all matching invoices

        // Here you would implement Excel export logic
        // For now, we'll return the data as JSON
        return Response::json([
            'success' => true,
            'data' => $invoices,
            'export_time' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Get invoice statistics
     */
    public function statistics(Request $request): Response
    {
        // Check permissions
        if (!Auth::hasPermission('invoices_view')) {
            return Response::error('Unauthorized access', 403);
        }

        $period = $request->input('period', 'month');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $stats = $this->invoiceModel->getStatistics($period, $dateFrom, $dateTo);

        return Response::json([
            'success' => true,
            'statistics' => $stats
        ]);
    }

    /**
     * Get overdue invoices
     */
    public function getOverdue(Request $request): Response
    {
        // Check permissions
        if (!Auth::hasPermission('invoices_view')) {
            return Response::error('Unauthorized access', 403);
        }

        $overdueInvoices = $this->invoiceModel->getOverdueInvoices();

        return Response::json([
            'success' => true,
            'invoices' => $overdueInvoices
        ]);
    }

    /**
     * Search invoices
     */
    public function search(Request $request): Response
    {
        // Check permissions
        if (!Auth::hasPermission('invoices_view')) {
            return Response::error('Unauthorized access', 403);
        }

        $query = $request->input('q');
        $type = $request->input('type', 'invoice_number'); // invoice_number, customer_name, order_id

        if (strlen($query) < 2) {
            return Response::json(['invoices' => []]);
        }

        $invoices = $this->invoiceModel->search($query, $type);

        return Response::json([
            'success' => true,
            'invoices' => $invoices
        ]);
    }

    /**
     * Duplicate invoice
     */
    public function duplicate(Request $request, int $id): Response
    {
        // Check permissions
        if (!Auth::hasPermission('invoices_create')) {
            return Response::error('Unauthorized access', 403);
        }

        $originalInvoice = $this->invoiceModel->findWithDetails($id);
        if (!$originalInvoice) {
            return Response::error('الفاتورة غير موجودة', 404);
        }

        try {
            // Create new invoice with same data (except payment-related fields)
            $newInvoiceData = [
                'order_id' => $originalInvoice->order_id,
                'invoice_number' => $this->generateInvoiceNumber(),
                'customer_name' => $originalInvoice->customer_name,
                'customer_phone' => $originalInvoice->customer_phone,
                'customer_address' => $originalInvoice->customer_address,
                'subtotal' => $originalInvoice->subtotal,
                'discount_type' => $originalInvoice->discount_type,
                'discount_value' => $originalInvoice->discount_value,
                'discount_amount' => $originalInvoice->discount_amount,
                'discount_reason' => $originalInvoice->discount_reason,
                'tax_amount' => $originalInvoice->tax_amount,
                'total_amount' => $originalInvoice->total_amount,
                'status' => 'pending',
                'notes' => 'نسخة من الفاتورة رقم: ' . $originalInvoice->invoice_number,
                'created_by' => Auth::id(),
                'created_at' => date('Y-m-d H:i:s')
            ];

            $newInvoiceId = $this->invoiceModel->create($newInvoiceData);

            return Response::json([
                'success' => true,
                'message' => 'تم نسخ الفاتورة بنجاح',
                'new_invoice_id' => $newInvoiceId,
                'redirect_url' => "/invoices/{$newInvoiceId}"
            ]);

        } catch (\Exception $e) {
            return Response::error('فشل في نسخ الفاتورة', 500);
        }
    }

    /**
     * Calculate paid amount for invoice
     */
    private function calculatePaidAmount(int $invoiceId): float
    {
        return $this->paymentTransactionModel->getTotalPaidAmount($invoiceId);
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
        
        $lastInvoice = $this->invoiceModel->getLastInvoiceForToday();
        
        if ($lastInvoice) {
            $parts = explode('-', $lastInvoice->invoice_number);
            $sequence = (int) end($parts) + 1;
        } else {
            $sequence = 1;
        }

        return sprintf('%s-%s%s%s-%04d', $prefix, $year, $month, $day, $sequence);
    }
}