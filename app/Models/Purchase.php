<?php

namespace App\Models;

use App\Support\Database;
use PDO;

class Purchase
{
    private $db;
    private $table = 'purchases';

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Get all purchases with pagination and filters
     */
    public function getAll(array $filters = [], int $page = 1, int $limit = 20): array
    {
        $offset = ($page - 1) * $limit;
        $whereConditions = [];
        $params = [];

        if (!empty($filters['supplier_id'])) {
            $whereConditions[] = "p.supplier_id = ?";
            $params[] = $filters['supplier_id'];
        }

        if (!empty($filters['status'])) {
            $whereConditions[] = "p.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['date_from'])) {
            $whereConditions[] = "p.created_at >= ?";
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $whereConditions[] = "p.created_at <= ?";
            $params[] = $filters['date_to'];
        }

        if (!empty($filters['search'])) {
            $whereConditions[] = "(p.reference_number LIKE ? OR s.name LIKE ? OR s.contact_person LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
        }

        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        $sql = "
            SELECT 
                p.*,
                s.name as supplier_name,
                s.contact_person as supplier_contact,
                s.phone as supplier_phone,
                u.name as created_by_name,
                COUNT(pd.id) as item_count
            FROM {$this->table} p
            LEFT JOIN suppliers s ON p.supplier_id = s.id
            LEFT JOIN users u ON p.created_by = u.id
            LEFT JOIN purchase_details pd ON p.id = pd.purchase_id
            {$whereClause}
            GROUP BY p.id
            ORDER BY p.created_at DESC
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

        if (!empty($filters['supplier_id'])) {
            $whereConditions[] = "p.supplier_id = ?";
            $params[] = $filters['supplier_id'];
        }

        if (!empty($filters['status'])) {
            $whereConditions[] = "p.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['date_from'])) {
            $whereConditions[] = "p.created_at >= ?";
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $whereConditions[] = "p.created_at <= ?";
            $params[] = $filters['date_to'];
        }

        if (!empty($filters['search'])) {
            $whereConditions[] = "(p.reference_number LIKE ? OR s.name LIKE ? OR s.contact_person LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
        }

        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        $sql = "
            SELECT COUNT(DISTINCT p.id)
            FROM {$this->table} p
            LEFT JOIN suppliers s ON p.supplier_id = s.id
            {$whereClause}
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchColumn();
    }

    /**
     * Find purchase by ID
     */
    public function find(int $id): ?array
    {
        $sql = "
            SELECT 
                p.*,
                s.name as supplier_name,
                s.contact_person as supplier_contact,
                s.email as supplier_email,
                s.phone as supplier_phone,
                s.address as supplier_address,
                u.name as created_by_name
            FROM {$this->table} p
            LEFT JOIN suppliers s ON p.supplier_id = s.id
            LEFT JOIN users u ON p.created_by = u.id
            WHERE p.id = ?
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);

        $purchase = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($purchase) {
            // Get purchase details
            $detailsSql = "
                SELECT 
                    pd.*,
                    ii.name as item_name,
                    ii.barcode,
                    u.symbol as unit_symbol
                FROM purchase_details pd
                LEFT JOIN inventory_items ii ON pd.item_id = ii.id
                LEFT JOIN units u ON ii.unit_id = u.id
                WHERE pd.purchase_id = ?
                ORDER BY pd.id ASC
            ";

            $detailsStmt = $this->db->prepare($detailsSql);
            $detailsStmt->execute([$id]);
            $purchase['details'] = $detailsStmt->fetchAll(PDO::FETCH_ASSOC);
        }

        return $purchase;
    }

    /**
     * Create new purchase
     */
    public function create(array $data): int
    {
        $this->db->beginTransaction();

        try {
            // Generate reference number
            $referenceNumber = $this->generateReferenceNumber();

            // Insert purchase
            $purchaseSql = "
                INSERT INTO {$this->table} (
                    supplier_id, reference_number, purchase_date, total_amount,
                    tax_amount, discount_amount, notes, status, created_by, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ";

            $purchaseStmt = $this->db->prepare($purchaseSql);
            $purchaseStmt->execute([
                $data['supplier_id'],
                $referenceNumber,
                $data['purchase_date'] ?? date('Y-m-d'),
                $data['total_amount'],
                $data['tax_amount'] ?? 0,
                $data['discount_amount'] ?? 0,
                $data['notes'] ?? null,
                $data['status'] ?? 'pending',
                $data['created_by']
            ]);

            $purchaseId = $this->db->lastInsertId();

            // Insert purchase details
            if (!empty($data['details'])) {
                $detailSql = "
                    INSERT INTO purchase_details (
                        purchase_id, item_id, quantity, unit_cost, total_cost, notes
                    ) VALUES (?, ?, ?, ?, ?, ?)
                ";

                $detailStmt = $this->db->prepare($detailSql);

                foreach ($data['details'] as $detail) {
                    $detailStmt->execute([
                        $purchaseId,
                        $detail['item_id'],
                        $detail['quantity'],
                        $detail['unit_cost'],
                        $detail['quantity'] * $detail['unit_cost'],
                        $detail['notes'] ?? null
                    ]);
                }
            }

            $this->db->commit();
            return $purchaseId;

        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log('Purchase creation error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update purchase
     */
    public function update(int $id, array $data): bool
    {
        $this->db->beginTransaction();

        try {
            // Update purchase
            $sql = "
                UPDATE {$this->table} SET
                    supplier_id = ?, purchase_date = ?, total_amount = ?,
                    tax_amount = ?, discount_amount = ?, notes = ?, status = ?,
                    updated_at = NOW()
                WHERE id = ?
            ";

            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                $data['supplier_id'],
                $data['purchase_date'],
                $data['total_amount'],
                $data['tax_amount'] ?? 0,
                $data['discount_amount'] ?? 0,
                $data['notes'] ?? null,
                $data['status'],
                $id
            ]);

            // Update purchase details if provided
            if (!empty($data['details'])) {
                // Delete existing details
                $deleteSql = "DELETE FROM purchase_details WHERE purchase_id = ?";
                $deleteStmt = $this->db->prepare($deleteSql);
                $deleteStmt->execute([$id]);

                // Insert new details
                $detailSql = "
                    INSERT INTO purchase_details (
                        purchase_id, item_id, quantity, unit_cost, total_cost, notes
                    ) VALUES (?, ?, ?, ?, ?, ?)
                ";

                $detailStmt = $this->db->prepare($detailSql);

                foreach ($data['details'] as $detail) {
                    $detailStmt->execute([
                        $id,
                        $detail['item_id'],
                        $detail['quantity'],
                        $detail['unit_cost'],
                        $detail['quantity'] * $detail['unit_cost'],
                        $detail['notes'] ?? null
                    ]);
                }
            }

            $this->db->commit();
            return $result;

        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log('Purchase update error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Update purchase status
     */
    public function updateStatus(int $id, string $status): bool
    {
        $this->db->beginTransaction();

        try {
            // Get current purchase
            $purchase = $this->find($id);
            if (!$purchase) {
                return false;
            }

            // Update status
            $sql = "UPDATE {$this->table} SET status = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([$status, $id]);

            // If status is completed, update inventory quantities
            if ($status === 'completed' && $purchase['status'] !== 'completed') {
                $this->updateInventoryFromPurchase($id);
            }

            $this->db->commit();
            return $result;

        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log('Purchase status update error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete purchase
     */
    public function delete(int $id): bool
    {
        $this->db->beginTransaction();

        try {
            // Check if purchase is completed
            $statusSql = "SELECT status FROM {$this->table} WHERE id = ?";
            $statusStmt = $this->db->prepare($statusSql);
            $statusStmt->execute([$id]);
            $status = $statusStmt->fetchColumn();

            if ($status === 'completed') {
                // Cannot delete completed purchases
                return false;
            }

            // Delete purchase details first
            $detailSql = "DELETE FROM purchase_details WHERE purchase_id = ?";
            $detailStmt = $this->db->prepare($detailSql);
            $detailStmt->execute([$id]);

            // Delete purchase
            $purchaseSql = "DELETE FROM {$this->table} WHERE id = ?";
            $purchaseStmt = $this->db->prepare($purchaseSql);
            $result = $purchaseStmt->execute([$id]);

            $this->db->commit();
            return $result;

        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log('Purchase deletion error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Update inventory quantities from completed purchase
     */
    private function updateInventoryFromPurchase(int $purchaseId): void
    {
        $detailsSql = "SELECT * FROM purchase_details WHERE purchase_id = ?";
        $detailsStmt = $this->db->prepare($detailsSql);
        $detailsStmt->execute([$purchaseId]);
        $details = $detailsStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($details as $detail) {
            // Update inventory quantity
            $updateSql = "
                UPDATE inventory_items 
                SET quantity = quantity + ?, 
                    unit_cost = ?, 
                    updated_at = NOW() 
                WHERE id = ?
            ";

            $updateStmt = $this->db->prepare($updateSql);
            $updateStmt->execute([
                $detail['quantity'],
                $detail['unit_cost'],
                $detail['item_id']
            ]);

            // Log inventory movement
            $movementSql = "
                INSERT INTO inventory_movements (
                    item_id, type, quantity_change, previous_quantity, new_quantity,
                    reason, reference_type, reference_id, created_at
                ) VALUES (?, 'purchase', ?, 0, ?, ?, 'purchase', ?, NOW())
            ";

            $movementStmt = $this->db->prepare($movementSql);
            $movementStmt->execute([
                $detail['item_id'],
                $detail['quantity'],
                $detail['quantity'],
                'Purchase completed',
                $purchaseId
            ]);
        }
    }

    /**
     * Generate unique reference number
     */
    private function generateReferenceNumber(): string
    {
        $prefix = 'PO';
        $year = date('Y');
        $month = date('m');
        
        // Get the next sequence number for this month
        $sql = "
            SELECT MAX(CAST(SUBSTRING(reference_number, 9) AS UNSIGNED)) as last_number
            FROM {$this->table}
            WHERE reference_number LIKE ?
            AND YEAR(created_at) = YEAR(NOW())
            AND MONTH(created_at) = MONTH(NOW())
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(["{$prefix}{$year}{$month}%"]);
        $lastNumber = $stmt->fetchColumn() ?: 0;
        
        $nextNumber = $lastNumber + 1;
        
        return sprintf("%s%s%s%04d", $prefix, $year, $month, $nextNumber);
    }

    /**
     * Get purchase statistics
     */
    public function getStatistics(?string $dateFrom = null, ?string $dateTo = null): array
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

        $sql = "
            SELECT 
                COUNT(*) as total_purchases,
                SUM(CASE WHEN status = 'completed' THEN total_amount ELSE 0 END) as total_spent,
                SUM(CASE WHEN status = 'pending' THEN total_amount ELSE 0 END) as pending_amount,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                AVG(total_amount) as average_purchase_value,
                MAX(created_at) as last_purchase_date
            FROM {$this->table}
            {$whereClause}
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get purchases by supplier
     */
    public function getBySupplier(int $supplierId, int $page = 1, int $limit = 20): array
    {
        $offset = ($page - 1) * $limit;

        $sql = "
            SELECT 
                p.*,
                u.name as created_by_name,
                COUNT(pd.id) as item_count
            FROM {$this->table} p
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
     * Get monthly purchase summary
     */
    public function getMonthlySummary(int $year = null): array
    {
        $year = $year ?: date('Y');
        
        $sql = "
            SELECT 
                MONTH(created_at) as month,
                MONTHNAME(created_at) as month_name,
                COUNT(*) as purchase_count,
                SUM(total_amount) as total_amount,
                SUM(CASE WHEN status = 'completed' THEN total_amount ELSE 0 END) as completed_amount
            FROM {$this->table}
            WHERE YEAR(created_at) = ?
            GROUP BY MONTH(created_at)
            ORDER BY MONTH(created_at)
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$year]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Search purchases
     */
    public function search(string $query, int $limit = 10): array
    {
        $sql = "
            SELECT 
                p.id,
                p.reference_number,
                p.total_amount,
                p.created_at,
                s.name as supplier_name,
                p.status
            FROM {$this->table} p
            LEFT JOIN suppliers s ON p.supplier_id = s.id
            WHERE p.reference_number LIKE ? 
            OR s.name LIKE ?
            OR s.contact_person LIKE ?
            ORDER BY p.created_at DESC
            LIMIT ?
        ";

        $searchTerm = '%' . $query . '%';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $limit]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}