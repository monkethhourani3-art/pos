<?php

namespace App\Models;

use App\Support\Facades\DB;
use App\Support\Facades\Auth;

class Coupon
{
    /**
     * إنشاء كوبون جديد
     */
    public function create(array $data): int
    {
        $user = Auth::user();
        
        $couponData = [
            'code' => $data['code'] ?? $this->generateCode(),
            'name' => $data['name'],
            'description' => $data['description'] ?? '',
            'discount_type' => $data['discount_type'], // 'percentage', 'fixed_amount', 'free_shipping'
            'discount_value' => $data['discount_value'],
            'minimum_order_amount' => $data['minimum_order_amount'] ?? 0,
            'maximum_discount_amount' => $data['maximum_discount_amount'] ?? null,
            'usage_limit' => $data['usage_limit'] ?? null,
            'used_count' => 0,
            'usage_limit_per_customer' => $data['usage_limit_per_customer'] ?? 1,
            'valid_from' => $data['valid_from'],
            'valid_until' => $data['valid_until'],
            'is_active' => $data['is_active'] ?? true,
            'is_public' => $data['is_public'] ?? false, // يمكن مشاركته أم لا
            'applicable_products' => json_encode($data['applicable_products'] ?? []),
            'applicable_categories' => json_encode($data['applicable_categories'] ?? []),
            'excluded_products' => json_encode($data['excluded_products'] ?? []),
            'customer_eligibility' => $data['customer_eligibility'] ?? 'all', // 'all', 'new', 'returning', 'vip'
            'first_time_only' => $data['first_time_only'] ?? false,
            'created_by' => $user->id,
            'created_at' => now()
        ];

        return DB::table('coupons')->insertGetId($couponData);
    }

    /**
     * الحصول على جميع الكوبونات
     */
    public function getAll(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $query = DB::table('coupons')
            ->select([
                'coupons.*',
                'users.first_name as created_by_name'
            ])
            ->leftJoin('users', 'coupons.created_by', '=', 'users.id');

        // تطبيق الفلاتر
        if (!empty($filters['search'])) {
            $query->where(function($q) use ($filters) {
                $q->where('coupons.code', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('coupons.name', 'like', '%' . $filters['search'] . '%');
            });
        }

        if (!empty($filters['discount_type'])) {
            $query->where('coupons.discount_type', $filters['discount_type']);
        }

        if (isset($filters['is_active'])) {
            $query->where('coupons.is_active', $filters['is_active']);
        }

        if (isset($filters['is_public'])) {
            $query->where('coupons.is_public', $filters['is_public']);
        }

        if (!empty($filters['date_from'])) {
            $query->where('coupons.valid_from', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('coupons.valid_until', '<=', $filters['date_to']);
        }

        // الترتيب
        $query->orderBy('coupons.created_at', 'desc');

        // الصفحات
        $total = $query->count();
        $coupons = $query->limit($perPage)
                         ->offset(($page - 1) * $perPage)
                         ->get();

        return [
            'data' => $coupons,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => ceil($total / $perPage)
            ]
        ];
    }

    /**
     * الحصول على كوبون بواسطة ID
     */
    public function getById(int $id): ?object
    {
        return DB::table('coupons')
            ->select([
                'coupons.*',
                'users.first_name as created_by_name'
            ])
            ->leftJoin('users', 'coupons.created_by', '=', 'users.id')
            ->where('coupons.id', $id)
            ->first();
    }

    /**
     * تحديث كوبون
     */
    public function update(int $id, array $data): bool
    {
        $updateData = [
            'name' => $data['name'],
            'description' => $data['description'] ?? '',
            'discount_type' => $data['discount_type'],
            'discount_value' => $data['discount_value'],
            'minimum_order_amount' => $data['minimum_order_amount'] ?? 0,
            'maximum_discount_amount' => $data['maximum_discount_amount'] ?? null,
            'usage_limit' => $data['usage_limit'] ?? null,
            'usage_limit_per_customer' => $data['usage_limit_per_customer'] ?? 1,
            'valid_from' => $data['valid_from'],
            'valid_until' => $data['valid_until'],
            'is_active' => $data['is_active'] ?? true,
            'is_public' => $data['is_public'] ?? false,
            'applicable_products' => json_encode($data['applicable_products'] ?? []),
            'applicable_categories' => json_encode($data['applicable_categories'] ?? []),
            'excluded_products' => json_encode($data['excluded_products'] ?? []),
            'customer_eligibility' => $data['customer_eligibility'] ?? 'all',
            'first_time_only' => $data['first_time_only'] ?? false,
            'updated_at' => now()
        ];

        // إزالة القيم الفارغة
        $updateData = array_filter($updateData, function($value) {
            return $value !== null;
        });

        return DB::table('coupons')
            ->where('id', $id)
            ->update($updateData) > 0;
    }

    /**
     * حذف كوبون
     */
    public function delete(int $id): bool
    {
        return DB::table('coupons')
            ->where('id', $id)
            ->delete() > 0;
    }

    /**
     * تبديل حالة النشاط
     */
    public function toggleStatus(int $id): bool
    {
        return DB::table('coupons')
            ->where('id', $id)
            ->update([
                'is_active' => DB::raw('NOT is_active'),
                'updated_at' => now()
            ]) > 0;
    }

    /**
     * التحقق من صحة الكوبون وتطبيقه
     */
    public function validateAndApply(string $code, array $orderItems, float $orderTotal, ?object $customer = null): array
    {
        $coupon = DB::table('coupons')
            ->where('code', strtoupper($code))
            ->where('is_active', true)
            ->first();

        if (!$coupon) {
            return ['success' => false, 'message' => 'كود الكوبون غير صحيح'];
        }

        // التحقق من صحة التاريخ
        $now = now();
        if ($now->lt($coupon->valid_from)) {
            return ['success' => false, 'message' => 'الكوبون غير نشط بعد'];
        }

        if ($now->gt($coupon->valid_until)) {
            return ['success' => false, 'message' => 'انتهت صلاحية الكوبون'];
        }

        // التحقق من حد الاستخدام العام
        if ($coupon->usage_limit && $coupon->used_count >= $coupon->usage_limit) {
            return ['success' => false, 'message' => 'تم استخدام الحد الأقصى للكوبون'];
        }

        // التحقق من حد الاستخدام لكل عميل
        if ($customer) {
            $customerUsageCount = $this->getCustomerUsageCount($coupon->id, $customer->id);
            if ($customerUsageCount >= $coupon->usage_limit_per_customer) {
                return ['success' => false, 'message' => 'تم استخدام هذا الكوبون من قبل'];
            }
        }

        // التحقق من الحد الأدنى للطلب
        if ($coupon->minimum_order_amount && $orderTotal < $coupon->minimum_order_amount) {
            return [
                'success' => false, 
                'message' => 'الحد الأدنى للطلب هو ' . number_format($coupon->minimum_order_amount, 2) . ' ر.س'
            ];
        }

        // التحقق من الأهلية للعميل
        if ($customer && !$this->checkCustomerEligibility($coupon, $customer)) {
            return ['success' => false, 'message' => 'هذا الكوبون غير متاح لك'];
        }

        // التحقق من المنتجات المطبقة
        $applicableProducts = json_decode($coupon->applicable_products, true) ?: [];
        $applicableCategories = json_decode($coupon->applicable_categories, true) ?: [];
        $excludedProducts = json_decode($coupon->excluded_products, true) ?: [];

        if (!$this->checkProductEligibility($orderItems, $applicableProducts, $applicableCategories, $excludedProducts)) {
            return ['success' => false, 'message' => 'لا يمكن تطبيق هذا الكوبون على المنتجات المحددة'];
        }

        // حساب قيمة الخصم
        $discountAmount = $this->calculateDiscountAmount($coupon, $orderItems, $orderTotal);

        if ($discountAmount <= 0) {
            return ['success' => false, 'message' => 'لا يمكن تطبيق هذا الكوبون على الطلب الحالي'];
        }

        // التحقق من الحد الأقصى للخصم
        if ($coupon->maximum_discount_amount && $discountAmount > $coupon->maximum_discount_amount) {
            $discountAmount = $coupon->maximum_discount_amount;
        }

        return [
            'success' => true,
            'coupon' => $coupon,
            'discount_amount' => $discountAmount,
            'final_total' => $orderTotal - $discountAmount
        ];
    }

    /**
     * حساب مبلغ الخصم
     */
    private function calculateDiscountAmount(object $coupon, array $orderItems, float $orderTotal): float
    {
        $eligibleTotal = 0;

        // تحديد المبلغ المؤهل للخصم
        $applicableProducts = json_decode($coupon->applicable_products, true) ?: [];
        $applicableCategories = json_decode($coupon->applicable_categories, true) ?: [];
        $excludedProducts = json_decode($coupon->excluded_products, true) ?: [];

        foreach ($orderItems as $item) {
            // التحقق من الاستبعاد
            if (in_array($item['product_id'], $excludedProducts)) {
                continue;
            }

            // التحقق من التطبيق على المنتجات المحددة
            if (!empty($applicableProducts)) {
                if (in_array($item['product_id'], $applicableProducts)) {
                    $eligibleTotal += $item['total'];
                }
            }
            // التحقق من التطبيق على الفئات المحددة
            elseif (!empty($applicableCategories)) {
                if (in_array($item['category_id'], $applicableCategories)) {
                    $eligibleTotal += $item['total'];
                }
            }
            // تطبيق على جميع المنتجات
            else {
                $eligibleTotal += $item['total'];
            }
        }

        if ($eligibleTotal <= 0) {
            return 0;
        }

        // حساب الخصم حسب النوع
        switch ($coupon->discount_type) {
            case 'percentage':
                $discountAmount = ($eligibleTotal * $coupon->discount_value) / 100;
                break;
            case 'fixed_amount':
                $discountAmount = min($coupon->discount_value, $eligibleTotal);
                break;
            case 'free_shipping':
                $discountAmount = 0; // سيتم التعامل معه في نظام الشحن
                break;
            default:
                $discountAmount = 0;
        }

        return $discountAmount;
    }

    /**
     * التحقق من أهلية العميل
     */
    private function checkCustomerEligibility(object $coupon, object $customer): bool
    {
        switch ($coupon->customer_eligibility) {
            case 'new':
                // التحقق من أن العميل جديد (أول طلب)
                $orderCount = DB::table('orders')
                    ->where('customer_id', $customer->id)
                    ->count();
                return $orderCount === 0;
            
            case 'returning':
                // التحقق من أن العميل قام بطلبات سابقة
                return DB::table('orders')
                    ->where('customer_id', $customer->id)
                    ->where('status', 'completed')
                    ->exists();
            
            case 'vip':
                // التحقق من حالة VIP
                return ($customer->customer_type ?? '') === 'vip';
            
            default:
                return true;
        }
    }

    /**
     * التحقق من أهلية المنتجات
     */
    private function checkProductEligibility(array $orderItems, array $applicableProducts, array $applicableCategories, array $excludedProducts): bool
    {
        // إذا لم يتم تحديد منتجات أو فئات، فهو صالح لجميع المنتجات
        if (empty($applicableProducts) && empty($applicableCategories)) {
            return true;
        }

        foreach ($orderItems as $item) {
            // التحقق من الاستبعاد
            if (in_array($item['product_id'], $excludedProducts)) {
                continue;
            }

            // التحقق من التطبيق على المنتجات المحددة
            if (!empty($applicableProducts)) {
                if (in_array($item['product_id'], $applicableProducts)) {
                    return true;
                }
            }

            // التحقق من التطبيق على الفئات المحددة
            if (!empty($applicableCategories)) {
                if (in_array($item['category_id'], $applicableCategories)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * الحصول على عدد استخدامات العميل للكوبون
     */
    private function getCustomerUsageCount(int $couponId, int $customerId): int
    {
        return DB::table('coupon_usage')
            ->where('coupon_id', $couponId)
            ->where('customer_id', $customerId)
            ->count();
    }

    /**
     * تسجيل استخدام الكوبون
     */
    public function recordUsage(int $couponId, int $orderId, ?int $customerId = null): bool
    {
        // تحديث عداد الاستخدام العام
        DB::table('coupons')
            ->where('id', $couponId)
            ->update([
                'used_count' => DB::raw('used_count + 1'),
                'updated_at' => now()
            ]);

        // تسجيل استخدام العميل
        if ($customerId) {
            $usageRecord = [
                'coupon_id' => $couponId,
                'order_id' => $orderId,
                'customer_id' => $customerId,
                'used_at' => now()
            ];

            DB::table('coupon_usage')->insert($usageRecord);
        }

        return true;
    }

    /**
     * الحصول على الكوبونات العامة المتاحة
     */
    public function getPublicCoupons(): array
    {
        $now = now();
        
        return DB::table('coupons')
            ->where('is_public', true)
            ->where('is_active', true)
            ->where('valid_from', '<=', $now)
            ->where('valid_until', '>=', $now)
            ->where(function($query) {
                $query->whereNull('usage_limit')
                      ->orWhereRaw('used_count < usage_limit');
            })
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * إنشاء كود كوبون عشوائي
     */
    private function generateCode(): string
    {
        do {
            $code = 'COUPON' . strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8));
        } while (DB::table('coupons')->where('code', $code)->exists());

        return $code;
    }

    /**
     * التحقق من تفرد كود الكوبون
     */
    public function isCodeUnique(string $code, ?int $excludeId = null): bool
    {
        $query = DB::table('coupons')->where('code', strtoupper($code));
        
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return !$query->exists();
    }

    /**
     * الحصول على إحصائيات الكوبونات
     */
    public function getStatistics(string $dateFrom = null, string $dateTo = null): array
    {
        $query = DB::table('coupons');

        if ($dateFrom) {
            $query->where('created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->where('created_at', '<=', $dateTo);
        }

        $stats = [
            'total_coupons' => $query->count(),
            'active_coupons' => $query->clone()->where('is_active', true)->count(),
            'public_coupons' => $query->clone()->where('is_public', true)->count(),
            'expired_coupons' => $query->clone()->where('valid_until', '<', now())->count(),
            'total_usage' => $query->clone()->sum('used_count'),
            'by_discount_type' => [],
            'top_coupons' => []
        ];

        // إحصائيات حسب نوع الخصم
        $typeStats = DB::table('coupons')
            ->select('discount_type', DB::raw('COUNT(*) as count'))
            ->groupBy('discount_type')
            ->get();

        foreach ($typeStats as $stat) {
            $stats['by_discount_type'][$stat->discount_type] = $stat->count;
        }

        // أفضل الكوبونات استخداماً
        $topCoupons = DB::table('coupons')
            ->select('code', 'name', 'used_count')
            ->orderBy('used_count', 'desc')
            ->limit(5)
            ->get();

        $stats['top_coupons'] = $topCoupons;

        return $stats;
    }

    /**
     * الحصول على استخدامات الكوبون
     */
    public function getUsageHistory(int $couponId, int $page = 1, int $perPage = 20): array
    {
        $query = DB::table('coupon_usage')
            ->select([
                'coupon_usage.*',
                'orders.order_number',
                'orders.total_amount',
                'users.first_name as customer_name'
            ])
            ->leftJoin('orders', 'coupon_usage.order_id', '=', 'orders.id')
            ->leftJoin('users', 'coupon_usage.customer_id', '=', 'users.id')
            ->where('coupon_usage.coupon_id', $couponId)
            ->orderBy('coupon_usage.used_at', 'desc');

        $total = $query->count();
        $usage = $query->limit($perPage)
                       ->offset(($page - 1) * $perPage)
                       ->get();

        return [
            'data' => $usage,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => ceil($total / $perPage)
            ]
        ];
    }

    /**
     * نسخ كوبون
     */
    public function duplicate(int $id, string $newName, ?string $newCode = null): ?int
    {
        $original = $this->getById($id);
        if (!$original) {
            return null;
        }

        $newCouponData = [
            'code' => $newCode ?? $this->generateCode(),
            'name' => $newName,
            'description' => $original->description,
            'discount_type' => $original->discount_type,
            'discount_value' => $original->discount_value,
            'minimum_order_amount' => $original->minimum_order_amount,
            'maximum_discount_amount' => $original->maximum_discount_amount,
            'usage_limit' => $original->usage_limit,
            'usage_limit_per_customer' => $original->usage_limit_per_customer,
            'valid_from' => $original->valid_from,
            'valid_until' => $original->valid_until,
            'is_active' => false, // نسخ غير نشط افتراضياً
            'is_public' => $original->is_public,
            'applicable_products' => $original->applicable_products,
            'applicable_categories' => $original->applicable_categories,
            'excluded_products' => $original->excluded_products,
            'customer_eligibility' => $original->customer_eligibility,
            'first_time_only' => $original->first_time_only,
            'created_by' => Auth::id(),
            'created_at' => now()
        ];

        return $this->create($newCouponData);
    }

    /**
     * تجديد كوبون منتهي الصلاحية
     */
    public function extend(int $id, string $newExpiryDate): bool
    {
        return DB::table('coupons')
            ->where('id', $id)
            ->update([
                'valid_until' => $newExpiryDate,
                'updated_at' => now()
            ]) > 0;
    }

    /**
     * الحصول على الكوبونات المنتهية الصلاحية
     */
    public function getExpired(): array
    {
        return DB::table('coupons')
            ->where('valid_until', '<', now())
            ->where('is_active', true)
            ->get()
            ->toArray();
    }

    /**
     * إحصائيات مفصلة للاستخدام
     */
    public function getDetailedUsageStats(int $couponId): array
    {
        $coupon = $this->getById($couponId);
        if (!$coupon) {
            return [];
        }

        $usageStats = DB::table('coupon_usage')
            ->select([
                DB::raw('DATE(used_at) as usage_date'),
                DB::raw('COUNT(*) as daily_usage'),
                DB::raw('SUM(orders.total_amount) as daily_revenue')
            ])
            ->join('orders', 'coupon_usage.order_id', '=', 'orders.id')
            ->where('coupon_usage.coupon_id', $couponId)
            ->groupBy(DB::raw('DATE(used_at)'))
            ->orderBy('usage_date', 'desc')
            ->limit(30)
            ->get();

        return [
            'coupon_info' => $coupon,
            'usage_by_day' => $usageStats,
            'total_usage' => $coupon->used_count,
            'usage_rate' => $coupon->usage_limit ? ($coupon->used_count / $coupon->usage_limit) * 100 : 0
        ];
    }
}