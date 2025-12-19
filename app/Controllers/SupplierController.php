<?php

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Models\Supplier;
use App\Support\Facades\Auth;
use App\Support\Facades\Session;
use App\Validation\Validator;

class SupplierController
{
    private $supplierModel;

    public function __construct()
    {
        $this->supplierModel = new Supplier();
    }

    /**
     * Display suppliers list
     */
    public function index(Request $request): Response
    {
        // Check permissions
        if (!Auth::hasPermission('suppliers.view')) {
            return Response::error('Unauthorized access', 403);
        }

        $page = $request->input('page', 1);
        $filters = [
            'search' => $request->input('search'),
            'active' => $request->input('active')
        ];

        $suppliers = $this->supplierModel->getAll($filters, $page, 20);
        $totalSuppliers = $this->supplierModel->getTotalCount($filters);
        $statistics = $this->supplierModel->getStatistics();

        $pagination = [
            'current_page' => $page,
            'total_pages' => ceil($totalSuppliers / 20),
            'total_items' => $totalSuppliers,
            'per_page' => 20
        ];

        return Response::view('suppliers.index', [
            'suppliers' => $suppliers,
            'pagination' => $pagination,
            'filters' => $filters,
            'statistics' => $statistics
        ]);
    }

    /**
     * Show create supplier form
     */
    public function create(Request $request): Response
    {
        // Check permissions
        if (!Auth::hasPermission('suppliers.create')) {
            return Response::error('Unauthorized access', 403);
        }

        return Response::view('suppliers.create');
    }

    /**
     * Store new supplier
     */
    public function store(Request $request): Response
    {
        // Check permissions
        if (!Auth::hasPermission('suppliers.create')) {
            return Response::error('Unauthorized access', 403);
        }

        $validator = new Validator();
        $data = $request->all();

        // Validation rules
        $validator->validate($data, [
            'name' => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'tax_number' => 'nullable|string|max:100',
            'payment_terms' => 'nullable|string|max:100',
            'credit_limit' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000'
        ]);

        if ($validator->fails()) {
            return Response::error($validator->firstError(), 422);
        }

        // Check email uniqueness
        if (!empty($data['email']) && !$this->supplierModel->isEmailUnique($data['email'])) {
            return Response::error('هذا البريد الإلكتروني مستخدم بالفعل', 422);
        }

        // Check phone uniqueness
        if (!empty($data['phone']) && !$this->supplierModel->isPhoneUnique($data['phone'])) {
            return Response::error('رقم الهاتف مستخدم بالفعل', 422);
        }

        try {
            $supplierId = $this->supplierModel->create($data);

            // Log activity
            $this->logActivity('supplier_created', "تم إنشاء مورد جديد: " . $data['name'], $supplierId);

            return Response::success('تم إنشاء المورد بنجاح', '/suppliers');

        } catch (\Exception $e) {
            error_log('Supplier creation error: ' . $e->getMessage());
            return Response::error('فشل في إنشاء المورد', 500);
        }
    }

    /**
     * Show supplier details
     */
    public function show(Request $request, int $id): Response
    {
        // Check permissions
        if (!Auth::hasPermission('suppliers.view')) {
            return Response::error('Unauthorized access', 403);
        }

        $supplier = $this->supplierModel->find($id);
        if (!$supplier) {
            return Response::error('المورد غير موجود', 404);
        }

        // Get supplier performance stats
        $performanceStats = $this->supplierModel->getPerformanceStats($id);
        
        // Get recent purchases
        $purchaseHistory = $this->supplierModel->getPurchaseHistory($id, 1, 10);

        return Response::view('suppliers.show', [
            'supplier' => $supplier,
            'performance_stats' => $performanceStats,
            'purchase_history' => $purchaseHistory
        ]);
    }

    /**
     * Show edit supplier form
     */
    public function edit(Request $request, int $id): Response
    {
        // Check permissions
        if (!Auth::hasPermission('suppliers.edit')) {
            return Response::error('Unauthorized access', 403);
        }

        $supplier = $this->supplierModel->find($id);
        if (!$supplier) {
            return Response::error('المورد غير موجود', 404);
        }

        return Response::view('suppliers.edit', [
            'supplier' => $supplier
        ]);
    }

    /**
     * Update supplier
     */
    public function update(Request $request, int $id): Response
    {
        // Check permissions
        if (!Auth::hasPermission('suppliers.edit')) {
            return Response::error('Unauthorized access', 403);
        }

        $validator = new Validator();
        $data = $request->all();

        // Validation rules
        $validator->validate($data, [
            'name' => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'tax_number' => 'nullable|string|max:100',
            'payment_terms' => 'nullable|string|max:100',
            'credit_limit' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
            'is_active' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return Response::error($validator->firstError(), 422);
        }

        // Check email uniqueness (exclude current supplier)
        if (!empty($data['email']) && !$this->supplierModel->isEmailUnique($data['email'], $id)) {
            return Response::error('هذا البريد الإلكتروني مستخدم بالفعل', 422);
        }

        // Check phone uniqueness (exclude current supplier)
        if (!empty($data['phone']) && !$this->supplierModel->isPhoneUnique($data['phone'], $id)) {
            return Response::error('رقم الهاتف مستخدم بالفعل', 422);
        }

        try {
            $result = $this->supplierModel->update($id, $data);

            if (!$result) {
                return Response::error('فشل في تحديث المورد', 422);
            }

            // Log activity
            $this->logActivity('supplier_updated', "تم تحديث المورد: " . $data['name'], $id);

            return Response::success('تم تحديث المورد بنجاح', '/suppliers');

        } catch (\Exception $e) {
            error_log('Supplier update error: ' . $e->getMessage());
            return Response::error('فشل في تحديث المورد', 500);
        }
    }

    /**
     * Delete supplier
     */
    public function destroy(Request $request, int $id): Response
    {
        // Check permissions
        if (!Auth::hasPermission('suppliers.delete')) {
            return Response::error('Unauthorized access', 403);
        }

        try {
            $result = $this->supplierModel->delete($id);

            if (!$result) {
                return Response::error('فشل في حذف المورد. قد يكون لديه أصناف مرتبطة به', 422);
            }

            // Log activity
            $this->logActivity('supplier_deleted', "تم حذف مورد بمعرف: " . $id, $id);

            return Response::success('تم حذف المورد بنجاح', '/suppliers');

        } catch (\Exception $e) {
            error_log('Supplier deletion error: ' . $e->getMessage());
            return Response::error('فشل في حذف المورد', 500);
        }
    }

    /**
     * Update supplier status
     */
    public function updateStatus(Request $request, int $id): Response
    {
        // Check permissions
        if (!Auth::hasPermission('suppliers.edit')) {
            return Response::error('Unauthorized access', 403);
        }

        $validator = new Validator();
        $data = $request->all();

        $validator->validate($data, [
            'is_active' => 'required|boolean'
        ]);

        if ($validator->fails()) {
            return Response::json(['error' => $validator->firstError()], 422);
        }

        try {
            $result = $this->supplierModel->updateStatus($id, $data['is_active']);

            if (!$result) {
                return Response::json(['error' => 'فشل في تحديث حالة المورد'], 422);
            }

            // Log activity
            $status = $data['is_active'] ? 'تفعيل' : 'إلغاء تفعيل';
            $this->logActivity('supplier_status_updated', 
                "تم {$status} المورد بمعرف: " . $id, $id);

            return Response::json([
                'success' => true,
                'message' => 'تم تحديث حالة المورد بنجاح'
            ]);

        } catch (\Exception $e) {
            error_log('Supplier status update error: ' . $e->getMessage());
            return Response::json(['error' => 'فشل في تحديث حالة المورد'], 500);
        }
    }

    /**
     * Search suppliers
     */
    public function search(Request $request): Response
    {
        // Check permissions
        if (!Auth::hasPermission('suppliers.view')) {
            return Response::error('Unauthorized access', 403);
        }

        $query = $request->input('q', '');
        
        if (empty($query)) {
            return Response::json(['suppliers' => []]);
        }

        try {
            $suppliers = $this->supplierModel->search($query, 20);
            return Response::json(['suppliers' => $suppliers]);
        } catch (\Exception $e) {
            error_log('Supplier search error: ' . $e->getMessage());
            return Response::json(['error' => 'فشل في البحث'], 500);
        }
    }

    /**
     * Get supplier performance statistics
     */
    public function performance(Request $request, int $id): Response
    {
        // Check permissions
        if (!Auth::hasPermission('suppliers.view')) {
            return Response::error('Unauthorized access', 403);
        }

        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        try {
            $stats = $this->supplierModel->getPerformanceStats($id, $dateFrom, $dateTo);
            return Response::json(['stats' => $stats]);
        } catch (\Exception $e) {
            error_log('Supplier performance stats error: ' . $e->getMessage());
            return Response::json(['error' => 'فشل في تحميل إحصائيات الأداء'], 500);
        }
    }

    /**
     * Get top suppliers
     */
    public function topSuppliers(Request $request): Response
    {
        // Check permissions
        if (!Auth::hasPermission('suppliers.view')) {
            return Response::error('Unauthorized access', 403);
        }

        $limit = $request->input('limit', 10);
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        try {
            $suppliers = $this->supplierModel->getTopSuppliers($limit, $dateFrom, $dateTo);
            return Response::json(['suppliers' => $suppliers]);
        } catch (\Exception $e) {
            error_log('Top suppliers error: ' . $e->getMessage());
            return Response::json(['error' => 'فشل في تحميل أفضل الموردين'], 500);
        }
    }

    /**
     * Export suppliers data
     */
    public function export(Request $request): Response
    {
        // Check permissions
        if (!Auth::hasPermission('suppliers.view')) {
            return Response::error('Unauthorized access', 403);
        }

        $format = $request->input('format', 'csv');
        $filters = [
            'search' => $request->input('search'),
            'active' => $request->input('active')
        ];

        try {
            $suppliers = $this->supplierModel->getAll($filters, 1, 10000); // Get all suppliers
            
            $filename = 'suppliers_export_' . date('Y-m-d_H-i-s');
            
            if ($format === 'csv') {
                return $this->exportToCsv($suppliers, $filename . '.csv');
            } else {
                return Response::error('تنسيق التصدير غير مدعوم', 400);
            }

        } catch (\Exception $e) {
            error_log('Suppliers export error: ' . $e->getMessage());
            return Response::error('فشل في تصدير البيانات', 500);
        }
    }

    /**
     * Log activity
     */
    private function logActivity(string $action, string $description, ?int $resourceId = null): void
    {
        // Implementation for activity logging
        // This would typically save to an activity log table
        error_log("Activity: {$action} - {$description} (Resource ID: {$resourceId})");
    }

    /**
     * Export to CSV
     */
    private function exportToCsv(array $suppliers, string $filename): Response
    {
        $content = "Name,Contact Person,Email,Phone,Address,City,Payment Terms,Credit Limit,Status,Item Count,Total Value\n";
        
        foreach ($suppliers as $supplier) {
            $content .= sprintf(
                '"%s","%s","%s","%s","%s","%s","%s","%s","%s","%s","%s"' . "\n",
                addslashes($supplier['name']),
                addslashes($supplier['contact_person'] ?? ''),
                addslashes($supplier['email'] ?? ''),
                addslashes($supplier['phone'] ?? ''),
                addslashes($supplier['address'] ?? ''),
                addslashes($supplier['city'] ?? ''),
                addslashes($supplier['payment_terms'] ?? ''),
                $supplier['credit_limit'] ?? 0,
                $supplier['is_active'] ? 'نشط' : 'غير نشط',
                $supplier['item_count'] ?? 0,
                $supplier['total_inventory_value'] ?? 0
            );
        }

        return Response::csv($content, $filename);
    }
}