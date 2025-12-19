<?php

namespace App\Models;

use App\Database\Database;
use PDO;

class OrderItem
{
    private $db;
    private $table = 'order_items';

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Create new order item
     */
    public function create(array $data): int
    {
        $sql = "INSERT INTO {$this->table} 
                (order_id, product_id, quantity, unit_price, total_price, notes, status, created_at) 
                VALUES 
                (:order_id, :product_id, :quantity, :unit_price, :total_price, :notes, :status, :created_at)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':order_id' => $data['order_id'],
            ':product_id' => $data['product_id'],
            ':quantity' => $data['quantity'],
            ':unit_price' => $data['unit_price'],
            ':total_price' => $data['total_price'],
            ':notes' => $data['notes'] ?? null,
            ':status' => $data['status'] ?? 'pending',
            ':created_at' => $data['created_at'] ?? date('Y-m-d H:i:s')
        ]);

        return $this->db->lastInsertId();
    }

    /**
     * Find order item by ID
     */
    public function find(int $id): ?object
    {
        $sql = "SELECT * FROM {$this->table} WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        
        return $stmt->fetch(PDO::FETCH_OBJ);
    }

    /**
     * Find order item by order and product
     */
    public function findByOrderAndProduct(int $orderId, int $productId): ?object
    {
        $sql = "SELECT * FROM {$this->table} WHERE order_id = :order_id AND product_id = :product_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':order_id' => $orderId,
            ':product_id' => $productId
        ]);
        
        return $stmt->fetch(PDO::FETCH_OBJ);
    }

    /**
     * Update order item
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
     * Delete order item
     */
    public function delete(int $id): bool
    {
        $sql = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Get order items
     */
    public function getOrderItems(int $orderId): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE order_id = :order_id ORDER BY id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':order_id' => $orderId]);
        
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Get order items with product details
     */
    public function getOrderItemsWithProducts(int $orderId): array
    {
        $sql = "SELECT oi.*, p.name as product_name, p.name_en, p.image, p.category_id,
                       c.name as category_name, c.name_en as category_name_en
                FROM {$this->table} oi
                LEFT JOIN products p ON oi.product_id = p.id
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE oi.order_id = :order_id
                ORDER BY oi.id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':order_id' => $orderId]);
        
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Update all items in an order to specific status
     */
    public function updateOrderItemsStatus(int $orderId, string $status): bool
    {
        $sql = "UPDATE {$this->table} SET status = :status WHERE order_id = :order_id";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute([
            ':status' => $status,
            ':order_id' => $orderId
        ]);
    }

    /**
     * Calculate order totals
     */
    public function calculateOrderTotals(int $orderId): array
    {
        $sql = "SELECT 
                    SUM(total_price) as subtotal,
                    SUM(total_price) * 0.15 as tax_amount,
                    SUM(total_price) * 1.15 as total_amount
                FROM {$this->table} 
                WHERE order_id = :order_id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':order_id' => $orderId]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'subtotal' => round($result['subtotal'] ?? 0, 2),
            'tax_amount' => round($result['tax_amount'] ?? 0, 2),
            'total_amount' => round($result['total_amount'] ?? 0, 2)
        ];
    }

    /**
     * Merge orders (move items from one order to another)
     */
    public function mergeOrders(int $primaryOrderId, int $secondaryOrderId): bool
    {
        // Update all items from secondary order to primary order
        $sql = "UPDATE {$this->table} 
                SET order_id = :primary_order_id 
                WHERE order_id = :secondary_order_id";
        
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute([
            ':primary_order_id' => $primaryOrderId,
            ':secondary_order_id' => $secondaryOrderId
        ]);
    }

    /**
     * Move specific items to another order
     */
    public function moveItemsToOrder(array $itemIds, int $newOrderId): bool
    {
        $placeholders = str_repeat('?,', count($itemIds) - 1) . '?';
        $sql = "UPDATE {$this->table} 
                SET order_id = ? 
                WHERE id IN ({$placeholders})";
        
        $params = array_merge([$newOrderId], $itemIds);
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute($params);
    }

    /**
     * Get popular products
     */
    public function getPopularProducts(string $period = 'month', int $limit = 10): array
    {
        $dateCondition = '';
        
        switch ($period) {
            case 'today':
                $dateCondition = "AND DATE(oi.created_at) = CURDATE()";
                break;
            case 'week':
                $dateCondition = "AND oi.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                break;
            case 'month':
                $dateCondition = "AND oi.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                break;
        }

        $sql = "SELECT p.id, p.name, p.name_en, p.image, p.price,
                       SUM(oi.quantity) as total_quantity,
                       SUM(oi.total_price) as total_revenue
                FROM {$this->table} oi
                LEFT JOIN products p ON oi.product_id = p.id
                WHERE oi.status != 'cancelled'
                {$dateCondition}
                GROUP BY p.id
                ORDER BY total_quantity DESC
                LIMIT :limit";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Get order items by status for kitchen
     */
    public function getKitchenItems(string $status = null): array
    {
        $whereClause = '';
        $params = [];

        if ($status) {
            $whereClause = 'WHERE oi.status = :status';
            $params[':status'] = $status;
        }

        $sql = "SELECT oi.*, o.table_id, t.table_number, p.name as product_name,
                       o.created_at as order_created_at,
                       TIMESTAMPDIFF(MINUTE, o.created_at, NOW()) as waiting_minutes
                FROM {$this->table} oi
                LEFT JOIN orders o ON oi.order_id = o.id
                LEFT JOIN tables t ON o.table_id = t.id
                LEFT JOIN products p ON oi.product_id = p.id
                {$whereClause}
                AND o.status IN ('confirmed', 'preparing', 'ready')
                ORDER BY 
                    CASE oi.status 
                        WHEN 'confirmed' THEN 1 
                        WHEN 'preparing' THEN 2 
                        WHEN 'ready' THEN 3 
                    END,
                    o.created_at ASC";
        
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Get items that need attention (issues reported)
     */
    public function getProblemItems(): array
    {
        $sql = "SELECT oi.*, o.table_id, t.table_number, p.name as product_name,
                       o.created_at as order_created_at
                FROM {$this->table} oi
                LEFT JOIN orders o ON oi.order_id = o.id
                LEFT JOIN tables t ON o.table_id = t.id
                LEFT JOIN products p ON oi.product_id = p.id
                WHERE oi.status = 'issue_reported'
                AND o.status IN ('confirmed', 'preparing')
                ORDER BY oi.updated_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Get order item modifications/additions history
     */
    public function getItemHistory(int $itemId): array
    {
        $sql = "SELECT oih.*, u.name as modified_by_name
                FROM order_item_history oih
                LEFT JOIN users u ON oih.modified_by = u.id
                WHERE oih.order_item_id = :item_id
                ORDER BY oih.created_at ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':item_id' => $itemId]);
        
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Add history record for item modification
     */
    public function addItemHistory(int $itemId, string $action, array $oldData, array $newData, int $userId): bool
    {
        $sql = "INSERT INTO order_item_history 
                (order_item_id, action, old_data, new_data, modified_by, created_at) 
                VALUES 
                (:order_item_id, :action, :old_data, :new_data, :modified_by, :created_at)";
        
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute([
            ':order_item_id' => $itemId,
            ':action' => $action,
            ':old_data' => json_encode($oldData),
            ':new_data' => json_encode($newData),
            ':modified_by' => $userId,
            ':created_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Bulk update items status
     */
    public function bulkUpdateStatus(array $itemIds, string $status): bool
    {
        if (empty($itemIds)) {
            return false;
        }

        $placeholders = str_repeat('?,', count($itemIds) - 1) . '?';
        $params = array_merge([$status], $itemIds);
        
        $sql = "UPDATE {$this->table} 
                SET status = ?, updated_at = NOW() 
                WHERE id IN ({$placeholders})";
        
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute($params);
    }

    /**
     * Get order completion time
     */
    public function getOrderCompletionTime(int $orderId): ?int
    {
        $sql = "SELECT 
                    TIMESTAMPDIFF(MINUTE, o.created_at, o.ready_at) as completion_time
                FROM orders o
                WHERE o.id = :order_id AND o.ready_at IS NOT NULL";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':order_id' => $orderId]);
        
        return $stmt->fetchColumn();
    }

    /**
     * Get average preparation time by product
     */
    public function getAveragePreparationTimeByProduct(int $productId, string $period = 'month'): ?float
    {
        $dateCondition = '';
        
        switch ($period) {
            case 'today':
                $dateCondition = "AND DATE(oi.created_at) = CURDATE()";
                break;
            case 'week':
                $dateCondition = "AND oi.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                break;
            case 'month':
                $dateCondition = "AND oi.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                break;
        }

        $sql = "SELECT AVG(TIMESTAMPDIFF(MINUTE, oi.created_at, oi.ready_at)) as avg_time
                FROM {$this->table} oi
                LEFT JOIN orders o ON oi.order_id = o.id
                WHERE oi.product_id = :product_id 
                AND oi.status = 'ready'
                AND oi.ready_at IS NOT NULL
                {$dateCondition}";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':product_id' => $productId]);
        
        return $stmt->fetchColumn();
    }
}