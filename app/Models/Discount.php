<?php

namespace App\Models;

use App\Support\Facades\DB;
use App\Support\Facades\Auth;

class Discount
{
    /**
     * إنشاء خصم جديد
     */
    public function create(array $data): int
    {
        $user = Auth::user();
        
        $discountData = [
            'code' => $data['code'] ?? $this->generateCode(),
            'name' => $data['name'],
            'name_en' => $data['name_en'] ?? $data['name'],
            'description' => $data['description'] ?? '',
            'type' => $data['type'], // 'percentage', 'fixed', 'buy_x_get_y'
            'value' => $data['value'],
            'min_order_amount' => $data['min_order_amount'] ?? 0,
            'max_discount_amount' => $data['max_discount_amount'] ?? null,
            'usage_limit' => $data['usage_limit'] ?? null,
            'used_count' => 0,
            'valid_from' => $data['valid_from'],
            'valid_until' => $data['valid_until'],
            'is_active' => $data['is_active'] ?? true,
            'applies_to' => $data['applies_to'] ?? 'all', // 'all', 'products', 'categories'
            'applicable_items' => json_encode($data['applicable_items'] ?? []),
            'created_by' => $user->id,
            'created_at' => now()
        ];

        return DB::table('discounts')->insertGetId($discountData);
    }

    /**
     * الحصول على جميع الخصومات
     */
    public function getAll(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $query = DB::table('discounts')
            ->select([
                'discounts.*',
                'users.first_name as created_by_name'
            ])
            ->leftJoin('users', 'discounts.created_by', '=', 'users.id');

        // تطبيق الفلاتر
        if (!empty($filters['search'])) {
            $query->where(function($q) use ($filters) {
                $q->where('discounts.code', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('discounts.name', 'like', '%' . $filters['search'] . '%');
            });
        }

        if (isset($filters['type'])) {
            $query->where('discounts.type', $filters['type']);
        }

        if (isset($filters['is_active'])) {
            $query->where('discounts.is_active', $filters['is_active']);
        }

        if (!empty($filters['date_from'])) {
            $query->where('discounts.valid_from', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('discounts.valid_until', '<=', $filters['date_to']);
        }

        // الترتيب
        $query->orderBy('discounts.created_at', 'desc');

        // الصفحات
        $total = $query->count();
        $discounts = $query->limit($perPage)
                          ->offset(($page - 1) * $perPage)
                          ->get();

        return [
            'data' => $discounts,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => ceil($total / $perPage)
            ]
        ];
    }

    /**
     * الحصول على خصم بواسطة ID
     */
    public function getById(int $id): ?object
    {
        return DB::table('discounts')
            ->select([
                'discounts.*',
                'users.first_name as created_by_name'
            ])
            ->leftJoin('users', 'discounts.created_by', '=', 'users.id')
            ->where('discounts.id', $id)
            ->first();
    }

    /**
     * تحديث خصم
     */
    public function update(int $id, array $data): bool
    {
        $updateData = [
            'name' => $data['name'],
            'name_en' => $data['name_en'] ?? $data['name'],
            'description' => $data['description'] ?? '',
            'type' => $data['type'],
            'value' => $data['value'],
            'min_order_amount' => $data['min_order_amount'] ?? 0,
            'max_discount_amount' => $data['max_discount_amount'] ?? null,
            'usage_limit' => $data['usage_limit'] ?? null,
            'valid_from' => $data['valid_from'],
            'valid_until' => $data['valid_until'],
            'is_active' => $data['is_active'] ?? true,
            'applies_to' => $data['applies_to'] ?? 'all',
            'applicable_items' => json_encode($data['applicable_items'] ?? []),
            'updated_at' => now()
        ];

        // إزالة القيم الفارغة
        $updateData = array_filter($updateData, function($value) {
            return $value !== null;
        });

        return DB::table('discounts')
            ->where('id', $id)
            ->update($updateData) > 0;
    }

    /**
     * حذف خصم
     */
    public function delete(int $id): bool
    {
        return DB::table('discounts')
            ->where('id', $id)
            ->delete() > 0;
    }

    /**
     * تبديل حالة النشاط
     */
    public function toggleStatus(int $id): bool
    {
        return DB::table('discounts')
            ->where('id', $id)
            ->update([
                'is_active' => DB::raw('NOT is_active'),
                'updated_at' => now()
            ]) > 0;
    }

    /**
     * التحقق من صحة الخصم وتطبيقه
     */
    public function validateAndApply(string $code, array $orderItems, float $orderTotal): array
    {
        $discount = DB::table('discounts')
            ->where('code', $code)
            ->where('is_active', true)
            ->first();

        if (!$discount) {
            return ['success' => false, 'message' => 'كود الخصم غير صحيح'];
        }

        // التحقق من صحة التاريخ
        $now = now();
        if ($now->lt($discount->valid_from)) {
            return ['success' => false, 'message' => 'الخصم غير نشط بعد'];
        }

        if ($now->gt($discount->valid_until)) {
            return ['success' => false, 'message' => 'انتهت صلاحية الخصم'];
        }

        // التحقق من حد الاستخدام
        if ($discount->usage_limit && $discount->used_count >= $discount->usage_limit) {
            return ['success' => false, 'message' => 'تم استخدام الحد الأقصى للخصم'];
        }

        // التحقق من الحد الأدنى للطلب
        if ($discount->min_order_amount && $orderTotal < $discount->min_order_amount) {
            return [
                'success' => false, 
                'message' => 'الحد الأدنى للطلب هو ' . number_format($discount->min_order_amount, 2) . ' ر.س'
            ];
        }

        // التحقق من التطبيق على العناصر
        $applicableItems = json_decode($discount->applicable_items, true) ?: [];
        $discountAmount = $this->calculateDiscountAmount($discount, $orderItems, $orderTotal, $applicableItems);

        if ($discountAmount <= 0) {
            return ['success' => false, 'message' => 'لا يمكن تطبيق هذا الخصم على الطلب الحالي'];
        }

        // التحقق من الحد الأقصى للخصم
        if ($discount->max_discount_amount && $discountAmount > $discount->max_discount_amount) {
            $discountAmount = $discount->max_discount_amount;
        }

        return [
            'success' => true,
            'discount' => $discount,
            'discount_amount' => $discountAmount,
            'final_total' => $orderTotal - $discountAmount
        ];
    }

    /**
     * حساب مبلغ الخصم
     */
    private function calculateDiscountAmount(object $discount, array $orderItems, float $orderTotal, array $applicableItems): float
    {
        $eligibleTotal = 0;

        // تحديد المبلغ المؤهل للخصم
        if ($discount->applies_to === 'all') {
            $eligibleTotal = $orderTotal;
        } elseif ($discount->applies_to === 'products') {
            foreach ($orderItems as $item) {
                if (in_array($item['product_id'], $applicableItems)) {
                    $eligibleTotal += $item['total'];
                }
            }
        } elseif ($discount->applies_to === 'categories') {
            foreach ($orderItems as $item) {
                if (in_array($item['category_id'], $applicableItems)) {
                    $eligibleTotal += $item['total'];
                }
            }
        }

        if ($eligibleTotal <= 0) {
            return 0;
        }

        // حساب الخصم حسب النوع
        switch ($discount->type) {
            case 'percentage':
                $discountAmount = ($eligibleTotal * $discount->value) / 100;
                break;
            case 'fixed':
                $discountAmount = min($discount->value, $eligibleTotal);
                break;
            case 'buy_x_get_y':
                // سيتم تطبيق منطق BOGO لاحقاً
                $discountAmount = 0;
                break;
            default:
                $discountAmount = 0;
        }

        return $discountAmount;
    }

    /**
     * تسجيل استخدام الخصم
     */
    public function recordUsage(int $discountId, int $orderId): bool
    {
        return DB::table('discounts')
            ->where('id', $discountId)
            ->update([
                'used_count' => DB::raw('used_count + 1'),
                'updated_at' => now()
            ]) > 0;
    }

    /**
     * الحصول على إحصائيات الخصومات
     */
    public function getStatistics(string $dateFrom = null, string $dateTo = null): array
    {
        $query = DB::table('discounts');

        if ($dateFrom) {
            $query->where('created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->where('created_at', '<=', $dateTo);
        }

        $stats = [
            'total_discounts' => $query->count(),
            'active_discounts' => $query->clone()->where('is_active', true)->count(),
            'expired_discounts' => $query->clone()->where('valid_until', '<', now())->count(),
            'total_usage' => $query->clone()->sum('used_count'),
            'by_type' => [],
            'top_discounts' => []
        ];

        // إحصائيات حسب النوع
        $typeStats = DB::table('discounts')
            ->select('type', DB::raw('COUNT(*) as count'))
            ->groupBy('type')
            ->get();

        foreach ($typeStats as $stat) {
            $stats['by_type'][$stat->type] = $stat->count;
        }

        // أفضل الخصومات استخداماً
        $topDiscounts = DB::table('discounts')
            ->select('code', 'name', 'used_count')
            ->orderBy('used_count', 'desc')
            ->limit(5)
            ->get();

        $stats['top_discounts'] = $topDiscounts;

        return $stats;
    }

    /**
     * البحث عن الخصومات المتاحة للطلب
     */
    public function getAvailableForOrder(array $orderItems, float $orderTotal): array
    {
        $now = now();
        
        return DB::table('discounts')
            ->where('is_active', true)
            ->where('valid_from', '<=', $now)
            ->where('valid_until', '>=', $now)
            ->where(function($query) use ($orderTotal) {
                $query->whereNull('min_order_amount')
                      ->orWhere('min_order_amount', '<=', $orderTotal);
            })
            ->where(function($query) {
                $query->whereNull('usage_limit')
                      ->orWhereRaw('used_count < usage_limit');
            })
            ->get()
            ->map(function($discount) use ($orderItems, $orderTotal) {
                $applicableItems = json_decode($discount->applicable_items, true) ?: [];
                $discountAmount = $this->calculateDiscountAmount($discount, $orderItems, $orderTotal, $applicableItems);
                
                return [
                    'id' => $discount->id,
                    'code' => $discount->code,
                    'name' => $discount->name,
                    'type' => $discount->type,
                    'value' => $discount->value,
                    'max_discount_amount' => $discount->max_discount_amount,
                    'discount_amount' => $discountAmount,
                    'savings' => $discountAmount
                ];
            })
            ->filter(function($discount) {
                return $discount['discount_amount'] > 0;
            })
            ->values()
            ->toArray();
    }

    /**
     * توليد كود خصم عشوائي
     */
    private function generateCode(): string
    {
        do {
            $code = 'DISC' . strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8));
        } while (DB::table('discounts')->where('code', $code)->exists());

        return $code;
    }

    /**
     * التحقق من تفرد كود الخصم
     */
    public function isCodeUnique(string $code, ?int $excludeId = null): bool
    {
        $query = DB::table('discounts')->where('code', $code);
        
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return !$query->exists();
    }

    /**
     * الحصول على الخصومات المنتهية الصلاحية
     */
    public function getExpired(): array
    {
        return DB::table('discounts')
            ->where('valid_until', '<', now())
            ->where('is_active', true)
            ->get()
            ->toArray();
    }

    /**
     * تجديد خصم منتهي الصلاحية
     */
    public function extend(int $id, string $newExpiryDate): bool
    {
        return DB::table('discounts')
            ->where('id', $id)
            ->update([
                'valid_until' => $newExpiryDate,
                'updated_at' => now()
            ]) > 0;
    }
}