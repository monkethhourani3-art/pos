<?php

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\InventoryItem;
use App\Support\Facades\Auth;
use App\Support\Facades\Session;
use App\Validation\Validator;

class PurchaseController
{
    private $purchaseModel;
    private $supplierModel;
    private $inventoryItemModel;

    public function __construct()
    {
        $this->purchaseModel = new Purchase();
        $this->supplierModel = new Supplier();
        $this->inventoryItemModel = new InventoryItem();
    }

    /**
     * Display purchases list
     */
    public function index(Request $request): Response
    {
        // Check permissions
        if (!Auth::hasPermission('purchases.view')) {
            return Response::error('Unauthorized access', 403);
        }

        $page = $request->input('page', 1);
        $filters = [
            'supplier_id' => $request->input('supplier_id'),
            'status' => $request->input('status'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'search' => $request->input('search')
        ];

        $purchases = $this->purchaseModel->getAll($filters, $page, 20);
        $totalPurchases = $this->purchaseModel->getTotalCount($filters);
        $statistics = $this->purchaseModel->getStatistics();
        
        // Get filter options
        $suppliers = $this->supplierModel->getActive();

        $pagination = [
            'current_page' => $page,
            'total_pages' => ceil($totalPurchases / 20),
            'total_items' => $totalPurchases,
            'per_page' => 20
        ];

        return Response::view('purchases.index', [
            'purchases' => $purchases,
            'pagination' => $pagination,
            'filters' => $filters,
            'suppliers' => $suppliers,
            'statistics' => $statistics
        ]);
    }

    /**
     * Show create purchase form
     */
    public function create(Request $request): Response
    {
        // Check permissions
        if (!Auth::hasPermission('purchases.create')) {
            return Response::error('Unauthorized access', 403);
        }

        $suppliers = $this->supplierModel->getActive();
        $inventoryItems = $this->inventoryItemModel->getAll([], 1, 100);

        return Response::view('purchases.create', [
            'suppliers' => $suppliers,
            'inventory_items' => $inventoryItems
        ]);
    }

    /**
     * Store new purchase
     */
    public function store(Request $request): Response
    {
        // Check permissions
        if (!Auth::hasPermission('purchases.create')) {
            return Response::error('Unauthorized access', 403);
        }

        $validator = new Validator();
        $data = $request->all();

        // Validation rules
        $validator->validate($data, [
            'supplier_id' => 'required|integer|min:1',
            'purchase_date' => 'required|date',
            'total_amount' => 'required|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000'
        ]);

        if ($validator->fails()) {
            return Response::error($validator->firstError(), 422);
        }

        // Validate purchase details
        if (empty($data['details']) || !is_array($data['details'])) {
            return Response::error('يرجى إضافة أصناف للشراء', 422);
        }

        // Validate each detail
        foreach ($data['details'] as $index => $detail) {
            if (empty($detail['item_id']) || empty($detail['quantity']) || empty($detail['unit_cost'])) {
                return Response::error("يرجى إكمال بيانات الصنف رقم " . ($index + 1), 422);
            }
        }

        // Add current user as creator
        $data['created_by'] = Auth::id();

        try {
            $purchaseId = $this->purchaseModel->create($data);

            // Log activity
            $this->logActivity('purchase_created', "تم إنشاء عملية شراء جديدة: " . $purchaseId, $purchaseId);

            return Response::success('تم إنشاء عملية الشراء بنجاح', '/purchases');

        } catch (\Exception $e) {
            error_log('Purchase creation error: ' . $e->getMessage());
            return Response::error('فشل في إنشاء عملية الشراء', 500);
        }
    }

    /**
     * Show purchase details
     */
    public function show(Request $request, int $id): Response
    {
        // Check permissions
        if (!Auth::hasPermission('purchases.view')) {
            return Response::error('Unauthorized access', 403);
        }

        $purchase = $this->purchaseModel->find($id);
        if (!$purchase) {
            return Response::error('عملية الشراء غير موجودة', 404);
        }

        return Response::view('purchases.show', [
            'purchase' => $purchase
        ]);
    }

    /**
     * Show edit purchase form
     */
    public function edit(Request $request, int $id): Response
    {
        // Check permissions
        if (!Auth::hasPermission('purchases.edit')) {
            return Response::error('Unauthorized access', 403);
        }

        $purchase = $this->purchaseModel->find($id);
        if (!$purchase) {
            return Response::error('عملية الشراء غير موجودة', 404);
        }

        // Check if purchase can be edited (only pending purchases)
        if ($purchase['status'] !== 'pending') {
            return Response::error('لا يمكن تعديل عملية شراء مكتملة أو ملغاة', 422);
        }

        $suppliers = $this->supplierModel->getActive();
        $inventoryItems = $this->inventoryItemModel->getAll([], 1, 100);

        return Response::view('purchases.edit', [
            'purchase' => $purchase,
            'suppliers' => $suppliers,
            'inventory_items' => $inventoryItems
        ]);
    }

    /**
     * Update purchase
     */
    public function update(Request $request, int $id): Response
    {
        // Check permissions
        if (!Auth::hasPermission('purchases.edit')) {
            return Response::error('Unauthorized access', 403);
        }

        $validator = new Validator();
        $data = $request->all();

        // Validation rules
        $validator->validate($data, [
            'supplier_id' => 'required|integer|min:1',
            'purchase_date' => 'required|date',
            'total_amount' => 'required|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
            'status' => 'required|in:pending,completed,cancelled'
        ]);

        if ($validator->fails()) {
            return Response::error($validator->firstError(), 422);
        }

        // Validate purchase details if provided
        if (isset($data['details'])) {
            if (empty($data['details']) || !is_array($data['details'])) {
                return Response::error('يرجى إضافة أصناف للشراء', 422);
            }

            foreach ($data['details'] as $index => $detail) {
                if (empty($detail['item_id']) || empty($detail['quantity']) || empty($detail['unit_cost'])) {
                    return Response::error("يرجى إكمال بيانات الصنف رقم " . ($index + 1), 422);
                }
            }
        }

        try {
            $result = $this->purchaseModel->update($id, $data);

            if (!$result) {
                return Response::error('فشل في تحديث عملية الشراء', 422);
            }

            // Log activity
            $this->logActivity('purchase_updated', "تم تحديث عملية شراء: " . $id, $id);

            return Response::success('تم تحديث عملية الشراء بنجاح', '/purchases');

        } catch (\Exception $e) {
            error_log('Purchase update error: ' . $e->getMessage());
            return Response::error('فشل في تحديث عملية الشراء', 500);
        }
    }

    /**
     * Update purchase status
     */
    public function updateStatus(Request $request, int $id): Response
    {
        // Check permissions
        if (!Auth::hasPermission('purchases.edit')) {
            return Response::error('Unauthorized access', 403);
        }

        $validator = new Validator();
        $data = $request->all();

        $validator->validate($data, [
            'status' => 'required|in:pending,completed,cancelled'
        ]);

        if ($validator->fails()) {
            return Response::json(['error' => $validator->firstError()], 422);
        }

        try {
            $result = $this->purchaseModel->updateStatus($id, $data['status']);

            if (!$result) {
                return Response::json(['error' => 'فشل في تحديث حالة الشراء'], 422);
            }

            // Log activity
            $statusTexts = [
                'pending' => 'تحديد كقيد الانتظار',
                'completed' => 'تحديد كمكتمل',
                'cancelled' => 'إلغاء'
            ];
            
            $this->logActivity('purchase_status_updated', 
                "تم " . $statusTexts[$data['status']] . " عملية الشراء: " . $id, $id);

            return Response::json([
                'success' => true,
                'message' => 'تم تحديث حالة عملية الشراء بنجاح'
            ]);

        } catch (\Exception $e) {
            error_log('Purchase status update error: ' . $e->getMessage());
            return Response::json(['error' => 'فشل في تحديث حالة عملية الشراء'], 500);
        }
    }

    /**
     * Delete purchase
     */
    public function destroy(Request $request, int $id): Response
    {
        // Check permissions
        if (!Auth::hasPermission('purchases.delete')) {
            return Response::error('Unauthorized access', 403);
        }

        try {
            $result = $this->purchaseModel->delete($id);

            if (!$result) {
                return Response::error('فشل في حذف عملية الشراء. قد تكون مكتملة بالفعل', 422);
            }

            // Log activity
            $this->logActivity('purchase_deleted', "تم حذف عملية شراء: " . $id, $id);

            return Response::success('تم حذف عملية الشراء بنجاح', '/purchases');

        } catch (\Exception $e) {
            error_log('Purchase deletion error: ' . $e->getMessage());
            return Response::error('فشل في حذف عملية الشراء', 500);
        }
    }

    /**
     * Get purchase statistics
     */
    public function statistics(Request $request): Response
    {
        // Check permissions
        if (!Auth::hasPermission('purchases.view')) {
            return Response::error('Unauthorized access', 403);
        }

        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        try {
            $statistics = $this->purchaseModel->getStatistics($dateFrom, $dateTo);
            $monthlySummary = $this->purchaseModel->getMonthlySummary();
            $topSuppliers = $this->supplierModel->getTopSuppliers(5, $dateFrom, $dateTo);

            return Response::view('purchases.statistics', [
                'statistics' => $statistics,
                'monthly_summary' => $monthlySummary,
                'top_suppliers' => $topSuppliers,
                'date_from' => $dateFrom,
                'date_to' => $dateTo
            ]);

        } catch (\Exception $e) {
            error_log('Purchase statistics error: ' . $e->getMessage());
            return Response::error('فشل في تحميل إحصائيات المشتريات', 500);
        }
    }

    /**
     * Search purchases
     */
    public function search(Request $request): Response
    {
        // Check permissions
        if (!Auth::hasPermission('purchases.view')) {
            return Response::error('Unauthorized access', 403);
        }

        $query = $request->input('q', '');
        
        if (empty($query)) {
            return Response::json(['purchases' => []]);
        }

        try {
            $purchases = $this->purchaseModel->search($query, 20);
            return Response::json(['purchases' => $purchases]);
        } catch (\Exception $e) {
            error_log('Purchase search error: ' . $e->getMessage());
            return Response::json(['error' => 'فشل في البحث'], 500);
        }
    }

    /**
     * Get purchases by supplier
     */
    public function bySupplier(Request $request, int $supplierId): Response
    {
        // Check permissions
        if (!Auth::hasPermission('purchases.view')) {
            return Response::error('Unauthorized access', 403);
        }

        $page = $request->input('page', 1);

        try {
            $purchases = $this->purchaseModel->getBySupplier($supplierId, $page, 20);
            $supplier = $this->supplierModel->find($supplierId);

            if (!$supplier) {
                return Response::error('المورد غير موجود', 404);
            }

            return Response::view('purchases.by-supplier', [
                'supplier' => $supplier,
                'purchases' => $purchases
            ]);

        } catch (\Exception $e) {
            error_log('Purchase by supplier error: ' . $e->getMessage());
            return Response::error('فشل في تحميل مشتريات المورد', 500);
        }
    }

    /**
     * Export purchases data
     */
    public function export(Request $request): Response
    {
        // Check permissions
        if (!Auth::hasPermission('purchases.view')) {
            return Response::error('Unauthorized access', 403);
        }

        $format = $request->input('format', 'csv');
        $filters = [
            'supplier_id' => $request->input('supplier_id'),
            'status' => $request->input('status'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to')
        ];

        try {
            $purchases = $this->purchaseModel->getAll($filters, 1, 10000); // Get all purchases
            
            $filename = 'purchases_export_' . date('Y-m-d_H-i-s');
            
            if ($format === 'csv') {
                return $this->exportToCsv($purchases, $filename . '.csv');
            } else {
                return Response::error('تنسيق التصدير غير مدعوم', 400);
            }

        } catch (\Exception $e) {
            error_log('Purchases export error: ' . $e->getMessage());
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
    private function exportToCsv(array $purchases, string $filename): Response
    {
        $content = "Reference,Supplier,Date,Status,Total Amount,Tax,Discount,Items Count,Created By\n";
        
        foreach ($purchases as $purchase) {
            $content .= sprintf(
                '"%s","%s","%s","%s","%s","%s","%s","%s","%s"' . "\n",
                addslashes($purchase['reference_number']),
                addslashes($purchase['supplier_name']),
                $purchase['purchase_date'],
                addslashes($purchase['status']),
                $purchase['total_amount'],
                $purchase['tax_amount'],
                $purchase['discount_amount'],
                $purchase['item_count'],
                addslashes($purchase['created_by_name'] ?? '')
            );
        }

        return Response::csv($content, $filename);
    }
}