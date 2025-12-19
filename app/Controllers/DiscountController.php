<?php

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Models\Discount;
use App\Support\Facades\Auth;
use App\Support\Facades\Session;
use App\Validation\Validator;

class DiscountController
{
    private $discountModel;

    public function __construct()
    {
        $this->discountModel = new Discount();
    }

    /**
     * عرض قائمة الخصومات
     */
    public function index(Request $request): Response
    {
        // Check permissions
        if (!Auth::hasPermission('discounts.manage')) {
            return Response::error('Unauthorized access', 403);
        }

        $filters = [
            'search' => $request->input('search'),
            'type' => $request->input('type'),
            'is_active' => $request->input('is_active'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to')
        ];

        $page = max(1, (int) $request->input('page', 1));
        $perPage = min(50, max(10, (int) $request->input('per_page', 20)));

        try {
            $result = $this->discountModel->getAll($filters, $page, $perPage);
            $statistics = $this->discountModel->getStatistics();

            return Response::view('discounts.index', [
                'discounts' => $result['data'],
                'pagination' => $result['pagination'],
                'filters' => $filters,
                'statistics' => $statistics
            ]);

        } catch (\Exception $e) {
            error_log('Discount index error: ' . $e->getMessage());
            return Response::error('فشل في تحميل قائمة الخصومات', 500);
        }
    }

    /**
     * عرض نموذج إنشاء خصم جديد
     */
    public function create(Request $request): Response
    {
        if (!Auth::hasPermission('discounts.create')) {
            return Response::error('Unauthorized access', 403);
        }

        // Get products and categories for selection
        $products = \App\Models\Product::all();
        $categories = \App\Models\Category::all();

        return Response::view('discounts.create', [
            'products' => $products,
            'categories' => $categories
        ]);
    }

    /**
     * حفظ خصم جديد
     */
    public function store(Request $request): Response
    {
        if (!Auth::hasPermission('discounts.create')) {
            return Response::error('Unauthorized access', 403);
        }

        $validator = new Validator();
        $rules = [
            'name' => 'required|min:3|max:255',
            'type' => 'required|in:percentage,fixed,buy_x_get_y',
            'value' => 'required|numeric|min:0',
            'valid_from' => 'required|date',
            'valid_until' => 'required|date|after:valid_from',
            'min_order_amount' => 'numeric|min:0',
            'max_discount_amount' => 'numeric|min:0',
            'usage_limit' => 'integer|min:1'
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
                if (!$this->discountModel->isCodeUnique($data['code'])) {
                    return Response::json([
                        'success' => false,
                        'message' => 'كود الخصم مستخدم بالفعل'
                    ], 422);
                }
            }

            // Handle applicable items
            $data['applicable_items'] = [];
            if ($data['applies_to'] === 'products' && !empty($data['selected_products'])) {
                $data['applicable_items'] = array_map('intval', $data['selected_products']);
            } elseif ($data['applies_to'] === 'categories' && !empty($data['selected_categories'])) {
                $data['applicable_items'] = array_map('intval', $data['selected_categories']);
            }

            $discountId = $this->discountModel->create($data);

            Session::flash('success', 'تم إنشاء الخصم بنجاح');
            
            return Response::json([
                'success' => true,
                'message' => 'تم إنشاء الخصم بنجاح',
                'redirect' => '/discounts/' . $discountId
            ]);

        } catch (\Exception $e) {
            error_log('Discount store error: ' . $e->getMessage());
            return Response::json([
                'success' => false,
                'message' => 'فشل في إنشاء الخصم'
            ], 500);
        }
    }

    /**
     * عرض تفاصيل خصم
     */
    public function show(Request $request, int $id): Response
    {
        if (!Auth::hasPermission('discounts.view')) {
            return Response::error('Unauthorized access', 403);
        }

        try {
            $discount = $this->discountModel->getById($id);
            
            if (!$discount) {
                return Response::error('الخصم غير موجود', 404);
            }

            // Get usage statistics
            $usageStats = $this->getUsageStatistics($id);

            return Response::view('discounts.show', [
                'discount' => $discount,
                'usage_stats' => $usageStats
            ]);

        } catch (\Exception $e) {
            error_log('Discount show error: ' . $e->getMessage());
            return Response::error('فشل في تحميل تفاصيل الخصم', 500);
        }
    }

    /**
     * عرض نموذج تعديل خصم
     */
    public function edit(Request $request, int $id): Response
    {
        if (!Auth::hasPermission('discounts.edit')) {
            return Response::error('Unauthorized access', 403);
        }

        try {
            $discount = $this->discountModel->getById($id);
            
            if (!$discount) {
                return Response::error('الخصم غير موجود', 404);
            }

            // Get products and categories
            $products = \App\Models\Product::all();
            $categories = \App\Models\Category::all();

            return Response::view('discounts.edit', [
                'discount' => $discount,
                'products' => $products,
                'categories' => $categories
            ]);

        } catch (\Exception $e) {
            error_log('Discount edit error: ' . $e->getMessage());
            return Response::error('فشل في تحميل نموذج التعديل', 500);
        }
    }

    /**
     * تحديث خصم
     */
    public function update(Request $request, int $id): Response
    {
        if (!Auth::hasPermission('discounts.edit')) {
            return Response::error('Unauthorized access', 403);
        }

        $validator = new Validator();
        $rules = [
            'name' => 'required|min:3|max:255',
            'type' => 'required|in:percentage,fixed,buy_x_get_y',
            'value' => 'required|numeric|min:0',
            'valid_from' => 'required|date',
            'valid_until' => 'required|date|after:valid_from',
            'min_order_amount' => 'numeric|min:0',
            'max_discount_amount' => 'numeric|min:0',
            'usage_limit' => 'integer|min:1'
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
            if (isset($data['code']) && $data['code'] !== $this->discountModel->getById($id)->code) {
                if (!$this->discountModel->isCodeUnique($data['code'], $id)) {
                    return Response::json([
                        'success' => false,
                        'message' => 'كود الخصم مستخدم بالفعل'
                    ], 422);
                }
            }

            // Handle applicable items
            $data['applicable_items'] = [];
            if ($data['applies_to'] === 'products' && !empty($data['selected_products'])) {
                $data['applicable_items'] = array_map('intval', $data['selected_products']);
            } elseif ($data['applies_to'] === 'categories' && !empty($data['selected_categories'])) {
                $data['applicable_items'] = array_map('intval', $data['selected_categories']);
            }

            $updated = $this->discountModel->update($id, $data);

            if ($updated) {
                Session::flash('success', 'تم تحديث الخصم بنجاح');
                
                return Response::json([
                    'success' => true,
                    'message' => 'تم تحديث الخصم بنجاح',
                    'redirect' => '/discounts/' . $id
                ]);
            } else {
                return Response::json([
                    'success' => false,
                    'message' => 'فشل في تحديث الخصم'
                ], 500);
            }

        } catch (\Exception $e) {
            error_log('Discount update error: ' . $e->getMessage());
            return Response::json([
                'success' => false,
                'message' => 'فشل في تحديث الخصم'
            ], 500);
        }
    }

    /**
     * حذف خصم
     */
    public function destroy(Request $request, int $id): Response
    {
        if (!Auth::hasPermission('discounts.delete')) {
            return Response::error('Unauthorized access', 403);
        }

        try {
            $discount = $this->discountModel->getById($id);
            
            if (!$discount) {
                return Response::json([
                    'success' => false,
                    'message' => 'الخصم غير موجود'
                ], 404);
            }

            // Check if discount has been used
            if ($discount->used_count > 0) {
                return Response::json([
                    'success' => false,
                    'message' => 'لا يمكن حذف خصم تم استخدامه من قبل'
                ], 422);
            }

            $deleted = $this->discountModel->delete($id);

            if ($deleted) {
                Session::flash('success', 'تم حذف الخصم بنجاح');
                
                return Response::json([
                    'success' => true,
                    'message' => 'تم حذف الخصم بنجاح'
                ]);
            } else {
                return Response::json([
                    'success' => false,
                    'message' => 'فشل في حذف الخصم'
                ], 500);
            }

        } catch (\Exception $e) {
            error_log('Discount delete error: ' . $e->getMessage());
            return Response::json([
                'success' => false,
                'message' => 'فشل في حذف الخصم'
            ], 500);
        }
    }

    /**
     * تبديل حالة النشاط
     */
    public function toggleStatus(Request $request, int $id): Response
    {
        if (!Auth::hasPermission('discounts.edit')) {
            return Response::error('Unauthorized access', 403);
        }

        try {
            $toggled = $this->discountModel->toggleStatus($id);

            if ($toggled) {
                $discount = $this->discountModel->getById($id);
                $status = $discount->is_active ? 'مفعل' : 'معطل';
                
                Session::flash('success', "تم {$status} الخصم بنجاح");
                
                return Response::json([
                    'success' => true,
                    'message' => "تم {$status} الخصم بنجاح",
                    'is_active' => $discount->is_active
                ]);
            } else {
                return Response::json([
                    'success' => false,
                    'message' => 'فشل في تغيير حالة الخصم'
                ], 500);
            }

        } catch (\Exception $e) {
            error_log('Discount toggle status error: ' . $e->getMessage());
            return Response::json([
                'success' => false,
                'message' => 'فشل في تغيير حالة الخصم'
            ], 500);
        }
    }

    /**
     * التحقق من كود الخصم وتطبيقه
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
            
            $result = $this->discountModel->validateAndApply(
                $data['code'],
                $orderItems,
                $data['order_total']
            );

            return Response::json($result);

        } catch (\Exception $e) {
            error_log('Discount validation error: ' . $e->getMessage());
            return Response::json([
                'success' => false,
                'message' => 'فشل في التحقق من الخصم'
            ], 500);
        }
    }

    /**
     * الحصول على الخصومات المتاحة للطلب
     */
    public function getAvailable(Request $request): Response
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
            
            $availableDiscounts = $this->discountModel->getAvailableForOrder(
                $orderItems,
                $data['order_total']
            );

            return Response::json([
                'success' => true,
                'discounts' => $availableDiscounts
            ]);

        } catch (\Exception $e) {
            error_log('Discount get available error: ' . $e->getMessage());
            return Response::json([
                'success' => false,
                'message' => 'فشل في جلب الخصومات المتاحة'
            ], 500);
        }
    }

    /**
     * تجديد خصم منتهي الصلاحية
     */
    public function extend(Request $request, int $id): Response
    {
        if (!Auth::hasPermission('discounts.edit')) {
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
            $extended = $this->discountModel->extend($id, $request->input('new_expiry_date'));

            if ($extended) {
                Session::flash('success', 'تم تجديد الخصم بنجاح');
                
                return Response::json([
                    'success' => true,
                    'message' => 'تم تجديد الخصم بنجاح'
                ]);
            } else {
                return Response::json([
                    'success' => false,
                    'message' => 'فشل في تجديد الخصم'
                ], 500);
            }

        } catch (\Exception $e) {
            error_log('Discount extend error: ' . $e->getMessage());
            return Response::json([
                'success' => false,
                'message' => 'فشل في تجديد الخصم'
            ], 500);
        }
    }

    /**
     * إحصائيات الاستخدام
     */
    private function getUsageStatistics(int $discountId): array
    {
        // This would typically query usage logs and generate statistics
        // For now, return basic stats from the discount record
        $discount = $this->discountModel->getById($discountId);
        
        return [
            'total_usage' => $discount->used_count ?? 0,
            'usage_rate' => $discount->usage_limit ? 
                (($discount->used_count / $discount->usage_limit) * 100) : 0,
            'usage_per_day' => [], // Would calculate from logs
            'revenue_impact' => 0 // Would calculate from order data
        ];
    }

    /**
     * توليد كود خصم فريد
     */
    private function generateUniqueCode(): string
    {
        do {
            $code = 'DISC' . strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8));
        } while ($this->discountModel->isCodeUnique($code));

        return $code;
    }

    /**
     * تصدير الخصومات
     */
    public function export(Request $request): Response
    {
        if (!Auth::hasPermission('discounts.view')) {
            return Response::error('Unauthorized access', 403);
        }

        try {
            $filters = [
                'search' => $request->input('search'),
                'type' => $request->input('type'),
                'is_active' => $request->input('is_active'),
                'date_from' => $request->input('date_from'),
                'date_to' => $request->input('date_to')
            ];

            $result = $this->discountModel->getAll($filters, 1, 10000); // Get all for export

            // Here you would generate an Excel or CSV file
            // For now, return JSON data
            return Response::json([
                'success' => true,
                'data' => $result['data'],
                'total' => $result['pagination']['total']
            ]);

        } catch (\Exception $e) {
            error_log('Discount export error: ' . $e->getMessage());
            return Response::json([
                'success' => false,
                'message' => 'فشل في تصدير الخصومات'
            ], 500);
        }
    }

    /**
     * البحث السريع في الخصومات
     */
    public function search(Request $request): Response
    {
        $query = $request->input('q');
        
        if (strlen($query) < 2) {
            return Response::json(['results' => []]);
        }

        try {
            $discounts = \App\Support\Facades\DB::table('discounts')
                ->where('is_active', true)
                ->where(function($q) use ($query) {
                    $q->where('code', 'like', '%' . $query . '%')
                      ->orWhere('name', 'like', '%' . $query . '%');
                })
                ->where('valid_from', '<=', now())
                ->where('valid_until', '>=', now())
                ->select(['id', 'code', 'name', 'type', 'value'])
                ->limit(10)
                ->get();

            return Response::json([
                'success' => true,
                'results' => $discounts
            ]);

        } catch (\Exception $e) {
            error_log('Discount search error: ' . $e->getMessage());
            return Response::json([
                'success' => false,
                'message' => 'فشل في البحث'
            ], 500);
        }
    }
}