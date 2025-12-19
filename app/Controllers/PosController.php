<?php

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Table;
use App\Support\Facades\Auth;
use App\Support\Facades\Session;
use App\Validation\Validator;

class PosController
{
    private $orderModel;
    private $orderItemModel;
    private $productModel;
    private $tableModel;

    public function __construct()
    {
        $this->orderModel = new Order();
        $this->orderItemModel = new OrderItem();
        $this->productModel = new Product();
        $this->tableModel = new Table();
    }

    /**
     * Display POS main screen
     */
    public function index(Request $request): Response
    {
        // Check permissions
        if (!Auth::hasPermission('pos_access')) {
            return Response::error('Unauthorized access', 403);
        }

        // Get active orders for current table
        $activeOrders = $this->orderModel->getActiveOrdersByUser(Auth::id());
        
        // Get available tables
        $availableTables = $this->tableModel->getAvailableTables();
        
        // Get products by category for quick access
        $productsByCategory = $this->productModel->getProductsByCategory();

        $data = [
            'active_orders' => $activeOrders,
            'available_tables' => $availableTables,
            'products_by_category' => $productsByCategory,
            'current_table' => Session::get('current_table'),
            'user' => Auth::user()
        ];

        return Response::view('pos.index', $data);
    }

    /**
     * Start new order for selected table
     */
    public function startOrder(Request $request): Response
    {
        $validator = new Validator($request->all(), [
            'table_id' => 'required|exists:tables,id'
        ]);

        if (!$validator->passes()) {
            return Response::error($validator->errors(), 422);
        }

        $tableId = $request->input('table_id');
        $userId = Auth::id();

        // Check if table is available
        $table = $this->tableModel->find($tableId);
        if (!$table || $table->status !== 'available') {
            return Response::error('الطاولة غير متاحة', 422);
        }

        // Create new order
        $orderData = [
            'table_id' => $tableId,
            'user_id' => $userId,
            'status' => 'pending',
            'subtotal' => 0,
            'tax_amount' => 0,
            'total_amount' => 0,
            'created_at' => date('Y-m-d H:i:s')
        ];

        $orderId = $this->orderModel->create($orderData);
        
        if (!$orderId) {
            return Response::error('فشل في إنشاء الطلب', 500);
        }

        // Update table status
        $this->tableModel->update($tableId, [
            'status' => 'occupied',
            'current_order_id' => $orderId
        ]);

        // Store current table in session
        Session::put('current_table', $tableId);
        Session::put('current_order', $orderId);

        return Response::json([
            'success' => true,
            'message' => 'تم بدء الطلب بنجاح',
            'order_id' => $orderId,
            'table_id' => $tableId
        ]);
    }

    /**
     * Add item to current order
     */
    public function addItem(Request $request): Response
    {
        $validator = new Validator($request->all(), [
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'notes' => 'nullable|string|max:500'
        ]);

        if (!$validator->passes()) {
            return Response::error($validator->errors(), 422);
        }

        $orderId = Session::get('current_order');
        if (!$orderId) {
            return Response::error('لا يوجد طلب نشط', 422);
        }

        $productId = $request->input('product_id');
        $quantity = $request->input('quantity');
        $notes = $request->input('notes');

        // Get product details
        $product = $this->productModel->find($productId);
        if (!$product || !$product->is_active) {
            return Response::error('المنتج غير متاح', 422);
        }

        // Check if item already exists in order
        $existingItem = $this->orderItemModel->findByOrderAndProduct($orderId, $productId);
        
        if ($existingItem) {
            // Update quantity
            $newQuantity = $existingItem->quantity + $quantity;
            $this->orderItemModel->update($existingItem->id, [
                'quantity' => $newQuantity,
                'total_price' => $product->price * $newQuantity
            ]);
        } else {
            // Add new item
            $itemData = [
                'order_id' => $orderId,
                'product_id' => $productId,
                'quantity' => $quantity,
                'unit_price' => $product->price,
                'total_price' => $product->price * $quantity,
                'notes' => $notes,
                'status' => 'pending'
            ];
            
            $this->orderItemModel->create($itemData);
        }

        // Update order totals
        $this->updateOrderTotals($orderId);

        // Get updated order items
        $orderItems = $this->orderItemModel->getOrderItemsWithProducts($orderId);

        return Response::json([
            'success' => true,
            'message' => 'تم إضافة المنتج بنجاح',
            'order_items' => $orderItems
        ]);
    }

    /**
     * Update item quantity in order
     */
    public function updateItem(Request $request, int $itemId): Response
    {
        $validator = new Validator($request->all(), [
            'quantity' => 'required|integer|min:0'
        ]);

        if (!$validator->passes()) {
            return Response::error($validator->errors(), 422);
        }

        $quantity = $request->input('quantity');
        $orderId = Session::get('current_order');

        // Get order item
        $item = $this->orderItemModel->find($itemId);
        if (!$item || $item->order_id != $orderId) {
            return Response::error('العنصر غير موجود', 404);
        }

        if ($quantity == 0) {
            // Remove item
            $this->orderItemModel->delete($itemId);
        } else {
            // Update quantity
            $this->orderItemModel->update($itemId, [
                'quantity' => $quantity,
                'total_price' => $item->unit_price * $quantity
            ]);
        }

        // Update order totals
        $this->updateOrderTotals($orderId);

        // Get updated order items
        $orderItems = $this->orderItemModel->getOrderItemsWithProducts($orderId);

        return Response::json([
            'success' => true,
            'message' => 'تم تحديث العنصر بنجاح',
            'order_items' => $orderItems
        ]);
    }

    /**
     * Remove item from order
     */
    public function removeItem(Request $request, int $itemId): Response
    {
        $orderId = Session::get('current_order');
        
        // Get order item
        $item = $this->orderItemModel->find($itemId);
        if (!$item || $item->order_id != $orderId) {
            return Response::error('العنصر غير موجود', 404);
        }

        // Remove item
        $this->orderItemModel->delete($itemId);

        // Update order totals
        $this->updateOrderTotals($orderId);

        return Response::json([
            'success' => true,
            'message' => 'تم حذف العنصر بنجاح'
        ]);
    }

    /**
     * Submit order to kitchen
     */
    public function submitOrder(Request $request): Response
    {
        $orderId = Session::get('current_order');
        if (!$orderId) {
            return Response::error('لا يوجد طلب نشط', 422);
        }

        // Check if order has items
        $orderItems = $this->orderItemModel->getOrderItems($orderId);
        if (empty($orderItems)) {
            return Response::error('الطلب لا يحتوي على عناصر', 422);
        }

        // Update order status
        $this->orderModel->update($orderId, [
            'status' => 'confirmed',
            'submitted_at' => date('Y-m-d H:i:s')
        ]);

        // Update order items status
        $this->orderItemModel->updateOrderItemsStatus($orderId, 'confirmed');

        // Clear session
        Session::forget('current_order');
        Session::forget('current_table');

        return Response::json([
            'success' => true,
            'message' => 'تم إرسال الطلب للمطبخ بنجاح'
        ]);
    }

    /**
     * Get current order details
     */
    public function getCurrentOrder(Request $request): Response
    {
        $orderId = Session::get('current_order');
        if (!$orderId) {
            return Response::json([
                'success' => false,
                'message' => 'لا يوجد طلب نشط'
            ]);
        }

        $order = $this->orderModel->findWithDetails($orderId);
        $orderItems = $this->orderItemModel->getOrderItemsWithProducts($orderId);

        return Response::json([
            'success' => true,
            'order' => $order,
            'order_items' => $orderItems
        ]);
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

    /**
     * Search products
     */
    public function searchProducts(Request $request): Response
    {
        $query = $request->input('q', '');
        $categoryId = $request->input('category_id');

        if (strlen($query) < 2) {
            return Response::json(['products' => []]);
        }

        $products = $this->productModel->search($query, $categoryId);

        return Response::json([
            'success' => true,
            'products' => $products
        ]);
    }
}