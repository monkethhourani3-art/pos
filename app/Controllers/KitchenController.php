<?php

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Models\Order;
use App\Models\OrderItem;
use App\Support\Facades\Auth;
use App\Support\Facades\Log;
use App\Validation\Validator;

class KitchenController
{
    private $orderModel;
    private $orderItemModel;

    public function __construct()
    {
        $this->orderModel = new Order();
        $this->orderItemModel = new OrderItem();
    }

    /**
     * Display kitchen dashboard (KDS)
     */
    public function index(Request $request): Response
    {
        // Check permissions
        if (!Auth::hasPermission('kitchen_access')) {
            return Response::error('Unauthorized access', 403);
        }

        // Get active orders for kitchen
        $activeOrders = $this->orderModel->getKitchenOrders();
        
        // Group orders by status
        $ordersByStatus = [
            'confirmed' => [],
            'preparing' => [],
            'ready' => []
        ];

        foreach ($activeOrders as $order) {
            if (isset($ordersByStatus[$order->status])) {
                $ordersByStatus[$order->status][] = $order;
            }
        }

        // Get order items for each order
        foreach ($ordersByStatus as $status => $orders) {
            foreach ($orders as $order) {
                $order->items = $this->orderItemModel->getOrderItemsWithProducts($order->id);
            }
        }

        $data = [
            'orders_by_status' => $ordersByStatus,
            'user' => Auth::user(),
            'auto_refresh' => true,
            'refresh_interval' => 30 // seconds
        ];

        return Response::view('kitchen.index', $data);
    }

    /**
     * Get real-time kitchen data (for AJAX updates)
     */
    public function getKitchenData(Request $request): Response
    {
        // Check permissions
        if (!Auth::hasPermission('kitchen_access')) {
            return Response::error('Unauthorized access', 403);
        }

        $lastUpdate = $request->input('last_update');
        
        // Get updated orders since last update
        $updatedOrders = $this->orderModel->getUpdatedKitchenOrders($lastUpdate);
        
        // Get new orders
        $newOrders = $this->orderModel->getNewKitchenOrders($lastUpdate);

        $data = [
            'updated_orders' => $updatedOrders,
            'new_orders' => $newOrders,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        return Response::json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Start preparing an order
     */
    public function startPreparing(Request $request, int $orderId): Response
    {
        // Check permissions
        if (!Auth::hasPermission('kitchen_update')) {
            return Response::error('Unauthorized access', 403);
        }

        $order = $this->orderModel->find($orderId);
        if (!$order) {
            return Response::error('الطلب غير موجود', 404);
        }

        if ($order->status !== 'confirmed') {
            return Response::error('لا يمكن بدء تحضير طلب غير مؤكد', 422);
        }

        // Update order status
        $this->orderModel->update($orderId, [
            'status' => 'preparing',
            'preparing_started_at' => date('Y-m-d H:i:s'),
            'preparing_by' => Auth::id(),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        // Update all order items status
        $this->orderItemModel->updateOrderItemsStatus($orderId, 'preparing');

        Log::info("Order started preparing", [
            'order_id' => $orderId,
            'user_id' => Auth::id()
        ]);

        return Response::json([
            'success' => true,
            'message' => 'تم بدء تحضير الطلب'
        ]);
    }

    /**
     * Mark order item as ready
     */
    public function markItemReady(Request $request, int $itemId): Response
    {
        // Check permissions
        if (!Auth::hasPermission('kitchen_update')) {
            return Response::error('Unauthorized access', 403);
        }

        $item = $this->orderItemModel->find($itemId);
        if (!$item) {
            return Response::error('العنصر غير موجود', 404);
        }

        // Update item status
        $this->orderItemModel->update($itemId, [
            'status' => 'ready',
            'ready_at' => date('Y-m-d H:i:s')
        ]);

        // Check if all items in the order are ready
        $orderItems = $this->orderItemModel->getOrderItems($item->order_id);
        $allReady = true;

        foreach ($orderItems as $orderItem) {
            if ($orderItem->status !== 'ready') {
                $allReady = false;
                break;
            }
        }

        // If all items are ready, update order status
        if ($allReady) {
            $this->orderModel->update($item->order_id, [
                'status' => 'ready',
                'ready_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        }

        Log::info("Order item marked ready", [
            'item_id' => $itemId,
            'order_id' => $item->order_id,
            'user_id' => Auth::id()
        ]);

        return Response::json([
            'success' => true,
            'message' => 'تم تحديد العنصر كجاهز',
            'all_items_ready' => $allReady
        ]);
    }

    /**
     * Mark entire order as ready
     */
    public function markOrderReady(Request $request, int $orderId): Response
    {
        // Check permissions
        if (!Auth::hasPermission('kitchen_update')) {
            return Response::error('Unauthorized access', 403);
        }

        $order = $this->orderModel->find($orderId);
        if (!$order) {
            return Response::error('الطلب غير موجود', 404);
        }

        // Update all order items status to ready
        $this->orderItemModel->updateOrderItemsStatus($orderId, 'ready');

        // Update order status
        $this->orderModel->update($orderId, [
            'status' => 'ready',
            'ready_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        Log::info("Order marked ready", [
            'order_id' => $orderId,
            'user_id' => Auth::id()
        ]);

        return Response::json([
            'success' => true,
            'message' => 'تم تحديد الطلب كجاهز'
        ]);
    }

    /**
     * Add notes to order item
     */
    public function addItemNotes(Request $request, int $itemId): Response
    {
        // Check permissions
        if (!Auth::hasPermission('kitchen_update')) {
            return Response::error('Unauthorized access', 403);
        }

        $validator = new Validator($request->all(), [
            'kitchen_notes' =>:1000'
 'required|string|max        ]);

        if (!$validator->passes()) {
            return Response::error($validator->errors(), 422);
        }

        $kitchenNotes = $request->input('kitchen_notes');
        $item = $this->orderItemModel->find($itemId);

        if (!$item) {
            return Response::error('العنصر غير موجود', 404);
        }

        // Update item with kitchen notes
        $this->orderItemModel->update($itemId, [
            'kitchen_notes' => $kitchenNotes,
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        Log::info("Kitchen notes added to item", [
            'item_id' => $itemId,
            'notes' => $kitchenNotes,
            'user_id' => Auth::id()
        ]);

        return Response::json([
            'success' => true,
            'message' => 'تم إضافة ملاحظات المطبخ'
        ]);
    }

    /**
     * Report item issue/problem
     */
    public function reportIssue(Request $request, int $itemId): Response
    {
        // Check permissions
        if (!Auth::hasPermission('kitchen_update')) {
            return Response::error('Unauthorized access', 403);
        }

        $validator = new Validator($request->all(),_type' => ' [
            'issuerequired|in:missing_ingredient,equipment_issue,quality_issue,other',
            'description' => 'required|string|max:1000'
        ]);

        if (!$validator->passes()) {
            return Response::error($validator->errors(), 422);
        }

        $issueType = $request->input('issue_type');
        $description = $request->input('description');

        $item = $this->orderItemModel->find($itemId);
        if (!$item) {
            return Response::error('العنصر غير موجود', 404);
        }

        // Update item status to indicate issue
        $this->orderItemModel->update($itemId, [
            'status' => 'issue_reported',
            'kitchen_notes' => "مشكلة: " . $description,
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        // Log the issue
        Log::warning("Kitchen issue reported", [
            'item_id' => $itemId,
            'order_id' => $item->order_id,
            'issue_type' => $issueType,
            'description' => $description,
            'user_id' => Auth::id()
        ]);

        return Response::json([
            'success' => true,
            'message' => 'تم الإبلاغ عن المشكلة'
        ]);
    }

    /**
     * Get kitchen performance metrics
     */
    public function metrics(Request $request): Response
    {
        // Check permissions
        if (!Auth::hasPermission('kitchen_view')) {
            return Response::error('Unauthorized access', 403);
        }

        $period = $request->input('period', 'today');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $metrics = $this->orderModel->getKitchenMetrics($period, $dateFrom, $dateTo);

        return Response::json([
            'success' => true,
            'metrics' => $metrics
        ]);
    }

    /**
     * Get orders ready for pickup/delivery
     */
    public function getReadyOrders(Request $request): Response
    {
        // Check permissions
        if (!Auth::hasPermission('kitchen_access')) {
            return Response::error('Unauthorized access', 403);
        }

        $readyOrders = $this->orderModel->getReadyOrders();

        return Response::json([
            'success' => true,
            'orders' => $readyOrders
        ]);
    }

    /**
     * Mark order as served (called when waiter picks up ready order)
     */
    public function markServed(Request $request, int $orderId): Response
    {
        // Check permissions
        if (!Authkitchen_update'))::hasPermission(' {
            return Response::error('Unauthorized access', 403);
        }

        $order = $this->orderModel->find($orderId);
        if (!$order) {
            return Response::error('الطلب غير موجود', 404);
        }

        if ($order->status !== 'ready') {
            return Response::error('الطلب غير جاهز للتسليم', 422);
        }

        // Update order status
        $this->orderModel->update($orderId, [
            'status' => 'served',
            'served_at' => date('Y-m-d H:i:s'),
            'served_by' => Auth::id(),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        Log::info("Order marked as served", [
            'order_id' => $orderId,
            'user_id' => Auth::id()
        ]);

        return Response::json([
            'success' => true,
            'message' => 'تم تحديد الطلب كمُسلم'
        ]);
    }

    /**
     * Get kitchen queue status
     */
    public function getQueueStatus(Request $request): Response
    {
        // Check permissions
        if (!Auth::hasPermission('kitchen_access')) {
            return Response::error('Unauthorized access', 403);
        }

        $queueStatus = $this->orderModel->getKitchenQueueStatus();

        return Response::json([
            'success' => true,
            'queue_status' => $queueStatus
        ]);
    }
}