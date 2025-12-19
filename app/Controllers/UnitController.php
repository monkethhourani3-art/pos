<?php

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Models\Unit;
use App\Support\Facades\Auth;
use App\Support\Facades\Session;
use App\Validation\Validator;

class UnitController
{
    private $unitModel;

    public function __construct()
    {
        $this->unitModel = new Unit();
    }

    /**
     * Display units list
     */
    public function index(Request $request): Response
    {
        // Check permissions
        if (!Auth::hasPermission('units.manage')) {
            return Response::error('Unauthorized access', 403);
        }

        $units = $this->unitModel->getAll();
        $statistics = $this->unitModel->getStatistics();

        return Response::view('units.index', [
            'units' => $units,
            'statistics' => $statistics
        ]);
    }

    /**
     * Show create unit form
     */
    public function create(Request $request): Response
    {
        // Check permissions
        if (!Auth::hasPermission('units.create')) {
            return Response::error('Unauthorized access', 403);
        }

        $baseUnits = $this->unitModel->getActive();

        return Response::view('units.create', [
            'base_units' => $baseUnits
        ]);
    }

    /**
     * Store new unit
     */
    public function store(Request $request): Response
    {
        // Check permissions
        if (!Auth::hasPermission('units.create')) {
            return Response::error('Unauthorized access', 403);
        }

        $validator = new Validator();
        $data = $request->all();

        // Validation rules
        $validator->validate($data, [
            'name' => 'required|string|max:255',
            'symbol' => 'required|string|max:50',
            'description' => 'nullable|string|max:500',
            'base_unit_id' => 'nullable|integer|min:1',
            'conversion_factor' => 'nullable|numeric|min:0.0001'
        ]);

        if ($validator->fails()) {
            return Response::error($validator->firstError(), 422);
        }

        // Check name uniqueness
        if (!$this->unitModel->isNameUnique($data['name'])) {
            return Response::error('اسم الوحدة مستخدم بالفعل', 422);
        }

        // Check symbol uniqueness
        if (!$this->unitModel->isSymbolUnique($data['symbol'])) {
            return Response::error('رمز الوحدة مستخدم بالفعل', 422);
        }

        // Set default values
        $data['conversion_factor'] = $data['conversion_factor'] ?? 1.0;
        $data['is_active'] = $data['is_active'] ?? 1;

        try {
            $unitId = $this->unitModel->create($data);

            // Log activity
            $this->logActivity('unit_created', "تم إنشاء وحدة جديدة: " . $data['name'], $unitId);

            return Response::success('تم إنشاء الوحدة بنجاح', '/units');

        } catch (\Exception $e) {
            error_log('Unit creation error: ' . $e->getMessage());
            return Response::error('فشل في إنشاء الوحدة', 500);
        }
    }

    /**
     * Show unit details
     */
    public function show(Request $request, int $id): Response
    {
        // Check permissions
        if (!Auth::hasPermission('units.view')) {
            return Response::error('Unauthorized access', 403);
        }

        $unit = $this->unitModel->find($id);
        if (!$unit) {
            return Response::error('الوحدة غير موجودة', 404);
        }

        return Response::view('units.show', [
            'unit' => $unit
        ]);
    }

    /**
     * Show edit unit form
     */
    public function edit(Request $request, int $id): Response
    {
        // Check permissions
        if (!Auth::hasPermission('units.edit')) {
            return Response::error('Unauthorized access', 403);
        }

        $unit = $this->unitModel->find($id);
        if (!$unit) {
            return Response::error('الوحدة غير موجودة', 404);
        }

        $baseUnits = $this->unitModel->getActive();

        return Response::view('units.edit', [
            'unit' => $unit,
            'base_units' => $baseUnits
        ]);
    }

    /**
     * Update unit
     */
    public function update(Request $request, int $id): Response
    {
        // Check permissions
        if (!Auth::hasPermission('units.edit')) {
            return Response::error('Unauthorized access', 403);
        }

        $validator = new Validator();
        $data = $request->all();

        // Validation rules
        $validator->validate($data, [
            'name' => 'required|string|max:255',
            'symbol' => 'required|string|max:50',
            'description' => 'nullable|string|max:500',
            'base_unit_id' => 'nullable|integer|min:1',
            'conversion_factor' => 'nullable|numeric|min:0.0001',
            'is_active' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return Response::error($validator->firstError(), 422);
        }

        // Check name uniqueness (exclude current unit)
        if (!$this->unitModel->isNameUnique($data['name'], $id)) {
            return Response::error('اسم الوحدة مستخدم بالفعل', 422);
        }

        // Check symbol uniqueness (exclude current unit)
        if (!$this->unitModel->isSymbolUnique($data['symbol'], $id)) {
            return Response::error('رمز الوحدة مستخدم بالفعل', 422);
        }

        // Set default values
        $data['conversion_factor'] = $data['conversion_factor'] ?? 1.0;
        $data['is_active'] = $data['is_active'] ?? 1;

        try {
            $result = $this->unitModel->update($id, $data);

            if (!$result) {
                return Response::error('فشل في تحديث الوحدة', 422);
            }

            // Log activity
            $this->logActivity('unit_updated', "تم تحديث الوحدة: " . $data['name'], $id);

            return Response::success('تم تحديث الوحدة بنجاح', '/units');

        } catch (\Exception $e) {
            error_log('Unit update error: ' . $e->getMessage());
            return Response::error('فشل في تحديث الوحدة', 500);
        }
    }

    /**
     * Delete unit
     */
    public function destroy(Request $request, int $id): Response
    {
        // Check permissions
        if (!Auth::hasPermission('units.delete')) {
            return Response::error('Unauthorized access', 403);
        }

        try {
            $result = $this->unitModel->delete($id);

            if (!$result) {
                return Response::error('فشل في حذف الوحدة. قد تكون مستخدمة في أصناف المخزون', 422);
            }

            // Log activity
            $this->logActivity('unit_deleted', "تم حذف وحدة بمعرف: " . $id, $id);

            return Response::success('تم حذف الوحدة بنجاح', '/units');

        } catch (\Exception $e) {
            error_log('Unit deletion error: ' . $e->Message());
            return Response::error('فشل في حذف الوحدة', 500);
        }
    }

    /**
     * Update unit status
     */
    public function updateStatus(Request $request, int $id): Response
    {
        // Check permissions
        if (!Auth::hasPermission('units.edit')) {
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
            $result = $this->unitModel->updateStatus($id, $data['is_active']);

            if (!$result) {
                return Response::json(['error' => 'فشل في تحديث حالة الوحدة'], 422);
            }

            // Log activity
            $status = $data['is_active'] ? 'تفعيل' : 'إلغاء تفعيل';
            $this->logActivity('unit_status_updated', 
                "تم {$status} الوحدة بمعرف: " . $id, $id);

            return Response::json([
                'success' => true,
                'message' => 'تم تحديث حالة الوحدة بنجاح'
            ]);

        } catch (\Exception $e) {
            error_log('Unit status update error: ' . $e->getMessage());
            return Response::json(['error' => 'فشل في تحديث حالة الوحدة'], 500);
        }
    }

    /**
     * Search units
     */
    public function search(Request $request): Response
    {
        // Check permissions
        if (!Auth::hasPermission('units.view')) {
            return Response::error('Unauthorized access', 403);
        }

        $query = $request->input('q', '');
        
        if (empty($query)) {
            return Response::json(['units' => []]);
        }

        try {
            $units = $this->unitModel->search($query, 20);
            return Response::json(['units' => $units]);
        } catch (\Exception $e) {
            error_log('Unit search error: ' . $e->getMessage());
            return Response::json(['error' => 'فشل في البحث'], 500);
        }
    }

    /**
     * Get common units
     */
    public function common(Request $request): Response
    {
        // Check permissions
        if (!Auth::hasPermission('units.view')) {
            return Response::error('Unauthorized access', 403);
        }

        try {
            $units = $this->unitModel->getCommon();
            return Response::json(['units' => $units]);
        } catch (\Exception $e) {
            error_log('Common units error: ' . $e->getMessage());
            return Response::json(['error' => 'فشل في تحميل الوحدات الشائعة'], 500);
        }
    }

    /**
     * Get unit hierarchy
     */
    public function hierarchy(Request $request): Response
    {
        // Check permissions
        if (!Auth::hasPermission('units.view')) {
            return Response::error('Unauthorized access', 403);
        }

        try {
            $hierarchy = $this->unitModel->getHierarchy();
            return Response::json(['hierarchy' => $hierarchy]);
        } catch (\Exception $e) {
            error_log('Unit hierarchy error: ' . $e->getMessage());
            return Response::json(['error' => 'فشل في تحميل تسلسل الوحدات'], 500);
        }
    }

    /**
     * Convert quantity between units
     */
    public function convert(Request $request): Response
    {
        // Check permissions
        if (!Auth::hasPermission('units.view')) {
            return Response::error('Unauthorized access', 403);
        }

        $validator = new Validator();
        $data = $request->all();

        $validator->validate($data, [
            'quantity' => 'required|numeric|min:0',
            'from_unit_id' => 'required|integer|min:1',
            'to_unit_id' => 'required|integer|min:1'
        ]);

        if ($validator->fails()) {
            return Response::json(['error' => $validator->firstError()], 422);
        }

        try {
            $convertedQuantity = $this->unitModel->convertQuantity(
                $data['quantity'],
                $data['from_unit_id'],
                $data['to_unit_id']
            );

            return Response::json([
                'original_quantity' => $data['quantity'],
                'converted_quantity' => $convertedQuantity,
                'success' => true
            ]);

        } catch (\Exception $e) {
            error_log('Unit conversion error: ' . $e->getMessage());
            return Response::json(['error' => 'فشل في تحويل الكمية'], 500);
        }
    }

    /**
     * Initialize default units
     */
    public function initializeDefault(Request $request): Response
    {
        // Check permissions
        if (!Auth::hasPermission('units.create')) {
            return Response::error('Unauthorized access', 403);
        }

        try {
            $units = $this->unitModel->getDefaultUnits();

            // Log activity
            $this->logActivity('default_units_initialized', 'تم إنشاء الوحدات الافتراضية');

            return Response::success('تم إنشاء الوحدات الافتراضية بنجاح', '/units');

        } catch (\Exception $e) {
            error_log('Default units initialization error: ' . $e->getMessage());
            return Response::error('فشل في إنشاء الوحدات الافتراضية', 500);
        }
    }

    /**
     * Export units data
     */
    public function export(Request $request): Response
    {
        // Check permissions
        if (!Auth::hasPermission('units.view')) {
            return Response::error('Unauthorized access', 403);
        }

        $format = $request->input('format', 'csv');

        try {
            $units = $this->unitModel->getAll();
            
            $filename = 'units_export_' . date('Y-m-d_H-i-s');
            
            if ($format === 'csv') {
                return $this->exportToCsv($units, $filename . '.csv');
            } else {
                return Response::error('تنسيق التصدير غير مدعوم', 400);
            }

        } catch (\Exception $e) {
            error_log('Units export error: ' . $e->getMessage());
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
    private function exportToCsv(array $units, string $filename): Response
    {
        $content = "Name,Symbol,Description,Base Unit,Conversion Factor,Status,Item Count\n";
        
        foreach ($units as $unit) {
            $content .= sprintf(
                '"%s","%s","%s","%s","%s","%s","%s"' . "\n",
                addslashes($unit['name']),
                addslashes($unit['symbol']),
                addslashes($unit['description'] ?? ''),
                addslashes($unit['base_unit_name'] ?? 'أساسية'),
                $unit['conversion_factor'],
                $unit['is_active'] ? 'نشطة' : 'غير نشطة',
                $unit['item_count'] ?? 0
            );
        }

        return Response::csv($content, $filename);
    }
}