<?php

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Models\InventoryItem;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\Category;
use App\Support\Facades\Auth;
use App\Support\Facades\Session;
use App\Validation\Validator;

class InventoryController
{
    private $inventoryItemModel;
    private $supplierModel;
    private $unitModel;
    private $categoryModel;

    public function __construct()
    {
        $this->inventoryItemModel = new InventoryItem();
        $this->supplierModel = new Supplier();
        $this->unitModel = new Unit();
        $this->categoryModel = new Category();
    }

    /**
     * Display inventory items list
     */
    public function index(Request $request): Response
    {
        // Check permissions
        if (!Auth::hasPermission('inventory.view')) {
            return Response::error('Unauthorized access', 403);
        }

        $page = $request->input('page', 1);
        $filters = [
            'category_id' => $request->input('category_id'),
            'supplier_id' => $request->input('supplier_id'),
            'low_stock' => $request->input('low_stock'),
            'search' => $request->input('search')
        ];

        $inventoryItems = $this->inventoryItemModel->getAll($filters, $page, 20);
        $totalItems = $this->inventoryItemModel->getTotalCount($filters);
        
        // Get filter options
        $categories = $this->categoryModel->getForInventory();
        $suppliers = $this->supplierModel->getActive();
        $statistics = $this->inventoryItemModel->getStatistics();

        $pagination = [
            'current_page' => $page,
            'total_pages' => ceil($totalItems / 20),
            'total_items' => $totalItems,
            'per_page' => 20
        ];

        return Response::view('inventory.index', [
            'inventory_items' => $inventoryItems,
            'pagination' => $pagination,
            'filters' => $filters,
            'categories' => $categories,
            'suppliers' => $suppliers,
            'statistics' => $statistics
        ]);
    }

    /**
     * Show create inventory item form
     */
    public function create(Request $request): Response
    {
        // Check permissions
        if (!Auth::hasPermission('inventory.create')) {
            return Response::error('Unauthorized access', 403);
        }

        $categories = $this->categoryModel->getForInventory();
        $suppliers = $this->supplierModel->getActive();
        $units = $this->unitModel->getActive();

        return Response::view('inventory.create', [
            'categories' => $categories,
            'suppliers' => $suppliers,
            'units' => $units
        ]);
    }

    /**
     * Store new inventory item
     */
    public function store(Request $request): Response
    {
        // Check permissions
        if (!Auth::hasPermission('inventory.create')) {
            return Response::error('Unauthorized access', 403);
        }

        $validator = new Validator();
        $data = $request->all();

        // Validation rules
        $validator->validate($data, [
            'name' => 'required|string|max:255',
            'category_id' => 'required|integer|min:1',
            'unit_id' => 'required|integer|min:1',
            'quantity' => 'required|numeric|min:0',
            'min_stock_level' => 'required|numeric|min:0',
            'reorder_level' => 'required|numeric|min:0',
            'unit_cost' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'barcode' => 'nullable|string|max:100',
            'sku' => 'nullable|string|max:100',
            'location' => 'nullable|string|max:255',
            'expiry_date' => 'nullable|date'
        ]);

        if ($validator->fails()) {
            return Response::error($validator->firstError(), 422);
        }

        try {
            $itemId = $this->inventoryItemModel->create($data);

            // Log activity
            $this->logActivity('inventory_created', "تم إنشاء صنف جديد: " . $data['name'], $itemId);

            return Response::success('تم إنشاء الصنف بنجاح', '/inventory');

        } catch (\Exception $e) {
            error_log('Inventory item creation error: ' . $e->getMessage());
            return Response::error('فشل في إنشاء الصنف', 500);
        }
    }

    /**
     * Show inventory item details
     */
    public function show(Request $request, int $id): Response
    {
        // Check permissions
        if (!Auth::hasPermission('inventory.view')) {
            return Response::error('Unauthorized access', 403);
        }

        $item = $this->inventoryItemModel->find($id);
        if (!$item) {
            return Response::error('الصنف غير موجود', 404);
        }

        // Get inventory movements
        $movements = $this->inventoryItemModel->getMovements($id, 20);

        return Response::view('inventory.show', [
            'item' => $item,
            'movements' => $movements
        ]);
    }

    /**
     * Show edit inventory item form
     */
    public function edit(Request $request, int $id): Response
    {
        // Check permissions
        if (!Auth::hasPermission('inventory.edit')) {
            return Response::error('Unauthorized access', 403);
        }

        $item = $this->inventoryItemModel->find($id);
        if (!$item) {
            return Response::error('الصنف غير موجود', 404);
        }

        $categories = $this->categoryModel->getForInventory();
        $suppliers = $this->supplierModel->getActive();
        $units = $this->unitModel->getActive();

        return Response::view('inventory.edit', [
            'item' => $item,
            'categories' => $categories,
            'suppliers' => $suppliers,
            'units' => $units
        ]);
    }

    /**
     * Update inventory item
     */
    public function update(Request $request, int $id): Response
    {
        // Check permissions
        if (!Auth::hasPermission('inventory.edit')) {
            return Response::error('Unauthorized access', 403);
        }

        $validator = new Validator();
        $data = $request->all();

        // Validation rules
        $validator->validate($data, [
            'name' => 'required|string|max:255',
            'category_id' => 'required|integer|min:1',
            'unit_id' => 'required|integer|min:1',
            'quantity' => 'required|numeric|min:0',
            'min_stock_level' => 'required|numeric|min:0',
            'reorder_level' => 'required|numeric|min:0',
            'unit_cost' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'barcode' => 'nullable|string|max:100',
            'sku' => 'nullable|string|max:100',
            'location' => 'nullable|string|max:255',
            'expiry_date' => 'nullable|date'
        ]);

        if ($validator->fails()) {
            return Response::error($validator->firstError(), 422);
        }

        try {
            $result = $this->inventoryItemModel->update($id, $data);

            if (!$result) {
                return Response::error('فشل في تحديث الصنف', 422);
            }

            // Log activity
            $this->logActivity('inventory_updated', "تم تحديث الصنف: " . $data['name'], $id);

            return Response::success('تم تحديث الصنف بنجاح', '/inventory');

        } catch (\Exception $e) {
            error_log('Inventory item update error: ' . $e->getMessage());
            return Response::error('فشل في تحديث الصنف', 500);
        }
    }

    /**
     * Delete inventory item
     */
    public function destroy(Request $request, int $id): Response
    {
        // Check permissions
        if (!Auth::hasPermission('inventory.delete')) {
            return Response::error('Unauthorized access', 403);
        }

        try {
            $result = $this->inventoryItemModel->delete($id);

            if (!$result) {
                return Response::error('فشل في حذف الصنف أو لا يمكن حذفه', 422);
            }

            // Log activity
            $this->logActivity('inventory_deleted', "تم حذف صنف بمعرف: " . $id, $id);

            return Response::success('تم حذف الصنف بنجاح', '/inventory');

        } catch (\Exception $e) {
            error_log('Inventory item deletion error: ' . $e->getMessage());
            return Response::error('فشل في حذف الصنف', 500);
        }
    }

    /**
     * Update inventory quantity
     */
    public function updateQuantity(Request $request, int $id): Response
    {
        // Check permissions
        if (!Auth::hasPermission('inventory.edit')) {
            return Response::error('Unauthorized access', 403);
        }

        $validator = new Validator();
        $data = $request->all();

        $validator->validate($data, [
            'quantity' => 'required|numeric',
            'type' => 'required|in:add,subtract,set',
            'reason' => 'nullable|string|max:255'
        ]);

        if ($validator->fails()) {
            return Response::json(['error' => $validator->firstError()], 422);
        }

        try {
            $result = $this->inventoryItemModel->updateQuantity(
                $id, 
                $data['quantity'], 
                $data['type'], 
                $data['reason']
            );

            if (!$result) {
                return Response::json(['error' => 'فشل في تحديث الكمية'], 422);
            }

            // Get updated item
            $updatedItem = $this->inventoryItemModel->find($id);

            // Log activity
            $this->logActivity('quantity_updated', 
                "تم تحديث كمية الصنف: " . $updatedItem['name'] . 
                " - النوع: " . $data['type'] . 
                " - الكمية: " . $data['quantity'], $id);

            return Response::json([
                'success' => true,
                'message' => 'تم تحديث الكمية بنجاح',
                'item' => $updatedItem
            ]);

        } catch (\Exception $e) {
            error_log('Quantity update error: ' . $e->getMessage());
            return Response::json(['error' => 'فشل في تحديث الكمية'], 500);
        }
    }

    /**
     * Get low stock items
     */
    public function lowStock(Request $request): Response
    {
        // Check permissions
        if (!Auth::hasPermission('inventory.view')) {
            return Response::error('Unauthorized access', 403);
        }

        $lowStockItems = $this->inventoryItemModel->getLowStockItems();

        return Response::view('inventory.low-stock', [
            'items' => $lowStockItems
        ]);
    }

    /**
     * Search inventory items
     */
    public function search(Request $request): Response
    {
        // Check permissions
        if (!Auth::hasPermission('inventory.view')) {
            return Response::error('Unauthorized access', 403);
        }

        $query = $request->input('q', '');
        
        if (empty($query)) {
            return Response::json(['items' => []]);
        }

        try {
            $items = $this->inventoryItemModel->search($query, 20);
            return Response::json(['items' => $items]);
        } catch (\Exception $e) {
            error_log('Inventory search error: ' . $e->getMessage());
            return Response::json(['error' => 'فشل في البحث'], 500);
        }
    }

    /**
     * Export inventory data
     */
    public function export(Request $request): Response
    {
        // Check permissions
        if (!Auth::hasPermission('inventory.view')) {
            return Response::error('Unauthorized access', 403);
        }

        $format = $request->input('format', 'csv');
        $filters = [
            'category_id' => $request->input('category_id'),
            'supplier_id' => $request->input('supplier_id'),
            'low_stock' => $request->input('low_stock')
        ];

        try {
            $items = $this->inventoryItemModel->getAll($filters, 1, 10000); // Get all items
            
            $filename = 'inventory_export_' . date('Y-m-d_H-i-s');
            
            if ($format === 'csv') {
                return $this->exportToCsv($items, $filename . '.csv');
            } elseif ($format === 'excel') {
                return $this->exportToExcel($items, $filename . '.xlsx');
            } else {
                return Response::error('تنسيق التصدير غير مدعوم', 400);
            }

        } catch (\Exception $e) {
            error_log('Inventory export error: ' . $e->getMessage());
            return Response::error('فشل في تصدير البيانات', 500);
        }
    }

    /**
     * Get inventory statistics
     */
    public function statistics(Request $request): Response
    {
        // Check permissions
        if (!Auth::hasPermission('inventory.view')) {
            return Response::error('Unauthorized access', 403);
        }

        try {
            $statistics = $this->inventoryItemModel->getStatistics();
            $valuation = $this->inventoryItemModel->getValuation();
            $lowStockItems = $this->inventoryItemModel->getLowStockItems();
            $expiringItems = $this->inventoryItemModel->getExpiringItems(30);

            return Response::view('inventory.statistics', [
                'statistics' => $statistics,
                'valuation' => $valuation,
                'low_stock_items' => $lowStockItems,
                'expiring_items' => $expiringItems
            ]);

        } catch (\Exception $e) {
            error_log('Inventory statistics error: ' . $e->getMessage());
            return Response::error('فشل في تحميل الإحصائيات', 500);
        }
    }

    /**
     * Get expiring items
     */
    public function expiring(Request $request): Response
    {
        // Check permissions
        if (!Auth::hasPermission('inventory.view')) {
            return Response::error('Unauthorized access', 403);
        }

        $days = $request->input('days', 30);
        
        try {
            $expiringItems = $this->inventoryItemModel->getExpiringItems($days);

            return Response::view('inventory.expiring', [
                'items' => $expiringItems,
                'days' => $days
            ]);

        } catch (\Exception $e) {
            error_log('Expiring items error: ' . $e->getMessage());
            return Response::error('فشل في تحميل الأصناف المنتهية الصلاحية', 500);
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
    private function exportToCsv(array $items, string $filename): Response
    {
        $content = "Name,Category,Supplier,Quantity,Unit,Cost,Price,Value,Stock Status,Location\n";
        
        foreach ($items as $item) {
            $content .= sprintf(
                '"%s","%s","%s","%s","%s","%s","%s","%s","%s","%s"' . "\n",
                addslashes($item['name']),
                addslashes($item['category_name'] ?? ''),
                addslashes($item['supplier_name'] ?? ''),
                $item['quantity'],
                addslashes($item['unit_symbol'] ?? ''),
                $item['unit_cost'],
                $item['selling_price'],
                $item['total_value'],
                addslashes($item['stock_status']),
                addslashes($item['location'] ?? '')
            );
        }

        return Response::csv($content, $filename);
    }

    /**
     * Export to Excel (placeholder - would need Excel library)
     */
    private function exportToExcel(array $items, string $filename): Response
    {
        // This would require an Excel library like PhpSpreadsheet
        // For now, return CSV format
        return $this->exportToCsv($items, str_replace('.xlsx', '.csv', $filename));
    }
}