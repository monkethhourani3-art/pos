<?php

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Models\Report;
use App\Models\SalesReport;
use App\Support\Facades\Auth;
use App\Support\Facades\Session;
use App\Validation\Validator;

class ReportsController
{
    private $reportModel;

    public function __construct()
    {
        $this->reportModel = new Report();
    }

    /**
     * Display reports dashboard
     */
    public function index(Request $request): Response
    {
        // Check permissions
        if (!Auth::hasPermission('reports.view')) {
            return Response::error('Unauthorized access', 403);
        }

        $dateFrom = $request->input('date_from', date('Y-m-01'));
        $dateTo = $request->input('date_to', date('Y-m-d'));

        try {
            $overviewStats = $this->reportModel->getDashboardStats($dateFrom, $dateTo);
            
            // Format data for the overview dashboard
            $formattedStats = [
                'total_sales' => $overviewStats['sales']['total_sales'] ?? 0,
                'total_orders' => $overviewStats['sales']['total_orders'] ?? 0,
                'average_order_value' => $overviewStats['sales']['average_order_value'] ?? 0,
                'unique_customers' => $overviewStats['customers']['unique_customers'] ?? 0,
                'sales_growth' => $overviewStats['growth']['sales_growth'] ?? 0,
                'orders_growth' => $overviewStats['growth']['orders_growth'] ?? 0,
                'avg_growth' => $overviewStats['growth']['avg_growth'] ?? 0,
                'customers_growth' => $overviewStats['growth']['customers_growth'] ?? 0,
                'top_products' => $overviewStats['products']['top_selling'] ?? [],
                'top_staff' => $overviewStats['staff']['top_performers'] ?? []
            ];

            return Response::view('reports.index', [
                'overview_stats' => $formattedStats,
                'sales_data' => $overviewStats['daily_sales'] ?? [],
                'products_data' => $overviewStats['products']['detailed'] ?? [],
                'date_from' => $dateFrom,
                'date_to' => $dateTo
            ]);

        } catch (\Exception $e) {
            error_log('Dashboard report error: ' . $e->getMessage());
            return Response::error('فشل في تحميل لوحة التحكم', 500);
        }
    }

    /**
     * Display sales report
     */
    public function sales(Request $request): Response
    {
        // Check permissions
        if (!Auth::hasPermission('reports.view')) {
            return Response::error('Unauthorized access', 403);
        }

        $filters = [
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'status' => $request->input('status'),
            'payment_method_id' => $request->input('payment_method_id')
        ];

        try {
            $salesReport = $this->reportModel->getSalesReport($filters);

            return Response::view('reports.sales', [
                'report' => $salesReport,
                'filters' => $filters
            ]);

        } catch (\Exception $e) {
            error_log('Sales report error: ' . $e->getMessage());
            return Response::error('فشل في تحميل تقرير المبيعات', 500);
        }
    }

    /**
     * Display inventory report
     */
    public function inventory(Request $request): Response
    {
        // Check permissions
        if (!Auth::hasPermission('reports.view')) {
            return Response::error('Unauthorized access', 403);
        }

        $filters = [
            'category_id' => $request->input('category_id'),
            'supplier_id' => $request->input('supplier_id'),
            'low_stock' => $request->input('low_stock'),
            'expiring' => $request->input('expiring'),
            'days' => $request->input('days', 30)
        ];

        try {
            $inventoryReport = $this->reportModel->getInventoryReport($filters);

            return Response::view('reports.inventory', [
                'report' => $inventoryReport,
                'filters' => $filters
            ]);

        } catch (\Exception $e) {
            error_log('Inventory report error: ' . $e->getMessage());
            return Response::error('فشل في تحميل تقرير المخزون', 500);
        }
    }

    /**
     * Display profit and loss report
     */
    public function profitLoss(Request $request): Response
    {
        // Check permissions
        if (!Auth::hasPermission('reports.view')) {
            return Response::error('Unauthorized access', 403);
        }

        $dateFrom = $request->input('date_from', date('Y-m-01'));
        $dateTo = $request->input('date_to', date('Y-m-d'));

        try {
            $profitLossReport = $this->reportModel->getProfitLossReport($dateFrom, $dateTo);

            return Response::view('reports.profit-loss', [
                'report' => $profitLossReport,
                'date_from' => $dateFrom,
                'date_to' => $dateTo
            ]);

        } catch (\Exception $e) {
            error_log('Profit/Loss report error: ' . $e->getMessage());
            return Response::error('فشل في تحميل تقرير الأرباح والخسائر', 500);
        }
    }

    /**
     * Display performance report
     */
    public function performance(Request $request): Response
    {
        // Check permissions
        if (!Auth::hasPermission('reports.view')) {
            return Response::error('Unauthorized access', 403);
        }

        $dateFrom = $request->input('date_from', date('Y-m-01'));
        $dateTo = $request->input('date_to', date('Y-m-d'));

        try {
            $performanceReport = $this->reportModel->getPerformanceMetrics($dateFrom, $dateTo);

            return Response::view('reports.performance', [
                'report' => $performanceReport,
                'date_from' => $dateFrom,
                'date_to' => $dateTo
            ]);

        } catch (\Exception $e) {
            error_log('Performance report error: ' . $e->getMessage());
            return Response::error('فشل في تحميل تقرير الأداء', 500);
        }
    }

    /**
     * Generate custom report
     */
    public function custom(Request $request): Response
    {
        // Check permissions
        if (!Auth::hasPermission('reports.view')) {
            return Response::error('Unauthorized access', 403);
        }

        $validator = new Validator();
        $parameters = $request->all();

        // Validation for custom report
        $validator->validate($parameters, [
            'report_type' => 'required|in:sales,inventory,profit_loss,performance,dashboard',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'group_by' => 'nullable|in:day,week,month,year',
            'format' => 'nullable|in:screen,pdf,csv,json'
        ]);

        if ($validator->fails()) {
            return Response::error($validator->firstError(), 422);
        }

        try {
            $reportData = $this->reportModel->generateCustomReport($parameters);
            $format = $parameters['format'] ?? 'screen';

            if ($format === 'json') {
                return Response::json($reportData);
            } elseif ($format === 'csv') {
                $csvData = $this->reportModel->exportReport($reportData['sales'] ?? $reportData, 'csv');
                return Response::csv($csvData, 'custom-report.csv');
            } elseif ($format === 'pdf') {
                // PDF generation would be implemented here
                return Response::error('تصدير PDF غير متوفر حالياً', 501);
            } else {
                return Response::view('reports.custom', [
                    'report_data' => $reportData,
                    'parameters' => $parameters
                ]);
            }

        } catch (\Exception $e) {
            error_log('Custom report error: ' . $e->getMessage());
            return Response::error('فشل في إنشاء التقرير المخصص', 500);
        }
    }

    /**
     * Export report
     */
    public function export(Request $request): Response
    {
        // Check permissions
        if (!Auth::hasPermission('reports.view')) {
            return Response::error('Unauthorized access', 403);
        }

        $validator = new Validator();
        $parameters = $request->all();

        $validator->validate($parameters, [
            'report_type' => 'required|in:sales,inventory,profit_loss,performance',
            'format' => 'required|in:csv,json,pdf',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date'
        ]);

        if ($validator->fails()) {
            return Response::error($validator->firstError(), 422);
        }

        try {
            $reportData = $this->reportModel->generateCustomReport($parameters);
            $format = $parameters['format'];
            $filename = $parameters['report_type'] . '-report-' . date('Y-m-d_H-i-s');

            if ($format === 'json') {
                $jsonData = $this->reportModel->exportReport($reportData, 'json');
                return Response::json($reportData);
            } elseif ($format === 'csv') {
                $csvData = $this->reportModel->exportReport($reportData, 'csv');
                return Response::csv($csvData, $filename . '.csv');
            } elseif ($format === 'pdf') {
                // PDF generation would be implemented here
                return Response::error('تصدير PDF غير متوفر حالياً', 501);
            }

        } catch (\Exception $e) {
            error_log('Export report error: ' . $e->getMessage());
            return Response::error('فشل في تصدير التقرير', 500);
        }
    }

    /**
     * Get chart data for dashboard
     */
    public function chartData(Request $request): Response
    {
        // Check permissions
        if (!Auth::hasPermission('reports.view')) {
            return Response::error('Unauthorized access', 403);
        }

        $chartType = $request->input('chart_type');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        try {
            $dashboardStats = $this->reportModel->getDashboardStats($dateFrom, $dateTo);
            
            $chartData = [];
            
            switch ($chartType) {
                case 'daily_sales':
                    $chartData = [
                        'labels' => array_map(function($item) {
                            return date('Y-m-d', strtotime($item['date']));
                        }, $dashboardStats['daily_sales']),
                        'data' => array_map(function($item) {
                            return (float)$item['daily_sales'];
                        }, $dashboardStats['daily_sales'])
                    ];
                    break;
                    
                case 'hourly_sales':
                    $chartData = [
                        'labels' => array_map(function($item) {
                            return $item['hour'] . ':00';
                        }, $dashboardStats['hourly_sales']),
                        'data' => array_map(function($item) {
                            return (float)$item['hourly_sales'];
                        }, $dashboardStats['hourly_sales'])
                    ];
                    break;
                    
                case 'top_products':
                    $chartData = [
                        'labels' => array_map(function($item) {
                            return $item['name'];
                        }, $dashboardStats['top_products']),
                        'data' => array_map(function($item) {
                            return (float)$item['total_sold'];
                        }, $dashboardStats['top_products'])
                    ];
                    break;
                    
                case 'payment_methods':
                    $chartData = [
                        'labels' => array_map(function($item) {
                            return $item['method_name'];
                        }, $dashboardStats['payment_methods']),
                        'data' => array_map(function($item) {
                            return (float)$item['total_amount'];
                        }, $dashboardStats['payment_methods'])
                    ];
                    break;
                    
                default:
                    return Response::error('نوع الرسم البياني غير مدعوم', 400);
            }

            return Response::json([
                'chart_data' => $chartData,
                'chart_type' => $chartType
            ]);

        } catch (\Exception $e) {
            error_log('Chart data error: ' . $e->getMessage());
            return Response::error('فشل في تحميل بيانات الرسم البياني', 500);
        }
    }

    /**
     * Get real-time dashboard data
     */
    public function realtimeData(Request $request): Response
    {
        // Check permissions
        if (!Auth::hasPermission('reports.view')) {
            return Response::error('Unauthorized access', 403);
        }

        try {
            $dashboardStats = $this->reportModel->getDashboardStats(
                date('Y-m-d'), 
                date('Y-m-d')
            );

            return Response::json([
                'today_stats' => [
                    'total_orders' => $dashboardStats['sales']['total_orders'] ?? 0,
                    'total_sales' => $dashboardStats['sales']['total_sales'] ?? 0,
                    'average_order_value' => $dashboardStats['sales']['average_order_value'] ?? 0,
                    'pending_orders' => $dashboardStats['sales']['pending_orders'] ?? 0,
                    'low_stock_items' => $dashboardStats['inventory']['low_stock_items'] ?? 0,
                    'expiring_items' => $dashboardStats['inventory']['expiring_items'] ?? 0
                ],
                'timestamp' => date('Y-m-d H:i:s')
            ]);

        } catch (\Exception $e) {
            error_log('Realtime data error: ' . $e->getMessage());
            return Response::error('فشل في تحميل البيانات الفورية', 500);
        }
    }

    /**
     * Get KPIs data
     */
    public function kpis(Request $request): Response
    {
        // Check permissions
        if (!Auth::hasPermission('reports.view')) {
            return Response::error('Unauthorized access', 403);
        }

        $period = $request->input('period', 'current_month');
        $dateFrom = null;
        $dateTo = null;

        // Set date range based on period
        switch ($period) {
            case 'today':
                $dateFrom = date('Y-m-d');
                $dateTo = date('Y-m-d');
                break;
            case 'week':
                $dateFrom = date('Y-m-d', strtotime('monday this week'));
                $dateTo = date('Y-m-d', strtotime('sunday this week'));
                break;
            case 'month':
                $dateFrom = date('Y-m-01');
                $dateTo = date('Y-m-t');
                break;
            case 'quarter':
                $quarter = ceil(date('n') / 3);
                $dateFrom = date('Y-m-d', mktime(0, 0, 0, ($quarter - 1) * 3 + 1, 1, date('Y')));
                $dateTo = date('Y-m-d', mktime(0, 0, 0, $quarter * 3, 0, date('Y')));
                break;
            case 'year':
                $dateFrom = date('Y-01-01');
                $dateTo = date('Y-12-31');
                break;
        }

        try {
            $dashboardStats = $this->reportModel->getDashboardStats($dateFrom, $dateTo);
            $performanceMetrics = $this->reportModel->getPerformanceMetrics($dateFrom, $dateTo);

            $kpis = [
                'revenue' => [
                    'value' => $dashboardStats['sales']['total_sales'] ?? 0,
                    'target' => 100000, // Example target
                    'unit' => 'ر.س'
                ],
                'orders' => [
                    'value' => $dashboardStats['sales']['total_orders'] ?? 0,
                    'target' => 1000,
                    'unit' => 'طلب'
                ],
                'average_order_value' => [
                    'value' => $dashboardStats['sales']['average_order_value'] ?? 0,
                    'target' => 150,
                    'unit' => 'ر.س'
                ],
                'customer_satisfaction' => [
                    'value' => $performanceMetrics['customer_satisfaction']['average_rating'] ?? 0,
                    'target' => 4.5,
                    'unit' => '/5'
                ],
                'processing_time' => [
                    'value' => $performanceMetrics['processing_time']['average_processing_time_minutes'] ?? 0,
                    'target' => 15,
                    'unit' => 'دقيقة'
                ],
                'profit_margin' => [
                    'value' => 0, // Would need calculation
                    'target' => 25,
                    'unit' => '%'
                ]
            ];

            return Response::json([
                'kpis' => $kpis,
                'period' => $period,
                'date_from' => $dateFrom,
                'date_to' => $dateTo
            ]);

        } catch (\Exception $e) {
            error_log('KPIs error: ' . $e->getMessage());
            return Response::error('فشل في تحميل مؤشرات الأداء', 500);
        }
    }

    /**
     * Schedule report generation
     */
    public function schedule(Request $request): Response
    {
        // Check permissions
        if (!Auth::hasPermission('reports.view')) {
            return Response::error('Unauthorized access', 403);
        }

        $validator = new Validator();
        $data = $request->all();

        $validator->validate($data, [
            'report_type' => 'required|in:sales,inventory,profit_loss,performance,dashboard',
            'frequency' => 'required|in:daily,weekly,monthly',
            'recipients' => 'required|array|min:1',
            'format' => 'required|in:pdf,csv,json',
            'is_active' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return Response::error($validator->firstError(), 422);
        }

        // This would integrate with a cron job system or task scheduler
        // For now, we'll just log the schedule request
        $this->logActivity('report_scheduled', 
            "تم جدولة تقرير {$data['report_type']} بشكل {$data['frequency']}", 
            null, $data);

        return Response::success('تم جدولة التقرير بنجاح', '/reports');
    }

    /**
     * Get report templates
     */
    public function templates(Request $request): Response
    {
        // Check permissions
        if (!Auth::hasPermission('reports.view')) {
            return Response::error('Unauthorized access', 403);
        }

        $templates = [
            [
                'id' => 'daily_sales_summary',
                'name' => 'ملخص المبيعات اليومي',
                'description' => 'تقرير شامل للمبيعات اليومية مع الإحصائيات الأساسية',
                'type' => 'sales',
                'frequency' => 'daily',
                'format' => 'pdf'
            ],
            [
                'id' => 'weekly_inventory_status',
                'name' => 'حالة المخزون الأسبوعية',
                'description' => 'تقرير حالة المخزون مع التنبيهات والأصناف المنتهية',
                'type' => 'inventory',
                'frequency' => 'weekly',
                'format' => 'pdf'
            ],
            [
                'id' => 'monthly_profit_analysis',
                'name' => 'تحليل الأرباح الشهري',
                'description' => 'تحليل مفصل للأرباح والخسائر مع المقارنات',
                'type' => 'profit_loss',
                'frequency' => 'monthly',
                'format' => 'pdf'
            ],
            [
                'id' => 'staff_performance_review',
                'name' => 'مراجعة أداء الموظفين',
                'description' => 'تقرير أداء الموظفين والإنتاجية',
                'type' => 'performance',
                'frequency' => 'monthly',
                'format' => 'pdf'
            ]
        ];

        return Response::json(['templates' => $templates]);
    }

    /**
     * Log activity
     */
    private function logActivity(string $action, string $description, ?int $resourceId = null, array $data = []): void
    {
        // Implementation for activity logging
        error_log("Report Activity: {$action} - {$description} (Resource ID: {$resourceId})");
        
        // Log data for scheduled reports
        if (!empty($data)) {
            error_log("Report Data: " . json_encode($data));
        }
    }
}