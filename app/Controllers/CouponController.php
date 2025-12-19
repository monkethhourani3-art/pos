<?php

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Models\Coupon;
use App\Support\Facades\Auth;
use App\Support\Facades\Session;
use App\Validation\Validator;

class CouponController
{
    private $couponModel;

    public function __construct()
    {
        $this->couponModel = new Coupon();
    }

    /**
     * عرض قائمة الكوبونات
     */
    public function index(Request $request): Response
    {
        // Check permissions
        if (!Auth::hasPermission('coupons.manage')) {
            return Response::error('Unauthorized access', 403);
        }

        $filters = [
            'search' => $request->input('search'),
            'discount_type' => $request->input('discount_type'),
            'is_active' => $request->input('is_active'),
            'is_public' => $request->input('is_public'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to')
        ];

        $page = max(1, (int) $request->input('page', 1));
        $perPage = min(50, max(10, (int) $request->input('per_page', 20)));

        try {
            $result = $this->couponModel->getAll($filters, $page, $perPage);
            $statistics = $this->couponModel->getStatistics();

            return Response::view('coupons.index', [
                'coupons' => $result['data'],
                'pagination' => $result['pagination'],
                'filters' => $filters,
                'statistics' => $statistics
            ]);

        } catch (\Exception $e) {
            error_log('Coupon index error: ' . $e->getMessage());
            return Response::error('فشل في تحميل قائمة الكوبونات', 500);
        }
    }

    /**
     * عرض نموذج إنشاء كوبون جديد
     */
    public function create(Request $request): Response
    {
        if (!Auth::hasPermission('coupons.create')) {
            return Response::error('Unauthorized access', 403);
        }

        // Get products and categories for selection
        $products = \App\Models\Product::all();
        $categories = \App\Models\Category::all();

        return Response::view('coupons.create', [
            'products' => $products,
            'categories' => $categories
        ]);
    }

    /**
     * حفظ كوبون جديد
     */
    public function store(Request $request): Response
    {
        if (!Auth::hasPermission('coupons.create')) {
            return Response::error('Unauthorized access', 403);
        }

        $validator = new Validator();
        $rules = [
            'name' => 'required|min:3|max:255',
            'code' => 'string|min:3|max:50',
            'discount_type' => 'required|in:percentage,fixed_amount,free_shipping',
            'discount_value' => 'required|numeric|min:0',
            'valid_from' => 'required|date',
            'valid_until' => 'required|date|after:valid_from',
            'minimum_order_amount' => 'numeric|min:0',
            'maximum_discount_amount' => 'numeric|min:0',
            'usage_limit' => 'integer|min:1',
            'usage_limit_per_customer' => 'integer|min:1'
        ];

        if (!$validator->validate($request->all(), $rules)) {
            return Response::json([
                'success' => false,
                'errors' => $validator->getErrors()
            ], 422);
        }

        try {
            $data = $request->all();
            
            // Generate code if not provided
            if (empty($data['code'])) {
                $data['code'] = $this->generateUniqueCode();
            } else {
                // Validate code uniqueness
                if (!$this->couponModel->isCodeUnique($data['code'])) {
                    return Response::json([
                        'success' => false,
                        'message' => 'كود الكوبون مستخدم بالفعل'
                    ], 422);
                }
            }

            // Process applicable products and categories
            $data['applicable_products'] = $request->input('applicable_products', []);
            $data['applicable_categories'] = $request->input('applicable_categories', []);
            $data['excluded_products'] = $request->input('excluded_products', []);

            $couponId = $this->couponModel->create($data);

            Session::flash('success', 'تم إنشاء الكوبون بنجاح');
            
            return Response::json([
                'success' => true,
                'message' => 'تم إنشاء الكوبون بنجاح',
                'redirect' => '/coupons/' . $couponId
            ]);

        } catch (\Exception $e) {
            error_log('Coupon store error: ' . $e->getMessage());
            return Response::json([
                'success' => false,
                'message' => 'فشل في إنشاء الكوبون'
            ], 500);
        }
    }

    /**
     * عرض تفاصيل كوبون
     */
    public function show(Request $request, int $id): Response
    {
        if (!Auth::hasPermission('coupons.view')) {
            return Response::error('Unauthorized access', 403);
        }

        try {
            $coupon = $this->couponModel->getById($id);
            
            if (!$coupon) {
                return Response::error('الكوبون غير موجود', 404);
            }

            // Get usage statistics
            $usageStats = $this->getUsageStatistics($id);

            return Response::view('coupons.show', [
                'coupon' => $coupon,
                'usage_stats' => $usageStats
            ]);

        } catch (\Exception $e) {
            error_log('Coupon show error: ' . $e->getMessage());
            return Response::error('فشل في تحميل تفاصيل الكوبون', 500);
        }
    }

    /**
     * عرض نموذج تعديل كوبون
     */
    public function edit(Request $request, int $id): Response
    {
        if (!Auth::hasPermission('coupons.edit')) {
            return Response::error('Unauthorized access', 403);
        }

        try {
            $coupon = $this->couponModel->getById($id);
            
            if (!$coupon) {
                return Response::error('الكوبون غير موجود', 404);
            }

            // Get products and categories
            $products = \App\Models\Product::all();
            $categories = \App\Models\Category::all();

            return Response::view('coupons.edit', [
                'coupon' => $coupon,
                'products' => $products,
                'categories' => $categories
            ]);

        } catch (\Exception $e) {
            error_log('Coupon edit error: ' . $e->getMessage());
            return Response::error('فشل في تحميل نموذج التعديل', 500);
        }
    }

    /**
     * تحديث كوبون
     */
    public function update(Request $request, int $id): Response
    {
        if (!Auth::hasPermission('coupons.edit')) {
            return Response::error('Unauthorized access', 403);
        }

        $validator = new Validator();
        $rules = [
            'name' => 'required|min:3|max:255',
            'code' => 'string|min:3|max:50',
            'discount_type' => 'required|in:percentage,fixed_amount,free_shipping',
            'discount_value' => 'required|numeric|min:0',
            'valid_from' => 'required|date',
            'valid_until' => 'required|date|after:valid_from',
            'minimum_order_amount' => 'numeric|min:0',
            'maximum_discount_amount' => 'numeric|min:0',
            'usage_limit' => 'integer|min:1',
            'usage_limit_per_customer' => 'integer|min:1'
        ];

        if (!$validator->validate($request->all(), $rules)) {
            return Response::json([
                'success' => false,
                'errors' => $validator->getErrors()
            ], 422);
        }

        try {
            $data = $request->all();
            
            // Check code uniqueness if changed
            if (isset($data['code']) && $data['code'] !== $this->couponModel->getById($id)->code) {
                if (!$this->couponModel->isCodeUnique($data['code'], $id)) {
                    return Response::json([
                        'success' => false,
                        'message' => 'كود الكوبون مستخدم بالفعل'
                    ], 422);
                }
            }

            // Process applicable products and categories
            $data['applicable_products'] = $request->input('applicable_products', []);
            $data['applicable_categories'] = $request->input('applicable_categories', []);
            $data['excluded_products'] = $request->input('excluded_products', []);

            $updated = $this->couponModel->update($id, $data);

            if ($updated) {
                Session::flash('success', 'تم تحديث الكوبون بنجاح');
                
                return Response::json([
                    'success' => true,
                    'message' => 'تم تحديث الكوبون بنجاح',
                    'redirect' => '/coupons/' . $id
                ]);
            } else {
                return Response::json([
                    'success' => false,
                    'message' => 'فشل في تحديث الكوبون'
                ], 500);
            }

        } catch (\Exception $e) {
            error_log('Coupon update error: ' . $e->getMessage());
            return Response::json([
                'success' => false,
                'message' => 'فشل في تحديث الكوبون'
            ], 500);
        }
    }

    /**
     * حذف كوبون
     */
    public function destroy(Request $request, int $id): Response
    {
        if (!Auth::hasPermission('coupons.delete')) {
            return Response::error('Unauthorized access', 403);
        }

        try {
            $coupon = $this->couponModel->getById($id);
            
            if (!$coupon) {
                return Response::json([
                    'success' => false,
                    'message' => 'الكوبون غير موجود'
                ], 404);
            }

            // Check if coupon has been used
            if ($coupon->used_count > 0) {
                return Response::json([
                    'success' => false,
                    'message' => 'لا يمكن حذف كوبون تم استخدامه من قبل'
                ], 422);
            }

            $deleted = $this->couponModel->delete($id);

            if ($deleted) {
                Session::flash('success', 'تم حذف الكوبون بنجاح');
                
                return Response::json([
                    'success' => true,
                    'message' => 'تم حذف الكوبون بنجاح'
                ]);
            } else {
                return Response::json([
                    'success' => false,
                    'message' => 'فشل في حذف الكوبون'
                ], 500);
            }

        } catch (\Exception $e) {
            error_log('Coupon delete error: ' . $e->getMessage());
            return Response::json([
                'success' => false,
                'message' => 'فشل في حذف الكوبون'
            ], 500);
        }
    }

    /**
     * تبديل حالة النشاط
     */
    public function toggleStatus(Request $request, int $id): Response
    {
        if (!Auth::hasPermission('coupons.edit')) {
            return Response::error('Unauthorized access', 403);
        }

        try {
            $toggled = $this->couponModel->toggleStatus($id);

            if ($toggled) {
                $coupon = $this->couponModel->getById($id);
                $status = $coupon->is_active ? 'مفعل' : 'معطل';
                
                Session::flash('success', "تم {$status} الكوبون بنجاح");
                
                return Response::json([
                    'success' => true,
                    'message' => "تم {$status} الكوبون بنجاح",
                    'is_active' => $coupon->is_active
                ]);
            } else {
                return Response::json([
                    'success' => false,
                    'message' => 'فشل في تغيير حالة الكوبون'
                ], 500);
            }

        } catch (\Exception $e) {
            error_log('Coupon toggle status error: ' . $e->getMessage());
            return Response::json([
                'success' => false,
                'message' => 'فشل في تغيير حالة الكوبون'
            ], 500);
        }
    }

    /**
     * التحقق من كود الكوبون وتطبيقه
     */
    public function validate(Request $request): Response
    {
        $validator = new Validator();
        $rules = [
            'code' => 'required|string|min:3',
            'order_items' => 'required|array',
            'order_total' => 'required|numeric|min:0'
        ];

        if (!$validator->validate($request->all(), $rules)) {
            return Response::json([
                'success' => false,
                'errors' => $validator->getErrors()
            ], 422);
        }

        try {
            $data = $request->all();
            $orderItems = json_decode($data['order_items'], true) ?: [];
            
            $result = $this->couponModel->validateAndApply(
                $data['code'],
                $orderItems,
                $data['order_total'],
                null // Customer info if available
            );

            return Response::json($result);

        } catch (\Exception $e) {
            error_log('Coupon validation error: ' . $e->getMessage());
            return Response::json([
                'success' => false,
                'message' => 'فشل في التحقق من الكوبون'
            ], 500);
        }
    }

    /**
     * الحصول على الكوبونات العامة المتاحة
     */
    public function getPublic(Request $request): Response
    {
        try {
            $publicCoupons = $this->couponModel->getPublicCoupons();

            return Response::json([
                'success' => true,
                'coupons' => $publicCoupons
            ]);

        } catch (\Exception $e) {
            error_log('Coupon get public error: ' . $e->getMessage());
            return Response::json([
                'success' => false,
                'message' => 'فشل في جلب الكوبونات العامة'
            ], 500);
        }
    }

    /**
     * تسجيل استخدام الكوبون
     */
    public function recordUsage(Request $request, int $id): Response
    {
        try {
            $orderId = $request->input('order_id');
            $customerId = $request->input('customer_id');
            $recorded = $this->couponModel->recordUsage($id, $orderId, $customerId);

            if ($recorded) {
                return Response::json([
                    'success' => true,
                    'message' => 'تم تسجيل استخدام الكوبون بنجاح'
                ]);
            } else {
                return Response::json([
                    'success' => false,
                    'message' => 'فشل في تسجيل استخدام الكوبون'
                ], 500);
            }

        } catch (\Exception $e) {
            error_log('Coupon record usage error: ' . $e->getMessage());
            return Response::json([
                'success' => false,
                'message' => 'فشل في تسجيل استخدام الكوبون'
            ], 500);
        }
    }

    /**
     * نسخ كوبون
     */
    public function duplicate(Request $request, int $id): Response
    {
        if (!Authcoupons.create'))::hasPermission(' {
            return Response::error('Unauthorized access', 403);
        }

        $validator = new Validator();
        $rules = [
            'new_name' => 'required|min:3|max:255',
            'new_code' => 'string|min:3|max:50'
        ];

        if (!$validator->validate($request->all(), $rules)) {
            return Response::json([
                'success' => false,
                'errors' => $validator->getErrors()
            ], 422);
        }

        try {
            $newName = $request->input('new_name');
            $newCode = $request->input('new_code');
            
            // Validate new code uniqueness if provided
            if ($newCode && !$this->couponModel->isCodeUnique($newCode)) {
                return Response::json([
                    'success' => false,
                    'message' => 'كود الكوبون الجديد مستخدم بالفعل'
                ], 422);
            }

            $newId = $this->couponModel->duplicate($id, $newName, $newCode);

            if ($newId) {
                Session::flash('success', 'تم نسخ الكوبون بنجاح');
                
                return Response::json([
                    'success' => true,
                    'message' => 'تم نسخ الكوبون بنجاح',
                    'redirect' => '/coupons/' . $newId . '/edit'
                ]);
            } else {
                return Response::json([
                    'success' => false,
                    'message' => 'فشل في نسخ الكوبون'
                ], 500);
            }

        } catch (\Exception $e) {
            error_log('Coupon duplicate error: ' . $e->getMessage());
            return Response::json([
                'success' => false,
                'message' => 'فشل في نسخ الكوبون'
            ], 500);
        }
    }

    /**
     * تجديد كوبون منتهي الصلاحية
     */
    public function extend(Request $request, int $id): Response
    {
        if (!Auth::hasPermission('coupons.edit')) {
            return Response::error('Unauthorized access', 403);
        }

        $validator = new Validator();
        $rules = [
            'new_expiry_date' => 'required|date|after:now'
        ];

        if (!$validator->validate($request->all(), $rules)) {
            return Response::json([
                'success' => false,
                'errors' => $validator->getErrors()
            ], 422);
        }

        try {
            $extended = $this->couponModel->extend($id, $request->input('new_expiry_date'));

            if ($extended) {
                Session::flash('success', 'تم تجديد الكوبون بنجاح');
                
                return Response::json([
                    'success' => true,
                    'message' => 'تم تجديد الكوبون بنجاح'
                ]);
            } else {
                return Response::json([
                    'success' => false,
                    'message' => 'فشل في تجديد الكوبون'
                ], 500);
            }

        } catch (\Exception $e) {
            error_log('Coupon extend error: ' . $e->getMessage());
            return Response::json([
                'success' => false,
                'message' => 'فشل في تجديد الكوبون'
            ], 500);
        }
    }

    /**
     * تاريخ استخدام الكوبون
     */
    public function usageHistory(Request $request, int $id): Response
    {
        if (!Auth::hasPermission('coupons.view')) {
            return Response::error('Unauthorized access', 403);
        }

        try {
            $page = max(1, (int) $request->input('page', 1));
            $perPage = min(50, max(10, (int) $request->input('per_page', 20)));
            
            $result = $this->couponModel->getUsageHistory($id, $page, $perPage);

            return Response::view('coupons.usage-history', [
                'usage' => $result['data'],
                'pagination' => $result['pagination'],
                'coupon_id' => $id
            ]);

        } catch (\Exception $e) {
            error_log('Coupon usage history error: ' . $e->getMessage());
            return Response::error('فشل في تحميل تاريخ الاستخدام', 500);
        }
    }

    /**
     * إحصائيات مفصلة للاستخدام
     */
    public function detailedStats(Request $request, int $id): Response
    {
        if (!Auth::hasPermission('coupons.view')) {
            return Response::error('Unauthorized access', 403);
        }

        try {
            $stats = $this->couponModel->getDetailedUsageStats($id);

            return Response::json([
                'success' => true,
                'stats' => $stats
            ]);

        } catch (\Exception $e) {
            error_log('Coupon detailed stats error: ' . $e->getMessage());
            return Response::json([
                'success' => false,
                'message' => 'فشل في تحميل الإحصائيات المفصلة'
            ], 500);
        }
    }

    /**
     * إحصائيات الاستخدام
     */
    private function getUsageStatistics(int $couponId): array
    {
        // This would typically query usage logs and generate statistics
        // For now, return basic stats from the coupon record
        $coupon = $this->couponModel->getById($couponId);
        
        return [
            'total_usage' => $coupon->used_count ?? 0,
            'usage_rate' => $coupon->usage_limit ? 
                (($coupon->used_count / $coupon->usage_limit) * 100) : 0,
            'usage_per_day' => [], // Would calculate from logs
            'revenue_impact' => 0 // Would calculate from order data
        ];
    }

    /**
     * توليد كود كوبون فريد
     */
    private function generateUniqueCode(): string
    {
        do {
            $code = 'COUPON' . strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8));
        } while ($this->couponModel->isCodeUnique($code));

        return $code;
    }

    /**
     * تصدير الكوبونات
     */
    public function export(Request $request): Response
    {
        if (!Auth::hasPermission('coupons.view')) {
            return Response::error('Unauthorized access', 403);
        }

        try {
            $filters = [
                'search' => $request->input('search'),
                'discount_type' => $request->input('discount_type'),
                'is_active' => $request->input('is_active'),
                'is_public' => $request->input('is_public'),
                'date_from' => $request->input('date_from'),
                'date_to' => $request->input('date_to')
            ];

            $result = $this->couponModel->getAll($filters, 1, 10000); // Get all for export

            // Here you would generate an Excel or CSV file
            // For now, return JSON data
            return Response::json([
                'success' => true,
                'data' => $result['data'],
                'total' => $result['pagination']['total']
            ]);

        } catch (\Exception $e) {
            error_log('Coupon export error: ' . $e->getMessage());
            return Response::json([
                'success' => false,
                'message' => 'فشل في تصدير الكوبونات'
            ], 500);
        }
    }

    /**
     * البحث السريع في الكوبونات
     */
    public function search(Request $request): Response
    {
        $query = $request->input('q');
        
        if (strlen($query) < 2) {
            return Response::json(['results' => []]);
        }

        try {
            $coupons = \App\Support\Facades\DB::table('coupons')
                ->where('is_active', true)
                ->where(function($q) use ($query) {
                    $q->where('code', 'like', '%' . $query . '%')
                      ->orWhere('name', 'like', '%' . $query . '%');
                })
                ->where('valid_from', '<=', now())
                ->where('valid_until', '>=', now())
                ->select(['id', 'code', 'name', 'discount_type', 'discount_value'])
                ->limit(10)
                ->get();

            return Response::json([
                'success' => true,
                'results' => $coupons
            ]);

        } catch (\Exception $e) {
            error_log('Coupon search error: ' . $e->getMessage());
            return Response::json([
                'success' => false,
                'message' => 'فشل في البحث'
            ], 500);
        }
    }
}