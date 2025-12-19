<?php

namespace App\Models;

use App\Support\Facades\DB;
use App\Support\Facades\Auth;

class LoyaltyProgram
{
    /**
     * إنشاء عميل جديد في برنامج الولاء
     */
    public function createCustomer(array $data): int
    {
        $customerData = [
            'customer_id' => $data['customer_id'], // من جدول العملاء أو الطلبات
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'tier' => $data['tier'] ?? 'bronze', // bronze, silver, gold, platinum
            'total_points' => $data['initial_points'] ?? 0,
            'available_points' => $data['initial_points'] ?? 0,
            'lifetime_spent' => 0,
            'total_orders' => 0,
            'join_date' => now(),
            'last_activity' => now(),
            'is_active' => true,
            'created_at' => now()
        ];

        return DB::table('loyalty_customers')->insertGetId($customerData);
    }

    /**
     * الحصول على عميل الولاء
     */
    public function getCustomer(int $customerId): ?object
    {
        return DB::table('loyalty_customers')
            ->where('customer_id', $customerId)
            ->first();
    }

    /**
     * الحصول على عميل بواسطة رقم الهاتف أو البريد الإلكتروني
     */
    public function findCustomer(string $identifier): ?object
    {
        return DB::table('loyalty_customers')
            ->where('phone', $identifier)
            ->orWhere('email', $identifier)
            ->where('is_active', true)
            ->first();
    }

    /**
     * إضافة نقاط للعميل
     */
    public function addPoints(int $customerId, int $points, string $reason, ?int $orderId = null): bool
    {
        $customer = $this->getCustomer($customerId);
        if (!$customer) {
            return false;
        }

        // إضافة سجل النقاط
        $pointRecord = [
            'customer_id' => $customerId,
            'points' => $points,
            'type' => 'earned', // earned, redeemed, adjusted
            'reason' => $reason,
            'order_id' => $orderId,
            'created_at' => now()
        ];

        DB::table('loyalty_points')->insert($pointRecord);

        // تحديث رصيد العميل
        return DB::table('loyalty_customers')
            ->where('customer_id', $customerId)
            ->update([
                'total_points' => DB::raw('total_points + ' . $points),
                'available_points' => DB::raw('available_points + ' . $points),
                'last_activity' => now(),
                'updated_at' => now()
            ]) > 0;
    }

    /**
     * استبدال النقاط
     */
    public function redeemPoints(int $customerId, int $points, string $reason, ?int $orderId = null): bool
    {
        $customer = $this->getCustomer($customerId);
        if (!$customer || $customer->available_points < $points) {
            return false;
        }

        // إضافة سجل النقاط
        $pointRecord = [
            'customer_id' => $customerId,
            'points' => -$points,
            'type' => 'redeemed',
            'reason' => $reason,
            'order_id' => $orderId,
            'created_at' => now()
        ];

        DB::table('loyalty_points')->insert($pointRecord);

        // تحديث رصيد العميل
        return DB::table('loyalty_customers')
            ->where('customer_id', $customerId)
            ->update([
                'available_points' => DB::raw('available_points - ' . $points),
                'last_activity' => now(),
                'updated_at' => now()
            ]) > 0;
    }

    /**
     * حساب النقاط المستحقة من الطلب
     */
    public function calculatePointsFromOrder(float $orderTotal, ?string $customerTier = null): int
    {
        // معدل النقاط الأساسي: نقطة لكل ريال
        $baseRate = 1;
        
        // مضاعفات المستويات
        $tierMultipliers = [
            'bronze' => 1,
            'silver' => 1.5,
            'gold' => 2,
            'platinum' => 3
        ];

        $multiplier = $tierMultipliers[$customerTier] ?? 1;
        $points = floor($orderTotal * $baseRate * $multiplier);

        // نقاط إضافية للطلبات الكبيرة
        if ($orderTotal >= 500) {
            $points += 50; // نقاط إضافية للطلبات الكبيرة
        } elseif ($orderTotal >= 200) {
            $points += 20;
        }

        return $points;
    }

    /**
     * التحقق من ترقية المستوى
     */
    public function checkTierUpgrade(int $customerId): bool
    {
        $customer = $this->getCustomer($customerId);
        if (!$customer) {
            return false;
        }

        $newTier = $this->determineTier($customer->lifetime_spent, $customer->total_orders);
        
        if ($newTier !== $customer->tier) {
            return DB::table('loyalty_customers')
                ->where('customer_id', $customerId)
                ->update([
                    'tier' => $newTier,
                    'updated_at' => now()
                ]) > 0;
        }

        return false;
    }

    /**
     * تحديد مستوى العميل بناءً على الإنفاق والطلبات
     */
    private function determineTier(float $lifetimeSpent, int $totalOrders): string
    {
        if ($lifetimeSpent >= 10000 && $totalOrders >= 100) {
            return 'platinum';
        } elseif ($lifetimeSpent >= 5000 && $totalOrders >= 50) {
            return 'gold';
        } elseif ($lifetimeSpent >= 1000 && $totalOrders >= 20) {
            return 'silver';
        } else {
            return 'bronze';
        }
    }

    /**
     * معالجة طلب جديد وتحديث بيانات الولاء
     */
    public function processOrder(int $customerId, float $orderTotal, int $orderId): array
    {
        $customer = $this->getCustomer($customerId);
        if (!$customer) {
            return ['success' => false, 'message' => 'عميل غير موجود في برنامج الولاء'];
        }

        // حساب النقاط المستحقة
        $pointsEarned = $this->calculatePointsFromOrder($orderTotal, $customer->tier);
        
        // إضافة النقاط
        $this->addPoints($customerId, $pointsEarned, "طلب رقم #{$orderId}", $orderId);

        // تحديث إحصائيات العميل
        DB::table('loyalty_customers')
            ->where('customer_id', $customerId)
            ->update([
                'lifetime_spent' => DB::raw('lifetime_spent + ' . $orderTotal),
                'total_orders' => DB::raw('total_orders + 1'),
                'last_activity' => now(),
                'updated_at' => now()
            ]);

        // التحقق من ترقية المستوى
        $tierUpgraded = $this->checkTierUpgrade($customerId);

        // تحديث آخر طلب
        DB::table('loyalty_customers')
            ->where('customer_id', $customerId)
            ->update(['last_order_id' => $orderId]);

        return [
            'success' => true,
            'points_earned' => $pointsEarned,
            'tier_upgraded' => $tierUpgraded,
            'current_tier' => $this->getCustomer($customerId)->tier,
            'total_points' => $this->getCustomer($customerId)->available_points
        ];
    }

    /**
     * الحصول على تاريخ نقاط العميل
     */
    public function getPointsHistory(int $customerId, int $page = 1, int $perPage = 20): array
    {
        $query = DB::table('loyalty_points')
            ->where('customer_id', $customerId)
            ->orderBy('created_at', 'desc');

        $total = $query->count();
        $points = $query->limit($perPage)
                        ->offset(($page - 1) * $perPage)
                        ->get();

        return [
            'data' => $points,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => ceil($total / $perPage)
            ]
        ];
    }

    /**
     * الحصول على المكافآت المتاحة للاستبدال
     */
    public function getAvailableRewards(): array
    {
        return DB::table('loyalty_rewards')
            ->where('is_active', true)
            ->where(function($query) {
                $query->whereNull('valid_from')
                      ->orWhere('valid_from', '<=', now());
            })
            ->where(function($query) {
                $query->whereNull('valid_until')
                      ->orWhere('valid_until', '>=', now());
            })
            ->orderBy('points_required', 'asc')
            ->get()
            ->toArray();
    }

    /**
     * استبدال نقاط بمكافأة
     */
    public function redeemReward(int $customerId, int $rewardId): array
    {
        $customer = $this->getCustomer($customerId);
        $reward = DB::table('loyalty_rewards')->where('id', $rewardId)->first();

        if (!$customer || !$reward) {
            return ['success' => false, 'message' => 'بيانات غير صحيحة'];
        }

        if ($customer->available_points < $reward->points_required) {
            return ['success' => false, 'message' => 'نقاط غير كافية'];
        }

        // استبدال النقاط
        $this->redeemPoints($customerId, $reward->points_required, "استبدال: {$reward->name}", null);

        // تسجيل الاستبدال
        $redemption = [
            'customer_id' => $customerId,
            'reward_id' => $rewardId,
            'points_used' => $reward->points_required,
            'status' => 'pending', // pending, fulfilled, cancelled
            'created_at' => now()
        ];

        $redemptionId = DB::table('loyalty_redemptions')->insertGetId($redemption);

        return [
            'success' => true,
            'redemption_id' => $redemptionId,
            'message' => 'تم استبدال المكافأة بنجاح'
        ];
    }

    /**
     * الحصول على إحصائيات برنامج الولاء
     */
    public function getStatistics(): array
    {
        $stats = [
            'total_customers' => DB::table('loyalty_customers')->count(),
            'active_customers' => DB::table('loyalty_customers')->where('is_active', true)->count(),
            'total_points_issued' => DB::table('loyalty_points')->where('points', '>', 0)->sum('points'),
            'total_points_redeemed' => abs(DB::table('loyalty_points')->where('points', '<', 0)->sum('points')),
            'total_rewards_redeemed' => DB::table('loyalty_redemptions')->count(),
            'by_tier' => [],
            'top_customers' => []
        ];

        // إحصائيات حسب المستوى
        $tierStats = DB::table('loyalty_customers')
            ->select('tier', DB::raw('COUNT(*) as count'))
            ->where('is_active', true)
            ->groupBy('tier')
            ->get();

        foreach ($tierStats as $stat) {
            $stats['by_tier'][$stat->tier] = $stat->count;
        }

        // أفضل العملاء
        $topCustomers = DB::table('loyalty_customers')
            ->select('customer_id', 'tier', 'lifetime_spent', 'total_points')
            ->where('is_active', true)
            ->orderBy('lifetime_spent', 'desc')
            ->limit(10)
            ->get();

        $stats['top_customers'] = $topCustomers;

        return $stats;
    }

    /**
     * الحصول على أفضل العملاء في فترة معينة
     */
    public function getTopCustomers(string $period = 'month', int $limit = 10): array
    {
        $dateFrom = match($period) {
            'week' => now()->subWeek(),
            'month' => now()->subMonth(),
            'quarter' => now()->subMonths(3),
            'year' => now()->subYear(),
            default => now()->subMonth()
        };

        return DB::table('loyalty_customers')
            ->select([
                'loyalty_customers.*',
                DB::raw('SUM(CASE WHEN loyalty_points.type = "earned" THEN loyalty_points.points ELSE 0 END) as period_points'),
                DB::raw('COUNT(DISTINCT loyalty_points.order_id) as period_orders')
            ])
            ->leftJoin('loyalty_points', 'loyalty_customers.customer_id', '=', 'loyalty_points.customer_id')
            ->where('loyalty_points.created_at', '>=', $dateFrom)
            ->where('loyalty_customers.is_active', true)
            ->groupBy('loyalty_customers.id')
            ->orderBy('period_points', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * إرسال إشعار للعملاء
     */
    public function sendNotification(int $customerId, string $type, string $message): bool
    {
        $notification = [
            'customer_id' => $customerId,
            'type' => $type, // points_earned, tier_upgraded, reward_available, expiry_warning
            'title' => $this->getNotificationTitle($type),
            'message' => $message,
            'is_read' => false,
            'created_at' => now()
        ];

        return DB::table('loyalty_notifications')->insert($notification) > 0;
    }

    /**
     * الحصول على عنوان الإشعار
     */
    private function getNotificationTitle(string $type): string
    {
        return match($type) {
            'points_earned' => 'تم إضافة نقاط جديدة',
            'tier_upgraded' => 'تهانينا! تم ترقية مستواك',
            'reward_available' => 'مكافأة جديدة متاحة',
            'expiry_warning' => 'تحذير: انتهاء صلاحية النقاط',
            default => 'إشعار برنامج الولاء'
        };
    }

    /**
     * فحص النقاط القريبة من الانتهاء
     */
    public function checkExpiringPoints(): array
    {
        // البحث عن النقاط التي ستنتهي خلال 30 يوماً
        $expiringDate = now()->addDays(30);
        
        return DB::table('loyalty_points')
            ->select([
                'loyalty_points.*',
                'loyalty_customers.phone',
                'loyalty_customers.email'
            ])
            ->join('loyalty_customers', 'loyalty_points.customer_id', '=', 'loyalty_customers.customer_id')
            ->where('loyalty_points.expires_at', '<=', $expiringDate)
            ->where('loyalty_points.expires_at', '>', now())
            ->where('loyalty_points.points', '>', 0)
            ->where('loyalty_customers.is_active', true)
            ->get()
            ->toArray();
    }

    /**
     * تجديد النقاط المنتهية الصلاحية
     */
    public function extendPoints(int $customerId, int $points, int $days): bool
    {
        $newExpiryDate = now()->addDays($days);
        
        return DB::table('loyalty_points')
            ->where('customer_id', $customerId)
            ->where('points', '>', 0)
            ->where('expires_at', '<=', now())
            ->update([
                'expires_at' => $newExpiryDate,
                'updated_at' => now()
            ]) > 0;
    }

    /**
     * إنشاء حملة ولاء جديدة
     */
    public function createCampaign(array $data): int
    {
        $campaignData = [
            'name' => $data['name'],
            'description' => $data['description'] ?? '',
            'type' => $data['type'], // points_multiplier, bonus_points, tier_bonus
            'parameters' => json_encode($data['parameters'] ?? []),
            'target_tier' => $data['target_tier'] ?? 'all',
            'valid_from' => $data['valid_from'],
            'valid_until' => $data['valid_until'],
            'usage_limit' => $data['usage_limit'] ?? null,
            'used_count' => 0,
            'is_active' => $data['is_active'] ?? true,
            'created_by' => Auth::id(),
            'created_at' => now()
        ];

        return DB::table('loyalty_campaigns')->insertGetId($campaignData);
    }

    /**
     * تطبيق حملة ولاء على طلب
     */
    public function applyCampaign(int $customerId, float $orderTotal, int $orderId): array
    {
        $customer = $this->getCustomer($customerId);
        if (!$customer) {
            return ['success' => false, 'message' => 'عميل غير موجود'];
        }

        $now = now();
        $campaigns = DB::table('loyalty_campaigns')
            ->where('is_active', true)
            ->where('valid_from', '<=', $now)
            ->where('valid_until', '>=', $now)
            ->where(function($query) use ($customer) {
                $query->where('target_tier', 'all')
                      ->orWhere('target_tier', $customer->tier);
            })
            ->where(function($query) {
                $query->whereNull('usage_limit')
                      ->orWhereRaw('used_count < usage_limit');
            })
            ->get();

        $appliedCampaigns = [];
        $totalBonusPoints = 0;

        foreach ($campaigns as $campaign) {
            $parameters = json_decode($campaign->parameters, true) ?: [];
            $bonusPoints = 0;

            switch ($campaign->type) {
                case 'points_multiplier':
                    $multiplier = $parameters['multiplier'] ?? 1;
                    $regularPoints = $this->calculatePointsFromOrder($orderTotal, $customer->tier);
                    $bonusPoints = floor($regularPoints * ($multiplier - 1));
                    break;
                
                case 'bonus_points':
                    $bonusPoints = $parameters['bonus_points'] ?? 0;
                    break;
                
                case 'tier_bonus':
                    $bonusPoints = $parameters[$customer->tier] ?? 0;
                    break;
            }

            if ($bonusPoints > 0) {
                $this->addPoints($customerId, $bonusPoints, "حملة: {$campaign->name}", $orderId);
                
                // زيادة عداد الاستخدام
                DB::table('loyalty_campaigns')
                    ->where('id', $campaign->id)
                    ->update([
                        'used_count' => DB::raw('used_count + 1'),
                        'updated_at' => now()
                    ]);

                $appliedCampaigns[] = [
                    'campaign_id' => $campaign->id,
                    'name' => $campaign->name,
                    'bonus_points' => $bonusPoints
                ];

                $totalBonusPoints += $bonusPoints;
            }
        }

        return [
            'success' => true,
            'applied_campaigns' => $appliedCampaigns,
            'total_bonus_points' => $totalBonusPoints
        ];
    }

    /**
     * الحصول على تقرير ولاء شامل
     */
    public function getReport(string $dateFrom = null, string $dateTo = null): array
    {
        $whereClause = '';
        $params = [];

        if ($dateFrom && $dateTo) {
            $whereClause = 'WHERE lp.created_at BETWEEN ? AND ?';
            $params = [$dateFrom, $dateTo];
        }

        $query = "
            SELECT 
                lc.tier,
                COUNT(DISTINCT lc.customer_id) as total_customers,
                SUM(CASE WHEN lp.type = 'earned' THEN lp.points ELSE 0 END) as total_points_earned,
                SUM(CASE WHEN lp.type = 'redeemed' THEN ABS(lp.points) ELSE 0 END) as total_points_redeemed,
                COUNT(DISTINCT lp.order_id) as total_orders,
                SUM(o.total_amount) as total_revenue
            FROM loyalty_customers lc
            LEFT JOIN loyalty_points lp ON lc.customer_id = lp.customer_id
            LEFT JOIN orders o ON lp.order_id = o.id
            {$whereClause}
            GROUP BY lc.tier
        ";

        $tierReport = DB::select($query, $params);

        return [
            'tier_breakdown' => $tierReport,
            'total_customers' => DB::table('loyalty_customers')->count(),
            'active_customers' => DB::table('loyalty_customers')->where('is_active', true)->count(),
            'average_points_per_customer' => DB::table('loyalty_customers')
                ->where('is_active', true)
                ->avg('available_points') ?: 0
        ];
    }
}