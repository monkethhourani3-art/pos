<?php

namespace App\Models;

use App\Database\Database;
use PDO;

class Invoice
{
    private $db;
    private $table = 'invoices';

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Create new invoice
     */
    public function create(array $data): int
    {
        $sql = "INSERT INTO {$this->table} 
                (order_id, invoice_number, customer_name, customer_phone, customer_address,
                 subtotal, discount_type, discount_value, discount_amount, discount_reason,
                 tax_amount, total_amount, status, created_by, notes, created_at) 
                VALUES 
                (:order_id, :invoice_number, :customer_name, :customer_phone, :customer_address,
                 :subtotal, :discount_type, :discount_value, :discount_amount, :discount_reason,
                 :tax_amount, :total_amount, :status, :created_by, :notes, :created_at)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':order_id' => $data['order_id'],
            ':invoice_number' => $data['invoice_number'],
            ':customer_name' => $data['customer_name'] ?? null,
            ':customer_phone' => $data['customer_phone'] ?? null,
            ':customer_address' => $data['customer_address'] ?? null,
            ':subtotal' => $data['subtotal'],
            ':discount_type' => $data['discount_type'] ?? null,
            ':discount_value' => $data['discount_value'] ?? null,
            ':discount_amount' => $data['discount_amount'] ?? 0,
            ':discount_reason' => $data['discount_reason'] ?? null,
            ':tax_amount' => $data['tax_amount'],
            ':total_amount' => $data['total_amount'],
            ':status' => $data['status'],
            ':created_by' => $data['created_by'],
            ':notes' => $data['notes'] ?? null,
            ':created_at' => $data['created_at']
        ]);

        return $this->db->lastInsertId();
    }

    /**
     * Find invoice by ID
     */
    public function find(int $id): ?object
    {
        $sql = "SELECT * FROM {$this->table} WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        
        return $stmt->fetch(PDO::FETCH_OBJ);
    }

    /**
     * Find invoice by order ID
     */
    public function findByOrderId(int $orderId): ?object
    {
        $sql = "SELECT * FROM {$this->table} WHERE order_id = :order_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':order_id' => $orderId]);
        
        return $stmt->fetch(PDO::FETCH_OBJ);
    }

    /**
     * Find invoice with details
     */
    public function findWithDetails(int $id): ?object
    {
        $sql = "SELECT i.*, o.table_id, o.created_at as order_created_at,
                       t.table_number, u.name as created_by_name
                FROM {$this->table} i
                LEFT JOIN orders o ON i.order_id = o.id
                LEFT JOIN tables t ON o.table_id = t.id
                LEFT JOIN users u ON i.created_by = u.id
                WHERE i.id = :id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        
        return $stmt->fetch(PDO::FETCH_OBJ);
    }

    /**
     * Update invoice
     */
    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = [':id' => $id];

        foreach ($data as $key => $value) {
            $fields[] = "{$key} = :{$key}";
            $params[":{$key}"] = $value;
        }

        $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute($params);
    }

    /**
     * Delete invoice
     */
    public function delete(int $id): bool
    {
        $sql = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Get all invoices with pagination
     */
    public function getAll(int $page = 1, int $limit = 20): array
    {
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT i.*, o.table_id, t.table_number, u.name as created_by_name
                FROM {$this->table} i
                LEFT JOIN orders o ON i.order_id = o.id
                LEFT JOIN tables t ON o.table_id = t.id
                LEFT JOIN users u ON i.created_by = u.id
                ORDER BY i.created_at DESC
                LIMIT :limit OFFSET :offset";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Get filtered invoices
     */
    public function getFilteredInvoices(array $filters, int $page = 1, int $limit = 20): array
    {
        $offset = ($page - 1) * $limit;
        $whereConditions = [];
        $params = [];

        if (!empty($filters['status'])) {
            $whereConditions[] = "i.status = :status";
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['date_from'])) {
            $whereConditions[] = "DATE(i.created_at) >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $whereConditions[] = "DATE(i.created_at) <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }

        if (!empty($filters['customer_name'])) {
            $whereConditions[] = "i.customer_name LIKE :customer_name";
            $params[':customer_name'] = "%{$filters['customer_name']}%";
        }

        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        $sql = "SELECT i.*, o.table_id, t.table_number, u.name as created_by_name
                FROM {$this->table} i
                LEFT JOIN orders o ON i.order_id = o.id
                LEFT JOIN tables t ON o.table_id = t.id
                LEFT JOIN users u ON i.created_by = u.id
                {$whereClause}
                ORDER BY i.created_at DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Get total count of filtered invoices
     */
    public function getTotalFilteredInvoices(array $filters): int
    {
        $whereConditions = [];
        $params = [];

        if (!empty($filters['status'])) {
            $whereConditions[] = "status = :status";
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['date_from'])) {
            $whereConditions[] = "DATE(created_at) >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $whereConditions[] = "DATE(created_at) <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }

        if (!empty($filters['customer_name'])) {
            $whereConditions[] = "customer_name LIKE :customer_name";
            $params[':customer_name'] = "%{$filters['customer_name']}%";
        }

        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        $sql = "SELECT COUNT(*) FROM {$this->table} {$whereClause}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchColumn();
    }

    /**
     * Search invoices
     */
    public function search(string $query, string $type = 'invoice_number', int $limit = 20): array
    {
        $whereClause = '';
        
        switch ($type) {
            case 'invoice_number':
                $whereClause = "WHERE i.invoice_number LIKE :query";
                break;
            case 'customer_name':
                $whereClause = "WHERE i.customer_name LIKE :query";
                break;
            case 'order_id':
                $whereClause = "WHERE i.order_id = :query";
                break;
            default:
                $whereClause = "WHERE (i.invoice_number LIKE :query OR i.customer_name LIKE :query)";
        }

        $sql = "SELECT i.*, t.table_number, u.name as created_by_name
                FROM {$this->table} i
                LEFT JOIN orders o ON i.order_id = o.id
                LEFT JOIN tables t ON o.table_id = t.id
                LEFT JOIN users u ON i.created_by = u.id
                {$whereClause}
                ORDER BY i.created_at DESC
                LIMIT :limit";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':query' => "%{$query}%",
            ':limit' => $limit
        ]);
        
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Get invoices by status
     */
    public function getByStatus(string $status): array
    {
        $sql = "SELECT i.*, t.table_number, u.name as created_by_name
                FROM {$this->table} i
                LEFT JOIN orders o ON i.order_id = o.id
                LEFT JOIN tables t ON o.table_id = t.id
                LEFT JOIN users u ON i.created_by = u.id
                WHERE i.status = :status
                ORDER BY i.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':status' => $status]);
        
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Get overdue invoices
     */
    public function getOverdueInvoices(): array
    {
        $sql = "SELECT i.*, t.table_number, u.name as created_by_name,
                       DATEDIFF(NOW(), i.created_at) as overdue_days
                FROM {$this->table} i
                LEFT JOIN orders o ON i.order_id = o.id
                LEFT JOIN tables t ON o.table_id = t.id
                LEFT JOIN users u ON i.created_by = u.id
                WHERE i.status = 'pending' 
                AND DATEDIFF(NOW(), i.created_at) > 30
                ORDER BY i.created_at ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Get last invoice for today
     */
    public function getLastInvoiceForToday(): ?object
    {
        $sql = "SELECT invoice_number FROM {$this->table} 
                WHERE DATE(created_at) = CURDATE() 
                ORDER BY created_at DESC LIMIT 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_OBJ);
    }

    /**
     * Get invoice statistics
     */
    public function getStatistics(string $period = 'month', ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $whereClause = $this->getDateWhereClause($period, $dateFrom, $dateTo);
        
        $sql = "SELECT 
                    COUNT(*) as total_invoices,
                    SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_invoices,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_invoices,
                    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_invoices,
                    SUM(total_amount) as total_revenue,
                    AVG(total_amount) as average_invoice_value,
                    SUM(discount_amount) as total_discounts
                FROM {$this->table} 
                {$whereClause}";
        
        $stmt = $this->db->prepare($sql);
        
        if ($dateFrom) $stmt->bindValue(':date_from', $dateFrom);
        if ($dateTo) $stmt->bindValue(':date_to', $dateTo);
        
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get monthly invoice summary
     */
    public function getMonthlySummary(int $year, int $month): array
    {
        $sql = "SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as invoice_count,
                    SUM(total_amount) as daily_revenue,
                    AVG(total_amount) as avg_invoice_value
                FROM {$this->table}
                WHERE YEAR(created_at) = :year AND MONTH(created_at) = :month
                GROUP BY DATE(created_at)
                ORDER BY date";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':year' => $year,
            ':month' => $month
        ]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get yearly invoice summary
     */
    public function getYearlySummary(int $year): array
    {
        $sql = "SELECT 
                    MONTH(created_at) as month,
                    COUNT(*) as invoice_count,
                    SUM(total_amount) as monthly_revenue,
                    AVG(total_amount) as avg_invoice_value
                FROM {$this->table}
                WHERE YEAR(created_at) = :year
                GROUP BY MONTH(created_at)
                ORDER BY month";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':year' => $year]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get top customers by invoice value
     */
    public function getTopCustomers(int $limit = 10, string $period = 'month'): array
    {
        $whereClause = $this->getDateWhereClause($period);
        
        $sql = "SELECT 
                    customer_name,
                    customer_phone,
                    COUNT(*) as invoice_count,
                    SUM(total_amount) as total_spent,
                    AVG(total_amount) as avg_invoice_value
                FROM {$this->table}
                {$whereClause} AND customer_name IS NOT NULL
                GROUP BY customer_name, customer_phone
                ORDER BY total_spent DESC
                LIMIT :limit";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Get discount statistics
     */
    public function getDiscountStatistics(string $period = 'month'): array
    {
        $whereClause = $this->getDateWhereClause($period);
        
        $sql = "SELECT 
                    COUNT(*) as total_invoices_with_discount,
                    SUM(discount_amount) as total_discount_amount,
                    AVG(discount_amount) as avg_discount_amount,
                    AVG(discount_value) as avg_discount_percentage
                FROM {$this->table}
                {$whereClause} AND discount_amount > 0";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Check if invoice number exists
     */
    public function invoiceNumberExists(string $invoiceNumber): bool
    {
        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE invoice_number = :invoice_number";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':invoice_number' => $invoiceNumber]);
        
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Generate next invoice number
     */
    public function generateNextInvoiceNumber(): string
    {
        $prefix = 'INV';
        $year = date('Y');
        $month = date('m');
        $day = date('d');
        
        $lastInvoice = $this->getLastInvoiceForToday();
        
        if ($lastInvoice) {
            $parts = explode('-', $lastInvoice->invoice_number);
            $sequence = (int) end($parts) + 1;
        } else {
            $sequence = 1;
        }

        return sprintf('%s-%s%s%s-%04d', $prefix, $year, $month, $day, $sequence);
    }

    /**
     * Get date where clause based on period
     */
    private function getDateWhereClause(string $period, ?string $dateFrom = null, ?string $dateTo = null): string
    {
        if ($dateFrom && $dateTo) {
            return "WHERE DATE(created_at) BETWEEN :date_from AND :date_to";
        }
        
        switch ($period) {
            case 'today':
                return "WHERE DATE(created_at) = CURDATE()";
            case 'week':
                return "WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            case 'month':
                return "WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            case 'quarter':
                return "WHERE created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
            case 'year':
                return "WHERE created_at >= DATE_SUB(NOW(), INTERVAL 365 DAY)";
            default:
                return "WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        }
    }

    /**
     * Bulk update invoice status
     */
    public function bulkUpdateStatus(array $invoiceIds, string $status): bool
    {
        if (empty($invoiceIds)) {
            return false;
        }

        $placeholders = str_repeat('?,', count($invoiceIds) - 1) . '?';
        $params = array_merge([$status, date('Y-m-d H:i:s')], $invoiceIds);
        
        $sql = "UPDATE {$this->table} 
                SET status = ?, updated_at = ? 
                WHERE id IN ({$placeholders})";
        
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute($params);
    }

    /**
     * Get invoices by date range for reporting
     */
    public function getByDateRange(string $dateFrom, string $dateTo): array
    {
        $sql = "SELECT i.*, t.table_number, u.name as created_by_name
                FROM {$this->table} i
                LEFT JOIN orders o ON i.order_id = o.id
                LEFT JOIN tables t ON o.table_id = t.id
                LEFT JOIN users u ON i.created_by = u.id
                WHERE DATE(i.created_at) BETWEEN :date_from AND :date_to
                ORDER BY i.created_at ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':date_from' => $dateFrom,
            ':date_to' => $dateTo
        ]);
        
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }
}