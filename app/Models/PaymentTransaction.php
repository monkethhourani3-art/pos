<?php

namespace App\Models;

use App\Database\Database;
use PDO;

class PaymentTransaction
{
    private $db;
    private $table = 'payment_transactions';

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Create new payment transaction
     */
    public function create(array $data): int
    {
        $sql = "INSERT INTO {$this->table} 
                (invoice_id, payment_method_id, amount, reference_number, 
                 status, notes, processed_by, processed_at, created_at) 
                VALUES 
                (:invoice_id, :payment_method_id, :amount, :reference_number,
                 :status, :notes, :processed_by, :processed_at, :created_at)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':invoice_id' => $data['invoice_id'],
            ':payment_method_id' => $data['payment_method_id'],
            ':amount' => $data['amount'],
            ':reference_number' => $data['reference_number'] ?? null,
            ':status' => $data['status'],
            ':notes' => $data['notes'] ?? null,
            ':processed_by' => $data['processed_by'],
            ':processed_at' => $data['processed_at'],
            ':created_at' => $data['created_at']
        ]);

        return $this->db->lastInsertId();
    }

    /**
     * Find transaction by ID
     */
    public function find(int $id): ?object
    {
        $sql = "SELECT * FROM {$this->table} WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        
        return $stmt->fetch(PDO::FETCH_OBJ);
    }

    /**
     * Find transaction with details
     */
    public function findWithDetails(int $id): ?object
    {
        $sql = "SELECT pt.*, i.invoice_number, i.total_amount as invoice_total,
                       pm.name as payment_method_name, pm.type as payment_method_type,
                       u.name as processed_by_name
                FROM {$this->table} pt
                LEFT JOIN invoices i ON pt.invoice_id = i.id
                LEFT JOIN payment_methods pm ON pt.payment_method_id = pm.id
                LEFT JOIN users u ON pt.processed_by = u.id
                WHERE pt.id = :id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        
        return $stmt->fetch(PDO::FETCH_OBJ);
    }

    /**
     * Update transaction
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
     * Delete transaction
     */
    public function delete(int $id): bool
    {
        $sql = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Get transactions by invoice ID
     */
    public function getByInvoiceId(int $invoiceId): array
    {
        $sql = "SELECT pt.*, pm.name as payment_method_name, pm.type as payment_method_type,
                       u.name as processed_by_name
                FROM {$this->table} pt
                LEFT JOIN payment_methods pm ON pt.payment_method_id = pm.id
                LEFT JOIN users u ON pt.processed_by = u.id
                WHERE pt.invoice_id = :invoice_id
                ORDER BY pt.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':invoice_id' => $invoiceId]);
        
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Get total paid amount for invoice
     */
    public function getTotalPaidAmount(int $invoiceId): float
    {
        $sql = "SELECT COALESCE(SUM(amount), 0) 
                FROM {$this->table} 
                WHERE invoice_id = :invoice_id AND status IN ('completed', 'refunded')";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':invoice_id' => $invoiceId]);
        
        return (float) $stmt->fetchColumn();
    }

    /**
     * Get total refund amount for transaction
     */
    public function getRefundAmount(int $transactionId): float
    {
        $sql = "SELECT COALESCE(SUM(ABS(amount)), 0) 
                FROM {$this->table} 
                WHERE reference_number LIKE CONCAT('REF_', (SELECT reference_number FROM {$this->table} WHERE id = :transaction_id))";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':transaction_id' => $transactionId]);
        
        return (float) $stmt->fetchColumn();
    }

    /**
     * Get payment history for invoice
     */
    public function getPaymentHistory(int $invoiceId): array
    {
        $sql = "SELECT pt.*, pm.name as payment_method_name, u.name as processed_by_name
                FROM {$this->table} pt
                LEFT JOIN payment_methods pm ON pt.payment_method_id = pm.id
                LEFT JOIN users u ON pt.processed_by = u.id
                WHERE pt.invoice_id = :invoice_id
                ORDER BY pt.created_at ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':invoice_id' => $invoiceId]);
        
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Get payment statistics
     */
    public function getPaymentStatistics(string $period = 'today', ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $whereClause = $this->getDateWhereClause($period, $dateFrom, $dateTo);
        
        $sql = "SELECT 
                    COUNT(*) as total_transactions,
                    SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as total_payments,
                    SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as total_refunds,
                    AVG(CASE WHEN amount > 0 THEN amount ELSE NULL END) as avg_payment_amount,
                    COUNT(DISTINCT invoice_id) as unique_invoices
                FROM {$this->table} 
                WHERE status IN ('completed', 'refunded')
                {$whereClause}";
        
        $stmt = $this->db->prepare($sql);
        
        if ($dateFrom) $stmt->bindValue(':date_from', $dateFrom);
        if ($dateTo) $stmt->bindValue(':date_to', $dateTo);
        
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get payment methods breakdown
     */
    public function getPaymentMethodsBreakdown(string $period = 'month', ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $whereClause = $this->getDateWhereClause($period, $dateFrom, $dateTo);
        
        $sql = "SELECT 
                    pm.name as payment_method_name,
                    pm.type as payment_method_type,
                    COUNT(pt.id) as transaction_count,
                    SUM(pt.amount) as total_amount,
                    AVG(pt.amount) as avg_amount
                FROM {$this->table} pt
                LEFT JOIN payment_methods pm ON pt.payment_method_id = pm.id
                WHERE pt.status IN ('completed', 'refunded') AND pt.amount > 0
                {$whereClause}
                GROUP BY pt.payment_method_id, pm.name, pm.type
                ORDER BY total_amount DESC";
        
        $stmt = $this->db->prepare($sql);
        
        if ($dateFrom) $stmt->bindValue(':date_from', $dateFrom);
        if ($dateTo) $stmt->bindValue(':date_to', $dateTo);
        
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get daily payment summary
     */
    public function getDailyPaymentSummary(string $date): array
    {
        $sql = "SELECT 
                    HOUR(processed_at) as hour,
                    COUNT(*) as transaction_count,
                    SUM(amount) as total_amount
                FROM {$this->table}
                WHERE DATE(processed_at) = :date AND status = 'completed'
                GROUP BY HOUR(processed_at)
                ORDER BY hour";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':date' => $date]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get monthly payment summary
     */
    public function getMonthlyPaymentSummary(int $year, int $month): array
    {
        $sql = "SELECT 
                    DATE(processed_at) as date,
                    COUNT(*) as transaction_count,
                    SUM(amount) as total_amount,
                    AVG(amount) as avg_amount
                FROM {$this->table}
                WHERE YEAR(processed_at) = :year AND MONTH(processed_at) = :month
                AND status = 'completed'
                GROUP BY DATE(processed_at)
                ORDER BY date";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':year' => $year,
            ':month' => $month
        ]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get transactions by status
     */
    public function getByStatus(string $status): array
    {
        $sql = "SELECT pt.*, i.invoice_number, pm.name as payment_method_name,
                       u.name as processed_by_name
                FROM {$this->table} pt
                LEFT JOIN invoices i ON pt.invoice_id = i.id
                LEFT JOIN payment_methods pm ON pt.payment_method_id = pm.id
                LEFT JOIN users u ON pt.processed_by = u.id
                WHERE pt.status = :status
                ORDER BY pt.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':status' => $status]);
        
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Get failed transactions
     */
    public function getFailedTransactions(): array
    {
        $sql = "SELECT pt.*, i.invoice_number, pm.name as payment_method_name,
                       u.name as processed_by_name
                FROM {$this->table} pt
                LEFT JOIN invoices i ON pt.invoice_id = i.id
                LEFT JOIN payment_methods pm ON pt.payment_method_id = pm.id
                LEFT JOIN users u ON pt.processed_by = u.id
                WHERE pt.status = 'failed'
                ORDER BY pt.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Get transactions by user
     */
    public function getByUser(int $userId, string $period = 'month'): array
    {
        $whereClause = $this->getDateWhereClause($period);
        
        $sql = "SELECT pt.*, i.invoice_number, pm.name as payment_method_name
                FROM {$this->table} pt
                LEFT JOIN invoices i ON pt.invoice_id = i.id
                LEFT JOIN payment_methods pm ON pt.payment_method_id = pm.id
                WHERE pt.processed_by = :user_id
                {$whereClause}
                ORDER BY pt.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Get largest transactions
     */
    public function getLargestTransactions(int $limit = 10): array
    {
        $sql = "SELECT pt.*, i.invoice_number, pm.name as payment_method_name,
                       u.name as processed_by_name
                FROM {$this->table} pt
                LEFT JOIN invoices i ON pt.invoice_id = i.id
                LEFT JOIN payment_methods pm ON pt.payment_method_id = pm.id
                LEFT JOIN users u ON pt.processed_by = u.id
                WHERE pt.status = 'completed' AND pt.amount > 0
                ORDER BY pt.amount DESC
                LIMIT :limit";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Search transactions
     */
    public function search(string $query, string $type = 'reference_number', int $limit = 20): array
    {
        $whereClause = '';
        
        switch ($type) {
            case 'reference_number':
                $whereClause = "WHERE pt.reference_number LIKE :query";
                break;
            case 'invoice_number':
                $whereClause = "WHERE i.invoice_number LIKE :query";
                break;
            case 'processed_by':
                $whereClause = "WHERE u.name LIKE :query";
                break;
            default:
                $whereClause = "WHERE (pt.reference_number LIKE :query OR i.invoice_number LIKE :query OR u.name LIKE :query)";
        }

        $sql = "SELECT pt.*, i.invoice_number, pm.name as payment_method_name,
                       u.name as processed_by_name
                FROM {$this->table} pt
                LEFT JOIN invoices i ON pt.invoice_id = i.id
                LEFT JOIN payment_methods pm ON pt.payment_method_id = pm.id
                LEFT JOIN users u ON pt.processed_by = u.id
                {$whereClause}
                ORDER BY pt.created_at DESC
                LIMIT :limit";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':query' => "%{$query}%",
            ':limit' => $limit
        ]);
        
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Get transaction summary by period
     */
    public function getTransactionSummary(string $period = 'week'): array
    {
        $whereClause = $this->getDateWhereClause($period);
        
        $sql = "SELECT 
                    DATE(pt.processed_at) as date,
                    COUNT(*) as transaction_count,
                    SUM(pt.amount) as daily_total,
                    COUNT(DISTINCT pt.invoice_id) as unique_invoices
                FROM {$this->table} pt
                WHERE pt.status = 'completed' AND pt.amount > 0
                {$whereClause}
                GROUP BY DATE(pt.processed_at)
                ORDER BY date";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get refund statistics
     */
    public function getRefundStatistics(string $period = 'month'): array
    {
        $whereClause = $this->getDateWhereClause($period);
        
        $sql = "SELECT 
                    COUNT(*) as refund_count,
                    SUM(ABS(amount)) as total_refund_amount,
                    AVG(ABS(amount)) as avg_refund_amount
                FROM {$this->table}
                WHERE status = 'refunded' AND amount < 0
                {$whereClause}";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get payment trends
     */
    public function getPaymentTrends(int $days = 30): array
    {
        $sql = "SELECT 
                    DATE(processed_at) as date,
                    COUNT(*) as transaction_count,
                    SUM(amount) as total_amount
                FROM {$this->table}
                WHERE processed_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
                AND status = 'completed'
                GROUP BY DATE(processed_at)
                ORDER BY date";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':days' => $days]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get cash flow analysis
     */
    public function getCashFlowAnalysis(string $period = 'month'): array
    {
        $whereClause = $this->getDateWhereClause($period);
        
        $sql = "SELECT 
                    pm.type as payment_type,
                    SUM(CASE WHEN pt.amount > 0 THEN pt.amount ELSE 0 END) as cash_in,
                    SUM(CASE WHEN pt.amount < 0 THEN ABS(pt.amount) ELSE 0 END) as cash_out,
                    COUNT(pt.id) as transaction_count
                FROM {$this->table} pt
                LEFT JOIN payment_methods pm ON pt.payment_method_id = pm.id
                WHERE pt.status IN ('completed', 'refunded')
                {$whereClause}
                GROUP BY pm.type
                ORDER BY cash_in DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get date where clause based on period
     */
    private function getDateWhereClause(string $period, ?string $dateFrom = null, ?string $dateTo = null): string
    {
        if ($dateFrom && $dateTo) {
            return "AND DATE(processed_at) BETWEEN :date_from AND :date_to";
        }
        
        switch ($period) {
            case 'today':
                return "AND DATE(processed_at) = CURDATE()";
            case 'yesterday':
                return "AND DATE(processed_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
            case 'week':
                return "AND processed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            case 'month':
                return "AND processed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            case 'quarter':
                return "AND processed_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
            case 'year':
                return "AND processed_at >= DATE_SUB(NOW(), INTERVAL 365 DAY)";
            default:
                return "AND processed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        }
    }

    /**
     * Bulk update transaction status
     */
    public function bulkUpdateStatus(array $transactionIds, string $status): bool
    {
        if (empty($transactionIds)) {
            return false;
        }

        $placeholders = str_repeat('?,', count($transactionIds) - 1) . '?';
        $params = array_merge([$status, date('Y-m-d H:i:s')], $transactionIds);
        
        $sql = "UPDATE {$this->table} 
                SET status = ?, updated_at = ? 
                WHERE id IN ({$placeholders})";
        
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute($params);
    }

    /**
     * Get reconciliation report
     */
    public function getReconciliationReport(string $date): array
    {
        $sql = "SELECT 
                    pt.*,
                    i.invoice_number,
                    pm.name as payment_method_name,
                    pm.type as payment_method_type,
                    CASE 
                        WHEN pt.status = 'completed' THEN 'نجح'
                        WHEN pt.status = 'failed' THEN 'فشل'
                        WHEN pt.status = 'refunded' THEN 'مسترد'
                        ELSE pt.status
                    END as status_arabic
                FROM {$this->table} pt
                LEFT JOIN invoices i ON pt.invoice_id = i.id
                LEFT JOIN payment_methods pm ON pt.payment_method_id = pm.id
                WHERE DATE(pt.processed_at) = :date
                ORDER BY pt.processed_at";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':date' => $date]);
        
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }
}