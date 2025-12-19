<?php

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Table;
use App\Support\Facades\Auth;
use App\Support\Facades\Log;
use App\Validation\Validator;

class OrderController
{
    private $orderModel;
    private $orderItemModel;
    private $tableModel;

    public function __construct()
    {
        $this->orderModel = new Order();
        $this->orderItemModel = new OrderItem();
        $this->tableModel = new Table();
    }

    /**
     * Display orders list
     */
    public function index(Request $request): Response
    {
        // Check permissions
        if (!Auth::hasPermission('orders_view')) {
            return Response::error('Unauthorized access', 403);
        }

        $page = $request->input('page', 1);
        $status = $request->input('status');
        $tableId = $request->input('table_id');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $filters = [
            'status' => $status,
            'table_id' => $tableId,
            'date_from' => $dateFrom,
            'date_to' => $dateTo
        ];

        $orders = $this->orderModel->getFilteredOrders($filters, $page, 20);
        $totalOrders = $this->orderModel->getTotalFilteredOrders($filters);
        $tables = $this->tableModel->getAll();

        $data = [
            'orders' => $orders,
            'total_orders' => $totalOrders,
            'current_page' => $page,
            'total_pages' => ceil($totalOrders / 20),
            'tables' => $tables,
            'filters' => $filters
        ];

        return Response::view('orders.index', $data);
    }

    /**
     * Display order details
     */
    public function show(Request $request, int $id): Response
    {
        // Check permissions
        if (!Auth::hasPermission('orders_view')) {
            return Response::error('Unauthorized access', 403);
        }

        $order = $this->orderModel->findWithDetails($id);
        if (!$order) {
            return Response::error('الطلب غير موجود', 404);
        }

        $orderItems = $this->orderItemModel->getOrderItemsWithProducts($id);
        $orderHistory = $this->orderModel->getOrderHistory($id);

        $data = [
            'order' => $order,
            'order_items' => $orderItems,
            'order_history' => $orderHistory
        ];

        return Response::view('orders.show', $data);
    }

    /**
     * Update order status
     */
    public function updateStatus(Request $request, int $id): Response
    {
        // Check permissions
        if (!Auth::hasPermission('orders_update')) {
            return Response::error('Unauthorized access', 403);
        }

        $validator = new Validator($request->all(), [
            'status' => 'required|in:pending,confirmed,preparing,ready,served,paid,cancelled'
        ]);

        if (!$validator->passes()) {
            return Response::error($validator->errors(), 422);
        }

        $status = $request->input('status');
        $notes = $request->input('notes', '');

        $order = $this->orderModel->find($id);
        if (!$order) {
            return Response::error('الطلب غير موجود', 404);
        }

        // Update order status
        $updateData = [
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        // Add status-specific timestamps
        switch ($status) {
            case 'preparing':
                $updateData['preparing_started_at'] = date('Y-m-d H:i:s');
                break;
            case 'ready':
                $updateData['ready_at'] = date('Y-m-d H:i:s');
                break;
            case 'served':
                $updateData['served_at'] = date('Y-m-d H:i:s');
                break;
            case 'paid':
                $updateData['paid_at'] = date('Y-m-d H:i:s');
                // Free the table
                if ($order->table_id) {
                    $this->tableModel->update($order->table_id, [
                        'status' => 'available',
                        'current_order_id' => null
                    ]);
                }
                break;
            case 'cancelled':
                $updateData['cancelled_at'] = date('Y-m-d H:i:s');
                // Free the table
                if ($order->table_id) {
                    $this->tableModel->update($order->table_id, [
                        'status' => 'available',
                        'current_order_id' => null
                    ]);
                }
                break;
        }

        $this->orderModel->update($id, $updateData);

        // Log status change
        Log::info("Order status updated", [
            'order_id' => $id,
            'old_status' => $order->status,
            'new_status' => $status,
            'user_id' => Auth::id(),
            'notes' => $notes
        ]);

        return Response::json([
            'success' => true,
            'message' => 'تم تحديث حالة الطلب بنجاح'
        ]);
    }

    /**
     * Cancel order
     */
    public function cancel(Request $request, int $id): Response
    {
        // Check permissions
        if (!Auth::hasPermission('orders_cancel')) {
            return Response::error('Unauthorized access', 403);
        }

        $validator = new Validator($request->all(), [
            'reason' => 'required|string|max:500'
        ]);

        if (!$validator->passes()) {
            return Response::error($validator->errors(), 422);
        }

        $reason = $request->input('reason');
        $order = $this->orderModel->find($id);

        if (!$order) {
            return Response::error('الطلب غير موجود', 404);
        }

        if (in_array($order->status, ['served', 'paid', 'cancelled'])) {
            return Response::error('لا يمكن إلغاء هذا الطلب', 422);
        }

        // Update order status
        $this->orderModel->update($id, [
            'status' => 'cancelled',
            'cancelled_at' => date('Y-m-d H:i:s'),
            'cancellation_reason' => $reason,
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        // Free the table
        if ($order->table_id) {
            $this->tableModel->update($order->table_id, [
                'status' => 'available',
                'current_order_id' => null
            ]);
        }

        // Update order items status
        $this->orderItemModel->updateOrderItemsStatus($id, 'cancelled');

        // Log cancellation
        Log::warning("Order cancelled", [
            'order_id' => $id,
            'reason' => $reason,
            'user_id' => Auth::id()
        ]);

        return Response::json([
            'success' => true,
            'message' => 'تم إلغاء الطلب بنجاح'
        ]);
    }

    /**
     * Get order statistics
     */
    public function statistics(Request $request): Response
    {
        // Check permissions
        if (!Auth::hasPermission('orders_view')) {
            return Response::error('Unauthorized access', 403);
        }

        $period = $request->input('period', 'today'); // today, week, month
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $stats = $this->orderModel->getStatistics($period, $dateFrom, $dateTo);

        return Response::json([
            'success' => true,
            'statistics' => $stats
        ]);
    }

    /**
     * Get orders by status for dashboard
     */
    public function getByStatus(Request $request): Response
    {
        // Check permissions
        if (!Auth::hasPermission('orders_view')) {
            return Response::error('Unauthorized access', 403);
        }

        $status = $request->input('status');
        $orders = $this->orderModel->getOrdersByStatus($status);

        return Response::json([
            'success' => true,
            'orders' => $orders
        ]);
    }

    /**
     * Merge orders (for combining multiple tables)
     */
    public function merge(Request $request): Response
    {
        // Check permissions
        if (!Auth::hasPermission('orders_update')) {
            return Response::error('Unauthorized access', 403);
        }

        $validator = new Validator($request->all(), [
            'primary_order_id' => 'required|exists:orders,id',
            'secondary_order_id' => 'required|exists:orders,id'
        ]);

        if (!$validator->passes()) {
            return Response::error($validator->errors(), 422);
        }

        $primaryOrderId = $request->input('primary_order_id');
        $secondaryOrderId = $request->input('secondary_order_id');

        // Get both orders
        $primaryOrder = $this->orderModel->find($primaryOrderId);
        $secondaryOrder = $this->orderModel->find($secondaryOrderId);

        if (!$primaryOrder || !$secondaryOrder) {
            return Response::error('أحد الطلبات غير موجود', 404);
        }

        if ($primaryOrder->status === 'paid' || $secondaryOrder->status === 'paid') {
            return Response::error('لا يمكن دمج طلبات مدفوعة', 422);
        }

        // Move items from secondary order to primary order
        $this->orderItemModel->mergeOrders($primaryOrderId, $secondaryOrderId);

        // Update secondary order status
        $this->orderModel->update($secondaryOrderId, [
            'status' => 'merged',
            'merged_into' => $primaryOrderId,
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        // Update primary order totals
        $this->updateOrderTotals($primaryOrderId);

        return Response::json([
            'success' => true,
            'message' => 'تم دمج الطلبات بنجاح'
        ]);
    }

    /**
     * Split order into multiple orders
     */
    public function split(Request $request, int $id): Response
    {
        // Check permissions
        if (!Auth::hasPermission('orders_update')) {
            return Response::error('Unauthorized access', 403);
        }

        $validator = new Validator($request->all(), [
            'items' => 'required|array',
            'items.*' => 'exists:order_items,id'
        ]);

        if (!$validator->passes()) {
            return Response::error($validator->errors(), 422);
        }

        $itemIds = $request->input('items');
        $order = $this->orderModel->find($id);

        if (!$order) {
            return Response::error('الطلب غير موجود', 404);
        }

        // Create new order for selected items
        $newOrderData = [
            'table_id' => $order->table_id,
            'user_id' => Auth::id(),
            'status' => $order->status,
            'created_at' => date('Y-m-d H:i:s')
        ];

        $newOrderId = $this->orderModel->create($newOrderData);

        // Move selected items to new order
        $this->orderItemModel->moveItemsToOrder($itemIds, $newOrderId);

        // Update new order totals
        $this->updateOrderTotals($newOrderId);

        // Update original order totals
        $this->updateOrderTotals($id);

        return Response::json([
            'success' => true,
            'message' => 'تم تقسيم الطلب بنجاح',
            'new_order_id' => $newOrderId
        ]);
    }

    /**
     * Print order receipt
     */
    public function printReceipt(Request $request, int $id): Response
    {
        // Check permissions
        if (!Auth::hasPermission('orders_print')) {
            return Response::error('Unauthorized access', 403);
        }

        $order = $this->orderModel->findWithDetails($id);
        if (!$order) {
            return Response::error('الطلب غير موجود', 404);
        }

        $orderItems = $this->orderItemModel->getOrderItemsWithProducts($id);

        $data = [
            'order' => $order,
            'order_items' => $orderItems,
            'print_time' => date('Y-m-d H:i:s')
        ];

        return Response::view('orders.receipt', $data);
    }

    /**
     * Update order totals
     */
    private function updateOrderTotals(int $orderId): void
    {
        $totals = $this->orderItemModel->calculateOrderTotals($orderId);
        
        $this->orderModel->update($orderId, [
            'subtotal' => $totals['subtotal'],
            'tax_amount' => $totals['tax_amount'],
            'total_amount' => $totals['total_amount']
        ]);
    }
}