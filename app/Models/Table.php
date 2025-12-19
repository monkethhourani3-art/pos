<?php

namespace App\Models;

use App\Database\Database;
use PDO;

class Table
{
    private $db;
    private $table = 'tables';

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Create new table
     */
    public function create(array $data): int
    {
        $sql = "INSERT INTO {$this->table} 
                (table_number, seats, area_id, status, qr_code, notes, created_at) 
                VALUES 
                (:table_number, :seats, :area_id, :status, :qr_code, :notes, :created_at)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':table_number' => $data['table_number'],
            ':seats' => $data['seats'],
            ':area_id' => $data['area_id'],
            ':status' => $data['status'] ?? 'available',
            ':qr_code' => $data['qr_code'] ?? null,
            ':notes' => $data['notes'] ?? null,
            ':created_at' => $data['created_at'] ?? date('Y-m-d H:i:s')
        ]);

        return $this->db->lastInsertId();
    }

    /**
     * Find table by ID
     */
    public function find(int $id): ?object
    {
        $sql = "SELECT t.*, a.name as area_name, a.name_en as area_name_en
                FROM {$this->table} t
                LEFT JOIN areas a ON t.area_id = a.id
                WHERE t.id = :id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        
        return $stmt->fetch(PDO::FETCH_OBJ);
    }

    /**
     * Update table
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
     * Delete table
     */
    public function delete(int $id): bool
    {
        $sql = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Get all tables
     */
    public function getAll(): array
    {
        $sql = "SELECT t.*, a.name as area_name
                FROM {$this->table} t
                LEFT JOIN areas a ON t.area_id = a.id
                ORDER BY t.table_number";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Get available tables
     */
    public function getAvailableTables(): array
    {
        $sql = "SELECT t.*, a.name as area_name
                FROM {$this->table} t
                LEFT JOIN areas a ON t.area_id = a.id
                WHERE t.status = 'available'
                ORDER BY t.table_number";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Get occupied tables
     */
    public function getOccupiedTables(): array
    {
        $sql = "SELECT t.*, a.name as area_name, o.id as current_order_id,
                       o.created_at as order_started_at,
                       TIMESTAMPDIFF(MINUTE, o.created_at, NOW()) as occupancy_duration
                FROM {$this->table} t
                LEFT JOIN areas a ON t.area_id = a.id
                LEFT JOIN orders o ON t.current_order_id = o.id
                WHERE t.status = 'occupied'
                ORDER BY o.created_at ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Get tables by area
     */
    public function getByArea(int $areaId): array
    {
        $sql = "SELECT t.*, a.name as area_name
                FROM {$this->table} t
                LEFT JOIN areas a ON t.area_id = a.id
                WHERE t.area_id = :area_id
                ORDER BY t.table_number";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':area_id' => $areaId]);
        
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Find table by number
     */
    public function findByNumber(string $tableNumber): ?object
    {
        $sql = "SELECT t.*, a.name as area_name
                FROM {$this->table} t
                LEFT JOIN areas a ON t.area_id = a.id
                WHERE t.table_number = :table_number";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':table_number' => $tableNumber]);
        
        return $stmt->fetch(PDO::FETCH_OBJ);
    }

    /**
     * Get table with current order
     */
    public function findWithCurrentOrder(int $id): ?object
    {
        $sql = "SELECT t.*, a.name as area_name, 
                       o.id as current_order_id, o.status as order_status,
                       o.created_at as order_started_at, o.total_amount,
                       u.name as order_user_name
                FROM {$this->table} t
                LEFT JOIN areas a ON t.area_id = a.id
                LEFT JOIN orders o ON t.current_order_id = o.id
                LEFT JOIN users u ON o.user_id = u.id
                WHERE t.id = :id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        
        return $stmt->fetch(PDO::FETCH_OBJ);
    }

    /**
     * Get table statistics
     */
    public function getStatistics(): array
    {
        $sql = "SELECT 
                    COUNT(*) as total_tables,
                    SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available_tables,
                    SUM(CASE WHEN status = 'occupied' THEN 1 ELSE 0 END) as occupied_tables,
                    SUM(CASE WHEN status = 'reserved' THEN 1 ELSE 0 END) as reserved_tables,
                    SUM(CASE WHEN status = 'cleaning' THEN 1 ELSE 0 END) as cleaning_tables,
                    SUM(seats) as total_seats,
                    SUM(CASE WHEN status = 'occupied' THEN seats ELSE 0 END) as occupied_seats
                FROM {$this->table}";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get occupancy rate by area
     */
    public function getOccupancyByArea(): array
    {
        $sql = "SELECT a.id, a.name as area_name,
                       COUNT(t.id) as total_tables,
                       SUM(CASE WHEN t.status = 'occupied' THEN 1 ELSE 0 END) as occupied_tables,
                       SUM(CASE WHEN t.status = 'available' THEN 1 ELSE 0 END) as available_tables,
                       ROUND(
                           (SUM(CASE WHEN t.status = 'occupied' THEN 1 ELSE 0 END) * 100.0) / 
                           NULLIF(COUNT(t.id), 0), 2
                       ) as occupancy_rate
                FROM areas a
                LEFT JOIN {$this->table} t ON a.id = t.area_id
                GROUP BY a.id, a.name
                ORDER BY a.name";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Update table status
     */
    public function updateStatus(int $id, string $status, ?int $orderId = null): bool
    {
        $data = ['status' => $status];
        
        if ($orderId !== null) {
            $data['current_order_id'] = $orderId;
        }
        
        if ($status === 'available') {
            $data['current_order_id'] = null;
        }
        
        return $this->update($id, $data);
    }

    /**
     * Get tables that need cleaning
     */
    public function getTablesNeedingCleaning(): array
    {
        $sql = "SELECT t.*, a.name as area_name,
                       TIMESTAMPDIFF(MINUTE, o.served_at, NOW()) as since_served
                FROM {$this->table} t
                LEFT JOIN areas a ON t.area_id = a.id
                LEFT JOIN orders o ON t.current_order_id = o.id
                WHERE t.status = 'cleaning'
                ORDER BY o.served_at ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Get table utilization for a period
     */
    public function getUtilization(string $period = 'today', ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $dateCondition = $this->getDateCondition($period, $dateFrom, $dateTo);
        
        $sql = "SELECT 
                    t.id, t.table_number, a.name as area_name,
                    COUNT(o.id) as total_orders,
                    SUM(o.total_amount) as total_revenue,
                    AVG(TIMESTAMPDIFF(MINUTE, o.created_at, o.served_at)) as avg_dining_duration,
                    MIN(o.created_at) as first_order,
                    MAX(o.created_at) as last_order
                FROM {$this->table} t
                LEFT JOIN areas a ON t.area_id = a.id
                LEFT JOIN orders o ON t.id = o.table_id
                {$dateCondition} AND o.status = 'served'
                GROUP BY t.id, t.table_number, a.name
                ORDER BY total_orders DESC";
        
        $stmt = $this->db->prepare($sql);
        if ($dateFrom) $stmt->bindValue(':date_from', $dateFrom);
        if ($dateTo) $stmt->bindValue(':date_to', $dateTo);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Generate QR code for table
     */
    public function generateQrCode(int $id): string
    {
        $qrCode = "TABLE_" . $id . "_" . time();
        $this->update($id, ['qr_code' => $qrCode]);
        
        return $qrCode;
    }

    /**
     * Find table by QR code
     */
    public function findByQrCode(string $qrCode): ?object
    {
        $sql = "SELECT t.*, a.name as area_name
                FROM {$this->table} t
                LEFT JOIN areas a ON t.area_id = a.id
                WHERE t.qr_code = :qr_code";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':qr_code' => $qrCode]);
        
        return $stmt->fetch(PDO::FETCH_OBJ);
    }

    /**
     * Get table reservations (if reservation system is implemented)
     */
    public function getReservations(int $tableId, string $date): array
    {
        // This assumes there's a reservations table
        $sql = "SELECT r.*, u.name as customer_name, u.phone as customer_phone
                FROM reservations r
                LEFT JOIN users u ON r.customer_id = u.id
                WHERE r.table_id = :table_id AND DATE(r.reservation_time) = :date
                ORDER BY r.reservation_time";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':table_id' => $tableId,
            ':date' => $date
        ]);
        
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Bulk update table status
     */
    public function bulkUpdateStatus(array $tableIds, string $status): bool
    {
        if (empty($tableIds)) {
            return false;
        }

        $placeholders = str_repeat('?,', count($tableIds) - 1) . '?';
        $params = array_merge([$status], $tableIds);
        
        $sql = "UPDATE {$this->table} 
                SET status = ?, updated_at = NOW() 
                WHERE id IN ({$placeholders})";
        
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute($params);
    }

    /**
     * Get tables with longest occupancy
     */
    public function getLongestOccupied(int $limit = 10): array
    {
        $sql = "SELECT t.*, a.name as area_name, o.created_at as order_started_at,
                       TIMESTAMPDIFF(MINUTE, o.created_at, NOW()) as occupancy_minutes,
                       u.name as order_user_name
                FROM {$this->table} t
                LEFT JOIN areas a ON t.area_id = a.id
                LEFT JOIN orders o ON t.current_order_id = o.id
                LEFT JOIN users u ON o.user_id = u.id
                WHERE t.status = 'occupied'
                ORDER BY o.created_at ASC
                LIMIT :limit";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Get date condition for queries
     */
    private function getDateCondition(string $period, ?string $dateFrom, ?string $dateTo): string
    {
        if ($dateFrom && $dateTo) {
            return "WHERE DATE(o.created_at) BETWEEN :date_from AND :date_to";
        }
        
        switch ($period) {
            case 'today':
                return "WHERE DATE(o.created_at) = CURDATE()";
            case 'week':
                return "WHERE o.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            case 'month':
                return "WHERE o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            default:
                return "WHERE DATE(o.created_at) = CURDATE()";
        }
    }
}