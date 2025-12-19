<?php

namespace App\Models;

use App\Support\Database;
use PDO;

class InventoryItem
{
    private $db;
    private $table = 'inventory_items';

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Get all inventory items with pagination and filters
     */
    public function getAll(array $filters = [], int $page = 1, int $limit = 20): array
    {
        $offset = ($page - 1) * $limit;
        $whereConditions = [];
        $params = [];

        // Apply filters
        if (!empty($filters['category_id'])) {
            $whereConditions[] = "i.category_id = ?";
            $params[] = $filters['category_id'];
        }

        if (!empty($filters['supplier_id'])) {
            $whereConditions[] = "i.supplier_id = ?";
            $params[] = $filters['supplier_id'];
        }

        if (isset($filters['low_stock'])) {
            $whereConditions[] = "i.quantity <= i.min_stock_level";
            $params[] = $filters['low_stock'];
        }

        if (!empty($filters['search'])) {
            $whereConditions[] = "(i.name LIKE ? OR i.description LIKE ? OR i.barcode LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
        }

        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        $sql = "
            SELECT 
                i.*,
                c.name as category_name,
                s.name as supplier_name,
                s.contact_person as supplier_contact,
                u.name as unit_name,
                u.symbol as unit_symbol,
                (i.quantity * i.unit_cost) as total_value,
                CASE 
                    WHEN i.quantity <= i.min_stock_level THEN 'low'
                    WHEN i.quantity <= i.reorder_level THEN 'medium'
                    ELSE 'normal'
                END as stock_status
            FROM {$this->table} i
            LEFT JOIN categories c ON i.category_id = c.id
            LEFT JOIN suppliers s ON i.supplier_id = s.id
            LEFT JOIN units u ON i.unit_id = u.id
            {$whereClause}
            ORDER BY i.name ASC
            LIMIT ? OFFSET ?
        ";

        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get total count for pagination
     */
    public function getTotalCount(array $filters = []): int
    {
        $whereConditions = [];
        $params = [];

        if (!empty($filters['category_id'])) {
            $whereConditions[] = "i.category_id = ?";
            $params[] = $filters['category_id'];
        }

        if (!empty($filters['supplier_id'])) {
            $whereConditions[] = "i.supplier_id = ?";
            $params[] = $filters['supplier_id'];
        }

        if (isset($filters['low_stock'])) {
            $whereConditions[] = "i.quantity <= i.min_stock_level";
            $params[] = $filters['low_stock'];
        }

        if (!empty($filters['search'])) {
            $whereConditions[] = "(i.name LIKE ? OR i.description LIKE ? OR i.barcode LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
        }

        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        $sql = "
            SELECT COUNT(*)
            FROM {$this->table} i
            LEFT JOIN categories c ON i.category_id = c.id
            LEFT JOIN suppliers s ON i.supplier_id = s.id
            LEFT JOIN units u ON i.unit_id = u.id
            {$whereClause}
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchColumn();
    }

    /**
     * Find inventory item by ID
     */
    public function find(int $id): ?array
    {
        $sql = "
            SELECT 
                i.*,
                c.name as category_name,
                s.name as supplier_name,
                s.contact_person as supplier_contact,
                s.email as supplier_email,
                s.phone as supplier_phone,
                u.name as unit_name,
                u.symbol as unit_symbol,
                (i.quantity * i.unit_cost) as total_value
            FROM {$this->table} i
            LEFT JOIN categories c ON i.category_id = c.id
            LEFT JOIN suppliers s ON i.supplier_id = s.id
            LEFT JOIN units u ON i.unit_id = u.id
            WHERE i.id = ?
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Create new inventory item
     */
    public function create(array $data): int
    {
        $sql = "
            INSERT INTO {$this->table} (
                name, description, barcode, sku, category_id, supplier_id, unit_id,
                quantity, min_stock_level, reorder_level, max_stock_level,
                unit_cost, selling_price, tax_rate, location, expiry_date,
                created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ";

        $stmt = $this->db->prepare($sql);
        
        $stmt->execute([
            $data['name'],
            $data['description'] ?? null,
            $data['barcode'] ?? null,
            $data['sku'] ?? null,
            $data['category_id'],
            $data['supplier_id'] ?? null,
            $data['unit_id'],
            $data['quantity'],
            $data['min_stock_level'],
            $data['reorder_level'],
            $data['max_stock_level'] ?? null,
            $data['unit_cost'],
            $data['selling_price'],
            $data['tax_rate'] ?? 0,
            $data['location'] ?? null,
            $data['expiry_date'] ?? null
        ]);

        return $this->db->lastInsertId();
    }

    /**
     * Update inventory item
     */
    public function update(int $id, array $data): bool
    {
        $sql = "
            UPDATE {$this->table} SET
                name = ?, description = ?, barcode = ?, sku = ?, category_id = ?,
                supplier_id = ?, unit_id = ?, quantity = ?, min_stock_level = ?,
                reorder_level = ?, max_stock_level = ?, unit_cost = ?, selling_price = ?,
                tax_rate = ?, location = ?, expiry_date = ?, updated_at = NOW()
            WHERE id = ?
        ";

        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute([
            $data['name'],
            $data['description'] ?? null,
            $data['barcode'] ?? null,
            $data['sku'] ?? null,
            $data['category_id'],
            $data['supplier_id'] ?? null,
            $data['unit_id'],
            $data['quantity'],
            $data['min_stock_level'],
            $data['reorder_level'],
            $data['max_stock_level'] ?? null,
            $data['unit_cost'],
            $data['selling_price'],
            $data['tax_rate'] ?? 0,
            $data['location'] ?? null,
            $data['expiry_date'] ?? null,
            $id
        ]);
    }

    /**
     * Delete inventory item
     */
    public function delete(int $id): bool
    {
        $sql = "DELETE FROM {$this->table} WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute([$id]);
    }

    /**
     * Update inventory quantity
     */
    public function updateQuantity(int $id, float $quantity, string $type = 'set', ?string $reason = null): bool
    {
        $this->db->beginTransaction();
        
        try {
            // Get current quantity
            $currentSql = "SELECT quantity FROM {$this->table} WHERE id = ?";
            $currentStmt = $this->db->prepare($currentSql);
            $currentStmt->execute([$id]);
            $currentQuantity = $currentStmt->fetchColumn();

            // Calculate new quantity
            switch ($type) {
                case 'add':
                    $newQuantity = $currentQuantity + $quantity;
                    break;
                case 'subtract':
                    $newQuantity = $currentQuantity - $quantity;
                    break;
                case 'set':
                default:
                    $newQuantity = $quantity;
                    break;
            }

            // Ensure quantity doesn't go negative
            if ($newQuantity < 0) {
                $newQuantity = 0;
            }

            // Update inventory quantity
            $updateSql = "UPDATE {$this->table} SET quantity = ?, updated_at = NOW() WHERE id = ?";
            $updateStmt = $this->db->prepare($updateSql);
            $updateStmt->execute([$newQuantity, $id]);

            // Log inventory movement
            $movementSql = "
                INSERT INTO inventory_movements (
                    item_id, type, quantity_change, previous_quantity, new_quantity,
                    reason, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, NOW())
            ";

            $quantityChange = $newQuantity - $currentQuantity;
            $movementStmt = $this->db->prepare($movementSql);
            $movementStmt->execute([
                $id,
                $type,
                $quantityChange,
                $currentQuantity,
                $newQuantity,
                $reason ?? $type
            ]);

            $this->db->commit();
            return true;

        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log('Inventory quantity update error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get low stock items
     */
    public function getLowStockItems(): array
    {
        $sql = "
            SELECT 
                i.*,
                c.name as category_name,
                s.name as supplier_name,
                u.name as unit_name,
                u.symbol as unit_symbol,
                (i.quantity * i.unit_cost) as total_value
            FROM {$this->table} i
            LEFT JOIN categories c ON i.category_id = c.id
            LEFT JOIN suppliers s ON i.supplier_id = s.id
            LEFT JOIN units u ON i.unit_id = u.id
            WHERE i.quantity <= i.min_stock_level
            ORDER BY i.quantity ASC, i.name ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get inventory statistics
     */
    public function getStatistics(): array
    {
        $sql = "
            SELECT 
                COUNT(*) as total_items,
                SUM(CASE WHEN quantity <= min_stock_level THEN 1 ELSE 0 END) as low_stock_items,
                SUM(CASE WHEN quantity <= reorder_level THEN 1 ELSE 0 END) as reorder_items,
                SUM(quantity * unit_cost) as total_value,
                AVG(unit_cost) as average_cost,
                MAX(last_updated) as last_movement
            FROM {$this->table}
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Search inventory items
     */
    public function search(string $query, int $limit = 10): array
    {
        $sql = "
            SELECT 
                i.*,
                c.name as category_name,
                u.name as unit_name,
                u.symbol as unit_symbol,
                (i.quantity * i.unit_cost) as total_value
            FROM {$this->table} i
            LEFT JOIN categories c ON i.category_id = c.id
            LEFT JOIN units u ON i.unit_id = u.id
            WHERE i.name LIKE ? OR i.description LIKE ? OR i.barcode LIKE ? OR i.sku LIKE ?
            ORDER BY 
                CASE 
                    WHEN i.name LIKE ? THEN 1
                    WHEN i.barcode LIKE ? THEN 2
                    WHEN i.sku LIKE ? THEN 3
                    ELSE 4
                END,
                i.name ASC
            LIMIT ?
        ";

        $searchTerm = '%' . $query . '%';
        $exactTerm = $query . '%';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $searchTerm, $searchTerm, $searchTerm, $searchTerm,
            $exactTerm, $exactTerm, $exactTerm,
            $limit
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get inventory movements for an item
     */
    public function getMovements(int $itemId, int $limit = 50): array
    {
        $sql = "
            SELECT 
                im.*,
                u.name as user_name
            FROM inventory_movements im
            LEFT JOIN users u ON im.user_id = u.id
            WHERE im.item_id = ?
            ORDER BY im.created_at DESC
            LIMIT ?
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$itemId, $limit]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get items expiring soon
     */
    public function getExpiringItems(int $days = 30): array
    {
        $sql = "
            SELECT 
                i.*,
                c.name as category_name,
                u.name as unit_name,
                u.symbol as unit_symbol,
                DATEDIFF(i.expiry_date, CURDATE()) as days_until_expiry
            FROM {$this->table} i
            LEFT JOIN categories c ON i.category_id = c.id
            ON i.unit_id LEFT JOIN units u = u.id
            WHERE i.expiry_date IS NOT NULL 
            AND i.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
            ORDER BY i.expiry_date ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$days]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get inventory valuation
     */
    public function getValuation(): array
    {
        $sql = "
            SELECT 
                c.name as category_name,
                COUNT(i.id) as item_count,
                SUM(i.quantity) as total_quantity,
                SUM(i.quantity * i.unit_cost) as total_cost_value,
                SUM(i.quantity * i.selling_price) as total_selling_value,
                SUM(i.quantity * (i.selling_price - i.unit_cost)) as total_profit_value
            FROM categories c
            LEFT JOIN {$this->table} i ON c.id = i.category_id
            GROUP BY c.id, c.name
            ORDER BY total_cost_value DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}