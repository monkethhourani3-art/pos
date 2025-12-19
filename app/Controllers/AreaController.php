<?php
/**
 * Area Controller
 * Restaurant POS System
 */

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Support\Facades\Auth;
use App\Support\Facades\Session;
use App\Support\Facades\Log;

class AreaController
{
    protected $db;

    public function __construct()
    {
        $app = new \App\Application();
        $this->db = $app->getDatabase();
    }

    /**
     * Show areas list
     */
    public function index(Request $request)
    {
        try {
            $areas = $this->db->fetchAll("
                SELECT 
                    a.*,
                    COUNT(t.id) as tables_count,
                    COUNT(CASE WHEN t.status = 'available' THEN 1 END) as available_tables,
                    COUNT(CASE WHEN t.status = 'occupied' THEN 1 END) as occupied_tables
                FROM areas a
                LEFT JOIN tables t ON a.id = t.area_id AND t.is_active = 1
                WHERE a.is_active = 1
                GROUP BY a.id
                ORDER BY a.sort_order, a.name
            ");
            
            return Response::view('areas.index', [
                'areas' => $areas
            ]);
            
        } catch (\Exception $e) {
            Log::error('Area index error: ' . $e->getMessage());
            Session::addErrorMessage('حدث خطأ في تحميل المناطق');
            
            return Response::view('areas.index', [
                'areas' => []
            ]);
        }
    }

    /**
     * Store new area
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
                'name' => 'required|min:2|max:50',
                'display_name' => 'required|min:2|max:100'
            ]);
            
            if (!$validator->validate()) {
                $errors = $validator->getErrors();
                Session::addErrorMessage('يرجى تصحيح الأخطاء التالية');
                return redirect('/restaurant/areas');
            }
            
            // Check if area name exists
            $existing = $this->db->fetchOne("
                SELECT id FROM areas 
                WHERE name = ? AND is_active = 1
            ", [$request->post('name')]);
            
            if ($existing) {
                Session::addErrorMessage('اسم المنطقة موجود مسبقاً');
                return redirect('/restaurant/areas');
            }
            
            // Get next sort order
            $maxOrder = $this->db->fetchOne("
                SELECT MAX(sort_order) as max_order 
                FROM areas 
                WHERE is_active = 1
            ");
            $sortOrder = ($maxOrder->max_order ?? 0) + 1;
            
            // Insert area
            $areaData = [
                'branch_id' => auth()->user()->branch_id ?? 1,
                'name' => $request->post('name'),
                'display_name' => $request->post('display_name'),
                'description' => $request->post('description'),
                'sort_order' => $sortOrder
            ];
            
            $areaId = $this->db->insert('areas', $areaData);
            
            // Log action
            Log::business('area_created', [
                'area_id' => $areaId,
                'area_name' => $request->post('display_name')
            ]);
            
            Session::addSuccessMessage('تم إضافة المنطقة بنجاح');
            return redirect('/restaurant/areas');
            
        } catch (\Exception $e) {
            Log::error('Area store error: ' . $e->getMessage());
            Session::addErrorMessage('حدث خطأ أثناء إضافة المنطقة');
            return redirect('/restaurant/areas');
        }
    }

    /**
     * Update area
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
                'name' => 'required|min:2|max:50',
                'display_name' => 'required|min:2|max:100'
            ]);
            
            if (!$validator->validate()) {
                $errors = $validator->getErrors();
                Session::addErrorMessage('يرجى تصحيح الأخطاء التالية');
                return redirect('/restaurant/areas');
            }
            
            // Check if area exists
            $area = $this->db->fetchOne("SELECT * FROM areas WHERE id = ?", [$id]);
            if (!$area) {
                abort(404);
            }
            
            // Check if area name exists (excluding current area)
            $existing = $this->db->fetchOne("
                SELECT id FROM areas 
                WHERE name = ? AND id != ? AND is_active = 1
            ", [$request->post('name'), $id]);
            
            if ($existing) {
                Session::addErrorMessage('اسم المنطقة موجود مسبقاً');
                return redirect('/restaurant/areas');
            }
            
            // Update area
            $updateData = [
                'name' => $request->post('name'),
                'display_name' => $request->post('display_name'),
                'description' => $request->post('description'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $this->db->update('areas', $updateData, 'id = ?', [$id]);
            
            // Log action
            Log::business('area_updated', [
                'area_id' => $id,
                'area_name' => $request->post('display_name')
            ]);
            
            Session::addSuccessMessage('تم تحديث المنطقة بنجاح');
            return redirect('/restaurant/areas');
            
        } catch (\Exception $e) {
            Log::error('Area update error: ' . $e->getMessage());
            Session::addErrorMessage('حدث خطأ أثناء تحديث المنطقة');
            return redirect('/restaurant/areas');
        }
    }

    /**
     * Delete area
     */
    public function destroy(Request $request, $id)
    {
        try {
            // Check permission
            if (!Auth::can('products.manage')) {
                abort(403);
            }
            
            $area = $this->db->fetchOne("SELECT * FROM areas WHERE id = ?", [$id]);
            if (!$area) {
                abort(404);
            }
            
            // Check if area has tables
            $tablesCount = $this->db->fetchOne("
                SELECT COUNT(*) as count 
                FROM tables 
                WHERE area_id = ? AND is_active = 1
            ", [$id]);
            
            if ($tablesCount && $tablesCount->count > 0) {
                Session::addErrorMessage('لا يمكن حذف هذه المنطقة لأنها تحتوي على طاولات');
                return redirect('/restaurant/areas');
            }
            
            // Soft delete
            $this->db->update('areas', [
                'is_active' => 0,
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$id]);
            
            // Log action
            Log::business('area_deleted', [
                'area_id' => $id,
                'area_name' => $area->display_name
            ]);
            
            Session::addSuccessMessage('تم حذف المنطقة بنجاح');
            return redirect('/restaurant/areas');
            
        } catch (\Exception $e) {
            Log::error('Area delete error: ' . $e->getMessage());
            Session::addErrorMessage('حدث خطأ أثناء حذف المنطقة');
            return redirect('/restaurant/areas');
        }
    }

    /**
     * Get areas for dropdown (AJAX)
     */
    public function dropdown(Request $request)
    {
        try {
            $areas = $this->db->fetchAll("
                SELECT id, name, display_name
                FROM areas 
                WHERE is_active = 1 
                ORDER BY sort_order, name
            ");
            
            return Response::json($areas);
            
        } catch (\Exception $e) {
            Log::error('Area dropdown error: ' . $e->getMessage());
            return Response::json(['error' => 'خطأ في تحميل المناطق'], 500);
        }
    }

    /**
     * Reorder areas
     */
    public function reorder(Request $request)
    {
        try {
            // Check permission
            if (!Auth::can('products.manage')) {
                abort(403);
            }
            
            $areas = $request->post('areas', []);
            
            if (empty($areas) || !is_array($areas)) {
                return Response::json(['error' => 'بيانات غير صحيحة'], 400);
            }
            
            $this->db->beginTransaction();
            
            foreach ($areas as $index => $areaId) {
                $this->db->update('areas', [
                    'sort_order' => $index + 1,
                    'updated_at' => date('Y-m-d H:i:s')
                ], 'id = ?', [$areaId]);
            }
            
            $this->db->commit();
            
            // Log action
            Log::business('areas_reordered', [
                'areas_count' => count($areas)
            ]);
            
            return Response::json([
                'success' => true,
                'message' => 'تم ترتيب المناطق بنجاح'
            ]);
            
        } catch (\Exception $e) {
            $this->db->rollback();
            Log::error('Area reorder error: ' . $e->getMessage());
            return Response::json(['error' => 'حدث خطأ في ترتيب المناطق'], 500);
        }
    }

    /**
     * Get area statistics
     */
    public function statistics(Request $request)
    {
        try {
            $areaId = $request->get('area_id');
            
            $query = "
                SELECT 
                    a.id,
                    a.name,
                    a.display_name,
                    COUNT(t.id) as total_tables,
                    COUNT(CASE WHEN t.status = 'available' THEN 1 END) as available_tables,
                    COUNT(CASE WHEN t.status = 'occupied' THEN 1 END) as occupied_tables,
                    COUNT(CASE WHEN t.status = 'reserved' THEN 1 END) as reserved_tables,
                    SUM(t.capacity) as total_capacity,
                    AVG(t.capacity) as avg_capacity
                FROM areas a
                LEFT JOIN tables t ON a.id = t.area_id AND t.is_active = 1
                WHERE a.is_active = 1
            ";
            
            $params = [];
            
            if ($areaId) {
                $query .= " AND a.id = ?";
                $params[] = $areaId;
            }
            
            $query .= " GROUP BY a.id ORDER BY a.sort_order, a.name";
            
            $areas = $this->db->fetchAll($query, $params);
            
            // Calculate overall statistics
            $overallStats = $this->db->fetchOne("
                SELECT 
                    COUNT(DISTINCT a.id) as total_areas,
                    COUNT(t.id) as total_tables,
                    SUM(CASE WHEN t.status = 'available' THEN 1 ELSE 0 END) as available_tables,
                    SUM(CASE WHEN t.status = 'occupied' THEN 1 ELSE 0 END) as occupied_tables,
                    SUM(t.capacity) as total_capacity
                FROM areas a
                LEFT JOIN tables t ON a.id = t.area_id AND t.is_active = 1
                WHERE a.is_active = 1
            ");
            
            $utilizationRate = $overallStats->total_tables > 0 ? 
                round(($overallStats->occupied_tables / $overallStats->total_tables) * 100, 1) : 0;
            
            $result = [
                'areas' => $areas,
                'overall' => [
                    'total_areas' => (int)($overallStats->total_areas ?? 0),
                    'total_tables' => (int)($overallStats->total_tables ?? 0),
                    'available_tables' => (int)($overallStats->available_tables ?? 0),
                    'occupied_tables' => (int)($overallStats->occupied_tables ?? 0),
                    'total_capacity' => (int)($overallStats->total_capacity ?? 0),
                    'utilization_rate' => $utilizationRate
                ]
            ];
            
            return Response::json($result);
            
        } catch (\Exception $e) {
            Log::error('Area statistics error: ' . $e->getMessage());
            return Response::json(['error' => 'خطأ في تحميل إحصائيات المناطق'], 500);
        }
    }

    /**
     * Get tables in area
     */
    public function tables(Request $request, $areaId)
    {
        try {
            $tables = $this->db->fetchAll("
                SELECT 
                    t.*,
                    COUNT(o.id) as active_orders,
                    MAX(o.created_at) as last_order_time
                FROM tables t
                LEFT JOIN orders o ON t.id = o.table_id AND o.status IN ('sent_to_kitchen', 'preparing', 'ready')
                WHERE t.area_id = ? AND t.is_active = 1
                GROUP BY t.id
                ORDER BY t.sort_order, t.table_number
            ", [$areaId]);
            
            return Response::json($tables);
            
        } catch (\Exception $e) {
            Log::error('Area tables error: ' . $e->getMessage());
            return Response::json(['error' => 'خطأ في تحميل طاولات المنطقة'], 500);
        }
    }
}