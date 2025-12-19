<?php

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Models\LoyaltyProgram;
use App\Support\Facades\Auth;
use App\Support\Facades\Session;
use App\Validation\Validator;

class LoyaltyController
{
    private $loyaltyModel;

    public function __construct()
    {
        $this->loyaltyModel = new LoyaltyProgram();
    }

    /**
     * عرض قائمة العملاء في برنامج الولاء
     */
    public function customers(Request $request): Response
    {
        // Check permissions
        if (!Auth::hasPermission('loyalty.manage')) {
            return Response::error('Unauthorized access', 403);
        }

        $filters = [
            'search' => $request->input('search'),
            'tier' => $request->input('tier'),
            'is_active' => $request->input('is_active')
        ];

        $page = max(1, (int) $request->input('page', 1));
        $perPage = min(50, max(10, (int) $request->input('per_page', 20)));

        try {
            // Get loyalty customers with customer details
            $query = \App\Support\Facades\DB::table('loyalty_customers')
                ->select([
                    'loyalty_customers.*',
                    'users.first_name',
                    'users.last_name',
                    'users.email',
                    'users.phone'
                ])
                ->leftJoin('users', 'loyalty_customers.customer_id', '=', 'users.id');

            // Apply filters
            if (!empty($filters['search'])) {
                $query->where(function($q) use ($filters) {
                    $q->where('users.first_name', 'like', '%' . $filters['search'] . '%')
                      ->orWhere('users.last_name', 'like', '%' . $filters['search'] . '%')
                      ->orWhere('users.email', 'like', '%' . $filters['search'] . '%')
                      ->orWhere('users.phone', 'like', '%' . $filters['search'] . '%');
                });
            }

            if (!empty($filters['tier'])) {
                $query->where('loyalty_customers.tier', $filters['tier']);
            }

            if (isset($filters['is_active'])) {
                $query->where('loyalty_customers.is_active', $filters['is_active']);
            }

            // Ordering and pagination
            $query->orderBy('loyalty_customers.last_activity', 'desc');
            $total = $query->count();
            $customers = $query->limit($perPage)
                               ->offset(($page - 1) * $perPage)
                               ->get();

            $statistics = $this->loyaltyModel->getStatistics();

            return Response::view('loyalty.customers', [
                'customers' => $customers,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'last_page' => ceil($total / $perPage)
                ],
                'filters' => $filters,
                'statistics' => $statistics
            ]);

        } catch (\Exception $e) {
            error_log('Loyalty customers error: ' . $e->getMessage());
            return Response::error('فشل في تحميل قائمة العملاء', 500);
        }
    }

    /**
     * عرض تفاصيل عميل في برنامج الولاء
     */
    public function customerDetails(Request $request, int $customerId): Response
    {
        if (!Auth::hasPermission('loyalty.view')) {
            return Response::error('Unauthorized access', 403);
        }

        try {
            $customer = $this->loyaltyModel->getCustomer($customerId);
            
            if (!$customer) {
                return Response::error('العميل غير موجود في برنامج الولاء', 404);
            }

            // Get customer details from users table
            $userDetails = \App\Support\Facades\DB::table('users')
                ->where('id', $customer->customer_id)
                ->first();

            // Get points history
            $pointsHistory = $this->loyaltyModel->getPointsHistory($customerId, 1, 50);

            // Get top customers for comparison
            $topCustomers = $this->loyaltyModel->getTopCustomers('month', 10);

            return Response::view('loyalty.customer-details', [
                'customer' => $customer,
                'user_details' => $userDetails,
                'points_history' => $pointsHistory['data'],
                'top_customers' => $topCustomers
            ]);

        } catch (\Exception $e) {
            error_log('Loyalty customer details error: ' . $e->getMessage());
            return Response::error('فشل في تحميل تفاصيل العميل', 500);
        }
    }

    /**
     * إنشاء عميل جديد في برنامج الولاء
     */
    public function createCustomer(Request $request): Response
    {
        if (!Auth::hasPermission('loyalty.create')) {
            return Response::error('Unauthorized access', 403);
        }

        $validator = new Validator();
        $rules = [
            'customer_id' => 'required|integer',
            'phone' => 'string|max:20',
            'email' => 'email',
            'tier' => 'in:bronze,silver,gold,platinum',
            'initial_points' => 'integer|min:0'
        ];

        if (!$validator->validate($request->all(), $rules)) {
            return Response::json([
                'success' => false,
                'errors' => $validator->getErrors()
            ], 422);
        }

        try {
            $data = $request->all();
            
            // Check if customer already exists in loyalty program
            $existingCustomer = $this->loyaltyModel->getCustomer($data['customer_id']);
            if ($existingCustomer) {
                return Response::json([
                    'success' => false,
                    'message' => 'العميل موجود بالفعل في برنامج الولاء'
                ], 422);
            }

            $customerId = $this->loyaltyModel->createCustomer($data);

            Session::flash('success', 'تم إضافة العميل إلى برنامج الولاء بنجاح');
            
            return Response::json([
                'success' => true,
                'message' => 'تم إضافة العميل إلى برنامج الولاء بنجاح',
                'customer_id' => $customerId
            ]);

        } catch (\Exception $e) {
            error_log('Loyalty create customer error: ' . $e->getMessage());
            return Response::json([
                'success' => false,
                'message' => 'فشل في إضافة العميل'
            ], 500);
        }
    }

    /**
     * البحث عن عميل بالهاتف أو البريد الإلكتروني
     */
    public function findCustomer(Request $request): Response
    {
        $validator = new Validator();
        $rules = [
            'identifier' => 'required|string|min:3'
        ];

        if (!$validator->validate($request->all(), $rules)) {
            return Response::json([
                'success' => false,
                'errors' => $validator->getErrors()
            ], 422);
        }

        try {
            $identifier = $request->input('identifier');
            $customer = $this->loyaltyModel->findCustomer($identifier);

            if ($customer) {
                return Response::json([
                    'success' => true,
                    'customer' => $customer
                ]);
            } else {
                return Response::json([
                    'success' => false,
                    'message' => 'لم يتم العثور على عميل بهذا المعرف'
                ], 404);
            }

        } catch (\Exception $e) {
            error_log('Loyalty find customer error: ' . $e->getMessage());
            return Response::json([
                'success' => false,
                'message' => 'فشل في البحث عن العميل'
            ], 500);
        }
    }

    /**
     * إضافة نقاط للعميل
     */
    public function addPoints(Request $request): Response
    {
        if (!Auth::hasPermission('loyalty.edit')) {
            return Response::error('Unauthorized access', 403);
        }

        $validator = new Validator();
        $rules = [
            'customer_id' => 'required|integer',
            'points' => 'required|integer|min:1',
            'reason' => 'required|string|min:3',
            'order_id' => 'integer'
        ];

        if (!$validator->validate($request->all(), $rules)) {
            return Response::json([
                'success' => false,
                'errors' => $validator->getErrors()
            ], 422);
        }

        try {
            $data = $request->all();
            $result = $this->loyaltyModel->addPoints(
                $data['customer_id'],
                $data['points'],
                $data['reason'],
                $data['order_id'] ?? null
            );

            if ($result) {
                Session::flash('success', 'تم إضافة النقاط بنجاح');
                
                return Response::json([
                    'success' => true,
                    'message' => 'تم إضافة النقاط بنجاح'
                ]);
            } else {
                return Response::json([
                    'success' => false,
                    'message' => 'فشل في إضافة النقاط'
                ], 500);
            }

        } catch (\Exception $e) {
            error_log('Loyalty add points error: ' . $e->getMessage());
            return Response::json([
                'success' => false,
                'message' => 'فشل في إضافة النقاط'
            ], 500);
        }
    }

    /**
     * استبدال نقاط
     */
    public function redeemPoints(Request $request): Response
    {
        if (!Auth::hasPermission('loyalty.edit')) {
            return Response::error('Unauthorized access', 403);
        }

        $validator = new Validator();
        $rules = [
            'customer_id' => 'required|integer',
            'points' => 'required|integer|min:1',
            'reason' => 'required|string|min:3',
            'order_id' => 'integer'
        ];

        if (!$validator->validate($request->all(), $rules)) {
            return Response::json([
                'success' => false,
                'errors' => $validator->getErrors()
            ], 422);
        }

        try {
            $data = $request->all();
            $result = $this->loyaltyModel->redeemPoints(
                $data['customer_id'],
                $data['points'],
                $data['reason'],
                $data['order_id'] ?? null
            );

            if ($result) {
                Session::flash('success', 'تم استبدال النقاط بنجاح');
                
                return Response::json([
                    'success' => true,
                    'message' => 'تم استبدال النقاط بنجاح'
                ]);
            } else {
                return Response::json([
                    'success' => false,
                    'message' => 'فشل في استبدال النقاط'
                ], 500);
            }

        } catch (\Exception $e) {
            error_log('Loyalty redeem points error: ' . $e->getMessage());
            return Response::json([
                'success' => false,
                'message' => 'فشل في استبدال النقاط'
            ], 500);
        }
    }

    /**
     * عرض المكافآت المتاحة
     */
    public function rewards(Request $request): Response
    {
        if (!Auth::hasPermission('loyalty.view')) {
            return Response::error('Unauthorized access', 403);
        }

        try {
            $rewards = $this->loyaltyModel->getAvailableRewards();

            return Response::view('loyalty.rewards', [
                'rewards' => $rewards
            ]);

        } catch (\Exception $e) {
            error_log('Loyalty rewards error: ' . $e->getMessage());
            return Response::error('فشل في تحميل المكافآت', 500);
        }
    }

    /**
     * استبدال مكافأة
     */
    public function redeemReward(Request $request): Response
    {
        if (!Auth::hasPermission('loyalty.edit')) {
            return Response::error('Unauthorized access', 403);
        }

        $validator = new Validator();
        $rules = [
            'customer_id' => 'required|integer',
            'reward_id' => 'required|integer'
        ];

        if (!$validator->validate($request->all(), $rules)) {
            return Response::json([
                'success' => false,
                'errors' => $validator->getErrors()
            ], 422);
        }

        try {
            $data = $request->all();
            $result = $this->loyaltyModel->redeemReward(
                $data['customer_id'],
                $data['reward_id']
            );

            return Response::json($result);

        } catch (\Exception $e) {
            error_log('Loyalty redeem reward error: ' . $e->getMessage());
            return Response::json([
                'success' => false,
                'message' => 'فشل في استبدال المكافأة'
            ], 500);
        }
    }

    /**
     * معالجة طلب جديد وتحديث بيانات الولاء
     */
    public function processOrder(Request $request): Response
    {
        $validator = new Validator();
        $rules = [
            'customer_id' => 'required|integer',
            'order_total' => 'required|numeric|min:0',
            'order_id' => 'required|integer'
        ];

        if (!$validator->validate($request->all(), $rules)) {
            return Response::json([
                'success' => false,
                'errors' => $validator->getErrors()
            ], 422);
        }

        try {
            $data = $request->all();
            $result = $this->loyaltyModel->processOrder(
                $data['customer_id'],
                $data['order_total'],
                $data['order_id']
            );

            return Response::json($result);

        } catch (\Exception $e) {
            error_log('Loyalty process order error: ' . $e->getMessage());
            return Response::json([
                'success' => false,
                'message' => 'فشل في معالجة طلب الولاء'
            ], 500);
        }
    }

    /**
     * إحصائيات برنامج الولاء
     */
    public function statistics(Request $request): Response
    {
        if (!Auth::hasPermission('loyalty.view')) {
            return Response::error('Unauthorized access', 403);
        }

        try {
            $dateFrom = $request->input('date_from');
            $dateTo = $request->input('date_to');
            
            $statistics = $this->loyaltyModel->getStatistics();
            $report = $this->loyaltyModel->getReport($dateFrom, $dateTo);
            $topCustomers = $this->loyaltyModel->getTopCustomers('month', 10);

            return Response::view('loyalty.statistics', [
                'statistics' => $statistics,
                'report' => $report,
                'top_customers' => $topCustomers
            ]);

        } catch (\Exception $e) {
            error_log('Loyalty statistics error: ' . $e->getMessage());
            return Response::error('فشل في تحميل الإحصائيات', 500);
        }
    }

    /**
     * أفضل العملاء
     */
    public function topCustomers(Request $request): Response
    {
        if (!Auth::hasPermission('loyalty.view')) {
            return Response::error('Unauthorized access', 403);
        }

        try {
            $period = $request->input('period', 'month');
            $limit = min(50, max(5, (int) $request->input('limit', 10)));
            
            $topCustomers = $this->loyaltyModel->getTopCustomers($period, $limit);

            return Response::view('loyalty.top-customers', [
                'top_customers' => $topCustomers,
                'period' => $period,
                'limit' => $limit
            ]);

        } catch (\Exception $e) {
            error_log('Loyalty top customers error: ' . $e->getMessage());
            return Response::error('فشل في تحميل أفضل العملاء', 500);
        }
    }

    /**
     * فحص النقاط القريبة من الانتهاء
     */
    public function expiringPoints(Request $request): Response
    {
        if (!Auth::hasPermission('loyalty.manage')) {
            return Response::error('Unauthorized access', 403);
        }

        try {
            $expiringPoints = $this->loyaltyModel->checkExpiringPoints();

            return Response::view('loyalty.expiring-points', [
                'expiring_points' => $expiringPoints
            ]);

        } catch (\Exception $e) {
            error_log('Loyalty expiring points error: ' . $e->getMessage());
            return Response::error('فشل في فحص النقاط المنتهية الصلاحية', 500);
        }
    }

    /**
     * تجديد نقاط منتهية الصلاحية
     */
    public function extendPoints(Request $request): Response
    {
        if (!Auth::hasPermission('loyalty.edit')) {
            return Response::error('Unauthorized access', 403);
        }

        $validator = new Validator();
        $rules = [
            'customer_id' => 'required|integer',
            'points' => 'required|integer|min:1',
            'days' => 'required|integer|min:1'
        ];

        if (!$validator->validate($request->all(), $rules)) {
            return Response::json([
                'success' => false,
                'errors' => $validator->getErrors()
            ], 422);
        }

        try {
            $data = $request->all();
            $extended = $this->loyaltyModel->extendPoints(
                $data['customer_id'],
                $data['points'],
                $data['days']
            );

            if ($extended) {
                Session::flash('success', 'تم تجديد النقاط بنجاح');
                
                return Response::json([
                    'success' => true,
                    'message' => 'تم تجديد النقاط بنجاح'
                ]);
            } else {
                return Response::json([
                    'success' => false,
                    'message' => 'فشل في تجديد النقاط'
                ], 500);
            }

        } catch (\Exception $e) {
            error_log('Loyalty extend points error: ' . $e->getMessage());
            return Response::json([
                'success' => false,
                'message' => 'فشل في تجديد النقاط'
            ], 500);
        }
    }

    /**
     * إنشاء حملة ولاء
     */
    public function createCampaign(Request $request): Response
    {
        if (!Auth::hasPermission('loyalty.manage')) {
            return Response::error('Unauthorized access', 403);
        }

        $validator = new Validator();
        $rules = [
            'name' => 'required|string|min:3|max:255',
            'type' => 'required|in:points_multiplier,bonus_points,tier_bonus',
            'valid_from' => 'required|date',
            'valid_until' => 'required|date|after:valid_from',
            'target_tier' => 'in:all,bronze,silver,gold,platinum'
        ];

        if (!$validator->validate($request->all(), $rules)) {
            return Response::json([
                'success' => false,
                'errors' => $validator->getErrors()
            ], 422);
        }

        try {
            $data = $request->all();
            $campaignId = $this->loyaltyModel->createCampaign($data);

            Session::flash('success', 'تم إنشاء حملة الولاء بنجاح');
            
            return Response::json([
                'success' => true,
                'message' => 'تم إنشاء حملة الولاء بنجاح',
                'campaign_id' => $campaignId
            ]);

        } catch (\Exception $e) {
            error_log('Loyalty create campaign error: ' . $e->getMessage());
            return Response::json([
                'success' => false,
                'message' => 'فشل في إنشاء حملة الولاء'
            ], 500);
        }
    }

    /**
     * تطبيق حملة ولاء على طلب
     */
    public function applyCampaign(Request $request): Response
    {
        $validator = new Validator();
        $rules = [
            'customer_id' => 'required|integer',
            'order_total' => 'required|numeric|min:0',
            'order_id' => 'required|integer'
        ];

        if (!$validator->validate($request->all(), $rules)) {
            return Response::json([
                'success' => false,
                'errors' => $validator->getErrors()
            ], 422);
        }

        try {
            $data = $request->all();
            $result = $this->loyaltyModel->applyCampaign(
                $data['customer_id'],
                $data['order_total'],
                $data['order_id']
            );

            return Response::json($result);

        } catch (\Exception $e) {
            error_log('Loyalty apply campaign error: ' . $e->getMessage());
            return Response::json([
                'success' => false,
                'message' => 'فشل في تطبيق حملة الولاء'
            ], 500);
        }
    }

    /**
     * حساب النقاط المستحقة من الطلب
     */
    public function calculatePoints(Request $request): Response
    {
        $validator = new Validator();
        $rules = [
            'order_total' => 'required|numeric|min:0',
            'customer_tier' => 'in:bronze,silver,gold,platinum'
        ];

        if (!$validator->validate($request->all(), $rules)) {
            return Response::json([
                'success' => false,
                'errors' => $validator->getErrors()
            ], 422);
        }

        try {
            $data = $request->all();
            $points = $this->loyaltyModel->calculatePointsFromOrder(
                $data['order_total'],
                $data['customer_tier'] ?? null
            );

            return Response::json([
                'success' => true,
                'points' => $points,
                'breakdown' => [
                    'base_rate' => 1,
                    'tier_multiplier' => $this->getTierMultiplier($data['customer_tier'] ?? 'bronze'),
                    'order_total' => $data['order_total']
                ]
            ]);

        } catch (\Exception $e) {
            error_log('Loyalty calculate points error: ' . $e->getMessage());
            return Response::json([
                'success' => false,
                'message' => 'فشل في حساب النقاط'
            ], 500);
        }
    }

    /**
     * الحصول على مضاعف المستوى
     */
    private function getTierMultiplier(string $tier): float
    {
        $multipliers = [
            'bronze' => 1,
            'silver' => 1.5,
            'gold' => 2,
            'platinum' => 3
        ];

        return $multipliers[$tier] ?? 1;
    }

    /**
     * تصدير بيانات الولاء
     */
    public function export(Request $request): Response
    {
        if (!Auth::hasPermission('loyalty.view')) {
            return Response::error('Unauthorized access', 403);
        }

        try {
            $customers = \App\Support\Facades\DB::table('loyalty_customers')
                ->select([
                    'loyalty_customers.*',
                    'users.first_name',
                    'users.last_name',
                    'users.email',
                    'users.phone'
                ])
                ->leftJoin('users', 'loyalty_customers.customer_id', '=', 'users.id')
                ->orderBy('loyalty_customers.last_activity', 'desc')
                ->get();

            // Here you would generate an Excel or CSV file
            // For now, return JSON data
            return Response::json([
                'success' => true,
                'data' => $customers,
                'total' => count($customers)
            ]);

        } catch (\Exception $e) {
            error_log('Loyalty export error: ' . $e->getMessage());
            return Response::json([
                'success' => false,
                'message' => 'فشل في تصدير بيانات الولاء'
            ], 500);
        }
    }
}