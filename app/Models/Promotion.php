<?php

namespace App\Models;

use App\Support\Facades\DB;
use App\Support\Facades\Auth;

class Promotion
{
    /**
     * إنشاء عرض جديد
     */
    public function create(array $data): int
    {
        $user = Auth::user();
        
        $promotionData = [
            'name' => $data['name'],
            'name_en' => $data['name_en'] ?? $data['name'],
            'description' => $data['description'] ?? '',
            'promotion_type' => $data['promotion_type'], // 'bogo', 'bundle', 'time_based', 'conditional'
            'rules' => json_encode($data['rules'] ?? []),
            'discount_percentage' => $data['discount_percentage'] ?? null,
            'discount_amount' => $data['discount_amount'] ?? null,
            'free_item_id' => $data['free_item_id'] ?? null,
            'free_item_quantity' => $data['free_item_quantity'] ?? 0,
            'valid_from' => $data['valid_from'],
            'valid_until' => $data['valid_until'],
            'usage_limit' => $data['usage_limit'] ?? null,
            'used_count' => 0,
            'min_order_amount' => $data['min_order_amount'] ?? 0,
            'max_order_amount' => $data['max_order_amount'] ?? null,
            'days_of_week' => json_encode($data['days_of_week'] ?? []),
            'time_from' => $data['time_from'] ?? null,
            'time_until' => $data['time_until'] ?? null,
            'customer_type' => $data['customer_type'] ?? 'all', // 'all', 'new', 'vip', 'regular'
            'location_restriction' => $data['location_restriction'] ?? 'all',
            'product_restrictions' => json_encode($data['product_restrictions'] ?? []),
            'is_active' => $data['is_active'] ?? true,
            'priority' => $data['priority'] ?? 0,
            'created_by' => $user->id,
            'created_at' => now()
        ];

        return DB::table('promotions')->insertGetId($promotionData);
    }

    /**
     * الحصول على جميع العروض
     */
    public function getAll(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $query = DB::table('promotions')
            ->select([
                'promotions.*',
                'users.first_name as created_by_name',
                'products.name as free_item_name'
            ])
            ->leftJoin('users', 'promotions.created_by', '=', 'users.id')
            ->leftJoin('products', 'promotions.free_item_id', '=', 'products.id');

        // تطبيق الفلاتر
        if (!empty($filters['search'])) {
            $query->where(function($q) use ($filters) {
                $q->where('promotions.name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('promotions.description', 'like', '%' . $filters['search'] . '%');
            });
        }

        if (!empty($filters['promotion_type'])) {
            $query->where('promotions.promotion_type', $filters['promotion_type']);
        }

        if (isset($filters['is_active'])) {
            $query->where('promotions.is_active', $filters['is_active']);
        }

        if (!empty($filters['date_from'])) {
            $query->where('promotions.valid_from', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('promotions.valid_until', '<=', $filters['date_to']);
        }

        // الترتيب
        $query->orderBy('promotions.priority', 'desc')
              ->orderBy('promotions.created_at', 'desc');

        // الصفحات
        $total = $query->count();
        $promotions = $query->limit($perPage)
                           ->offset(($page - 1) * $perPage)
                           ->get();

        return [
            'data' => $promotions,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => ceil($total / $perPage)
            ]
        ];
    }

    /**
     * الحصول على عرض بواسطة ID
     */
    public function getById(int $id): ?object
    {
        return DB::table('promotions')
            ->select([
                'promotions.*',
                'users.first_name as created_by_name',
                'products.name as free_item_name'
            ])
            ->leftJoin('users', 'promotions.created_by', '=', 'users.id')
            ->leftJoin('products', 'promotions.free_item_id', '=', 'products.id')
            ->where('promotions.id', $id)
            ->first();
    }

    /**
     * تحديث عرض
     */
    public function update(int $id, array $data): bool
    {
        $updateData = [
            'name' => $data['name'],
            'name_en' => $data['name_en'] ?? $data['name'],
            'description' => $data['description'] ?? '',
            'promotion_type' => $data['promotion_type'],
            'rules' => json_encode($data['rules'] ?? []),
            'discount_percentage' => $data['discount_percentage'] ?? null,
            'discount_amount' => $data['discount_amount'] ?? null,
            'free_item_id' => $data['free_item_id'] ?? null,
            'free_item_quantity' => $data['free_item_quantity'] ?? 0,
            'valid_from' => $data['valid_from'],
            'valid_until' => $data['valid_until'],
            'usage_limit' => $data['usage_limit'] ?? null,
            'min_order_amount' => $data['min_order_amount'] ?? 0,
            'max_order_amount' => $data['max_order_amount'] ?? null,
            'days_of_week' => json_encode($data['days_of_week'] ?? []),
            'time_from' => $data['time_from'] ?? null,
            'time_until' => $data['time_until'] ?? null,
            'customer_type' => $data['customer_type'] ?? 'all',
            'location_restriction' => $data['location_restriction'] ?? 'all',
            'product_restrictions' => json_encode($data['product_restrictions'] ?? []),
            'is_active' => $data['is_active'] ?? true,
            'priority' => $data['priority'] ?? 0,
            'updated_at' => now()
        ];

        // إزالة القيم الفارغة
        $updateData = array_filter($updateData, function($value) {
            return $value !== null;
        });

        return DB::table('promotions')
            ->where('id', $id)
            ->update($updateData) > 0;
    }

    /**
     * حذف عرض
     */
    public function delete(int $id): bool
    {
        return DB::table('promotions')
            ->where('id', $id)
            ->delete() > 0;
    }

    /**
     * تبديل حالة النشاط
     */
    public function toggleStatus(int $id): bool
    {
        return DB::table('promotions')
            ->where('id', $id)
            ->update([
                'is_active' => DB::raw('NOT is_active'),
                'updated_at' => now()
            ]) > 0;
    }

    /**
     * تحديث الأولوية
     */
    public function updatePriority(int $id, int $priority): bool
    {
        return DB::table('promotions')
            ->where('id', $id)
            ->update([
                'priority' => $priority,
                'updated_at' => now()
            ]) > 0;
    }

    /**
     * التحقق من صحة العرض وتطبيقه
     */
    public function validateAndApply(array $orderItems, float $orderTotal, ?object $customer = null): array
    {
        $now = now();
        $currentDay = $now->dayOfWeek; // 0 = الأحد, 1 = الاثنين, إلخ
        $currentTime = $now->format('H:i:s');

        // الحصول على العروض النشطة والمؤهلة
        $promotions = DB::table('promotions')
            ->where('is_active', true)
            ->where('valid_from', '<=', $now)
            ->where('valid_until', '>=', $now)
            ->where(function($query) use ($orderTotal) {
                $query->whereNull('min_order_amount')
                      ->orWhere('min_order_amount', '<=', $orderTotal);
            })
            ->where(function($query) use ($orderTotal) {
                $query->whereNull('max_order_amount')
                      ->orWhere('max_order_amount', '>=', $orderTotal);
            })
            ->where(function($query) {
                $query->whereNull('usage_limit')
                      ->orWhereRaw('used_count < usage_limit');
            })
            ->orderBy('priority', 'desc')
            ->get();

        $applicablePromotions = [];

        foreach ($promotions as $promotion) {
            // التحقق من اليوم
            $daysOfWeek = json_decode($promotion->days_of_week, true) ?: [];
            if (!empty($daysOfWeek) && !in_array($currentDay, $daysOfWeek)) {
                continue;
            }

            // التحقق من الوقت
            if ($promotion->time_from && $promotion->time_until) {
                if ($currentTime < $promotion->time_from || $currentTime > $promotion->time_until) {
                    continue;
                }
            }

            // التحقق من نوع العميل
            if ($promotion->customer_type !== 'all' && $customer) {
                if (!$this->checkCustomerType($promotion->customer_type, $customer)) {
                    continue;
                }
            }

            // التحقق من القيود على المنتجات
            $productRestrictions = json_decode($promotion->product_restrictions, true) ?: [];
            if (!$this->checkProductRestrictions($orderItems, $productRestrictions)) {
                continue;
            }

            // حساب قيمة العرض
            $promotionValue = $this->calculatePromotionValue($promotion, $orderItems, $orderTotal);

            if ($promotionValue > 0) {
                $applicablePromotions[] = [
                    'id' => $promotion->id,
                    'name' => $promotion->name,
                    'promotion_type' => $promotion->promotion_type,
                    'value' => $promotionValue,
                    'rules' => json_decode($promotion->rules, true),
                    'free_item_id' => $promotion->free_item_id,
                    'free_item_quantity' => $promotion->free_item_quantity
                ];
            }
        }

        return $applicablePromotions;
    }

    /**
     * حساب قيمة العرض
     */
    private function calculatePromotionValue(object $promotion, array $orderItems, float $orderTotal): float
    {
        $rules = json_decode($promotion->rules, true) ?: [];

        switch ($promotion->promotion_type) {
            case 'bogo':
                return $this->calculateBogoValue($promotion, $orderItems, $rules);
            
            case 'bundle':
                return $this->calculateBundleValue($promotion, $orderItems, $rules);
            
            case 'time_based':
                return $this->calculateTimeBasedValue($promotion, $orderTotal, $rules);
            
            case 'conditional':
                return $this->calculateConditionalValue($promotion, $orderItems, $orderTotal, $rules);
            
            default:
                return 0;
        }
    }

    /**
     * حساب قيمة عرض اشتري واحصل على مجاناً
     */
    private function calculateBogoValue(object $promotion, array $orderItems, array $rules): float
    {
        if (!$promotion->free_item_id || !$promotion->free_item_quantity) {
            return 0;
        }

        $freeItemTotal = 0;
        $requiredQuantity = $rules['required_quantity'] ?? 1;

        foreach ($orderItems as $item) {
            if ($item['product_id'] == $promotion->free_item_id) {
                $qualifyingSets = floor($item['quantity'] / $requiredQuantity);
                $freeItems = $qualifyingSets * $promotion->free_item_quantity;
                $freeItemTotal += $freeItems * $item['unit_price'];
            }
        }

        return $freeItemTotal;
    }

    /**
     * حساب قيمة عرض الحزم
     */
    private function calculateBundleValue(object $promotion, array $orderItems, array $rules): float
    {
        $bundleItems = $rules['bundle_items'] ?? [];
        $bundleDiscount = $rules['bundle_discount'] ?? 0;
        $bundlePrice = $rules['bundle_price'] ?? 0;

        if (empty($bundleItems) || $bundlePrice <= 0) {
            return 0;
        }

        $totalBundleValue = 0;
        $availableQuantities = [];

        // حساب الكميات المتاحة لكل عنصر في الحزمة
        foreach ($orderItems as $item) {
            foreach ($bundleItems as $bundleItem) {
                if ($item['product_id'] == $bundleItem['product_id']) {
                    $availableQuantities[$bundleItem['product_id']] = ($availableQuantities[$bundleItem['product_id']] ?? 0) + $item['quantity'];
                }
            }
        }

        // حساب عدد الحزم الممكنة
        $maxBundles = PHP_INT_MAX;
        foreach ($bundleItems as $bundleItem) {
            $availableQty = $availableQuantities[$bundleItem['product_id']] ?? 0;
            $maxBundles = min($maxBundles, floor($availableQty / $bundleItem['quantity']));
        }

        if ($maxBundles > 0) {
            // حساب قيمة الحزمة الأصلية
            $originalBundleValue = 0;
            foreach ($bundleItems as $bundleItem) {
                $originalBundleValue += $bundleItem['quantity'] * $this->getProductPrice($bundleItem['product_id']);
            }
            
            $totalBundleValue = $maxBundles * ($originalBundleValue - $bundlePrice);
        }

        return $totalBundleValue;
    }

    /**
     * حساب قيمة العرض المحدود بالوقت
     */
    private function calculateTimeBasedValue(object $promotion, float $orderTotal, array $rules): float
    {
        $discountPercentage = $promotion->discount_percentage ?? 0;
        $discountAmount = $promotion->discount_amount ?? 0;

        if ($discountPercentage > 0) {
            return ($orderTotal * $discountPercentage) / 100;
        } elseif ($discountAmount > 0) {
            return min($discountAmount, $orderTotal);
        }

        return 0;
    }

    /**
     * حساب قيمة العرض المشروط
     */
    private function calculateConditionalValue(object $promotion, array $orderItems, float $orderTotal, array $rules): float
    {
        $conditions = $rules['conditions'] ?? [];
        $discount = $rules['discount'] ?? [];

        // التحقق من الشروط
        foreach ($conditions as $condition) {
            if (!$this->checkCondition($condition, $orderItems, $orderTotal)) {
                return 0;
            }
        }

        // تطبيق الخصم
        $discountType = $discount['type'] ?? 'percentage';
        $discountValue = $discount['value'] ?? 0;

        if ($discountType === 'percentage') {
            return ($orderTotal * $discountValue) / 100;
        } elseif ($discountType === 'fixed') {
            return min($discountValue, $orderTotal);
        }

        return 0;
    }

    /**
     * التحقق من نوع العميل
     */
    private function checkCustomerType(string $promotionCustomerType, object $customer): bool
    {
        switch ($promotionCustomerType) {
            case 'new':
                return $customer->is_new ?? false;
            case 'vip':
                return ($customer->customer_type ?? '') === 'vip';
            case 'regular':
                return ($customer->customer_type ?? '') === 'regular';
            default:
                return true;
        }
    }

    /**
     * التحقق من قيود المنتجات
     */
    private function checkProductRestrictions(array $orderItems, array $restrictions): bool
    {
        if (empty($restrictions)) {
            return true;
        }

        $requiredProducts = $restrictions['required_products'] ?? [];
        $excludedProducts = $restrictions['excluded_products'] ?? [];

        // التحقق من المنتجات المطلوبة
        if (!empty($requiredProducts)) {
            $orderProductIds = array_column($orderItems, 'product_id');
            foreach ($requiredProducts as $requiredProduct) {
                if (!in_array($requiredProduct, $orderProductIds)) {
                    return false;
                }
            }
        }

        // التحقق من المنتجات المستبعدة
        if (!empty($excludedProducts)) {
            $orderProductIds = array_column($orderItems, 'product_id');
            foreach ($excludedProducts as $excludedProduct) {
                if (in_array($excludedProduct, $orderProductIds)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * التحقق من شرط معين
     */
    private function checkCondition(array $condition, array $orderItems, float $orderTotal): bool
    {
        $type = $condition['type'] ?? '';
        $value = $condition['value'] ?? 0;
        $operator = $condition['operator'] ?? 'gte';

        switch ($type) {
            case 'order_total':
                return $this->compareValues($orderTotal, $value, $operator);
            
            case 'item_quantity':
                $productId = $condition['product_id'] ?? null;
                if (!$productId) return false;
                
                $totalQuantity = 0;
                foreach ($orderItems as $item) {
                    if ($item['product_id'] == $productId) {
                        $totalQuantity += $item['quantity'];
                    }
                }
                return $this->compareValues($totalQuantity, $value, $operator);
            
            case 'category_total':
                $categoryId = $condition['category_id'] ?? null;
                if (!$categoryId) return false;
                
                $categoryTotal = 0;
                foreach ($orderItems as $item) {
                    if ($item['category_id'] == $categoryId) {
                        $categoryTotal += $item['total'];
                    }
                }
                return $this->compareValues($categoryTotal, $value, $operator);
            
            default:
                return false;
        }
    }

    /**
     * مقارنة القيم
     */
    private function compareValues($value1, $value2, string $operator): bool
    {
        switch ($operator) {
            case 'gte':
                return $value1 >= $value2;
            case 'gt':
                return $value1 > $value2;
            case 'lte':
                return $value1 <= $value2;
            case 'lt':
                return $value1 < $value2;
            case 'eq':
                return $value1 == $value2;
            case 'ne':
                return $value1 != $value2;
            default:
                return false;
        }
    }

    /**
     * الحصول على سعر المنتج
     */
    private function getProductPrice(int $productId): float
    {
        $product = DB::table('products')->where('id', $productId)->first();
        return $product ? $product->base_price : 0;
    }

    /**
     * تسجيل استخدام العرض
     */
    public function recordUsage(int $promotionId, int $orderId): bool
    {
        return DB::table('promotions')
            ->where('id', $promotionId)
            ->update([
                'used_count' => DB::raw('used_count + 1'),
                'updated_at' => now()
            ]) > 0;
    }

    /**
     * الحصول على إحصائيات العروض
     */
    public function getStatistics(string $dateFrom = null, string $dateTo = null): array
    {
        $query = DB::table('promotions');

        if ($dateFrom) {
            $query->where('created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->where('created_at', '<=', $dateTo);
        }

        $stats = [
            'total_promotions' => $query->count(),
            'active_promotions' => $query->clone()->where('is_active', true)->count(),
            'expired_promotions' => $query->clone()->where('valid_until', '<', now())->count(),
            'total_usage' => $query->clone()->sum('used_count'),
            'by_type' => [],
            'top_promotions' => []
        ];

        // إحصائيات حسب النوع
        $typeStats = DB::table('promotions')
            ->select('promotion_type', DB::raw('COUNT(*) as count'))
            ->groupBy('promotion_type')
            ->get();

        foreach ($typeStats as $stat) {
            $stats['by_type'][$stat->promotion_type] = $stat->count;
        }

        // أفضل العروض استخداماً
        $topPromotions = DB::table('promotions')
            ->select('name', 'promotion_type', 'used_count')
            ->orderBy('used_count', 'desc')
            ->limit(5)
            ->get();

        $stats['top_promotions'] = $topPromotions;

        return $stats;
    }

    /**
     * الحصول على العروض النشطة حالياً
     */
    public function getActive(): array
    {
        $now = now();
        
        return DB::table('promotions')
            ->where('is_active', true)
            ->where('valid_from', '<=', $now)
            ->where('valid_until', '>=', $now)
            ->where(function($query) {
                $query->whereNull('usage_limit')
                      ->orWhereRaw('used_count < usage_limit');
            })
            ->orderBy('priority', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * نسخ عرض
     */
    public function duplicate(int $id, string $newName): ?int
    {
        $original = $this->getById($id);
        if (!$original) {
            return null;
        }

        $newPromotionData = [
            'name' => $newName,
            'name_en' => $original->name_en,
            'description' => $original->description,
            'promotion_type' => $original->promotion_type,
            'rules' => $original->rules,
            'discount_percentage' => $original->discount_percentage,
            'discount_amount' => $original->discount_amount,
            'free_item_id' => $original->free_item_id,
            'free_item_quantity' => $original->free_item_quantity,
            'valid_from' => $original->valid_from,
            'valid_until' => $original->valid_until,
            'usage_limit' => $original->usage_limit,
            'min_order_amount' => $original->min_order_amount,
            'max_order_amount' => $original->max_order_amount,
            'days_of_week' => $original->days_of_week,
            'time_from' => $original->time_from,
            'time_until' => $original->time_until,
            'customer_type' => $original->customer_type,
            'location_restriction' => $original->location_restriction,
            'product_restrictions' => $original->product_restrictions,
            'is_active' => false, // نسخ غير نشط افتراضياً
            'priority' => $original->priority,
            'created_by' => Auth::id(),
            'created_at' => now()
        ];

        return $this->create($newPromotionData);
    }
}