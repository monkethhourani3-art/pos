<?php

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Models\Invoice;
use App\Models\PaymentTransaction;
use App\Models\PaymentMethod;
use App\Models\Order;
use App\Support\Facades\Auth;
use App\Support\Facades\Session;
use App\Validation\Validator;

class ApiController
{
    private $invoiceModel;
    private $paymentTransactionModel;
    private $paymentMethodModel;
    private $orderModel;

    public function __construct()
    {
        $this->invoiceModel = new Invoice();
        $this->paymentTransactionModel = new PaymentTransaction();
        $this->paymentMethodModel = new PaymentMethod();
        $this->orderModel = new Order();
    }

    /**
     * Get all invoices with pagination
     */
    public function invoices(Request $request): Response
    {
        try {
            if (!Auth::hasPermission('invoices.view')) {
                return Response::json(['error' => 'Unauthorized'], 403);
            }

            $page = $request->input('page', 1);
            $limit = $request->input('limit', 20);
            $filters = [
                'status' => $request->input('status'),
                'date_from' => $request->input('date_from'),
                'date_to' => $request->input('date_to'),
                'customer_name' => $request->input('customer_name'),
                'search' => $request->input('search')
            ];

            $invoices = $this->invoiceModel->getFilteredInvoices($filters, $page, $limit);
            $totalInvoices = $this->invoiceModel->getTotalFilteredInvoices($filters);

            $pagination = [
                'current_page' => $page,
                'total_pages' => ceil($totalInvoices / $limit),
                'total_items' => $totalInvoices,
                'per_page' => $limit
            ];

            return Response::json([
                'invoices' => $invoices,
                'pagination' => $pagination
            ]);

        } catch (\Exception $e) {
            error_log('API Invoices Error: ' . $e->getMessage());
            return Response::json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Get invoice details
     */
    public function invoiceDetails(Request $request, int $id): Response
    {
        try {
            if (!Auth::hasPermission('invoices.view')) {
                return Response::json(['error' => 'Unauthorized'], 403);
            }

            $invoice = $this->invoiceModel->findWithDetails($id);
            if (!$invoice) {
                return Response::json(['error' => 'Invoice not found'], 404);
            }

            return Response::json(['invoice' => $invoice]);

        } catch (\Exception $e) {
            error_log('API Invoice Details Error: ' . $e->getMessage());
            return Response::json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Get invoice statistics
     */
    public function invoiceStats(Request $request): Response
    {
        try {
            if (!Auth::hasPermission('invoices.view')) {
                return Response::json(['error' => 'Unauthorized'], 403);
            }

            $dateFrom = $request->input('date_from');
            $dateTo = $request->input('date_to');

            $stats = $this->invoiceModel->getStatistics($dateFrom, $dateTo);

            return Response::json(['stats' => $stats]);

        } catch (\Exception $e) {
            error_log('API Invoice Stats Error: ' . $e->getMessage());
            return Response::json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Get overdue invoices
     */
    public function overdueInvoices(Request $request): Response
    {
        try {
            if (!Auth::hasPermission('invoices.view')) {
                return Response::json(['error' => 'Unauthorized'], 403);
            }

            $overdueInvoices = $this->invoiceModel->getOverdueInvoices();

            return Response::json(['invoices' => $overdueInvoices]);

        } catch (\Exception $e) {
            error_log('API Overdue Invoices Error: ' . $e->getMessage());
            return Response::json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Get all payments
     */
    public function payments(Request $request): Response
    {
        try {
            if (!Auth::hasPermission('payments.process')) {
                return Response::json(['error' => 'Unauthorized'], 403);
            }

            $page = $request->input('page', 1);
            $limit = $request->input('limit', 20);
            $filters = [
                'status' => $request->input('status'),
                'method_id' => $request->input('payment_method_id'),
                'date_from' => $request->input('date_from'),
                'date_to' => $request->input('date_to')
            ];

            $payments = $this->paymentTransactionModel->getFilteredTransactions($filters, $page, $limit);
            $totalPayments = $this->paymentTransactionModel->getTotalFilteredTransactions($filters);

            $pagination = [
                'current_page' => $page,
                'total_pages' => ceil($totalPayments / $limit),
                'total_items' => $totalPayments,
                'per_page' => $limit
            ];

            return Response::json([
                'payments' => $payments,
                'pagination' => $pagination
            ]);

        } catch (\Exception $e) {
            error_log('API Payments Error: ' . $e->getMessage());
            return Response::json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Get payment methods
     */
    public function paymentMethods(Request $request): Response
    {
        try {
            if (!Auth::hasPermission('payments.process')) {
                return Response::json(['error' => 'Unauthorized'], 403);
            }

            $paymentMethods = $this->paymentMethodModel->getActiveMethods();

            return Response::json($paymentMethods);

        } catch (\Exception $e) {
            error_log('API Payment Methods Error: ' . $e->getMessage());
            return Response::json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Get order payments
     */
    public function orderPayments(Request $request, int $orderId): Response
    {
        try {
            if (!Auth::hasPermission('payments.process')) {
                return Response::json(['error' => 'Unauthorized'], 403);
            }

            $order = $this->orderModel->find($orderId);
            if (!$order) {
                return Response::json(['error' => 'Order not found'], 404);
            }

            $payments = $this->paymentTransactionModel->getByOrderId($orderId);

            return Response::json(['payments' => $payments]);

        } catch (\Exception $e) {
            error_log('API Order Payments Error: ' . $e->getMessage());
            return Response::json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Get payment statistics
     */
    public function paymentStats(Request $request): Response
    {
        try {
            if (!Auth::hasPermission('payments.process')) {
                return Response::json(['error' => 'Unauthorized'], 403);
            }

            $dateFrom = $request->input('date_from');
            $dateTo = $request->input('date_to');

            $stats = $this->paymentTransactionModel->getStatistics($dateFrom, $dateTo);

            return Response::json(['stats' => $stats]);

        } catch (\Exception $e) {
            error_log('API Payment Stats Error: ' . $e->getMessage());
            return Response::json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Get payment transactions
     */
    public function paymentTransactions(Request $request): Response
    {
        try {
            if (!Auth::hasPermission('payments.process')) {
                return Response::json(['error' => 'Unauthorized'], 403);
            }

            $page = $request->input('page', 1);
            $limit = $request->input('limit', 20);

            $transactions = $this->paymentTransactionModel->getPaginatedTransactions($page, $limit);

            return Response::json(['transactions' => $transactions]);

        } catch (\Exception $e) {
            error_log('API Payment Transactions Error: ' . $e->getMessage());
            return Response::json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Get order invoice
     */
    public function orderInvoice(Request $request, int $orderId): Response
    {
        try {
            if (!Auth::hasPermission('invoices.view')) {
                return Response::json(['error' => 'Unauthorized'], 403);
            }

            $order = $this->orderModel->find($orderId);
            if (!$order) {
                return Response::json(['error' => 'Order not found'], 404);
            }

            $invoice = $this->invoiceModel->getByOrderId($orderId);

            return Response::json(['invoice' => $invoice]);

        } catch (\Exception $e) {
            error_log('API Order Invoice Error: ' . $e->getMessage());
            return Response::json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Get order payment history
     */
    public function orderPaymentHistory(Request $request, int $orderId): Response
    {
        try {
            if (!Auth::hasPermission('payments.process')) {
                return Response::json(['error' => 'Unauthorized'], 403);
            }

            $order = $this->orderModel->find($orderId);
            if (!$order) {
                return Response::json(['error' => 'Order not found'], 404);
            }

            $payments = $this->paymentTransactionModel->getByOrderId($orderId);

            return Response::json(['payments' => $payments]);

        } catch (\Exception $e) {
            error_log('API Order Payment History Error: ' . $e->getMessage());
            return Response::json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Get dashboard statistics
     */
    public function dashboardStats(Request $request): Response
    {
        try {
            if (!Auth::hasPermission('dashboard.view')) {
                return Response::json(['error' => 'Unauthorized'], 403);
            }

            $stats = [
                'total_orders_today' => $this->orderModel->getTodayOrderCount(),
                'total_sales_today' => $this->orderModel->getTodaySales(),
                'pending_orders' => $this->orderModel->getPendingOrderCount(),
                'active_tables' => $this->orderModel->getActiveTableCount(),
                'total_invoices_today' => $this->invoiceModel->getTodayInvoiceCount(),
                'total_invoices_amount_today' => $this->invoiceModel->getTodayInvoiceAmount(),
                'pending_payments' => $this->paymentTransactionModel->getPendingPaymentCount(),
                'successful_payments_today' => $this->paymentTransactionModel->getTodaySuccessfulPayments()
            ];

            return Response::json(['stats' => $stats]);

        } catch (\Exception $e) {
            error_log('API Dashboard Stats Error: ' . $e->getMessage());
            return Response::json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Get sales statistics
     */
    public function salesStats(Request $request): Response
    {
        try {
            if (!Auth::hasPermission('reports.view')) {
                return Response::json(['error' => 'Unauthorized'], 403);
            }

            $dateFrom = $request->input('date_from');
            $dateTo = $request->input('date_to');

            $stats = [
                'daily_sales' => $this->orderModel->getDailySales($dateFrom, $dateTo),
                'hourly_sales' => $this->orderModel->getHourlySales($dateFrom, $dateTo),
                'top_products' => $this->orderModel->getTopProducts($dateFrom, $dateTo),
                'payment_methods' => $this->paymentTransactionModel->getPaymentMethodStats($dateFrom, $dateTo),
                'total_revenue' => $this->orderModel->getTotalRevenue($dateFrom, $dateTo),
                'average_order_value' => $this->orderModel->getAverageOrderValue($dateFrom, $dateTo)
            ];

            return Response::json(['stats' => $stats]);

        } catch (\Exception $e) {
            error_log('API Sales Stats Error: ' . $e->getMessage());
            return Response::json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Get all products
     */
    public function products(Request $request): Response
    {
        try {
            $categoryId = $request->input('category_id');
            $search = $request->input('search');
            $available = $request->input('available');

            $products = $this->productModel->getForApi($categoryId, $search, $available);

            return Response::json(['products' => $products]);

        } catch (\Exception $e) {
            error_log('API Products Error: ' . $e->getMessage());
            return Response::json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Get product categories
     */
    public function categories(Request $request): Response
    {
        try {
            $categories = $this->categoryModel->getForApi();

            return Response::json(['categories' => $categories]);

        } catch (\Exception $e) {
            error_log('API Categories Error: ' . $e->getMessage());
            return Response::json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Search products
     */
    public function searchProducts(Request $request): Response
    {
        try {
            $query = $request->input('q');
            $limit = $request->input('limit', 10);

            if (empty($query)) {
                return Response::json(['products' => []]);
            }

            $products = $this->productModel->search($query, $limit);

            return Response::json(['products' => $products]);

        } catch (\Exception $e) {
            error_log('API Search Products Error: ' . $e->getMessage());
            return Response::json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Get all tables
     */
    public function tables(Request $request): Response
    {
        try {
            $tables = $this->tableModel->getForApi();

            return Response::json(['tables' => $tables]);

        } catch (\Exception $e) {
            error_log('API Tables Error: ' . $e->getMessage());
            return Response::json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Get table status
     */
    public function tableStatus(Request $request): Response
    {
        try {
            $status = $this->tableModel->getStatusSummary();

            return Response::json(['status' => $status]);

        } catch (\Exception $e) {
            error_log('API Table Status Error: ' . $e->getMessage());
            return Response::json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Get table orders
     */
    public function tableOrders(Request $request, int $tableId): Response
    {
        try {
            $orders = $this->orderModel->getByTableId($tableId);

            return Response::json(['orders' => $orders]);

        } catch (\Exception $e) {
            error_log('API Table Orders Error: ' . $e->getMessage());
            return Response::json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Get all orders
     */
    public function orders(Request $request): Response
    {
        try {
            $status = $request->input('status');
            $tableId = $request->input('table_id');
            $dateFrom = $request->input('date_from');
            $dateTo = $request->input('date_to');

            $orders = $this->orderModel->getForApi($status, $tableId, $dateFrom, $dateTo);

            return Response::json(['orders' => $orders]);

        } catch (\Exception $e) {
            error_log('API Orders Error: ' . $e->getMessage());
            return Response::json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Get active orders
     */
    public function activeOrders(Request $request): Response
    {
        try {
            $orders = $this->orderModel->getActiveOrders();

            return Response::json(['orders' => $orders]);

        } catch (\Exception $e) {
            error_log('API Active Orders Error: ' . $e->getMessage());
            return Response::json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Get kitchen orders
     */
    public function kitchenOrders(Request $request): Response
    {
        try {
            $orders = $this->orderModel->getKitchenOrders();

            return Response::json(['orders' => $orders]);

        } catch (\Exception $e) {
            error_log('API Kitchen Orders Error: ' . $e->getMessage());
            return Response::json(['error' => 'Internal server error'], 500);
        }
    }

    // ================================================
    // INVENTORY API ENDPOINTS
    // ================================================

    /**
     * Get all inventory items
     */
    public function inventory(Request $request): Response
    {
        try {
            if (!Auth::hasPermission('inventory.view')) {
                return Response::json(['error' => 'Unauthorized'], 403);
            }

            $page = $request->input('page', 1);
            $limit = $request->input('limit', 20);
            $filters = [
                'category_id' => $request->input('category_id'),
                'supplier_id' => $request->input('supplier_id'),
                'low_stock' => $request->input('low_stock'),
                'search' => $request->input('search')
            ];

            $items = $this->inventoryItemModel->getAll($filters, $page, $limit);
            $totalItems = $this->inventoryItemModel->getTotalCount($filters);

            $pagination = [
                'current_page' => $page,
                'total_pages' => ceil($totalItems / $limit),
                'total_items' => $totalItems,
                'per_page' => $limit
            ];

            return Response::json([
                'inventory_items' => $items,
                'pagination' => $pagination
            ]);

        } catch (\Exception $e) {
            error_log('API Inventory Error: ' . $e->getMessage());
            return Response::json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Get inventory item details
     */
    public function inventoryItem(Request $request, int $id): Response
    {
        try {
            if (!Auth::hasPermission('inventory.view')) {
                return Response::json(['error' => 'Unauthorized'], 403);
            }

            $item = $this->inventoryItemModel->find($id);
            if (!$item) {
                return Response::json(['error' => 'Inventory item not found'], 404);
            }

            return Response::json(['item' => $item]);

        } catch (\Exception $e) {
            error_log('API Inventory Item Error: ' . $e->getMessage());
            return Response::json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Get low stock items
     */
    public function lowStockItems(Request $request): Response
    {
        try {
            if (!Auth::hasPermission('inventory.view')) {
                return Response::json(['error' => 'Unauthorized'], 403);
            }

            $items = $this->inventoryItemModel->getLowStockItems();

            return Response::json(['items' => $items]);

        } catch (\Exception $e) {
            error_log('API Low Stock Items Error: ' . $e->getMessage());
            return Response::json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Get expiring items
     */
    public function expiringItems(Request $request): Response
    {
        try {
            if (!Auth::hasPermission('inventory.view')) {
                return Response::json(['error' => 'Unauthorized'], 403);
            }

            $days = $request->input('days', 30);
            $items = $this->inventoryItemModel->getExpiringItems($days);

            return Response::json(['items' => $items]);

        } catch (\Exception $e) {
            error_log('API Expiring Items Error: ' . $e->getMessage());
            return Response::json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Get inventory statistics
     */
    public function inventoryStats(Request $request): Response
    {
        try {
            if (!Auth::hasPermission('inventory.view')) {
                return Response::json(['error' => 'Unauthorized'], 403);
            }

            $stats = $this->inventoryItemModel->getStatistics();

            return Response::json(['statistics' => $stats]);

        } catch (\Exception $e) {
            error_log('API Inventory Stats Error: ' . $e->getMessage());
            return Response::json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Get inventory valuation
     */
    public function inventoryValuation(Request $request): Response
    {
        try {
            if (!Auth::hasPermission('inventory.view')) {
                return Response::json(['error' => 'Unauthorized'], 403);
            }

            $valuation = $this->inventoryItemModel->getValuation();

            return Response::json(['valuation' => $valuation]);

        } catch (\Exception $e) {
            error_log('API Inventory Valuation Error: ' . $e->getMessage());
            return Response::json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Get inventory movements
     */
    public function inventoryMovements(Request $request, int $itemId): Response
    {
        try {
            if (!Auth::hasPermission('inventory.view')) {
                return Response::json(['error' => 'Unauthorized'], 403);
            }

            $limit = $request->input('limit', 50);
            $movements = $this->inventoryItemModel->getMovements($itemId, $limit);

            return Response::json(['movements' => $movements]);

        } catch (\Exception $e) {
            error_log('API Inventory Movements Error: ' . $e->getMessage());
            return Response::json(['error' => 'Internal server error'], 500);
        }
    }

    // ================================================
    // SUPPLIERS API ENDPOINTS
    // ================================================

    /**
     * Get all suppliers
     */
    public function suppliers(Request $request): Response
    {
        try {
            if (!Auth::hasPermission('suppliers.view')) {
                return Response::json(['error' => 'Unauthorized'], 403);
            }

            $page = $request->input('page', 1);
            $limit = $request->input('limit', 20);
            $filters = [
                'search' => $request->input('search'),
                'active' => $request->input('active')
            ];

            $suppliers = $this->supplierModel->getAll($filters, $page, $limit);
            $totalSuppliers = $this->supplierModel->getTotalCount($filters);

            $pagination = [
                'current_page' => $page,
                'total_pages' => ceil($totalSuppliers / $limit),
                'total_items' => $totalSuppliers,
                'per_page' => $limit
            ];

            return Response::json([
                'suppliers' => $suppliers,
                'pagination' => $pagination
            ]);

        } catch (\Exception $e) {
            error_log('API Suppliers Error: ' . $e->getMessage());
            return Response::json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Get supplier details
     */
    public function supplier(Request $request, int $id): Response
    {
        try {
            if (!Auth::hasPermission('suppliers.view')) {
                return Response::json(['error' => 'Unauthorized'], 403);
            }

            $supplier = $this->supplierModel->find($id);
            if (!$supplier) {
                return Response::json(['error' => 'Supplier not found'], 404);
            }

            return Response::json(['supplier' => $supplier]);

        } catch (\Exception $e) {
            error_log('API Supplier Error: ' . $e->getMessage());
            return Response::json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Get supplier purchases
     */
    public function supplierPurchases(Request $request, int $supplierId): Response
    {
        try {
            if (!Auth::hasPermission('suppliers.view')) {
                return Response::json(['error' => 'Unauthorized'], 403);
            }

            $page = $request->input('page', 1);
            $purchases = $this->supplierModel->getPurchaseHistory($supplierId, $page, 20);

            return Response::json(['purchases' => $purchases]);

        } catch (\Exception $e) {
            error_log('API Supplier Purchases Error: ' . $e->getMessage());
            return Response::json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Get supplier performance
     */
    public function supplierPerformance(Request $request, int $supplierId): Response
    {
        try {
            if (!Auth::hasPermission('suppliers.view')) {
                return Response::json(['error' => 'Unauthorized'], 403);
            }

            $dateFrom = $request->input('date_from');
            $dateTo = $request->input('date_to');
            $stats = $this->supplierModel->getPerformanceStats($supplierId, $dateFrom, $dateTo);

            return Response::json(['stats' => $stats]);

        } catch (\Exception $e) {
            error_log('API Supplier Performance Error: ' . $e->getMessage());
            return Response::json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Get top suppliers
     */
    public function topSuppliers(Request $request): Response
    {
        try {
            if (!Auth::hasPermission('suppliers.view')) {
                return Response::json(['error' => 'Unauthorized'], 403);
            }

            $limit = $request->input('limit', 10);
            $dateFrom = $request->input('date_from');
            $dateTo = $request->input('date_to');
            $suppliers = $this->supplierModel->getTopSuppliers($limit, $dateFrom, $dateTo);

            return Response::json(['suppliers' => $suppliers]);

        } catch (\Exception $e) {
            error_log('API Top Suppliers Error: ' . $e->getMessage());
            return Response::json(['error' => 'Internal server error'], 500);
        }
    }

    // ================================================
    // PURCHASES API ENDPOINTS
    // ================================================

    /**
     * Get all purchases
     */
    public function purchases(Request $request): Response
    {
        try {
            if (!Auth::hasPermission('purchases.view')) {
                return Response::json(['error' => 'Unauthorized'], 403);
            }

            $page = $request->input('page', 1);
            $limit = $request->input('limit', 20);
            $filters = [
                'supplier_id' => $request->input('supplier_id'),
                'status' => $request->input('status'),
                'date_from' => $request->input('date_from'),
                'date_to' => $request->input('date_to'),
                'search' => $request->input('search')
            ];

            $purchases = $this->purchaseModel->getAll($filters, $page, $limit);
            $totalPurchases = $this->purchaseModel->getTotalCount($filters);

            $pagination = [
                'current_page' => $page,
                'total_pages' => ceil($totalPurchases / $limit),
                'total_items' => $totalPurchases,
                'per_page' => $limit
            ];

            return Response::json([
                'purchases' => $purchases,
                'pagination' => $pagination
            ]);

        } catch (\Exception $e) {
            error_log('API Purchases Error: ' . $e->getMessage());
            return Response::json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Get purchase details
     */
    public function purchase(Request $request, int $id): Response
    {
        try {
            if (!Auth::hasPermission('purchases.view')) {
                return Response::json(['error' => 'Unauthorized'], 403);
            }

            $purchase = $this->purchaseModel->find($id);
            if (!$purchase) {
                return Response::json(['error' => 'Purchase not found'], 404);
            }

            return Response::json(['purchase' => $purchase]);

        } catch (\Exception $e) {
            error_log('API Purchase Error: ' . $e->getMessage());
            return Response::json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Get purchase statistics
     */
    public function purchaseStats(Request $request): Response
    {
        try {
            if (!Auth::hasPermission('purchases.view')) {
                return Response::json(['error' => 'Unauthorized'], 403);
            }

            $dateFrom = $request->input('date_from');
            $dateTo = $request->input('date_to');
            $stats = $this->purchaseModel->getStatistics($dateFrom, $dateTo);

            return Response::json(['statistics' => $stats]);

        } catch (\Exception $e) {
            error_log('API Purchase Stats Error: ' . $e->getMessage());
            return Response::json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Get monthly purchases
     */
    public function monthlyPurchases(Request $request): Response
    {
        try {
            if (!Auth::hasPermission('purchases.view')) {
                return Response::json(['error' => 'Unauthorized'], 403);
            }

            $year = $request->input('year', date('Y'));
            $summary = $this->purchaseModel->getMonthlySummary($year);

            return Response::json(['monthly_summary' => $summary]);

        } catch (\Exception $e) {
            error_log('API Monthly Purchases Error: ' . $e->getMessage());
            return Response::json(['error' => 'Internal server error'], 500);
        }
    }

    // ================================================
    // UNITS API ENDPOINTS
    // ================================================

    /**
     * Get all units
     */
    public function units(Request $request): Response
    {
        try {
            if (!Auth::hasPermission('units.view')) {
                return Response::json(['error' => 'Unauthorized'], 403);
            }

            $units = $this->unitModel->getAll();

            return Response::json(['units' => $units]);

        } catch (\Exception $e) {
            error_log('API Units Error: ' . $e->getMessage());
            return Response::json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Get active units
     */
    public function activeUnits(Request $request): Response
    {
        try {
            if (!Auth::hasPermission('units.view')) {
                return Response::json(['error' => 'Unauthorized'], 403);
            }

            $units = $this->unitModel->getActive();

            return Response::json(['units' => $units]);

        } catch (\Exception $e) {
            error_log('API Active Units Error: ' . $e->getMessage());
            return Response::json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Get common units
     */
    public function commonUnits(Request $request): Response
    {
        try {
            if (!Auth::hasPermission('units.view')) {
                return Response::json(['error' => 'Unauthorized'], 403);
            }

            $units = $this->unitModel->getCommon();

            return Response::json(['units' => $units]);

        } catch (\Exception $e) {
            error_log('API Common Units Error: ' . $e->getMessage());
            return Response::json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Get unit hierarchy
     */
    public function unitHierarchy(Request $request): Response
    {
        try {
            if (!Auth::hasPermission('units.view')) {
                return Response::json(['error' => 'Unauthorized'], 403);
            }

            $hierarchy = $this->unitModel->getHierarchy();

            return Response::json(['hierarchy' => $hierarchy]);

        } catch (\Exception $e) {
            error_log('API Unit Hierarchy Error: ' . $e->getMessage());
            return Response::json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Convert between units
     */
    public function convertUnit(Request $request): Response
    {
        try {
            if (!Auth::hasPermission('units.view')) {
                return Response::json(['error' => 'Unauthorized'], 403);
            }

            $quantity = $request->input('quantity');
            $fromUnitId = $request->input('from_unit_id');
            $toUnitId = $request->input('to_unit_id');

            if (!$quantity || !$fromUnitId || !$toUnitId) {
                return Response::json(['error' => 'Missing required parameters'], 400);
            }

            $convertedQuantity = $this->unitModel->convertQuantity($quantity, $fromUnitId, $toUnitId);

            return Response::json([
                'original_quantity' => $quantity,
                'converted_quantity' => $convertedQuantity
            ]);

        } catch (\Exception $e) {
            error_log('API Unit Conversion Error: ' . $e->getMessage());
            return Response::json(['error' => 'Internal server error'], 500);
        }
    }
}