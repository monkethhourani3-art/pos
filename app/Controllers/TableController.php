<?php
/**
 * Table Controller
 * Restaurant POS System
 */

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Support\Facades\Auth;
use App\Support\Facades\Session;
use App\Support\Facades\Log;

class TableController
{
    protected $db;

    public function __construct()
    {
        $app = new \App\Application();
        $this->db = $app->getDatabase();
    }

    /**
     * Show tables layout
     */
    public function index(Request $request)
    {
        try {
            // Get all areas
            $areas = $this->db->fetchAll("
                SELECT * FROM areas 
                WHERE is_active = 1 
                ORDER BY sort_order, name
            ");
            
            // Get all tables with area info
            $tables = $this->db->fetchAll("
                SELECT 
                    t.*,
                    a.name as area_name,
                    a.display_name as area_display_name,
                    a.sort_order as area_sort_order,
                    COUNT(o.id) as active_orders
                FROM tables t
                LEFT JOIN areas a ON t.area_id = a.id
                LEFT JOIN orders o ON t.id = o.table_id AND o.status IN ('sent_to_kitchen', 'preparing', 'ready')
                WHERE t.is_active = 1
                GROUP BY t.id
                ORDER BY a.sort_order, t.sort_order, t.table_number
            ");
            
            // Group tables by area
            $tablesByArea = [];
            foreach ($tables as $table) {
                $areaKey = $table->area_id ?: 'no_area';
                if (!isset($tablesByArea[$areaKey])) {
                    $tablesByArea[$areaKey] = [
                        'area' => $table,
                        'tables' => []
                    ];
                }
                $tablesByArea[$areaKey]['tables'][] = $table;
            }
            
            return Response::view('tables.index', [
                'areas' => $areas,
                'tablesByArea' => $tablesByArea
            ]);
            
        } catch (\Exception $e) {
            Log::error('Table index error: ' . $e->getMessage());
            Session::addErrorMessage('حدث خطأ في تحميل الطاولات');
            
            return Response::view('tables.index', [
                'areas' => [],
                'tablesByArea' => []
            ]);
        }
    }

    /**
     * Store new table
     */
    public function store(Request $request)
    {
        try {
            // Check permission
            if (!Auth::can('products.manage')) {
                abort(403);
            }
            
            // Validate input
            $validator = new \App\Validation\Validator($request->post(), [
                'table_number' => 'required|max:10',
                'table_name' => 'required|max:50',
                'area_id' => 'required|integer',
                'capacity' => 'required|integer|min:1|max:20'
            ]);
            
            if (!$validator->validate()) {
                $errors = $validator->getErrors();
                Session::addErrorMessage('يرجى تصحيح الأخطاء التالية');
                return redirect('/restaurant/tables');
            }
            
            // Check if table number exists in same area
            $existing = $this->db->fetchOne("
                SELECT id FROM tables 
                WHERE table_number = ? AND area_id = ? AND is_active = 1
            ", [$request->post('table_number'), $request->post('area_id')]);
            
            if ($existing) {
                Session::addErrorMessage('رقم الطاولة موجود مسبقاً في هذه المنطقة');
                return redirect('/restaurant/tables');
            }
            
            // Get next sort order
            $maxOrder = $this->db->fetchOne("
                SELECT MAX(sort_order) as max_order 
                FROM tables 
                WHERE area_id = ? AND is_active = 1
            ", [$request->post('area_id')]);
            $sortOrder = ($maxOrder->max_order ?? 0) + 1;
            
            // Insert table
            $tableData = [
                'branch_id' => auth()->user()->branch_id ?? 1,
                'area_id' => $request->post('area_id'),
                'table_number' => $request->post('table_number'),
                'table_name' => $request->post('table_name'),
                'capacity' => $request->post('capacity'),
                'status' => 'available',
                'sort_order' => $sortOrder,
                'x_position' => $request->post('x_position', 0),
                'y_position' => $request->post('y_position', 0)
            ];
            
            $tableId = $this->db->insert('tables', $tableData);
            
            // Log action
            Log::business('table_created', [
                'table_id' => $tableId,
                'table_number' => $request->post('table_number'),
                'area_id' => $request->post('area_id')
            ]);
            
            Session::addSuccessMessage('تم إضافة الطاولة بنجاح');
            return redirect('/restaurant/tables');
            
        } catch (\Exception $e) {
            Log::error('Table store error: ' . $e->getMessage());
            Session::addErrorMessage('حدث خطأ أثناء إضافة الطاولة');
            return redirect('/restaurant/tables');
        }
    }

    /**
     * Update table
     */
    public function update(Request $request, $id)
    {
        try {
            // Check permission
            if (!Auth::can('products.manage')) {
                abort(403);
            }
            
            // Validate input
            $validator = new \App\Validation\Validator($request->post(), [
                'table_number' => 'required|max:10',
                'table_name' => 'required|max:50',
                'area_id' => 'required|integer',
                'capacity' => 'required|integer|min:1|max:20'
            ]);
            
            if (!$validator->validate()) {
                $errors = $validator->getErrors();
                Session::addErrorMessage('يرجى تصحيح الأخطاء التالية');
                return redirect('/restaurant/tables');
            }
            
            // Check if table exists
            $table = $this->db->fetchOne("SELECT * FROM tables WHERE id = ?", [$id]);
            if (!$table) {
                abort(404);
            }
            
            // Check if table number exists in same area (excluding current table)
            $existing = $this->db->fetchOne("
                SELECT id FROM tables 
                WHERE table_number = ? AND area_id = ? AND id != ? AND is_active = 1
            ", [$request->post('table_number'), $request->post('area_id'), $id]);
            
            if ($existing) {
                Session::addErrorMessage('رقم الطاولة موجود مسبقاً في هذه المنطقة');
                return redirect('/restaurant/tables');
            }
            
            // Update table
            $updateData = [
                'area_id' => $request->post('area_id'),
                'table_number' => $request->post('table_number'),
                'table_name' => $request->post('table_name'),
                'capacity' => $request->post('capacity'),
                'x_position' => $request->post('x_position', 0),
                'y_position' => $request->post('y_position', 0),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $this->db->update('tables', $updateData, 'id = ?', [$id]);
            
            // Log action
            Log::business('table_updated', [
                'table_id' => $id,
                'table_number' => $request->post('table_number')
            ]);
            
            Session::addSuccessMessage('تم تحديث الطاولة بنجاح');
            return redirect('/restaurant/tables');
            
        } catch (\Exception $e) {
            Log::error('Table update error: ' . $e->getMessage());
            Session::addErrorMessage('حدث خطأ أثناء تحديث الطاولة');
            return redirect('/restaurant/tables');
        }
    }

    /**
     * Delete table
     */
    public function destroy(Request $request, $id)
    {
        try {
            // Check permission
            if (!Auth::can('products.manage')) {
                abort(403);
            }
            
            $table = $this->db->fetchOne("SELECT * FROM tables WHERE id = ?", [$id]);
            if (!$table) {
                abort(404);
            }
            
            // Check if table has active orders
            $activeOrders = $this->db->fetchOne("
                SELECT COUNT(*) as count 
                FROM orders 
                WHERE table_id = ? AND status IN ('sent_to_kitchen', 'preparing', 'ready')
            ", [$id]);
            
            if ($activeOrders && $activeOrders->count > 0) {
                Session::addErrorMessage('لا يمكن حذف هذه الطاولة لأنها تحتوي على طلبات نشطة');
                return redirect('/restaurant/tables');
            }
            
            // Soft delete
            $this->db->update('tables', [
                'is_active' => 0,
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$id]);
            
            // Log action
            Log::business('table_deleted', [
                'table_id' => $id,
                'table_number' => $table->table_number
            ]);
            
            Session::addSuccessMessage('تم حذف الطاولة بنجاح');
            return redirect('/restaurant/tables');
            
        } catch (\Exception $e) {
            Log::error('Table delete error: ' . $e->getMessage());
            Session::addErrorMessage('حدث خطأ أثناء حذف الطاولة');
            return redirect('/restaurant/tables');
        }
    }

    /**
     * Update table status
     */
    public function updateStatus(Request $request, $id)
    {
        try {
            $table = $this->db->fetchOne("SELECT * FROM tables WHERE id = ?", [$id]);
            if (!$table) {
                return Response::json(['error' => 'الطاولة غير موجودة'], 404);
            }
            
            $status = $request->post('status');
            $validStatuses = ['available', 'occupied', 'reserved', 'cleaning', 'out_of_service'];
            
            if (!in_array($status, $validStatuses)) {
                return Response::json(['error' => 'حالة غير صحيحة'], 400);
            }
            
            // Update table status
            $this->db->update('tables', [
                'status' => $status,
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$id]);
            
            // If setting to available, clear any related data
            if ($status === 'available') {
                // Any cleanup needed when table becomes available
            }
            
            // Log action
            Log::business('table_status_updated', [
                'table_id' => $id,
                'table_number' => $table->table_number,
                'old_status' => $table->status,
                'new_status' => $status
            ]);
            
            return Response::json([
                'success' => true,
                'status' => $status,
                'message' => 'تم تحديث حالة الطاولة'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Table status update error: ' . $e->getMessage());
            return Response::json(['error' => 'حدث خطأ'], 500);
        }
    }

    /**
     * Get table status (for AJAX)
     */
    public function status(Request $request)
    {
        try {
            $tables = $this->db->fetchAll("
                SELECT 
                    t.id,
                    t.table_number,
                    t.table_name,
                    t.capacity,
                    t.status,
                    a.display_name as area_name,
                    COUNT(o.id) as active_orders,
                    MAX(o.created_at) as last_order_time
                FROM tables t
                LEFT JOIN areas a ON t.area_id = a.id
                LEFT JOIN orders o ON t.id = o.table_id AND o.status IN ('sent_to_kitchen', 'preparing', 'ready')
                WHERE t.is_active = 1
                GROUP BY t.id
                ORDER BY a.sort_order, t.sort_order, t.table_number
            ");
            
            return Response::json($tables);
            
        } catch (\Exception $e) {
            Log::error('Table status error: ' . $e->getMessage());
            return Response::json(['error' => 'خطأ في تحميل حالة الطاولات'], 500);
        }
    }

    /**
     * Get tables by area
     */
    public function byArea(Request $request)
    {
        try {
            $areaId = $request->get('area_id');
            
            $query = "
                SELECT 
                    t.*,
                    COUNT(o.id) as active_orders
                FROM tables t
                LEFT JOIN orders o ON t.id = o.table_id AND o.status IN ('sent_to_kitchen', 'preparing', 'ready')
                WHERE t.is_active = 1
            ";
            
            $params = [];
            
            if ($areaId) {
                $query .= " AND t.area_id = ?";
                $params[] = $areaId;
            }
            
            $query .= " GROUP BY t.id ORDER BY t.sort_order, t.table_number";
            
            $tables = $this->db->fetchAll($query, $params);
            
            return Response::json($tables);
            
        } catch (\Exception $e) {
            Log::error('Tables by area error: ' . $e->getMessage());
            return Response::json(['error' => 'خطأ في تحميل طاولات المنطقة'], 500);
        }
    }

    /**
     * Reorder tables
     */
    public function reorder(Request $request)
    {
        try {
            // Check permission
            if (!Auth::can('products.manage')) {
                abort(403);
            }
            
            $tables = $request->post('tables', []);
            $areaId = $request->post('area_id');
            
            if (empty($tables) || !is_array($tables) || !$areaId) {
                return Response::json(['error' => 'بيانات غير صحيحة'], 400);
            }
            
            $this->db->beginTransaction();
            
            foreach ($tables as $index => $tableId) {
                $this->db->update('tables', [
                    'sort_order' => $index + 1,
                    'updated_at' => date('Y-m-d H:i:s')
                ], 'id = ? AND area_id = ?', [$tableId, $areaId]);
            }
            
            $this->db->commit();
            
            // Log action
            Log::business('tables_reordered', [
                'area_id' => $areaId,
                'tables_count' => count($tables)
            ]);
            
            return Response::json([
                'success' => true,
                'message' => 'تم ترتيب الطاولات بنجاح'
            ]);
            
        } catch (\Exception $e) {
            $this->db->rollback();
            Log::error('Table reorder error: ' . $e->getMessage());
            return Response::json(['error' => 'حدث خطأ في ترتيب الطاولات'], 500);
        }
    }

    /**
     * Get table statistics
     */
    public function statistics(Request $request)
    {
        try {
            $stats = $this->db->fetchOne("
                SELECT 
                    COUNT(*) as total_tables,
                    SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available_tables,
                    SUM(CASE WHEN status = 'occupied' THEN 1 ELSE 0 END) as occupied_tables,
                    SUM(CASE WHEN status = 'reserved' THEN 1 ELSE 0 END) as reserved_tables,
                    SUM(CASE WHEN status = 'cleaning' THEN 1 ELSE 0 END) as cleaning_tables,
                    SUM(CASE WHEN status = 'out_of_service' THEN 1 ELSE 0 END) as out_of_service_tables,
                    SUM(CASE WHEN status != 'available' THEN 1 ELSE 0 END) as busy_tables,
                    AVG(capacity) as avg_capacity
                FROM tables 
                WHERE is_active = 1
            ");
            
            // Get table utilization for today
            $utilization = $this->db->fetchOne("
                SELECT 
                    COUNT(DISTINCT o.table_id) as occupied_tables_today,
                    COUNT(o.id) as total_orders_today,
                    AVG(o.total_amount) as avg_order_value
                FROM orders o
                WHERE DATE(o.created_at) = CURDATE()
                AND o.table_id IS NOT NULL
                AND o.status != 'cancelled'
            ");
            
            $result = [
                'total_tables' => (int)($stats->total_tables ?? 0),
                'available_tables' => (int)($stats->available_tables ?? 0),
                'occupied_tables' => (int)($stats->occupied_tables ?? 0),
                'reserved_tables' => (int)($stats->reserved_tables ?? 0),
                'cleaning_tables' => (int)($stats->cleaning_tables ?? 0),
                'out_of_service_tables' => (int)($stats->out_of_service_tables ?? 0),
                'busy_tables' => (int)($stats->busy_tables ?? 0),
                'utilization_rate' => $stats->total_tables > 0 ? round(($stats->busy_tables / $stats->total_tables) * 100, 1) : 0,
                'avg_capacity' => round($stats->avg_capacity ?? 0, 1),
                'occupied_today' => (int)($utilization->occupied_tables_today ?? 0),
                'orders_today' => (int)($utilization->total_orders_today ?? 0),
                'avg_order_value' => round($utilization->avg_order_value ?? 0, 2)
            ];
            
            return Response::json($result);
            
        } catch (\Exception $e) {
            Log::error('Table statistics error: ' . $e->getMessage());
            return Response::json(['error' => 'خطأ في تحميل الإحصائيات'], 500);
        }
    }
}