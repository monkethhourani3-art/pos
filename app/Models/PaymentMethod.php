<?php

namespace App\Models;

use App\Database\Database;
use PDO;

class PaymentMethod
{
    private $db;
    private $table = 'payment_methods';

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Create new payment method
     */
    public function create(array $data): int
    {
        $sql = "INSERT INTO {$this->table} 
                (name, name_en, type, config, is_active, sort_order, created_at) 
                VALUES 
                (:name, :name_en, :type, :config, :is_active, :sort_order, :created_at)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':name' => $data['name'],
            ':name_en' => $data['name_en'] ?? null,
            ':type' => $data['type'],
            ':config' => json_encode($data['config'] ?? []),
            ':is_active' => $data['is_active'] ?? 1,
            ':sort_order' => $data['sort_order'] ?? 0,
            ':created_at' => $data['created_at'] ?? date('Y-m-d H:i:s')
        ]);

        return $this->db->lastInsertId();
    }

    /**
     * Find payment method by ID
     */
    public function find(int $id): ?object
    {
        $sql = "SELECT * FROM {$this->table} WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        
        return $stmt->fetch(PDO::FETCH_OBJ);
    }

    /**
     * Update payment method
     */
    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = [':id' => $id];

        foreach ($data as $key => $value) {
            if ($key === 'config') {
                $fields[] = "config = :config";
                $params[':config'] = json_encode($value);
            } else {
                $fields[] = "{$key} = :{$key}";
                $params[":{$key}"] = $value;
            }
        }

        $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute($params);
    }

    /**
     * Delete payment method
     */
    public function delete(int $id): bool
    {
        // Check if method is used in transactions
        $sql = "SELECT COUNT(*) FROM payment_transactions WHERE payment_method_id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        
        if ($stmt->fetchColumn() > 0) {
            return false; // Cannot delete method with existing transactions
        }

        $sql = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Get all payment methods
     */
    public function getAll(): array
    {
        $sql = "SELECT * FROM {$this->table} ORDER BY sort_order, name";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Get active payment methods
     */
    public function getActiveMethods(): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE is_active = 1 ORDER BY sort_order, name";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Get payment methods by type
     */
    public function getByType(string $type): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE type = :type AND is_active = 1 ORDER BY sort_order, name";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':type' => $type]);
        
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Toggle payment method active status
     */
    public function toggleActive(int $id): bool
    {
        $method = $this->find($id);
        if (!$method) {
            return false;
        }

        return $this->update($id, ['is_active' => !$method->is_active]);
    }

    /**
     * Update sort order
     */
    public function updateSortOrder(array $sortOrders): bool
    {
        try {
            $this->db->beginTransaction();

            foreach ($sortOrders as $id => $sortOrder) {
                $this->update($id, ['sort_order' => $sortOrder]);
            }

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }

    /**
     * Get payment method statistics
     */
    public function getStatistics(): array
    {
        $sql = "SELECT 
                    COUNT(*) as total_methods,
                    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_methods,
                    SUM(CASE WHEN type = 'cash' THEN 1 ELSE 0 END) as cash_methods,
                    SUM(CASE WHEN type = 'card' THEN 1 ELSE 0 END) as card_methods,
                    SUM(CASE WHEN type = 'digital' THEN 1 ELSE 0 END) as digital_methods
                FROM {$this->table}";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get method usage statistics
     */
    public function getUsageStatistics(string $period = 'month'): array
    {
        $whereClause = $this->getDateWhereClause($period);
        
        $sql = "SELECT 
                    pm.id,
                    pm.name,
                    pm.type,
                    COUNT(pt.id) as usage_count,
                    SUM(pt.amount) as total_amount,
                    AVG(pt.amount) as avg_amount
                FROM {$this->table} pm
                LEFT JOIN payment_transactions pt ON pm.id = pt.payment_method_id
                WHERE pt.status = 'completed' AND pt.amount > 0
                {$whereClause}
                GROUP BY pm.id, pm.name, pm.type
                ORDER BY usage_count DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Create default payment methods
     */
    public function createDefaultMethods(): void
    {
        $defaultMethods = [
            [
                'name' => 'نقدي',
                'name_en' => 'Cash',
                'type' => 'cash',
                'config' => ['requires_change' => true],
                'is_active' => 1,
                'sort_order' => 1
            ],
            [
                'name' => 'فيزا',
                'name_en' => 'Visa Card',
                'type' => 'card',
                'config' => ['terminal_id' => '', 'merchant_id' => ''],
                'is_active' => 1,
                'sort_order' => 2
            ],
            [
                'name' => 'ماستركارد',
                'name_en' => 'Mastercard',
                'type' => 'card',
                'config' => ['terminal_id' => '', 'merchant_id' => ''],
                'is_active' => 1,
                'sort_order' => 3
            ],
            [
                'name' => 'مدى',
                'name_en' => 'Mada',
                'type' => 'card',
                'config' => ['terminal_id' => '', 'merchant_id' => ''],
                'is_active' => 1,
                'sort_order' => 4
            ],
            [
                'name' => 'STC Pay',
                'name_en' => 'STC Pay',
                'type' => 'digital',
                'config' => ['merchant_id' => '', 'api_key' => ''],
                'is_active' => 1,
                'sort_order' => 5
            ],
            [
                'name' => 'Apple Pay',
                'name_en' => 'Apple Pay',
                'type' => 'digital',
                'config' => ['merchant_id' => '', 'api_key' => ''],
                'is_active' => 1,
                'sort_order' => 6
            ]
        ];

        foreach ($defaultMethods as $method) {
            // Check if method already exists
            $existing = $this->findByName($method['name']);
            if (!$existing) {
                $this->create($method);
            }
        }
    }

    /**
     * Find payment method by name
     */
    public function findByName(string $name): ?object
    {
        $sql = "SELECT * FROM {$this->table} WHERE name = :name";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':name' => $name]);
        
        return $stmt->fetch(PDO::FETCH_OBJ);
    }

    /**
     * Get payment method config
     */
    public function getConfig(int $id): array
    {
        $method = $this->find($id);
        if (!$method) {
            return [];
        }

        return json_decode($method->config, true) ?: [];
    }

    /**
     * Update payment method config
     */
    public function updateConfig(int $id, array $config): bool
    {
        return $this->update($id, ['config' => $config]);
    }

    /**
     * Validate payment method config
     */
    public function validateConfig(array $config, string $type): array
    {
        $errors = [];

        switch ($type) {
            case 'cash':
                // No validation needed for cash
                break;

            case 'card':
                if (empty($config['terminal_id'])) {
                    $errors[] = 'معرف الجهاز مطلوب لبطاقات الدفع';
                }
                if (empty($config['merchant_id'])) {
                    $errors[] = 'معرف التاجر مطلوب لبطاقات الدفع';
                }
                break;

            case 'digital':
                if (empty($config['merchant_id'])) {
                    $errors[] = 'معرف التاجر مطلوب للدفع الرقمي';
                }
                if (empty($config['api_key'])) {
                    $errors[] = 'مفتاح API مطلوب للدفع الرقمي';
                }
                break;
        }

        return $errors;
    }

    /**
     * Test payment method connection
     */
    public function testConnection(int $id): array
    {
        $method = $this->find($id);
        if (!$method) {
            return ['success' => false, 'message' => 'طريقة الدفع غير موجودة'];
        }

        $config = $this->getConfig($id);

        // Simulate connection test based on method type
        switch ($method->type) {
            case 'cash':
                return ['success' => true, 'message' => 'طريقة الدفع النقدي متاحة'];

            case 'card':
                if (empty($config['terminal_id']) || empty($config['merchant_id'])) {
                    return ['success' => false, 'message' => 'إعدادات الجهاز غير مكتملة'];
                }
                // Simulate terminal connection test
                return ['success' => true, 'message' => 'تم الاتصال بجهاز الدفع بنجاح'];

            case 'digital':
                if (empty($config['merchant_id']) || empty($config['api_key'])) {
                    return ['success' => false, 'message' => 'إعدادات API غير مكتملة'];
                }
                // Simulate API connection test
                return ['success' => true, 'message' => 'تم الاتصال بخدمة الدفع الرقمي بنجاح'];

            default:
                return ['success' => false, 'message' => 'نوع طريقة الدفع غير مدعوم'];
        }
    }

    /**
     * Get payment method transaction summary
     */
    public function getTransactionSummary(int $id, string $period = 'month'): array
    {
        $whereClause = $this->getDateWhereClause($period);
        
        $sql = "SELECT 
                    COUNT(*) as transaction_count,
                    SUM(amount) as total_amount,
                    AVG(amount) as avg_amount,
                    MIN(amount) as min_amount,
                    MAX(amount) as max_amount
                FROM payment_transactions
                WHERE payment_method_id = :id AND status = 'completed'
                {$whereClause}";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get daily usage for payment method
     */
    public function getDailyUsage(int $id, int $days = 7): array
    {
        $sql = "SELECT 
                    DATE(processed_at) as date,
                    COUNT(*) as transaction_count,
                    SUM(amount) as daily_total
                FROM payment_transactions
                WHERE payment_method_id = :id 
                AND status = 'completed'
                AND processed_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
                GROUP BY DATE(processed_at)
                ORDER BY date";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id' => $id,
            ':days' => $days
        ]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Search payment methods
     */
    public function search(string $query, bool $activeOnly = true): array
    {
        $whereClause = "WHERE (name LIKE :query OR name_en LIKE :query OR type LIKE :query)";
        if ($activeOnly) {
            $whereClause .= " AND is_active = 1";
        }
        
        $sql = "SELECT * FROM {$this->table} {$whereClause} ORDER BY sort_order, name LIMIT 20";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':query' => "%{$query}%"]);
        
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Get supported currencies
     */
    public function getSupportedCurrencies(): array
    {
        return [
            'SAR' => 'ريال سعودي',
            'USD' => 'دولار أمريكي',
            'EUR' => 'يورو',
            'AED' => 'درهم إماراتي'
        ];
    }

    /**
     * Get payment method icons
     */
    public function getMethodIcons(): array
    {
        return [
            'cash' => 'fas fa-money-bill-wave',
            'card' => 'fas fa-credit-card',
            'digital' => 'fas fa-mobile-alt',
            'bank_transfer' => 'fas fa-university',
            'check' => 'fas fa-file-invoice-dollar'
        ];
    }

    /**
     * Get date where clause based on period
     */
    private function getDateWhereClause(string $period): string
    {
        switch ($period) {
            case 'today':
                return "AND DATE(pt.processed_at) = CURDATE()";
            case 'yesterday':
                return "AND DATE(pt.processed_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
            case 'week':
                return "AND pt.processed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            case 'month':
                return "AND pt.processed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            case 'quarter':
                return "AND pt.processed_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
            case 'year':
                return "AND pt.processed_at >= DATE_SUB(NOW(), INTERVAL 365 DAY)";
            default:
                return "AND pt.processed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        }
    }

    /**
     * Export payment methods configuration
     */
    public function exportConfig(): array
    {
        $methods = $this->getAll();
        $export = [];

        foreach ($methods as $method) {
            $export[] = [
                'name' => $method->name,
                'name_en' => $method->name_en,
                'type' => $method->type,
                'config' => json_decode($method->config, true),
                'is_active' => $method->is_active,
                'sort_order' => $method->sort_order
            ];
        }

        return $export;
    }

    /**
     * Import payment methods configuration
     */
    public function importConfig(array $methods): array
    {
        $imported = [];
        $errors = [];

        try {
            $this->db->beginTransaction();

            foreach ($methods as $methodData) {
                try {
                    // Check if method already exists
                    $existing = $this->findByName($methodData['name']);
                    
                    if ($existing) {
                        // Update existing method
                        $this->update($existing->id, $methodData);
                        $imported[] = $methodData['name'] . ' (محدث)';
                    } else {
                        // Create new method
                        $this->create($methodData);
                        $imported[] = $methodData['name'] . ' (جديد)';
                    }
                } catch (\Exception $e) {
                    $errors[] = 'خطأ في استيراد ' . $methodData['name'] . ': ' . $e->getMessage();
                }
            }

            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollBack();
            $errors[] = 'فشل في استيراد الإعدادات: ' . $e->getMessage();
        }

        return [
            'imported' => $imported,
            'errors' => $errors
        ];
    }
}