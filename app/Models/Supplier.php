<?php

namespace App\Models;

use App\Support\Database;
use PDO;

class Supplier
{
    private $db;
    private $table = 'suppliers';

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Get all suppliers with pagination and filters
     */
    public function getAll(array $filters = [], int $page = 1, int $limit = 20): array
    {
        $offset = ($page - 1) * $limit;
        $whereConditions = [];
        $params = [];

        if (!empty($filters['search'])) {
            $whereConditions[] = "(name LIKE ? OR contact_person LIKE ? OR email LIKE ? OR phone LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        }

        if (isset($filters['active'])) {
            $whereConditions[] = "is_active = ?";
            $params[] = $filters['active'];
        }

        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        $sql = "
            SELECT 
                s.*,
                COUNT(ii.id) as item_count,
                SUM(ii.quantity * ii.unit_cost) as total_inventory_value,
                MAX(p.created_at) as last_purchase_date,
                COUNT(DISTINCT p.id) as total_purchases
            FROM {$this->table} s
            LEFT JOIN inventory_items ii ON s.id = ii.supplier_id
            LEFT JOIN purchases p ON s.id = p.supplier_id
            {$whereClause}
            GROUP BY s.id
            ORDER BY s.name ASC
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

        if (!empty($filters['search'])) {
            $whereConditions[] = "(name LIKE ? OR contact_person LIKE ? OR email LIKE ? OR phone LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        }

        if (isset($filters['active'])) {
            $whereConditions[] = "is_active = ?";
            $params[] = $filters['active'];
        }

        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        $sql = "
            SELECT COUNT(*)
            FROM {$this->table} s
            {$whereClause}
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchColumn();
    }

    /**
     * Find supplier by ID
     */
    public function find(int $id): ?array
    {
        $sql = "
            SELECT 
                s.*,
                COUNT(ii.id) as item_count,
                SUM(ii.quantity * ii.unit_cost) as total_inventory_value,
                MAX(p.created_at) as last_purchase_date,
                COUNT(DISTINCT p.id) as total_purchases
            FROM {$this->table} s
            LEFT JOIN inventory_items ii ON s.id = ii.supplier_id
            LEFT JOIN purchases p ON s.id = p.supplier_id
            WHERE s.id = ?
            GROUP BY s.id
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Create new supplier
     */
    public function create(array $data): int
    {
        $sql = "
            INSERT INTO {$this->table} (
                name, contact_person, email, phone, address, city, postal_code,
                country, tax_number, payment_terms, credit_limit, notes, is_active,
                created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ";

        $stmt = $this->db->prepare($sql);
        
        $stmt->execute([
            $data['name'],
            $data['contact_person'] ?? null,
            $data['email'] ?? null,
            $data['phone'] ?? null,
            $data['address'] ?? null,
            $data['city'] ?? null,
            $data['postal_code'] ?? null,
            $data['country'] ?? 'المملكة العربية السعودية',
            $data['tax_number'] ?? null,
            $data['payment_terms'] ?? 'نقدي',
            $data['credit_limit'] ?? 0,
            $data['notes'] ?? null,
            $data['is_active'] ?? 1
        ]);

        return $this->db->lastInsertId();
    }

    /**
     * Update supplier
     */
    public function update(int $id, array $data): bool
    {
        $sql = "
            UPDATE {$this->table} SET
                name = ?, contact_person = ?, email = ?, phone = ?, address = ?,
                city = ?, postal_code = ?, country = ?, tax_number = ?,
                payment_terms = ?, credit_limit = ?, notes = ?, is_active = ?,
                updated_at = NOW()
            WHERE id = ?
        ";

        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute([
            $data['name'],
            $data['contact_person'] ?? null,
            $data['email'] ?? null,
            $data['phone'] ?? null,
            $data['address'] ?? null,
            $data['city'] ?? null,
            $data['postal_code'] ?? null,
            $data['country'] ?? 'المملكة العربية السعودية',
            $data['tax_number'] ?? null,
            $data['payment_terms'] ?? 'نقدي',
            $data['credit_limit'] ?? 0,
            $data['notes'] ?? null,
            $data['is_active'] ?? 1,
            $id
        ]);
    }

    /**
     * Delete supplier
     */
    public function delete(int $id): bool
    {
        // Check if supplier has associated inventory items
        $checkSql = "SELECT COUNT(*) FROM inventory_items WHERE supplier_id = ?";
        $checkStmt = $this->db->prepare($checkSql);
        $checkStmt->execute([$id]);
        $itemCount = $checkStmt->fetchColumn();

        if ($itemCount > 0) {
            // Cannot delete supplier with associated items
            return false;
        }

        $sql = "DELETE FROM {$this->table} WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute([$id]);
    }

    /**
     * Get active suppliers for dropdowns
     */
    public function getActive(): array
    {
        $sql = "
            SELECT id, name, contact_person
            FROM {$this->table}
            WHERE is_active = 1
            ORDER BY name ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Search suppliers
     */
    public function search(string $query, int $limit = 10): array
    {
        $sql = "
            SELECT 
                id, name, contact_person, email, phone
            FROM {$this->table}
            WHERE is_active = 1 
            AND (name LIKE ? OR contact_person LIKE ? OR email LIKE ? OR phone LIKE ?)
            ORDER BY 
                CASE 
                    WHEN name LIKE ? THEN 1
                    WHEN contact_person LIKE ? THEN 2
                    ELSE 3
                END,
                name ASC
            LIMIT ?
        ";

        $searchTerm = '%' . $query . '%';
        $exactTerm = $query . '%';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $searchTerm, $searchTerm, $searchTerm, $searchTerm,
            $exactTerm, $exactTerm,
            $limit
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get supplier performance statistics
     */
    public function getPerformanceStats(int $supplierId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $whereConditions = ["p.supplier_id = ?"];
        $params = [$supplierId];

        if ($dateFrom) {
            $whereConditions[] = "p.created_at >= ?";
            $params[] = $dateFrom;
        }

        if ($dateTo) {
            $whereConditions[] = "p.created_at <= ?";
            $params[] = $dateTo;
        }

        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

        $sql = "
            SELECT 
                COUNT(DISTINCT p.id) as total_orders,
                SUM(p.total_amount) as total_spent,
                AVG(p.total_amount) as average_order_value,
                MIN(p.created_at) as first_order_date,
                MAX(p.created_at) as last_order_date,
                SUM(CASE WHEN p.status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
                SUM(CASE WHEN p.status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
                SUM(CASE WHEN p.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders
            FROM purchases p
            {$whereClause}
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get supplier purchase history
     */
    public function getPurchaseHistory(int $supplierId, int $page = 1, int $limit = 20): array
    {
        $offset = ($page - 1) * $limit;

        $sql = "
            SELECT 
                p.*,
                u.name as created_by_name,
                COUNT(pd.id) as item_count
            FROM purchases p
            LEFT JOIN users u ON p.created_by = u.id
            LEFT JOIN purchase_details pd ON p.id = pd.purchase_id
            WHERE p.supplier_id = ?
            GROUP BY p.id
            ORDER BY p.created_at DESC
            LIMIT ? OFFSET ?
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$supplierId, $limit, $offset]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get top suppliers by value
     */
    public function getTopSuppliers(int $limit = 10, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $whereConditions = ["p.status = 'completed'"];
        $params = [];

        if ($dateFrom) {
            $whereConditions[] = "p.created_at >= ?";
            $params[] = $dateFrom;
        }

        if ($dateTo) {
            $whereConditions[] = "p.created_at <= ?";
            $params[] = $dateTo;
        }

        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

        $sql = "
            SELECT 
                s.id,
                s.name,
                s.contact_person,
                s.email,
                s.phone,
                COUNT(DISTINCT p.id) as order_count,
                SUM(p.total_amount) as total_spent,
                AVG(p.total_amount) as average_order_value,
                MAX(p.created_at) as last_order_date
            FROM {$this->table} s
            LEFT JOIN purchases p ON s.id = p.supplier_id
            {$whereClause}
            GROUP BY s.id
            ORDER BY total_spent DESC
            LIMIT ?
        ";

        $params[] = $limit;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Update supplier status
     */
    public function updateStatus(int $id, bool $isActive): bool
    {
        $sql = "UPDATE {$this->table} SET is_active = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute([$isActive ? 1 : 0, $id]);
    }

    /**
     * Get supplier categories
     */
    public function getCategories(): array
    {
        $sql = "
            SELECT DISTINCT category
            FROM {$this->table}
            WHERE category IS NOT NULL AND category != ''
            ORDER BY category ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Get overall supplier statistics
     */
    public function getStatistics(): array
    {
        $sql = "
            SELECT 
                COUNT(*) as total_suppliers,
                COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_suppliers,
                COUNT(CASE WHEN is_active = 0 THEN 1 END) as inactive_suppliers,
                AVG(credit_limit) as average_credit_limit,
                SUM(credit_limit) as total_credit_limit
            FROM {$this->table}
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Validate email uniqueness
     */
    public function isEmailUnique(string $email, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE email = ?";
        $params = [$email];

        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchColumn() == 0;
    }

    /**
     * Validate phone uniqueness
     */
    public function isPhoneUnique(string $phone, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE phone = ?";
        $params = [$phone];

        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchColumn() == 0;
    }
}