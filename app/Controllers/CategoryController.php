<?php
/**
 * Category Controller
 * Restaurant POS System
 */

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Support\Facades\Auth;
use App\Support\Facades\Session;
use App\Support\Facades\Log;

class CategoryController
{
    protected $db;

    public function __construct()
    {
        $app = new \App\Application();
        $this->db = $app->getDatabase();
    }

    /**
     * Show categories list
     */
    public function index(Request $request)
    {
        try {
            $categories = $this->db->fetchAll("
                SELECT 
                    c.*,
                    COUNT(p.id) as products_count,
                    COUNT(CASE WHEN p.is_available = 1 THEN 1 END) as available_products
                FROM categories c
                LEFT JOIN products p ON c.id = p.category_id AND p.deleted_at IS NULL
                WHERE c.is_active = 1
                GROUP BY c.id
                ORDER BY c.sort_order, c.name_ar
            ");
            
            return Response::view('categories.index', [
                'categories' => $categories
            ]);
            
        } catch (\Exception $e) {
            Log::error('Category index error: ' . $e->getMessage());
            Session::addErrorMessage('حدث خطأ في تحميل الفئات');
            
            return Response::view('categories.index', [
                'categories' => []
            ]);
        }
    }

    /**
     * Store new category
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
                'name_ar' => 'required|min:2|max:255',
                'name_en' => 'required|min:2|max:255'
            ]);
            
            if (!$validator->validate()) {
                $errors = $validator->getErrors();
                Session::addErrorMessage('يرجى تصحيح الأخطاء التالية');
                return redirect('/categories');
            }
            
            // Get next sort order
            $maxOrder = $this->db->fetchOne("
                SELECT MAX(sort_order) as max_order 
                FROM categories 
                WHERE is_active = 1
            ");
            $sortOrder = ($maxOrder->max_order ?? 0) + 1;
            
            // Insert category
            $categoryData = [
                'branch_id' => auth()->user()->branch_id ?? 1,
                'name' => $request->post('name_en'), // Using English name as system name
                'display_name' => $request->post('name_ar'),
                'description' => $request->post('description_ar'),
                'sort_order' => $sortOrder
            ];
            
            $categoryId = $this->db->insert('categories', $categoryData);
            
            // Handle image upload
            if ($request->file('image')) {
                $imagePath = $this->handleImageUpload($request->file('image'));
                if ($imagePath) {
                    $this->db->update('categories', ['image' => $imagePath], 'id = ?', [$categoryId]);
                }
            }
            
            // Log action
            Log::business('category_created', [
                'category_id' => $categoryId,
                'category_name' => $request->post('name_ar')
            ]);
            
            Session::addSuccessMessage('تم إضافة الفئة بنجاح');
            return redirect('/categories');
            
        } catch (\Exception $e) {
            Log::error('Category store error: ' . $e->getMessage());
            Session::addErrorMessage('حدث خطأ أثناء إضافة الفئة');
            return redirect('/categories');
        }
    }

    /**
     * Update category
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
                'name_ar' => 'required|min:2|max:255',
                'name_en' => 'required|min:2|max:255'
            ]);
            
            if (!$validator->validate()) {
                $errors = $validator->getErrors();
                Session::addErrorMessage('يرجى تصحيح الأخطاء التالية');
                return redirect('/categories');
            }
            
            // Check if category exists
            $category = $this->db->fetchOne("SELECT * FROM categories WHERE id = ?", [$id]);
            if (!$category) {
                abort(404);
            }
            
            // Handle image upload
            $imagePath = $category->image;
            if ($request->file('image')) {
                $imagePath = $this->handleImageUpload($request->file('image'));
                
                // Delete old image if exists
                if ($category->image && file_exists(PUBLIC_PATH . $category->image)) {
                    unlink(PUBLIC_PATH . $category->image);
                }
            }
            
            // Update category
            $updateData = [
                'name' => $request->post('name_en'),
                'display_name' => $request->post('name_ar'),
                'description' => $request->post('description_ar'),
                'image' => $imagePath,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $this->db->update('categories', $updateData, 'id = ?', [$id]);
            
            // Log action
            Log::business('category_updated', [
                'category_id' => $id,
                'category_name' => $request->post('name_ar')
            ]);
            
            Session::addSuccessMessage('تم تحديث الفئة بنجاح');
            return redirect('/categories');
            
        } catch (\Exception $e) {
            Log::error('Category update error: ' . $e->getMessage());
            Session::addErrorMessage('حدث خطأ أثناء تحديث الفئة');
            return redirect('/categories');
        }
    }

    /**
     * Delete category
     */
    public function destroy(Request $request, $id)
    {
        try {
            // Check permission
            if (!Auth::can('products.manage')) {
                abort(403);
            }
            
            $category = $this->db->fetchOne("SELECT * FROM categories WHERE id = ?", [$id]);
            if (!$category) {
                abort(404);
            }
            
            // Check if category has products
            $productsCount = $this->db->fetchOne("
                SELECT COUNT(*) as count 
                FROM products 
                WHERE category_id = ? AND deleted_at IS NULL
            ", [$id]);
            
            if ($productsCount && $productsCount->count > 0) {
                Session::addErrorMessage('لا يمكن حذف هذه الفئة لأنها تحتوي على منتجات');
                return redirect('/categories');
            }
            
            // Soft delete
            $this->db->update('categories', [
                'is_active' => 0,
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$id]);
            
            // Delete image if exists
            if ($category->image && file_exists(PUBLIC_PATH . $category->image)) {
                unlink(PUBLIC_PATH . $category->image);
            }
            
            // Log action
            Log::business('category_deleted', [
                'category_id' => $id,
                'category_name' => $category->display_name
            ]);
            
            Session::addSuccessMessage('تم حذف الفئة بنجاح');
            return redirect('/categories');
            
        } catch (\Exception $e) {
            Log::error('Category delete error: ' . $e->getMessage());
            Session::addErrorMessage('حدث خطأ أثناء حذف الفئة');
            return redirect('/categories');
        }
    }

    /**
     * Reorder categories
     */
    public function reorder(Request $request)
    {
        try {
            // Check permission
            if (!Auth::can('products.manage')) {
                abort(403);
            }
            
            $categories = $request->post('categories', []);
            
            if (empty($categories) || !is_array($categories)) {
                return Response::json(['error' => 'بيانات غير صحيحة'], 400);
            }
            
            $this->db->beginTransaction();
            
            foreach ($categories as $index => $categoryId) {
                $this->db->update('categories', [
                    'sort_order' => $index + 1,
                    'updated_at' => date('Y-m-d H:i:s')
                ], 'id = ?', [$categoryId]);
            }
            
            $this->db->commit();
            
            // Log action
            Log::business('categories_reordered', [
                'categories_count' => count($categories)
            ]);
            
            return Response::json([
                'success' => true,
                'message' => 'تم ترتيب الفئات بنجاح'
            ]);
            
        } catch (\Exception $e) {
            $this->db->rollback();
            Log::error('Category reorder error: ' . $e->getMessage());
            return Response::json(['error' => 'حدث خطأ في ترتيب الفئات'], 500);
        }
    }

    /**
     * Get categories for dropdown (AJAX)
     */
    public function dropdown(Request $request)
    {
        try {
            $categories = $this->db->fetchAll("
                SELECT id, name_ar, name_en, display_name
                FROM categories 
                WHERE is_active = 1 
                ORDER BY sort_order, name_ar
            ");
            
            return Response::json($categories);
            
        } catch (\Exception $e) {
            Log::error('Category dropdown error: ' . $e->getMessage());
            return Response::json(['error' => 'خطأ في تحميل الفئات'], 500);
        }
    }

    /**
     * Handle image upload
     */
    protected function handleImageUpload($file)
    {
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            return null;
        }
        
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        $maxSize = 2 * 1024 * 1024; // 2MB
        
        // Validate file type
        if (!in_array($file['type'], $allowedTypes)) {
            throw new \Exception('نوع الملف غير مدعوم. يرجى استخدام JPG أو PNG أو WebP');
        }
        
        // Validate file size
        if ($file['size'] > $maxSize) {
            throw new \Exception('حجم الملف كبير جداً. الحد الأقصى 2 ميجابايت');
        }
        
        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'category_' . uniqid() . '.' . $extension;
        $uploadPath = PUBLIC_PATH . '/uploads/categories/' . $filename;
        
        // Create directory if not exists
        $dir = dirname($uploadPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
            throw new \Exception('فشل في رفع الصورة');
        }
        
        return '/uploads/categories/' . $filename;
    }
}