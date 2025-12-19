<?php

namespace App\Models;

use App\Database\Database;
use PDO;

class Product
{
    private $db;
    private $table = 'products';

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Create new product
     */
    public function create(array $data): int
    {
        $sql = "INSERT INTO {$this->table} 
                (name, name_en, description, description_en, price, cost_price, category_id, 
                 image, is_active, is_featured, preparation_time, allergens, nutritional_info, 
                 created_at) 
                VALUES 
                (:name, :name_en, :description, :description_en, :price, :cost_price, :category_id,
                 :image, :is_active, :is_featured, :preparation_time, :allergens, :nutritional_info, :created_at)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':name' => $data['name'],
            ':name_en' => $data['name_en'] ?? null,
            ':description' => $data['description'] ?? null,
            ':description_en' => $data['description_en'] ?? null,
            ':price' => $data['price'],
            ':cost_price' => $data['cost_price'] ?? 0,
            ':category_id' => $data['category_id'],
            ':image' => $data['image'] ?? null,
            ':is_active' => $data['is_active'] ?? 1,
            ':is_featured' => $data['is_featured'] ?? 0,
            ':preparation_time' => $data['preparation_time'] ?? 15,
            ':allergens' => $data['allergens'] ?? null,
            ':nutritional_info' => $data['nutritional_info'] ?? null,
            ':created_at' => $data['created_at'] ?? date('Y-m-d H:i:s')
        ]);

        return $this->db->lastInsertId();
    }

    /**
     * Find product by ID
     */
    public function find(int $id): ?object
    {
        $sql = "SELECT p.*, c.name as category_name, c.name_en as category_name_en
                FROM {$this->table} p
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE p.id = :id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        
        return $stmt->fetch(PDO::FETCH_OBJ);
    }

    /**
     * Update product
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
     * Delete product
     */
    public function delete(int $id): bool
    {
        $sql = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Get all products
     */
    public function getAll(int $page = 1, int $limit = 20, bool $activeOnly = true): array
    {
        $offset = ($page - 1) * $limit;
        $whereClause = $activeOnly ? "WHERE p.is_active = 1" : "";
        
        $sql = "SELECT p.*, c.name as category_name, c.name_en as category_name_en
                FROM {$this->table} p
                LEFT JOIN categories c ON p.category_id = c.id
                {$whereClause}
                ORDER BY p.name
                LIMIT :limit OFFSET :offset";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Get products by category
     */
    public function getByCategory(int $categoryId, bool $activeOnly = true): array
    {
        $whereClause = "WHERE p.category_id = :category_id";
        if ($activeOnly) {
            $whereClause .= " AND p.is_active = 1";
        }
        
        $sql = "SELECT p.*, c.name as category_name
                FROM {$this->table} p
                LEFT JOIN categories c ON p.category_id = c.id
                {$whereClause}
                ORDER BY p.name";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':category_id' => $categoryId]);
        
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Get products by category grouped
     */
    public function getProductsByCategory(bool $activeOnly = true): array
    {
        $whereClause = $activeOnly ? "WHERE p.is_active = 1" : "";
        
        $sql = "SELECT p.*, c.name as category_name, c.name_en as category_name_en
                FROM {$this->table} p
                LEFT JOIN categories c ON p.category_id = c.id
                {$whereClause}
                ORDER BY c.sort_order, c.name, p.name";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        $products = $stmt->fetchAll(PDO::FETCH_OBJ);
        
        // Group products by category
        $grouped = [];
        foreach ($products as $product) {
            $categoryName = $product->category_name ?? 'بدون فئة';
            if (!isset($grouped[$categoryName])) {
                $grouped[$categoryName] = [];
            }
            $grouped[$categoryName][] = $product;
        }
        
        return $grouped;
    }

    /**
     * Search products
     */
    public function search(string $query, ?int $categoryId = null, int $limit = 20): array
    {
        $whereConditions = ["(p.name LIKE :query OR p.name_en LIKE :query OR p.description LIKE :query)"];
        $params = [':query' => "%{$query}%"];

        if ($categoryId) {
            $whereConditions[] = "p.category_id = :category_id";
            $params[':category_id'] = $categoryId;
        }

        $whereClause = "WHERE " . implode(' AND ', $whereConditions) . " AND p.is_active = 1";
        
        $sql = "SELECT p.*, c.name as category_name
                FROM {$this->table} p
                LEFT JOIN categories c ON p.category_id = c.id
                {$whereClause}
                ORDER BY 
                    CASE 
                        WHEN p.name LIKE :exact_match THEN 1
                        WHEN p.name LIKE :prefix_match THEN 2
                        ELSE 3
                    END,
                    p.name
                LIMIT :limit";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':query' => "%{$query}%",
            ':exact_match' => $query,
            ':prefix_match' => "{$query}%",
            ':category_id' => $categoryId,
            ':limit' => $limit
        ]);
        
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Get featured products
     */
    public function getFeatured(int $limit = 10): array
    {
        $sql = "SELECT p.*, c.name as category_name
                FROM {$this->table} p
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE p.is_featured = 1 AND p.is_active = 1
                ORDER BY p.name
                LIMIT :limit";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Get product statistics
     */
    public function getStatistics(): array
    {
        $sql = "SELECT 
                    COUNT(*) as total_products,
                    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_products,
                    SUM(CASE WHEN is_featured = 1 THEN 1 ELSE 0 END) as featured_products,
                    AVG(price) as average_price
                FROM {$this->table}";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get products by price range
     */
    public function getByPriceRange(float $minPrice, float $maxPrice, bool $activeOnly = true): array
    {
        $whereClause = "WHERE p.price BETWEEN :min_price AND :max_price";
        if ($activeOnly) {
            $whereClause .= " AND p.is_active = 1";
        }
        
        $sql = "SELECT p.*, c.name as category_name
                FROM {$this->table} p
                LEFT JOIN categories c ON p.category_id = c.id
                {$whereClause}
                ORDER BY p.price";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':min_price' => $minPrice,
            ':max_price' => $maxPrice
        ]);
        
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Update product image
     */
    public function updateImage(int $id, string $imagePath): bool
    {
        return $this->update($id, ['image' => $imagePath]);
    }

    /**
     * Toggle product active status
     */
    public function toggleActive(int $id): bool
    {
        $product = $this->find($id);
        if (!$product) {
            return false;
        }

        return $this->update($id, ['is_active' => !$product->is_active]);
    }

    /**
     * Toggle product featured status
     */
    public function toggleFeatured(int $id): bool
    {
        $product = $this->find($id);
        if (!$product) {
            return false;
        }

        return $this->update($id, ['is_featured' => !$product->is_featured]);
    }

    /**
     * Get products with low stock (if inventory tracking is enabled)
     */
    public function getLowStockProducts(int $threshold = 10): array
    {
        // This assumes there's a stock column in the products table
        // If not, you can modify this based on your inventory system
        $sql = "SELECT p.*, c.name as category_name
                FROM {$this->table} p
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE p.stock <= :threshold AND p.is_active = 1
                ORDER BY p.stock ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':threshold' => $threshold]);
        
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Get similar products
     */
    public function getSimilar(int $productId, int $limit = 5): array
    {
        $sql = "SELECT p.*, c.name as category_name
                FROM {$this->table} p
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE p.category_id = (
                    SELECT category_id FROM {$this->table} WHERE id = :product_id
                ) 
                AND p.id != :product_id 
                AND p.is_active = 1
                ORDER BY p.name
                LIMIT :limit";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':product_id' => $productId,
            ':limit' => $limit
        ]);
        
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Bulk update prices
     */
    public function bulkUpdatePrices(array $updates): bool
    {
        if (empty($updates)) {
            return false;
        }

        foreach ($updates as $productId => $newPrice) {
            $this->update($productId, ['price' => $newPrice]);
        }
        
        return true;
    }

    /**
     * Get products for POS (optimized for quick access)
     */
    public function getForPos(): array
    {
        $sql = "SELECT p.id, p.name, p.name_en, p.price, p.image, p.preparation_time,
                       c.id as category_id, c.name as category_name
                FROM {$this->table} p
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE p.is_active = 1
                ORDER BY c.sort_order, c.name, p.name";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }
}