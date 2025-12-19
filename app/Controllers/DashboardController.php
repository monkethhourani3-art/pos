<?php

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Models\Report;
use App\Models\Order;
use App\Models\InventoryItem;
use App\Models\PaymentTransaction;
use App\Support\Facades\Auth;
use App\Support\Facades\Session;
use App\Validation\Validator;

class DashboardController
{
    private $reportModel;
    private $orderModel;
    private $inventoryItemModel;
    private $paymentTransactionModel;

    public function __construct()
    {
        $this->reportModel = new Report();
        $this->orderModel = new Order();
        $this->inventoryItemModel = new InventoryItem();
        $this->paymentTransactionModel = new PaymentTransaction();
    }

    /**
     * Display main dashboard
     */
    public function index(Request $request): Response
    {
        // Check permissions
        if (!Auth::hasPermission('dashboard.view')) {
            return Response::error('Unauthorized access', 403);
        }

        $dateFrom = $request->input('date_from', date('Y-m-01'));
        $dateTo = $request->input('date_to', date('Y-m-d'));
        $refresh = $request->input('refresh', false);

        try {
            // Get dashboard statistics
            $dashboardStats = $this->reportModel->getDashboardStats($dateFrom, $dateTo);
            
            // Get real-time data if requested
            if ($refresh) {
                $realtimeData = $this->getRealtimeStats();
                $dashboardStats['realtime'] = $realtimeData;
            }

            // Get active alerts and notifications
            $alerts = $this->getDashboardAlerts();

            return Response::view('dashboard.index', [
                'dashboard_stats' => $dashboardStats,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'alerts' => $alerts
            ]);

        } catch (\Exception $e) {
            error_log('Dashboard error: ' . $e->getMessage());
            return Response::error('فشل في تحميل لوحة التحكم', 500);
        }
    }

    /**
     * Get realtime dashboard updates
     */
    public function realtime(Request $request): Response
    {
        // Check permissions
        if (!Auth::hasPermission('dashboard.view')) {
            return Response::error('Unauthorized access', 403);
        }

        try {
            $realtimeData = $this->getRealtimeStats();
            
            return Response::json([
                'success' => true,
                'data' => $realtimeData,
                'timestamp' => date('Y-m-d H:i:s')
            ]);

        } catch (\Exception $e) {
            error_log('Realtime dashboard error: ' . $e->getMessage());
            return Response::json([
                'success' => false,
                'error' => 'فشل في تحميل البيانات الفورية'
            ], 500);
        }
    }

    /**
     * Get dashboard widget data
     */
    public function widget(Request $request): Response
    {
        // Check permissions
        if (!Auth::hasPermission('dashboard.view')) {
            return Response::error('Unauthorized access', 403);
        }

        $widgetType = $request->input('widget_type');
        $dateFrom = $request->input('date_from', date('Y-m-01'));
        $dateTo = $request->input('date_to', date('Y-m-d'));

        try {
            $widgetData = [];

            switch ($widgetType) {
                case 'sales_summary':
                    $widgetData = $this->getSalesWidgetData($dateFrom, $dateTo);
                    break;
                    
                case 'orders_status':
                    $widgetData = $this->getOrdersStatusWidgetData();
                    break;
                    
                case 'inventory_alerts':
                    $widgetData = $this->getInventoryAlertsWidgetData();
                    break;
                    
                case 'payment_summary':
                    $widgetData = $this->getPaymentWidgetData($dateFrom, $dateTo);
                    break;
                    
                case 'top_products':
                    $widgetData = $this->getTopProductsWidgetData($dateFrom, $dateTo);
                    break;
                    
                case 'recent_activities':
                    $widgetData = $this->getRecentActivitiesWidgetData();
                    break;
                    
                case 'kpi_cards':
                    $widgetData = $this->getKpiCardsWidgetData($dateFrom, $dateTo);
                    break;
                    
                default:
                    return Response::error('نوع الودجت غير مدعوم', 400);
            }

            return Response::json([
                'success' => true,
                'widget_type' => $widgetType,
                'data' => $widgetData
            ]);

        } catch (\Exception $e) {
            error_log('Widget data error: ' . $e->getMessage());
            return Response::json([
                'success' => false,
                'error' => 'فشل في تحميل بيانات الودجت'
            ], 500);
        }
    }

    /**
     * Update dashboard layout
     */
    public function updateLayout(Request $request): Response
    {
        // Check permissions
        if (!Auth::hasPermission('dashboard.manage')) {
            return Response::error('Unauthorized access', 403);
        }

        $validator = new Validator();
        $data = $request->all();

        $validator->validate($data, [
            'layout' => 'required|array',
            'widgets' => 'required|array'
        ]);

        if ($validator->fails()) {
            return Response::json(['error' => $validator->firstError()], 422);
        }

        try {
            // Save user's dashboard layout preference
            // This would typically save to user preferences or session
            Session::put('dashboard_layout', $data);

            return Response::json([
                'success' => true,
                'message' => 'تم تحديث تخطيط لوحة التحكم بنجاح'
            ]);

        } catch (\Exception $e) {
            error_log('Dashboard layout update error: ' . $e->getMessage());
            return Response::json([
                'success' => false,
                'error' => 'فشل في تحديث التخطيط'
            ], 500);
        }
    }

    /**
     * Get dashboard alerts
     */
    public function alerts(Request $request): Response
    {
        // Check permissions
        if (!Auth::hasPermission('dashboard.view')) {
            return Response::error('Unauthorized access', 403);
        }

        try {
            $alerts = $this->getDashboardAlerts();
            
            return Response::json([
                'success' => true,
                'alerts' => $alerts
            ]);

        } catch (\Exception $e) {
            error_log('Dashboard alerts error: ' . $e->getMessage());
            return Response::json([
                'success' => false,
                'error' => 'فشل في تحميل التنبيهات'
            ], 500);
        }
    }

    /**
     * Mark alert as read
     */
    public function markAlertRead(Request $request, int $alertId): Response
    {
        // Check permissions
        if (!Auth::hasPermission('dashboard.view')) {
            return Response::error('Unauthorized access', 403);
        }

        try {
            // This would typically update the alert status in database
            $this->logActivity('alert_marked_read', "تم تحديد التنبيه كمقروء: " . $alertId);

            return Response::json([
                'success' => true,
                'message' => 'تم تحديد التنبيه كمقروء'
            ]);

        } catch (\Exception $e) {
            error_log('Mark alert read error: ' . $e->getMessage());
            return Response::json([
                'success' => false,
                'error' => 'فشل في تحديد التنبيه'
            ], 500);
        }
    }

    /**
     * Export dashboard data
     */
    public function export(Request $request): Response
    {
        // Check permissions
        if (!Auth::hasPermission('reports.view')) {
            return Response::error('Unauthorized access', 403);
        }

        $format = $request->input('format', 'csv');
        $dateFrom = $request->input('date_from', date('Y-m-01'));
        $dateTo = $request->input('date_to', date('Y-m-d'));

        try {
            $dashboardStats = $this->reportModel->getDashboardStats($dateFrom, $dateTo);
            
            $filename = 'dashboard-export-' . date('Y-m-d_H-i-s');
            
            if ($format === 'json') {
                return Response::json($dashboardStats);
            } elseif ($format === 'csv') {
                $csvData = $this->exportDashboardToCsv($dashboardStats);
                return Response::csv($csvData, $filename . '.csv');
            } else {
                return Response::error('تنسيق التصدير غير مدعوم', 400);
            }

        } catch (\Exception $e) {
            error_log('Dashboard export error: ' . $e->getMessage());
            return Response::error('فشل في تصدير بيانات لوحة التحكم', 500);
        }
    }

    /**
     * Get realtime statistics
     */
    private function getRealtimeStats(): array
    {
        // Today's stats
        $today = date('Y-m-d');
        
        $todayOrders = $this->orderModel->getTodayStats();
        $todayPayments = $this->paymentTransactionModel->getTodayStats();
        $activeOrders = $this->orderModel->getActiveOrdersCount();
        $lowStockItems = $this->inventoryItemModel->getLowStockItemsCount();
        
        return [
            'today_orders' => $todayOrders,
            'today_payments' => $todayPayments,
            'active_orders' => $activeOrders,
            'low_stock_items' => $lowStockItems,
            'last_updated' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Get dashboard alerts
     */
    private function getDashboardAlerts(): array
    {
        $alerts = [];

        // Low stock alerts
        $lowStockItems = $this->inventoryItemModel->getLowStockItems();
        if (count($lowStockItems) > 0) {
            $alerts[] = [
                'id' => 'low_stock',
                'type' => 'warning',
                'title' => 'تنبيه مخزون منخفض',
                'message' => count($lowStockItems) . ' أصناف تحتاج إعادة طلب',
                'count' => count($lowStockItems),
                'link' => '/inventory/low-stock',
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }

        // Expiring items alerts
        $expiringItems = $this->inventoryItemModel->getExpiringItems(7);
        if (count($expiringItems) > 0) {
            $alerts[] = [
                'id' => 'expiring_items',
                'type' => 'danger',
                'title' => 'أصناف منتهية الصلاحية',
                'message' => count($expiringItems) . ' أصناف تنتهي خلال 7 أيام',
                'count' => count($expiringItems),
                'link' => '/inventory/expiring',
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }

        // Pending orders alerts
        $pendingOrders = $this->orderModel->getPendingOrdersCount();
        if ($pendingOrders > 0) {
            $alerts[] = [
                'id' => 'pending_orders',
                'type' => 'info',
                'title' => 'طلبات معلقة',
                'message' => $pendingOrders . ' طلبات في انتظار المعالجة',
                'count' => $pendingOrders,
                'link' => '/orders?status=pending',
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }

        return $alerts;
    }

    /**
     * Get sales widget data
     */
    private function getSalesWidgetData(string $dateFrom, string $dateTo): array
    {
        $dashboardStats = $this->reportModel->getDashboardStats($dateFrom, $dateTo);
        
        return [
            'total_sales' => $dashboardStats['sales']['total_sales'] ?? 0,
            'total_orders' => $dashboardStats['sales']['total_orders'] ?? 0,
            'average_order_value' => $dashboardStats['sales']['average_order_value'] ?? 0,
            'completed_orders' => $dashboardStats['sales']['completed_orders'] ?? 0,
            'pending_orders' => $dashboardStats['sales']['pending_orders'] ?? 0,
            'trend' => 'up' // Would calculate based on previous period
        ];
    }

    /**
     * Get orders status widget data
     */
    private function getOrdersStatusWidgetData(): array
    {
        $orders = $this->orderModel->getOrdersStatusSummary();
        
        return [
            'pending' => $orders['pending'] ?? 0,
            'preparing' => $orders['preparing'] ?? 0,
            'ready' => $orders['ready'] ?? 0,
            'served' => $orders['served'] ?? 0,
            'cancelled' => $orders['cancelled'] ?? 0
        ];
    }

    /**
     * Get inventory alerts widget data
     */
    private function getInventoryAlertsWidgetData(): array
    {
        $lowStockItems = $this->inventoryItemModel->getLowStockItems();
        $expiringItems = $this->inventoryItemModel->getExpiringItems(7);
        
        return [
            'low_stock_count' => count($lowStockItems),
            'expiring_count' => count($expiringItems),
            'low_stock_items' => array_slice($lowStockItems, 0, 5),
            'expiring_items' => array_slice($expiringItems, 0, 5)
        ];
    }

    /**
     * Get payment widget data
     */
    private function getPaymentWidgetData(string $dateFrom, string $dateTo): array
    {
        $dashboardStats = $this->reportModel->getDashboardStats($dateFrom, $dateTo);
        
        return [
            'total_payments' => $dashboardStats['payments']['total_payments'] ?? 0,
            'total_transactions' => $dashboardStats['payments']['total_transactions'] ?? 0,
            'average_payment' => $dashboardStats['payments']['average_payment'] ?? 0,
            'payment_methods' => $dashboardStats['payment_methods'] ?? []
        ];
    }

    /**
     * Get top products widget data
     */
    private function getTopProductsWidgetData(string $dateFrom, string $dateTo): array
    {
        $dashboardStats = $this->reportModel->getDashboardStats($dateFrom, $dateTo);
        
        return [
            'top_products' => array_slice($dashboardStats['top_products'] ?? [], 0, 10)
        ];
    }

    /**
     * Get recent activities widget data
     */
    private function getRecentActivitiesWidgetData(): array
    {
        // This would get recent system activities
        // For now, return mock data
        return [
            'activities' => [
                [
                    'id' => 1,
                    'type' => 'order',
                    'message' => 'تم إنشاء طلب جديد #12345',
                    'timestamp' => date('Y-m-d H:i:s', strtotime('-5 minutes')),
                    'icon' => 'fas fa-shopping-cart'
                ],
                [
                    'id' => 2,
                    'type' => 'payment',
                    'message' => 'تم استلام دفعة بقيمة 150 ر.س',
                    'timestamp' => date('Y-m-d H:i:s', strtotime('-10 minutes')),
                    'icon' => 'fas fa-credit-card'
                ],
                [
                    'id' => 3,
                    'type' => 'inventory',
                    'message' => 'تم تحديث مخزون المنتج XYZ',
                    'timestamp' => date('Y-m-d H:i:s', strtotime('-15 minutes')),
                    'icon' => 'fas fa-boxes'
                ]
            ]
        ];
    }

    /**
     * Get KPI cards widget data
     */
    private function getKpiCardsWidgetData(string $dateFrom, string $dateTo): array
    {
        $dashboardStats = $this->reportModel->getDashboardStats($dateFrom, $dateTo);
        $performanceMetrics = $this->reportModel->getPerformanceMetrics($dateFrom, $dateTo);
        
        return [
            'revenue' => [
                'value' => $dashboardStats['sales']['total_sales'] ?? 0,
                'target' => 100000,
                'unit' => 'ر.س',
                'trend' => 'up'
            ],
            'orders' => [
                'value' => $dashboardStats['sales']['total_orders'] ?? 0,
                'target' => 1000,
                'unit' => 'طلب',
                'trend' => 'up'
            ],
            'average_order' => [
                'value' => $dashboardStats['sales']['average_order_value'] ?? 0,
                'target' => 150,
                'unit' => 'ر.س',
                'trend' => 'stable'
            ],
            'customer_satisfaction' => [
                'value' => $performanceMetrics['customer_satisfaction']['average_rating'] ?? 0,
                'target' => 4.5,
                'unit' => '/5',
                'trend' => 'up'
            ]
        ];
    }

    /**
     * Export dashboard to CSV
     */
    private function exportDashboardToCsv(array $dashboardStats): string
    {
        $csv = "Metric,Value\n";
        
        $csv .= "Total Sales," . ($dashboardStats['sales']['total_sales'] ?? 0) . "\n";
        $csv .= "Total Orders," . ($dashboardStats['sales']['total_orders'] ?? 0) . "\n";
        $csv .= "Average Order Value," . ($dashboardStats['sales']['average_order_value'] ?? 0) . "\n";
        $csv .= "Total Payments," . ($dashboardStats['payments']['total_payments'] ?? 0) . "\n";
        $csv .= "Total Transactions," . ($dashboardStats['payments']['total_transactions'] ?? 0) . "\n";
        $csv .= "Total Inventory Items," . ($dashboardStats['inventory']['total_items'] ?? 0) . "\n";
        $csv .= "Low Stock Items," . ($dashboardStats['inventory']['low_stock_items'] ?? 0) . "\n";
        $csv .= "Expiring Items," . ($dashboardStats['inventory']['expiring_items'] ?? 0) . "\n";
        
        return $csv;
    }

    /**
     * Log activity
     */
    private function logActivity(string $action, string $description, ?int $resourceId = null): void
    {
        // Implementation for activity logging
        error_log("Dashboard Activity: {$action} - {$description} (Resource ID: {$resourceId})");
    }
}
                'user' => Auth::user()
            ];
            
            return Response::view('dashboard.index', $data);
            
        } catch (\Exception $e) {
            Log::error('Dashboard error: ' . $e->getMessage());
            
            return Response::view('dashboard.index', [
                'stats' => $this->getDefaultStats(),
                'recentOrders' => [],
                'popularProducts' => [],
                'activeTables' => [],
                'currentShift' => null,
                'user' => Auth::user(),
                'error' => 'حدث خطأ في تحميل لوحة التحكم'
            ]);
        }
    }

    /**
     * Get dashboard statistics
     */
    protected function getDashboardStats()
    {
        $today = date('Y-m-d');
        $currentMonth = date('Y-m');
        
        // Today's sales
        $todaySales = $this->db->fetchOne("
            SELECT 
                COALESCE(SUM(total_amount), 0) as total,
                COUNT(*) as orders_count,
                COALESCE(AVG(total_amount), 0) as avg_order
            FROM orders 
            WHERE DATE(created_at) = ? AND status != 'cancelled'
        ", [$today]);
        
        // This month's sales
        $monthSales = $this->db->fetchOne("
            SELECT 
                COALESCE(SUM(total_amount), 0) as total,
                COUNT(*) as orders_count,
                COALESCE(AVG(total_amount), 0) as avg_order
            FROM orders 
            WHERE DATE_FORMAT(created_at, '%Y-%m') = ? AND status != 'cancelled'
        ", [$currentMonth]);
        
        // Active orders
        $activeOrders = $this->db->fetchOne("
            SELECT COUNT(*) as count
            FROM orders 
            WHERE status IN ('sent_to_kitchen', 'preparing', 'ready')
        ");
        
        // Total products
        $totalProducts = $this->db->fetchOne("
            SELECT COUNT(*) as count
            FROM products 
            WHERE is_available = 1 AND deleted_at IS NULL
        ");
        
        // Total tables
        $totalTables = $this->db->fetchOne("
            SELECT COUNT(*) as count
            FROM tables 
            WHERE is_active = 1
        ");
        
        // Available tables
        $availableTables = $this->db->fetchOne("
            SELECT COUNT(*) as count
            FROM tables 
            WHERE is_active = 1 AND status = 'available'
        ");
        
        // Total customers today
        $totalCustomers = $this->db->fetchOne("
            SELECT COUNT(DISTINCT customer_name) as count
            FROM orders 
            WHERE DATE(created_at) = ? AND customer_name IS NOT NULL
        ", [$today]);
        
        return [
            'today_sales' => [
                'total' => $todaySales->total ?? 0,
                'orders' => $todaySales->orders_count ?? 0,
                'avg_order' => $todaySales->avg_order ?? 0
            ],
            'month_sales' => [
                'total' => $monthSales->total ?? 0,
                'orders' => $monthSales->orders_count ?? 0,
                'avg_order' => $monthSales->avg_order ?? 0
            ],
            'active_orders' => $activeOrders->count ?? 0,
            'total_products' => $totalProducts->count ?? 0,
            'total_tables' => $totalTables->count ?? 0,
            'available_tables' => $availableTables->count ?? 0,
            'total_customers' => $totalCustomers->count ?? 0
        ];
    }

    /**
     * Get recent orders
     */
    protected function getRecentOrders($limit = 10)
    {
        return $this->db->fetchAll("
            SELECT 
                o.id,
                o.order_number,
                o.order_type,
                o.status,
                o.total_amount,
                o.created_at,
                t.table_number,
                t.table_name,
                CONCAT(u.first_name, ' ', u.last_name) as waiter_name
            FROM orders o
            LEFT JOIN tables t ON o.table_id = t.id
            LEFT JOIN users u ON o.user_id = u.id
            WHERE o.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY o.created_at DESC
            LIMIT ?
        ", [$limit]);
    }

    /**
     * Get popular products
     */
    protected function getPopularProducts($limit = 5)
    {
        return $this->db->fetchAll("
            SELECT 
                p.id,
                p.name_ar,
                p.name_en,
                p.base_price,
                SUM(oi.quantity) as total_sold,
                SUM(oi.total_price) as total_revenue
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            JOIN orders o ON oi.order_id = o.id
            WHERE o.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            AND o.status != 'cancelled'
            GROUP BY p.id
            ORDER BY total_sold DESC
            LIMIT ?
        ", [$limit]);
    }

    /**
     * Get active tables
     */
    protected function getActiveTables($limit = 20)
    {
        return $this->db->fetchAll("
            SELECT 
                t.id,
                t.table_number,
                t.table_name,
                t.capacity,
                t.status,
                a.display_name as area_name,
                COUNT(o.id) as active_orders
            FROM tables t
            LEFT JOIN areas a ON t.area_id = a.id
            LEFT JOIN orders o ON t.id = o.table_id AND o.status IN ('sent_to_kitchen', 'preparing', 'ready')
            WHERE t.is_active = 1
            GROUP BY t.id
            ORDER BY a.sort_order, t.table_number
            LIMIT ?
        ", [$limit]);
    }

    /**
     * Get current shift
     */
    protected function getCurrentShift()
    {
        return $this->db->fetchOne("
            SELECT 
                s.*,
                CONCAT(u.first_name, ' ', u.last_name) as opened_by_name
            FROM shifts s
            JOIN users u ON s.opened_by = u.id
            WHERE s.status = 'open'
            ORDER BY s.opened_at DESC
            LIMIT 1
        ");
    }

    /**
     * Get default stats when database is empty
     */
    protected function getDefaultStats()
    {
        return [
            'today_sales' => ['total' => 0, 'orders' => 0, 'avg_order' => 0],
            'month_sales' => ['total' => 0, 'orders' => 0, 'avg_order' => 0],
            'active_orders' => 0,
            'total_products' => 0,
            'total_tables' => 0,
            'available_tables' => 0,
            'total_customers' => 0
        ];
    }

    /**
     * Get dashboard statistics as JSON (for AJAX)
     */
    public function stats(Request $request)
    {
        try {
            $stats = $this->getDashboardStats();
            return Response::json($stats);
        } catch (\Exception $e) {
            Log::error('Dashboard stats error: ' . $e->getMessage());
            return Response::json(['error' => 'خطأ في تحميل الإحصائيات'], 500);
        }
    }

    /**
     * Get today's sales chart data
     */
    public function salesChart(Request $request)
    {
        try {
            $date = $request->get('date', date('Y-m-d'));
            
            $salesData = $this->db->fetchAll("
                SELECT 
                    HOUR(created_at) as hour,
                    SUM(total_amount) as total,
                    COUNT(*) as orders
                FROM orders 
                WHERE DATE(created_at) = ? 
                AND status != 'cancelled'
                GROUP BY HOUR(created_at)
                ORDER BY hour
            ", [$date]);
            
            // Format for chart.js
            $chartData = [
                'labels' => [],
                'datasets' => [
                    [
                        'label' => 'المبيعات',
                        'data' => [],
                        'backgroundColor' => 'rgba(52, 152, 219, 0.2)',
                        'borderColor' => 'rgba(52, 152, 219, 1)',
                        'borderWidth' => 2
                    ]
                ]
            ];
            
            // Fill missing hours with 0
            for ($hour = 0; $hour < 24; $hour++) {
                $chartData['labels'][] = sprintf('%02d:00', $hour);
                $chartData['datasets'][0]['data'][] = 0;
            }
            
            // Update with actual data
            foreach ($salesData as $sale) {
                $chartData['datasets'][0]['data'][$sale->hour] = (float)$sale->total;
            }
            
            return Response::json($chartData);
            
        } catch (\Exception $e) {
            Log::error('Sales chart error: ' . $e->getMessage());
            return Response::json(['error' => 'خطأ في تحميل بيانات المبيعات'], 500);
        }
    }
}