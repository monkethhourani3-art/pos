<?php

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Models\Promotion;
use App\Support\Facades\Auth;
use App\Support\Facades\Session;
use App\Validation\Validator;

class PromotionController
{
    private $promotionModel;

    public function __construct()
    {
        $this->promotionModel = new Promotion();
    }

    /**
     * عرض قائمة العروض
     */
    public function index(Request $request): Response
    {
        // Check permissions
        if (!Auth::hasPermission('promotions.manage')) {
            return Response::error('Unauthorized access', 403);
        }

        $filters = [
            'search' => $request->input('search'),
            'promotion_type' => $request->input('promotion_type'),
            'is_active' => $request->input('is_active'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to')
        ];

        $page = max(1, (int) $request->input('page', 1));
        $perPage = min(50, max(10, (int) $request->input('per_page', 20)));

        try {
            $result = $this->promotionModel->getAll($filters, $page, $perPage);
            $statistics = $this->promotionModel->getStatistics();

            return Response::view('promotions.index', [
                'promotions' => $result['data'],
                'pagination' => $result['pagination'],
                'filters' => $filters,
                'statistics' => $statistics
            ]);

        } catch (\Exception $e) {
            error_log('Promotion index error: ' . $e->getMessage());
            return Response::error('فشل في تحميل قائمة العروض', 500);
        }
    }

    /**
     * عرض نموذج إنشاء عرض جديد
     */
    public function create(Request $request): Response
    {
        if (!Auth::hasPermission('promotions.create')) {
            return Response::error('Unauthorized access', 403);
        }

        // Get products for selection
        $products = \App\Models\Product::all();

        return Response::view('promotions.create', [
            'products' => $products
        ]);
    }

    /**
     * حفظ عرض جديد
     */
    public function store(Request $request): Response
    {
        if (!Auth::hasPermission('promotions.create')) {
            return Response::error('Unauthorized access', 403);
        }

        $validator = new Validator();
        $rules = [
            'name' => 'required|min:3|max:255',
            'promotion_type' => 'required|in:bogo,bundle,time_based,conditional',
            'valid_from' => 'required|date',
            'valid_until' => 'required|date|after:valid_from',
            'discount_percentage' => 'numeric|min:0|max:100',
            'discount_amount' => 'numeric|min:0',
            'min_order_amount' => 'numeric|min:0',
            'max_order_amount' => 'numeric|min:0'
        ];

        if (!$validator->validate($request->all(), $rules)) {
            return Response::json([
                'success' => false,
                'errors' => $validator->getErrors()
            ], 422);
        }

        try {
            $data = $request->all();
            
            // Process rules based on promotion type
            $data['rules'] = $this->processPromotionRules($data);
            
            // Process applicable days and times
            $data['days_of_week'] = $request->input('days_of_week', []);
            $data['time_from'] = $request->input('time_from');
            $data['time_until'] = $request->input('time_until');
            
            // Process product restrictions
            $data['product_restrictions'] = $this->processProductRestrictions($data);

            $promotionId = $this->promotionModel->create($data);

            Session::flash('success', 'تم إنشاء العرض بنجاح');
            
            return Response::json([
                'success' => true,
                'message' => 'تم إنشاء العرض بنجاح',
                'redirect' => '/promotions/' . $promotionId
            ]);

        } catch (\Exception $e) {
            error_log('Promotion store error: ' . $e->getMessage());
            return Response::json([
                'success' => false,
                'message' => 'فشل في إنشاء العرض'
            ], 500);
        }
    }

    /**
     * عرض تفاصيل عرض
     */
    public function show(Request $request, int $id): Response
    {
        if (!Auth::hasPermission('promotions.view')) {
            return Response::error('Unauthorized access', 403);
        }

        try {
            $promotion = $this->promotionModel->getById($id);
            
            if (!$promotion) {
                return Response::error('العرض غير موجود', 404);
            }

            // Get usage statistics
            $usageStats = $this->getUsageStatistics($id);

            return Response::view('promotions.show', [
                'promotion' => $promotion,
                'usage_stats' => $usageStats
            ]);

        } catch (\Exception $e) {
            error_log('Promotion show error: ' . $e->getMessage());
            return Response::error('فشل في تحميل تفاصيل العرض', 500);
        }
    }

    /**
     * عرض نموذج تعديل عرض
     */
    public function edit(Request $request, int $id): Response
    {
        if (!Auth::hasPermission('promotions.edit')) {
            return Response::error('Unauthorized access', 403);
        }

        try {
            $promotion = $this->promotionModel->getById($id);
            
            if (!$promotion) {
                return Response::error('العرض غير موجود', 404);
            }

            // Get products for selection
            $products = \App\Models\Product::all();

            return Response::view('promotions.edit', [
                'promotion' => $promotion,
                'products' => $products
            ]);

        } catch (\Exception $e) {
            error_log('Promotion edit error: ' . $e->getMessage());
            return Response::error('فشل في تحميل نموذج التعديل', 500);
        }
    }

    /**
     * تحديث عرض
     */
    public function update(Request $request, int $id): Response
    {
        if (!Auth::hasPermission('promotions.edit')) {
            return Response::error('Unauthorized access', 403);
        }

        $validator = new Validator();
        $rules = [
            'name' => 'required|min:3|max:255',
            'promotion_type' => 'required|in:bogo,bundle,time_based,conditional',
            'valid_from' => 'required|date',
            'valid_until' => 'required|date|after:valid_from',
            'discount_percentage' => 'numeric|min:0|max:100',
            'discount_amount' => 'numeric|min:0',
            'min_order_amount' => 'numeric|min:0',
            'max_order_amount' => 'numeric|min:0'
        ];

        if (!$validator->validate($request->all(), $rules)) {
            return Response::json([
                'success' => false,
                'errors' => $validator->getErrors()
            ], 422);
        }

        try {
            $data = $request->all();
            
            // Process rules based on promotion type
            $data['rules'] = $this->processPromotionRules($data);
            
            // Process applicable days and times
            $data['days_of_week'] = $request->input('days_of_week', []);
            $data['time_from'] = $request->input('time_from');
            $data['time_until'] = $request->input('time_until');
            
            // Process product restrictions
            $data['product_restrictions'] = $this->processProductRestrictions($data);

            $updated = $this->promotionModel->update($id, $data);

            if ($updated) {
                Session::flash('success', 'تم تحديث العرض بنجاح');
                
                return Response::json([
                    'success' => true,
                    'message' => 'تم تحديث العرض بنجاح',
                    'redirect' => '/promotions/' . $id
                ]);
            } else {
                return Response::json([
                    'success' => false,
                    'message' => 'فشل في تحديث العرض'
                ], 500);
            }

        } catch (\Exception $e) {
            error_log('Promotion update error: ' . $e->getMessage());
            return Response::json([
                'success' => false,
                'message' => 'فشل في تحديث العرض'
            ], 500);
        }
    }

    /**
     * حذف عرض
     */
    public function destroy(Request $request, int $id): Response
    {
        if (!Auth::hasPermission('promotions.delete')) {
            return Response::error('Unauthorized access', 403);
        }

        try {
            $promotion = $this->promotionModel->getById($id);
            
            if (!$promotion) {
                return Response::json([
                    'success' => false,
                    'message' => 'العرض غير موجود'
                ], 404);
            }

            // Check if promotion has been used
            if ($promotion->used_count > 0) {
                return Response::json([
                    'success' => false,
                    'message' => 'لا يمكن حذف عرض تم استخدامه من قبل'
                ], 422);
            }

            $deleted = $this->promotionModel->delete($id);

            if ($deleted) {
                Session::flash('success', 'تم حذف العرض بنجاح');
                
                return Response::json([
                    'success' => true,
                    'message' => 'تم حذف العرض بنجاح'
                ]);
            } else {
                return Response::json([
                    'success' => false,
                    'message' => 'فشل في حذف العرض'
                ], 500);
            }

        } catch (\Exception $e) {
            error_log('Promotion delete error: ' . $e->getMessage());
            return Response::json([
                'success' => false,
                'message' => 'فشل في حذف العرض'
            ], 500);
        }
    }

    /**
     * تبديل حالة النشاط
     */
    public function toggleStatus(Request $request, int $id): Response
    {
        if (!Auth::hasPermission('promotions.edit')) {
            return Response::error('Unauthorized access', 403);
        }

        try {
            $toggled = $this->promotionModel->toggleStatus($id);

            if ($toggled) {
                $promotion = $this->promotionModel->getById($id);
                $status = $promotion->is_active ? 'مفعل' : 'معطل';
                
                Session::flash('success', "تم {$status} العرض بنجاح");
                
                return Response::json([
                    'success' => true,
                    'message' => "تم {$status} العرض بنجاح",
                    'is_active' => $promotion->is_active
                ]);
            } else {
                return Response::json([
                    'success' => false,
                    'message' => 'فشل في تغيير حالة العرض'
                ], 500);
            }

        } catch (\Exception $e) {
            error_log('Promotion toggle status error: ' . $e->getMessage());
            return Response::json([
                'success' => false,
                'message' => 'فشل في تغيير حالة العرض'
            ], 500);
        }
    }

    /**
     * تحديث أولوية العرض
     */
    public function updatePriority(Request $request, int $id): Response
    {
        if (!Auth::hasPermission('promotions.edit')) {
            return Response::error('Unauthorized access', 403);
        }

        $validator = new Validator();
        $rules = [
            'priority' => 'required|integer|min:0|max:100'
        ];

        if (!$validator->validate($request->all(), $rules)) {
            return Response::json([
                'success' => false,
                'errors' => $validator->getErrors()
            ], 422);
        }

        try {
            $priority = (int) $request->input('priority');
            $updated = $this->promotionModel->updatePriority($id, $priority);

            if ($updated) {
                return Response::json([
                    'success' => true,
                    'message' => 'تم تحديث أولوية العرض بنجاح',
                    'priority' => $priority
                ]);
            } else {
                return Response::json([
                    'success' => false,
                    'message' => 'فشل في تحديث أولوية العرض'
                ], 500);
            }

        } catch (\Exception $e) {
            error_log('Promotion update priority error: ' . $e->getMessage());
            return Response::json([
                'success' => false,
                'message' => 'فشل في تحديث أولوية العرض'
            ], 500);
        }
    }

    /**
     * التحقق من العروض المتاحة وتطبيقها
     */
    public function validate(Request $request): Response
    {
        $validator = new Validator();
        $rules = [
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
            
            $availablePromotions = $this->promotionModel->validateAndApply(
                $orderItems,
                $data['order_total'],
                null // Customer info if available
            );

            return Response::json([
                'success' => true,
                'promotions' => $availablePromotions
            ]);

        } catch (\Exception $e) {
            error_log('Promotion validation error: ' . $e->getMessage());
            return Response::json([
                'success' => false,
                'message' => 'فشل في التحقق من العروض'
            ], 500);
        }
    }

    /**
     * الحصول على العروض النشطة
     */
    public function getActive(Request $request): Response
    {
        try {
            $activePromotions = $this->promotionModel->getActive();

            return Response::json([
                'success' => true,
                'promotions' => $activePromotions
            ]);

        } catch (\Exception $e) {
            error_log('Promotion get active error: ' . $e->getMessage());
            return Response::json([
                'success' => false,
                'message' => 'فشل في جلب العروض النشطة'
            ], 500);
        }
    }

    /**
     * نسخ عرض
     */
    public function duplicate(Request $request, int $id): Response
    {
        if (!Auth::hasPermission('promotions.create')) {
            return Response::error('Unauthorized access', 403);
        }

        $validator = new Validator();
        $rules = [
            'new_name' => 'required|min:3|max:255'
        ];

        if (!$validator->validate($request->all(), $rules)) {
            return Response::json([
                'success' => false,
                'errors' => $validator->getErrors()
            ], 422);
        }

        try {
            $newName = $request->input('new_name');
            $newId = $this->promotionModel->duplicate($id, $newName);

            if ($newId) {
                Session::flash('success', 'تم نسخ العرض بنجاح');
                
                return Response::json([
                    'success' => true,
                    'message' => 'تم نسخ العرض بنجاح',
                    'redirect' => '/promotions/' . $newId . '/edit'
                ]);
            } else {
                return Response::json([
                    'success' => false,
                    'message' => 'فشل في نسخ العرض'
                ], 500);
            }

        } catch (\Exception $e) {
            error_log('Promotion duplicate error: ' . $e->getMessage());
            return Response::json([
                'success' => false,
                'message' => 'فشل في نسخ العرض'
            ], 500);
        }
    }

    /**
     * تسجيل استخدام العرض
     */
    public function recordUsage(Request $request, int $id): Response
    {
        try {
            $orderId = $request->input('order_id');
            $recorded = $this->promotionModel->recordUsage($id, $orderId);

            if ($recorded) {
                return Response::json([
                    'success' => true,
                    'message' => 'تم تسجيل استخدام العرض بنجاح'
                ]);
            } else {
                return Response::json([
                    'success' => false,
                    'message' => 'فشل في تسجيل استخدام العرض'
                ], 500);
            }

        } catch (\Exception $e) {
            error_log('Promotion record usage error: ' . $e->getMessage());
            return Response::json([
                'success' => false,
                'message' => 'فشل في تسجيل استخدام العرض'
            ], 500);
        }
    }

    /**
     * إحصائيات الاستخدام
     */
    private function getUsageStatistics(int $promotionId): array
    {
        // This would typically query usage logs and generate detailed statistics
        // For now, return basic stats from the promotion record
        $promotion = $this->promotionModel->getById($promotionId);
        
        return [
            'total_usage' => $promotion->used_count ?? 0,
            'usage_rate' => $promotion->usage_limit ? 
                (($promotion->used_count / $promotion->usage_limit) * 100) : 0,
            'usage_per_day' => [], // Would calculate from logs
            'revenue_impact' => 0 // Would calculate from order data
        ];
    }

    /**
     * معالجة قواعد العرض حسب النوع
     */
    private function processPromotionRules(array $data): array
    {
        $rules = [];
        
        switch ($data['promotion_type']) {
            case 'bogo':
                $rules = [
                    'required_quantity' => (int) ($data['bogo_required_quantity'] ?? 1),
                    'free_quantity' => (int) ($data['bogo_free_quantity'] ?? 1)
                ];
                break;
                
            case 'bundle':
                $rules = [
                    'bundle_items' => json_decode($data['bundle_items'] ?? '[]', true),
                    'bundle_price' => (float) ($data['bundle_price'] ?? 0)
                ];
                break;
                
            case 'time_based':
                $rules = [
                    'time_restrictions' => [
                        'days_of_week' => $data['days_of_week'] ?? [],
                        'time_from' => $data['time_from'] ?? null,
                        'time_until' => $data['time_until'] ?? null
                    ]
                ];
                break;
                
            case 'conditional':
                $rules = [
                    'conditions' => json_decode($data['conditions'] ?? '[]', true),
                    'discount' => [
                        'type' => $data['conditional_discount_type'] ?? 'percentage',
                        'value' => (float) ($data['conditional_discount_value'] ?? 0)
                    ]
                ];
                break;
        }
        
        return $rules;
    }

    /**
     * معالجة قيود المنتجات
     */
    private function processProductRestrictions(array $data): array
    {
        $restrictions = [];
        
        if (!empty($data['required_products'])) {
            $restrictions['required_products'] = array_map('intval', $data['required_products']);
        }
        
        if (!empty($data['excluded_products'])) {
            $restrictions['excluded_products'] = array_map('intval', $data['excluded_products']);
        }
        
        if (!empty($data['required_categories'])) {
            $restrictions['required_categories'] = array_map('intval', $data['required_categories']);
        }
        
        if (!empty($data['excluded_categories'])) {
            $restrictions['excluded_categories'] = array_map('intval', $data['excluded_categories']);
        }
        
        return $restrictions;
    }

    /**
     * تصدير العروض
     */
    public function export(Request $request): Response
    {
        if (!Auth::hasPermission('promotions.view')) {
            return Response::error('Unauthorized access', 403);
        }

        try {
            $filters = [
                'search' => $request->input('search'),
                'promotion_type' => $request->input('promotion_type'),
                'is_active' => $request->input('is_active'),
                'date_from' => $request->input('date_from'),
                'date_to' => $request->input('date_to')
            ];

            $result = $this->promotionModel->getAll($filters, 1, 10000); // Get all for export

            // Here you would generate an Excel or CSV file
            // For now, return JSON data
            return Response::json([
                'success' => true,
                'data' => $result['data'],
                'total' => $result['pagination']['total']
            ]);

        } catch (\Exception $e) {
            error_log('Promotion export error: ' . $e->getMessage());
            return Response::json([
                'success' => false,
                'message' => 'فشل في تصدير العروض'
            ], 500);
        }
    }

    /**
     * البحث السريع في العروض
     */
    public function search(Request $request): Response
    {
        $query = $request->input('q');
        
        if (strlen($query) < 2) {
            return Response::json(['results' => []]);
        }

        try {
            $promotions = \App\Support\Facades\DB::table('promotions')
                ->where('is_active', true)
                ->where('name', 'like', '%' . $query . '%')
                ->where('valid_from', '<=', now())
                ->where('valid_until', '>=', now())
                ->select(['id', 'name', 'promotion_type', 'priority'])
                ->limit(10)
                ->get();

            return Response::json([
                'success' => true,
                'results' => $promotions
            ]);

        } catch (\Exception $e) {
            error_log('Promotion search error: ' . $e->getMessage());
            return Response::json([
                'success' => false,
                'message' => 'فشل في البحث'
            ], 500);
        }
    }
}