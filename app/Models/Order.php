<?php

namespace App\Models;

use App\Database\Database;
use PDO;

class Order
{
    private $db;
    private $table = 'orders';

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Create new order
     */
    public function create(array $data): int
    {
        $sql = "INSERT INTO {$this->table} 
                (table_id, user_id, status, subtotal, tax_amount, total_amount, notes, created_at) 
                VALUES 
                (:table_id, :user_id, :status, :subtotal, :tax_amount, :total_amount, :notes, :created_at)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':table_id' => $data['table_id'] ?? null,
            ':user_id' => $data['user_id'],
            ':status' => $data['status'],
            ':subtotal' => $data['subtotal'] ?? 0,
            ':tax_amount' => $data['tax_amount'] ?? 0,
            ':total_amount' => $data['total_amount'] ?? 0,
            ':notes' => $data['notes'] ?? null,
            ':created_at' => $data['created_at']
        ]);

        return $this->db->lastInsertId();
    }

    /**
     * Find order by ID
     */
    public function find(int $id): ?object
    {
        $sql = "SELECT * FROM {$this->table} WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        
        return $stmt->fetch(PDO::FETCH_OBJ);
    }

    /**
     * Find order with details
     */
    public function findWithDetails(int $id): ?object
    {
        $sql = "SELECT o.*, 
                       t.table_number, t.seats as table_seats,
                       u.name as user_name,
                       a.name as area_name
                FROM {$this->table} o
                LEFT JOIN tables t ON o.table_id = t.id
                LEFT JOIN users u ON o.user_id = u.id
                LEFT JOIN areas a ON t.area_id = a.id
                WHERE o.id = :id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        
        return $stmt->fetch(PDO::FETCH_OBJ);
    }

    /**
     * Update order
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
     * Delete order
     */
    public function delete(int $id): bool
    {
        $sql = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Get active orders for user
     */
    public function getActiveOrdersByUser(int $userId): array
    {
        $sql = "SELECT o.*, t.table_number, t.seats as table_seats
                FROM {$this->table} o
                LEFT JOIN tables t ON o.table_id = t.id
                WHERE o.user_id = :user_id AND o.status IN ('pending', 'confirmed', 'preparing')
                ORDER BY o.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Get filtered orders for listing
     */
    public function getFilteredOrders(array $filters, int $page = 1, int $limit = 20): array
    {
        $offset = ($page - 1) * $limit;
        $whereConditions = [];
        $params = [];

        if (!empty($filters['status'])) {
            $whereConditions[] = "o.status = :status";
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['table_id'])) {
            $whereConditions[] = "o.table_id = :table_id";
            $params[':table_id'] = $filters['table_id'];
        }

        if (!empty($filters['date_from'])) {
            $whereConditions[] = "DATE(o.created_at) >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $whereConditions[] = "DATE(o.created_at) <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }

        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        $sql = "SELECT o.*, t.table_number, u.name as user_name
                FROM {$this->table} o
                LEFT JOIN tables t ON o.table_id = t.id
                LEFT JOIN users u ON o.user_id = u.id
                {$whereClause}
                ORDER BY o.created_at DESC
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
     * Get total count of filtered orders
     */
    public function getTotalFilteredOrders(array $filters): int
    {
        $whereConditions = [];
        $params = [];

        if (!empty($filters['status'])) {
            $whereConditions[] = "status = :status";
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['table_id'])) {
            $whereConditions[] = "table_id = :table_id";
            $params[':table_id'] = $filters['table_id'];
        }

        if (!empty($filters['date_from'])) {
            $whereConditions[] = "DATE(created_at) >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $whereConditions[] = "DATE(created_at) <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }

        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        $sql = "SELECT COUNT(*) FROM {$this->table} {$whereClause}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchColumn();
    }

    /**
     * Get orders by status
     */
    public function getOrdersByStatus(string $status): array
    {
        $sql = "SELECT o.*, t.table_number, u.name as user_name
                FROM {$this->table} o
                LEFT JOIN tables t ON o.table_id = t.id
                LEFT JOIN users u ON o.user_id = u.id
                WHERE o.status = :status
                ORDER BY o.created_at ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':status' => $status]);
        
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Get kitchen orders (confirmed and preparing)
     */
    public function getKitchenOrders(): array
    {
        $sql = "SELECT o.*, t.table_number, u.name as user_name,
                       TIMESTAMPDIFF(MINUTE, o.created_at, NOW()) as waiting_minutes
                FROM {$this->table} o
                LEFT JOIN tables t ON o.table_id = t.id
                LEFT JOIN users u ON o.user_id = u.id
                WHERE o.status IN ('confirmed', 'preparing', 'ready')
                ORDER BY 
                    CASE o.status 
                        WHEN 'confirmed' THEN 1 
                        WHEN 'preparing' THEN 2 
                        WHEN 'ready' THEN 3 
                    END,
                    o.created_at ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Get updated kitchen orders since timestamp
     */
    public function getUpdatedKitchenOrders(string $lastUpdate): array
    {
        $sql = "SELECT o.*, t.table_number, u.name as user_name
                FROM {$this->table} o
                LEFT JOIN tables t ON o.table_id = t.id
                LEFT JOIN users u ON o.user_id = u.id
                WHERE o.updated_at > :last_update 
                AND o.status IN ('confirmed', 'preparing', 'ready')
                ORDER BY o.updated_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':last_update' => $lastUpdate]);
        
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Get new kitchen orders since timestamp
     */
    public function getNewKitchenOrders(string $lastUpdate): array
    {
        $sql = "SELECT o.*, t.table_number, u.name as user_name
                FROM {$this->table} o
                LEFT JOIN tables t ON o.table_id = t.id
                LEFT JOIN users u ON o.user_id = u.id
                WHERE o.created_at > :last_update 
                AND o.status = 'confirmed'
                ORDER BY o.created_at ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':last_update' => $lastUpdate]);
        
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Get ready orders for pickup
     */
    public function getReadyOrders(): array
    {
        $sql = "SELECT o.*, t.table_number, u.name as user_name,
                       TIMESTAMPDIFF(MINUTE, o.ready_at, NOW()) as ready_minutes
                FROM {$this->table} o
                LEFT JOIN tables t ON o.table_id = t.id
                LEFT JOIN users u ON o.user_id = u.id
                WHERE o.status = 'ready'
                ORDER BY o.ready_at ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Get order statistics
     */
    public function getStatistics(string $period = 'today', ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $whereClause = $this->getDateWhereClause($period, $dateFrom, $dateTo);
        
        $sql = "SELECT 
                    COUNT(*) as total_orders,
                    SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as completed_orders,
                    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders,
                    SUM(total_amount) as total_revenue,
                    AVG(total_amount) as average_order_value
                FROM {$this->table} 
                {$whereClause}";
        
        $stmt = $this->db->prepare($sql);
        
        if ($dateFrom) $stmt->bindValue(':date_from', $dateFrom);
        if ($dateTo) $stmt->bindValue(':date_to', $dateTo);
        
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get kitchen performance metrics
     */
    public function getKitchenMetrics(string $period = 'today', ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $whereClause = $this->getDateWhereClause($period, $dateFrom, $dateTo);
        
        $sql = "SELECT 
                    COUNT(*) as total_orders,
                    AVG(TIMESTAMPDIFF(MINUTE, created_at, ready_at)) as avg_preparation_time,
                    MIN(TIMESTAMPDIFF(MINUTE, created_at, ready_at)) as min_preparation_time,
                    MAX(TIMESTAMPDIFF(MINUTE, created_at, ready_at)) as max_preparation_time,
                    SUM(CASE WHEN status = 'ready' THEN 1 ELSE 0 END) as orders_ready,
                    SUM(CASE WHEN status = 'preparing' THEN 1 ELSE 0 END) as orders_preparing
                FROM {$this->table} 
                {$whereClause} AND status IN ('confirmed', 'preparing', 'ready')";
        
        $stmt = $this->db->prepare($sql);
        
        if ($dateFrom) $stmt->bindValue(':date_from', $dateFrom);
        if ($dateTo) $stmt->bindValue(':date_to', $dateTo);
        
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get kitchen queue status
     */
    public function getKitchenQueueStatus(): array
    {
        $sql = "SELECT 
                    status,
                    COUNT(*) as count,
                    AVG(TIMESTAMPDIFF(MINUTE, created_at, NOW())) as avg_waiting_time
                FROM {$this->table} 
                WHERE status IN ('confirmed', 'preparing')
                GROUP BY status";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get order history/status changes
     */
    public function getOrderHistory(int $orderId): array
    {
        $sql = "SELECT oh.*, u.name as changed_by_name
                FROM order_history oh
                LEFT JOIN users u ON oh.changed_by = u.id
                WHERE oh.order_id = :order_id
                ORDER BY oh.created_at ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':order_id' => $orderId]);
        
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Get date where clause based on period
     */
    private function getDateWhereClause(string $period, ?string $dateFrom, ?string $dateTo): string
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
            default:
                return "WHERE DATE(created_at) = CURDATE()";
        }
    }
}