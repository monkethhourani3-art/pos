<?php

namespace App\Models;

use App\Support\Database;
use PDO;

class Unit
{
    private $db;
    private $table = 'units';

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Get all units
     */
    public function getAll(): array
    {
        $sql = "
            SELECT 
                u.*,
                COUNT(ii.id) as item_count
            FROM {$this->table} u
            LEFT JOIN inventory_items ii ON u.id = ii.unit_id
            GROUP BY u.id
            ORDER BY u.name ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get active units for dropdowns
     */
    public function getActive(): array
    {
        $sql = "
            SELECT id, name, symbol, description
            FROM {$this->table}
            WHERE is_active = 1
            ORDER BY name ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Find unit by ID
     */
    public function find(int $id): ?array
    {
        $sql = "
            SELECT 
                u.*,
                COUNT(ii.id) as item_count
            FROM {$this->table} u
            LEFT JOIN inventory_items ii ON u.id = ii.unit_id
            WHERE u.id = ?
            GROUP BY u.id
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Create new unit
     */
    public function create(array $data): int
    {
        $sql = "
            INSERT INTO {$this->table} (
                name, symbol, description, base_unit_id, conversion_factor, 
                is_active, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
        ";

        $stmt = $this->db->prepare($sql);
        
        $stmt->execute([
            $data['name'],
            $data['symbol'],
            $data['description'] ?? null,
            $data['base_unit_id'] ?? null,
            $data['conversion_factor'] ?? 1.0,
            $data['is_active'] ?? 1
        ]);

        return $this->db->lastInsertId();
    }

    /**
     * Update unit
     */
    public function update(int $id, array $data): bool
    {
        $sql = "
            UPDATE {$this->table} SET
                name = ?, symbol = ?, description = ?, base_unit_id = ?,
                conversion_factor = ?, is_active = ?, updated_at = NOW()
            WHERE id = ?
        ";

        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute([
            $data['name'],
            $data['symbol'],
            $data['description'] ?? null,
            $data['base_unit_id'] ?? null,
            $data['conversion_factor'] ?? 1.0,
            $data['is_active'] ?? 1,
            $id
        ]);
    }

    /**
     * Delete unit
     */
    public function delete(int $id): bool
    {
        // Check if unit is used in inventory items
        $checkSql = "SELECT COUNT(*) FROM inventory_items WHERE unit_id = ?";
        $checkStmt = $this->db->prepare($checkSql);
        $checkStmt->execute([$id]);
        $itemCount = $checkStmt->fetchColumn();

        if ($itemCount > 0) {
            // Cannot delete unit with associated items
            return false;
        }

        $sql = "DELETE FROM {$this->table} WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute([$id]);
    }

    /**
     * Update unit status
     */
    public function updateStatus(int $id, bool $isActive): bool
    {
        $sql = "UPDATE {$this->table} SET is_active = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute([$isActive ? 1 : 0, $id]);
    }

    /**
     * Search units
     */
    public function search(string $query, int $limit = 10): array
    {
        $sql = "
            SELECT 
                id, name, symbol, description
            FROM {$this->table}
            WHERE is_active = 1 
            AND (name LIKE ? OR symbol LIKE ?)
            ORDER BY 
                CASE 
                    WHEN name LIKE ? THEN 1
                    WHEN symbol LIKE ? THEN 2
                    ELSE 3
                END,
                name ASC
            LIMIT ?
        ";

        $searchTerm = '%' . $query . '%';
        $exactTerm = $query . '%';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $searchTerm, $searchTerm,
            $exactTerm, $exactTerm,
            $limit
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get commonly used units
     */
    public function getCommon(): array
    {
        $sql = "
            SELECT 
                u.*,
                COUNT(ii.id) as usage_count
            FROM {$this->table} u
            LEFT JOIN inventory_items ii ON u.id = ii.unit_id
            WHERE u.is_active = 1
            GROUP BY u.id
            HAVING usage_count > 0
            ORDER BY usage_count DESC, u.name ASC
            LIMIT 20
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get unit hierarchy (for conversion calculations)
     */
    public function getHierarchy(): array
    {
        $sql = "
            SELECT 
                u1.id,
                u1.name as unit_name,
                u1.symbol as unit_symbol,
                u1.conversion_factor,
                u2.name as base_unit_name,
                u2.symbol as base_unit_symbol
            FROM {$this->table} u1
            LEFT JOIN {$this->table} u2 ON u1.base_unit_id = u2.id
            WHERE u1.is_active = 1
            ORDER BY u1.name ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Convert quantity between units
     */
    public function convertQuantity(float $quantity, int $fromUnitId, int $toUnitId): float
    {
        if ($fromUnitId === $toUnitId) {
            return $quantity;
        }

        // Get unit details
        $fromUnit = $this->find($fromUnitId);
        $toUnit = $this->find($toUnitId);

        if (!$fromUnit || !$toUnit) {
            throw new \Exception('Invalid unit IDs');
        }

        // If both units have the same base unit
        if ($fromUnit['base_unit_id'] && $toUnit['base_unit_id'] && 
            $fromUnit['base_unit_id'] === $toUnit['base_unit_id']) {
            return ($quantity * $fromUnit['conversion_factor']) / $toUnit['conversion_factor'];
        }

        // For now, assume direct conversion (can be enhanced with full hierarchy)
        return $quantity;
    }

    /**
     * Get unit statistics
     */
    public function getStatistics(): array
    {
        $sql = "
            SELECT 
                COUNT(*) as total_units,
                COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_units,
                COUNT(CASE WHEN base_unit_id IS NOT NULL THEN 1 END) as derived_units,
                COUNT(CASE WHEN conversion_factor != 1.0 THEN 1 END) as custom_conversions
            FROM {$this->table}
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Validate unit name uniqueness
     */
    public function isNameUnique(string $name, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE name = ?";
        $params = [$name];

        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchColumn() == 0;
    }

    /**
     * Validate unit symbol uniqueness
     */
    public function isSymbolUnique(string $symbol, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE symbol = ?";
        $params = [$symbol];

        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchColumn() == 0;
    }

    /**
     * Get default units for common measurements
     */
    public function getDefaultUnits(): array
    {
        $defaultUnits = [
            ['name' => 'قطعة', 'symbol' => 'قطعة', 'description' => 'وحدة القياس الأساسية'],
            ['name' => 'كيلوجرام', 'symbol' => 'كجم', 'description' => 'كيلوجرام'],
            ['name' => 'جرام', 'symbol' => 'جم', 'description' => 'جرام'],
            ['name' => 'لتر', 'symbol' => 'لتر', 'description' => 'لتر'],
            ['name' => 'مللي لتر', 'symbol' => 'مل', 'description' => 'مللي لتر'],
            ['name' => 'عبوة', 'symbol' => 'عبوة', 'description' => 'عبوة'],
            ['name' => 'علبة', 'symbol' => 'علبة', 'description' => 'علبة'],
            ['name' => 'زجاجة', 'symbol' => 'زجاجة', 'description' => 'زجاجة'],
            ['name' => 'كيس', 'symbol' => 'كيس', 'description' => 'كيس'],
            ['name' => 'علبة', 'symbol' => 'علبة', 'description' => 'علبة']
        ];

        foreach ($defaultUnits as $unit) {
            if (!$this->isNameUnique($unit['name'])) {
                continue;
            }

            try {
                $this->create($unit);
            } catch (\Exception $e) {
                // Skip if creation fails
                continue;
            }
        }

        return $this->getActive();
    }
}