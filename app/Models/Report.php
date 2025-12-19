<?php

namespace App\Models;

use App\Support\Database;
use PDO;

class Report
{
    private $db;
    private $table = 'reports';

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Get dashboard statistics
     */
    public function getDashboardStats(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $whereConditions = [];
        $params = [];

        if ($dateFrom) {
            $whereConditions[] = "created_at >= ?";
            $params[] = $dateFrom;
        }

        if ($dateTo) {
            $whereConditions[] = "created_at <= ?";
            $params[] = $dateTo;
        }

        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        // Sales statistics
        $salesSql = "
            SELECT 
                COUNT(*) as total_orders,
                SUM(total_amount) as total_sales,
                AVG(total_amount) as average_order_value,
                MIN(total_amount) as min_order_value,
                MAX(total_amount) as max_order_value,
                SUM(CASE WHEN status = 'completed' THEN total_amount ELSE 0 END) as completed_sales,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_orders,
                COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_orders
            FROM orders 
            {$whereClause}
        ";

        // Payment statistics
        $paymentSql = "
            SELECT 
                SUM(amount) as total_payments,
                COUNT(*) as total_transactions,
                AVG(amount) as average_payment,
                SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as completed_payments,
                SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending_payments,
                SUM(CASE WHEN status = 'failed' THEN amount ELSE 0 END) as failed_payments
            FROM payment_transactions 
            {$whereClause}
        ";

        // Inventory statistics
        $inventorySql = "
            SELECT 
                COUNT(*) as total_items,
                SUM(quantity * unit_cost) as total_inventory_value,
                SUM(quantity) as total_quantity,
                COUNT(CASE WHEN quantity <= min_stock_level THEN 1 END) as low_stock_items,
                COUNT(CASE WHEN expiry_date IS NOT NULL AND expiry_date <= DATE_ADD(NOW(), INTERVAL 30 DAY) THEN 1 END) as expiring_items
            FROM inventory_items
        ";

        // Daily sales for chart
        $dailySalesSql = "
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as orders_count,
                SUM(total_amount) as daily_sales
            FROM orders 
            {$whereClause}
            GROUP BY DATE(created_at)
            ORDER BY date DESC
            LIMIT 30
        ";

        // Hourly sales for chart
        $hourlySalesSql = "
            SELECT 
                HOUR(created_at) as hour,
                COUNT(*) as orders_count,
                SUM(total_amount) as hourly_sales
            FROM orders 
            WHERE DATE(created_at) = CURDATE()
            GROUP BY HOUR(created_at)
            ORDER BY hour
        ";

        // Top products
        $topProductsSql = "
            SELECT 
                p.name,
                SUM(oi.quantity) as total_sold,
                SUM(oi.quantity * oi.price) as total_revenue,
                AVG(oi.price) as average_price
            FROM order_items oi
            JOIN orders o ON oi.order_id = o.id
            JOIN products p ON oi.product_id = p.id
            {$whereClause}
            AND o.status = 'completed'
            GROUP BY p.id, p.name
            ORDER BY total_sold DESC
            LIMIT 10
        ";

        // Payment method distribution
        $paymentMethodsSql = "
            SELECT 
                pm.name as method_name,
                COUNT(pt.id) as transaction_count,
                SUM(pt.amount) as total_amount,
                AVG(pt.amount) as average_amount
            FROM payment_transactions pt
            JOIN payment_methods pm ON pt.payment_method_id = pm.id
            {$whereClause}
            AND pt.status = 'completed'
            GROUP BY pm.id, pm.name
            ORDER BY total_amount DESC
        ";

        // Execute all queries
        $salesStmt = $this->db->prepare($salesSql);
        $salesStmt->execute($params);
        $salesStats = $salesStmt->fetch(PDO::FETCH_ASSOC);

        $paymentStmt = $this->db->prepare($paymentSql);
        $paymentStmt->execute($params);
        $paymentStats = $paymentStmt->fetch(PDO::FETCH_ASSOC);

        $inventoryStmt = $this->db->prepare($inventorySql);
        $inventoryStmt->execute();
        $inventoryStats = $inventoryStmt->fetch(PDO::FETCH_ASSOC);

        $dailySalesStmt = $this->db->prepare($dailySalesSql);
        $dailySalesStmt->execute($params);
        $dailySales = $dailySalesStmt->fetchAll(PDO::FETCH_ASSOC);

        $hourlySalesStmt = $this->db->prepare($hourlySalesSql);
        $hourlySalesStmt->execute();
        $hourlySales = $hourlySalesStmt->fetchAll(PDO::FETCH_ASSOC);

        $topProductsStmt = $this->db->prepare($topProductsSql);
        $topProductsStmt->execute($params);
        $topProducts = $topProductsStmt->fetchAll(PDO::FETCH_ASSOC);

        $paymentMethodsStmt = $this->db->prepare($paymentMethodsSql);
        $paymentMethodsStmt->execute($params);
        $paymentMethods = $paymentMethodsStmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'sales' => $salesStats,
            'payments' => $paymentStats,
            'inventory' => $inventoryStats,
            'daily_sales' => $dailySales,
            'hourly_sales' => $hourlySales,
            'top_products' => $topProducts,
            'payment_methods' => $paymentMethods
        ];
    }

    /**
     * Get sales report
     */
    public function getSalesReport(array $filters = []): array
    {
        $whereConditions = [];
        $params = [];

        if (!empty($filters['date_from'])) {
            $whereConditions[] = "o.created_at >= ?";
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $whereConditions[] = "o.created_at <= ?";
            $params[] = $filters['date_to'];
        }

        if (!empty($filters['status'])) {
            $whereConditions[] = "o.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['payment_method_id'])) {
            $whereConditions[] = "pt.payment_method_id = ?";
            $params[] = $filters['payment_method_id'];
        }

        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        // Main sales data
        $salesSql = "
            SELECT 
                o.id,
                o.order_number,
                o.created_at,
                o.total_amount,
                o.tax_amount,
                o.discount_amount,
                o.status,
                u.name as customer_name,
                t.table_number,
                pt.payment_method_id,
                pm.name as payment_method_name,
                pt.amount as paid_amount,
                pt.status as payment_status
            FROM orders o
            LEFT JOIN users u ON o.customer_id = u.id
            LEFT JOIN tables t ON o.table_id = t.id
            LEFT JOIN payment_transactions pt ON o.id = pt.order_id
            LEFT JOIN payment_methods pm ON pt.payment_method_id = pm.id
            {$whereClause}
            ORDER BY o.created_at DESC
        ";

        // Summary statistics
        $summarySql = "
            SELECT 
                COUNT(*) as total_orders,
                SUM(total_amount) as total_sales,
                SUM(tax_amount) as total_tax,
                SUM(discount_amount) as total_discount,
                AVG(total_amount) as average_order_value,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_orders,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_orders,
                COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_orders
            FROM orders o
            {$whereClause}
        ";

        // Daily breakdown
        $dailySql = "
            SELECT 
                DATE(o.created_at) as date,
                COUNT(*) as orders_count,
                SUM(o.total_amount) as daily_sales,
                AVG(o.total_amount) as average_order_value
            FROM orders o
            {$whereClause}
            GROUP BY DATE(o.created_at)
            ORDER BY date DESC
        ";

        // Payment method breakdown
        $paymentBreakdownSql = "
            SELECT 
                pm.name as payment_method,
                COUNT(pt.id) as transaction_count,
                SUM(pt.amount) as total_amount,
                AVG(pt.amount) as average_amount,
                COUNT(CASE WHEN pt.status = 'completed' THEN 1 END) as successful_transactions,
                COUNT(CASE WHEN pt.status = 'failed' THEN 1 END) as failed_transactions
            FROM payment_transactions pt
            LEFT JOIN payment_methods pm ON pt.payment_method_id = pm.id
            LEFT JOIN orders o ON pt.order_id = o.id
            {$whereClause}
            GROUP BY pm.id, pm.name
            ORDER BY total_amount DESC
        ";

        $salesStmt = $this->db->prepare($salesSql);
        $salesStmt->execute($params);
        $sales = $salesStmt->fetchAll(PDO::FETCH_ASSOC);

        $summaryStmt = $this->db->prepare($summarySql);
        $summaryStmt->execute($params);
        $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);

        $dailyStmt = $this->db->prepare($dailySql);
        $dailyStmt->execute($params);
        $daily = $dailyStmt->fetchAll(PDO::FETCH_ASSOC);

        $paymentBreakdownStmt = $this->db->prepare($paymentBreakdownSql);
        $paymentBreakdownStmt->execute($params);
        $paymentBreakdown = $paymentBreakdownStmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'sales' => $sales,
            'summary' => $summary,
            'daily_breakdown' => $daily,
            'payment_breakdown' => $paymentBreakdown
        ];
    }

    /**
     * Get inventory report
     */
    public function getInventoryReport(array $filters = []): array
    {
        $whereConditions = [];
        $params = [];

        if (!empty($filters['category_id'])) {
            $whereConditions[] = "ii.category_id = ?";
            $params[] = $filters['category_id'];
        }

        if (!empty($filters['supplier_id'])) {
            $whereConditions[] = "ii.supplier_id = ?";
            $params[] = $filters['supplier_id'];
        }

        if (isset($filters['low_stock'])) {
            $whereConditions[] = "ii.quantity <= ii.min_stock_level";
        }

        if (isset($filters['expiring'])) {
            $whereConditions[] = "ii.expiry_date IS NOT NULL AND ii.expiry_date <= DATE_ADD(NOW(), INTERVAL ? DAY)";
            $params[] = $filters['days'] ?? 30;
        }

        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        // Main inventory data
        $inventorySql = "
            SELECT 
                ii.*,
                c.name as category_name,
                s.name as supplier_name,
                u.name as unit_name,
                u.symbol as unit_symbol,
                (ii.quantity * ii.unit_cost) as total_cost_value,
                (ii.quantity * ii.selling_price) as total_selling_value,
                (ii.quantity * (ii.selling_price - ii.unit_cost)) as total_profit_value,
                CASE 
                    WHEN ii.quantity <= ii.min_stock_level THEN 'low'
                    WHEN ii.quantity <= ii.reorder_level THEN 'medium'
                    ELSE 'normal'
                END as stock_status
            FROM inventory_items ii
            LEFT JOIN categories c ON ii.category_id = c.id
            LEFT JOIN suppliers s ON ii.supplier_id = s.id
            LEFT JOIN units u ON ii.unit_id = u.id
            {$whereClause}
            ORDER BY ii.name ASC
        ";

        // Category valuation
        $categoryValuationSql = "
            SELECT 
                c.name as category_name,
                COUNT(ii.id) as item_count,
                SUM(ii.quantity) as total_quantity,
                SUM(ii.quantity * ii.unit_cost) as total_cost_value,
                SUM(ii.quantity * ii.selling_price) as total_selling_value,
                SUM(ii.quantity * (ii.selling_price - ii.unit_cost)) as total_profit_value,
                AVG(ii.unit_cost) as average_cost,
                AVG(ii.selling_price) as average_selling_price
            FROM categories c
            LEFT JOIN inventory_items ii ON c.id = ii.category_id
            GROUP BY c.id, c.name
            ORDER BY total_cost_value DESC
        ";

        // Low stock items
        $lowStockSql = "
            SELECT 
                ii.*,
                c.name as category_name,
                s.name as supplier_name,
                u.symbol as unit_symbol,
                (ii.quantity * ii.unit_cost) as total_value
            FROM inventory_items ii
            LEFT JOIN categories c ON ii.category_id = c.id
            LEFT JOIN suppliers s ON ii.supplier_id = s.id
            LEFT JOIN units u ON ii.unit_id = u.id
            WHERE ii.quantity <= ii.min_stock_level
            ORDER BY ii.quantity ASC, ii.name ASC
        ";

        // Expiring items
        $expiringSql = "
            SELECT 
                ii.*,
                c.name as category_name,
                s.name as supplier_name,
                u.symbol as unit_symbol,
                DATEDIFF(ii.expiry_date, CURDATE()) as days_until_expiry,
                (ii.quantity * ii.unit_cost) as total_value
            FROM inventory_items ii
            LEFT JOIN categories c ON ii.category_id = c.id
            LEFT JOIN suppliers s ON ii.supplier_id = s.id
            LEFT JOIN units u ON ii.unit_id = u.id
            WHERE ii.expiry_date IS NOT NULL 
            AND ii.expiry_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
            ORDER BY ii.expiry_date ASC
        ";

        $inventoryStmt = $this->db->prepare($inventorySql);
        $inventoryStmt->execute($params);
        $inventory = $inventoryStmt->fetchAll(PDO::FETCH_ASSOC);

        $categoryValuationStmt = $this->db->prepare($categoryValuationSql);
        $categoryValuationStmt->execute();
        $categoryValuation = $categoryValuationStmt->fetchAll(PDO::FETCH_ASSOC);

        $lowStockStmt = $this->db->prepare($lowStockSql);
        $lowStockStmt->execute();
        $lowStock = $lowStockStmt->fetchAll(PDO::FETCH_ASSOC);

        $expiringStmt = $this->db->prepare($expiringSql);
        $expiringStmt->execute([$filters['days'] ?? 30]);
        $expiring = $expiringStmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'inventory' => $inventory,
            'category_valuation' => $categoryValuation,
            'low_stock' => $lowStock,
            'expiring' => $expiring
        ];
    }

    /**
     * Get profit and loss report
     */
    public function getProfitLossReport(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $whereConditions = [];
        $params = [];

        if ($dateFrom) {
            $whereConditions[] = "o.created_at >= ?";
            $params[] = $dateFrom;
        }

        if ($dateTo) {
            $whereConditions[] = "o.created_at <= ?";
            $params[] = $dateTo;
        }

        $whereClause = !empty($whereConditions) ? 'AND ' . implode(' AND ', $whereConditions) : '';

        // Revenue calculation
        $revenueSql = "
            SELECT 
                SUM(total_amount) as gross_revenue,
                SUM(tax_amount) as total_tax_collected,
                SUM(discount_amount) as total_discounts_given,
                SUM(total_amount - tax_amount - discount_amount) as net_revenue
            FROM orders o
            WHERE o.status = 'completed'
            {$whereClause}
        ";

        // Cost of goods sold
        $cogsSql = "
            SELECT 
                SUM(oi.quantity * oi.unit_cost) as cost_of_goods_sold,
                SUM(oi.quantity * oi.price) as revenue_from_items,
                SUM(oi.quantity * (oi.price - oi.unit_cost)) as gross_profit
            FROM order_items oi
            JOIN orders o ON oi.order_id = o.id
            WHERE o.status = 'completed'
            {$whereClause}
        ";

        // Operating expenses (example - would need actual expense tracking)
        $expensesSql = "
            SELECT 
                SUM(p.total_amount) as total_expenses,
                COUNT(DISTINCT p.supplier_id) as supplier_count
            FROM purchases p
            WHERE p.status = 'completed'
            {$whereClause}
        ";

        // Monthly profit/loss breakdown
        $monthlyBreakdownSql = "
            SELECT 
                DATE_FORMAT(o.created_at, '%Y-%m') as month,
                SUM(o.total_amount) as monthly_revenue,
                SUM(oi.quantity * oi.unit_cost) as monthly_cogs,
                SUM(oi.quantity * (oi.price - oi.unit_cost)) as monthly_gross_profit
            FROM orders o
            LEFT JOIN order_items oi ON o.id = oi.order_id
            WHERE o.status = 'completed'
            {$whereClause}
            GROUP BY DATE_FORMAT(o.created_at, '%Y-%m')
            ORDER BY month DESC
        ";

        $revenueStmt = $this->db->prepare($revenueSql);
        $revenueStmt->execute($params);
        $revenue = $revenueStmt->fetch(PDO::FETCH_ASSOC);

        $cogsStmt = $this->db->prepare($cogsSql);
        $cogsStmt->execute($params);
        $cogs = $cogsStmt->fetch(PDO::FETCH_ASSOC);

        $expensesStmt = $this->db->prepare($expensesSql);
        $expensesStmt->execute($params);
        $expenses = $expensesStmt->fetch(PDO::FETCH_ASSOC);

        $monthlyBreakdownStmt = $this->db->prepare($monthlyBreakdownSql);
        $monthlyBreakdownStmt->execute($params);
        $monthlyBreakdown = $monthlyBreakdownStmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate final profit/loss
        $grossProfit = $cogs['gross_profit'] ?? 0;
        $operatingExpenses = $expenses['total_expenses'] ?? 0;
        $netProfit = $grossProfit - $operatingExpenses;
        $profitMargin = ($revenue['net_revenue'] ?? 0) > 0 ? 
            ($netProfit / $revenue['net_revenue']) * 100 : 0;

        return [
            'revenue' => $revenue,
            'cost_of_goods_sold' => $cogs,
            'operating_expenses' => $expenses,
            'monthly_breakdown' => $monthlyBreakdown,
            'summary' => [
                'gross_profit' => $grossProfit,
                'operating_expenses' => $operatingExpenses,
                'net_profit' => $netProfit,
                'profit_margin' => $profitMargin
            ]
        ];
    }

    /**
     * Get performance metrics
     */
    public function getPerformanceMetrics(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $whereConditions = [];
        $params = [];

        if ($dateFrom) {
            $whereConditions[] = "created_at >= ?";
            $params[] = $dateFrom;
        }

        if ($dateTo) {
            $whereConditions[] = "created_at <= ?";
            $params[] = $dateTo;
        }

        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        // Order processing time
        $processingTimeSql = "
            SELECT 
                AVG(TIMESTAMPDIFF(MINUTE, created_at, updated_at)) as average_processing_time_minutes,
                MIN(TIMESTAMPDIFF(MINUTE, created_at, updated_at)) as min_processing_time,
                MAX(TIMESTAMPDIFF(MINUTE, created_at, updated_at)) as max_processing_time
            FROM orders
            WHERE status = 'completed'
            {$whereClause}
        ";

        // Customer satisfaction (example metric)
        $satisfactionSql = "
            SELECT 
                COUNT(*) as total_ratings,
                AVG(rating) as average_rating,
                COUNT(CASE WHEN rating >= 4 THEN 1 END) as positive_ratings,
                COUNT(CASE WHEN rating <= 2 THEN 1 END) as negative_ratings
            FROM order_ratings
            {$whereClause}
        ";

        // Staff productivity
        $staffProductivitySql = "
            SELECT 
                u.name as staff_name,
                COUNT(o.id) as orders_processed,
                SUM(o.total_amount) as total_sales,
                AVG(o.total_amount) as average_order_value
            FROM users u
            LEFT JOIN orders o ON u.id = o.created_by
            WHERE u.role_id IN (2, 3) -- cashier and manager roles
            {$whereClause}
            GROUP BY u.id, u.name
            ORDER BY total_sales DESC
        ";

        // Table turnover rate
        $tableTurnoverSql = "
            SELECT 
                t.table_number,
                COUNT(o.id) as total_orders,
                SUM(o.total_amount) as total_revenue,
                AVG(TIMESTAMPDIFF(HOUR, o.created_at, o.updated_at)) as average_occupation_hours
            FROM tables t
            LEFT JOIN orders o ON t.id = o.table_id
            {$whereClause}
            GROUP BY t.id, t.table_number
            ORDER BY total_revenue DESC
        ";

        $processingTimeStmt = $this->db->prepare($processingTimeSql);
        $processingTimeStmt->execute($params);
        $processingTime = $processingTimeStmt->fetch(PDO::FETCH_ASSOC);

        $satisfactionStmt = $this->db->prepare($satisfactionSql);
        $satisfactionStmt->execute($params);
        $satisfaction = $satisfactionStmt->fetch(PDO::FETCH_ASSOC);

        $staffProductivityStmt = $this->db->prepare($staffProductivitySql);
        $staffProductivityStmt->execute($params);
        $staffProductivity = $staffProductivityStmt->fetchAll(PDO::FETCH_ASSOC);

        $tableTurnoverStmt = $this->db->prepare($tableTurnoverSql);
        $tableTurnoverStmt->execute($params);
        $tableTurnover = $tableTurnoverStmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'processing_time' => $processingTime,
            'customer_satisfaction' => $satisfaction,
            'staff_productivity' => $staffProductivity,
            'table_turnover' => $tableTurnover
        ];
    }

    /**
     * Generate custom report
     */
    public function generateCustomReport(array $parameters): array
    {
        $reportType = $parameters['report_type'] ?? 'sales';
        $dateFrom = $parameters['date_from'] ?? null;
        $dateTo = $parameters['date_to'] ?? null;
        $groupBy = $parameters['group_by'] ?? null;
        $filters = $parameters['filters'] ?? [];

        switch ($reportType) {
            case 'sales':
                return $this->getSalesReport($filters);
            case 'inventory':
                return $this->getInventoryReport($filters);
            case 'profit_loss':
                return $this->getProfitLossReport($dateFrom, $dateTo);
            case 'performance':
                return $this->getPerformanceMetrics($dateFrom, $dateTo);
            case 'dashboard':
                return $this->getDashboardStats($dateFrom, $dateTo);
            default:
                throw new \Exception('Invalid report type');
        }
    }

    /**
     * Export report data
     */
    public function exportReport(array $data, string $format = 'csv'): string
    {
        if ($format === 'csv') {
            return $this->exportToCsv($data);
        } elseif ($format === 'json') {
            return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } else {
            throw new \Exception('Unsupported export format');
        }
    }

    /**
     * Export data to CSV format
     */
    private function exportToCsv(array $data): string
    {
        if (empty($data)) {
            return '';
        }

        $output = fopen('php://temp', 'r+');
        
        // Add headers
        $headers = array_keys($data[0]);
        fputcsv($output, $headers);
        
        // Add data rows
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        return $csv;
    }
}