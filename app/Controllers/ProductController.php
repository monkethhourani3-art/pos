<?php
/**
 * Product Controller
 * Restaurant POS System
 */

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Support\Facades\Auth;
use App\Support\Facades\Session;
use App\Support\Facades\Log;

class ProductController
{
    protected $db;

    public function __construct()
    {
        $app = new \App\Application();
        $this->db = $app->getDatabase();
    }

    /**
     * Show products list
     */
    public function index(Request $request)
    {
        try {
            $page = max(1, (int)$request->get('page', 1));
            $perPage = 20;
            $search = $request->get('search', '');
            $category = $request->get('category', '');
            $status = $request->get('status', '');
            
            // Build query
            $query = "
                SELECT 
                    p.*,
                    c.name_ar as category_name_ar,
                    c.name_en as category_name_en,
                    CONCAT(u.first_name, ' ', u.last_name) as created_by_name
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                LEFT JOIN users u ON p.created_by = u.id
                WHERE p.deleted_at IS NULL
            ";
            
            $params = [];
            
            // Add search filter
            if (!empty($search)) {
                $query .= " AND (p.name_ar LIKE ? OR p.name_en LIKE ? OR p.sku LIKE ?)";
                $searchTerm = "%{$search}%";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            // Add category filter
            if (!empty($category)) {
                $query .= " AND p.category_id = ?";
                $params[] = $category;
            }
            
            // Add status filter
            if ($status !== '') {
                $query .= " AND p.is_available = ?";
                $params[] = (int)$status;
            }
            
            // Get total count
            $countQuery = str_replace("SELECT 
                    p.*,
                    c.name_ar as category_name_ar,
                    c.name_en as category_name_en,
                    CONCAT(u.first_name, ' ', u.last_name) as created_by_name", "SELECT COUNT(*)", $query);
            
            $totalCount = $this->db->fetchOne($countQuery, $params);
            $total = $totalCount ? (int)$totalCount->{'COUNT(*)'} : 0;
            
            // Add pagination
            $offset = ($page - 1) * $perPage;
            $query .= " ORDER BY p.sort_order, p.name_ar LIMIT ? OFFSET ?";
            $params[] = $perPage;
            $params[] = $offset;
            
            $products = $this->db->fetchAll($query, $params);
            
            // Get categories for filter
            $categories = $this->db->fetchAll("
                SELECT id, name_ar, name_en 
                FROM categories 
                WHERE is_active = 1 
                ORDER BY sort_order, name_ar
            ");
            
            // Calculate pagination info
            $totalPages = ceil($total / $perPage);
            $hasNext = $page < $totalPages;
            $hasPrev = $page > 1;
            
            $data = [
                'products' => $products,
                'categories' => $categories,
                'currentPage' => $page,
                'totalPages' => $totalPages,
                'total' => $total,
                'hasNext' => $hasNext,
                'hasPrev' => $hasPrev,
                'perPage' => $perPage,
                'filters' => [
                    'search' => $search,
                    'category' => $category,
                    'status' => $status
                ]
            ];
            
            return Response::view('products.index', $data);
            
        } catch (\Exception $e) {
            Log::error('Product index error: ' . $e->getMessage());
            
            Session::addErrorMessage('حدث خطأ في تحميل المنتجات');
            
            return Response::view('products.index', [
                'products' => [],
                'categories' => [],
                'currentPage' => 1,
                'totalPages' => 1,
                'total' => 0,
                'hasNext' => false,
                'hasPrev' => false,
                'perPage' => 20,
                'filters' => ['search' => '', 'category' => '', 'status' => '']
            ]);
        }
    }

    /**
     * Show create product form
     */
    public function create(Request $request)
    {
        // Check permission
        if (!Auth::can('products.manage')) {
            abort(403);
        }
        
        // Get categories
        $categories = $this->db->fetchAll("
            SELECT id, name_ar, name_en 
            FROM categories 
            WHERE is_active = 1 
            ORDER BY sort_order, name_ar
        ");
        
        // Get product modifiers
        $modifierTemplates = $this->db->fetchAll("
            SELECT * FROM product_modifiers 
            WHERE is_active = 1 
            ORDER BY sort_order
        ");
        
        return Response::view('products.create', [
            'categories' => $categories,
            'modifierTemplates' => $modifierTemplates
        ]);
    }

    /**
     * Store new product
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
                'name_en' => 'required|min:2|max:255',
                'category_id' => 'required|integer',
                'base_price' => 'required|numeric|min:0',
                'type' => 'required|in:item,combo,service'
            ]);
            
            if (!$validator->validate()) {
                $errors = $validator->getErrors();
                Session::addErrorMessage('يرجى تصحيح الأخطاء التالية:');
                Session::flashInput($request->post());
                return redirect('/products/create');
            }
            
            // Check SKU uniqueness
            $sku = $request->post('sku');
            if (!empty($sku)) {
                $existing = $this->db->fetchOne("
                    SELECT id FROM products 
                    WHERE sku = ? AND branch_id = ? AND deleted_at IS NULL
                ", [$sku, auth()->user()->branch_id ?? 1]);
                
                if ($existing) {
                    Session::addErrorMessage('رقم المنتج (SKU) موجود مسبقاً');
                    Session::flashInput($request->post());
                    return redirect('/products/create');
                }
            }
            
            // Handle image upload
            $imagePath = null;
            if ($request->file('image')) {
                $imagePath = $this->handleImageUpload($request->file('image'));
            }
            
            // Insert product
            $productData = [
                'branch_id' => auth()->user()->branch_id ?? 1,
                'category_id' => $request->post('category_id'),
                'sku' => $request->post('sku'),
                'name_ar' => $request->post('name_ar'),
                'name_en' => $request->post('name_en'),
                'description_ar' => $request->post('description_ar'),
                'description_en' => $request->post('description_en'),
                'image' => $imagePath,
                'type' => $request->post('type'),
                'base_price' => $request->post('base_price'),
                'cost_price' => $request->post('cost_price', 0),
                'preparation_time' => $request->post('preparation_time', 0),
                'is_available' => $request->post('is_available', 1) ? 1 : 0,
                'is_featured' => $request->post('is_featured', 0) ? 1 : 0,
                'created_by' => Auth::id()
            ];
            
            $productId = $this->db->insert('products', $productData);
            
            // Handle product modifiers
            $this->saveProductModifiers($productId, $request->post('modifiers', []));
            
            // Log action
            Log::business('product_created', [
                'product_id' => $productId,
                'product_name' => $request->post('name_ar'),
                'price' => $request->post('base_price')
            ]);
            
            Session::addSuccessMessage('تم إضافة المنتج بنجاح');
            return redirect('/products');
            
        } catch (\Exception $e) {
            Log::error('Product store error: ' . $e->getMessage());
            Session::addErrorMessage('حدث خطأ أثناء إضافة المنتج');
            Session::flashInput($request->post());
            return redirect('/products/create');
        }
    }

    /**
     * Show product details
     */
    public function show(Request $request, $id)
    {
        try {
            $product = $this->db->fetchOne("
                SELECT 
                    p.*,
                    c.name_ar as category_name_ar,
                    c.name_en as category_name_en,
                    CONCAT(u.first_name, ' ', u.last_name) as created_by_name
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                LEFT JOIN users u ON p.created_by = u.id
                WHERE p.id = ? AND p.deleted_at IS NULL
            ", [$id]);
            
            if (!$product) {
                abort(404);
            }
            
            // Get product modifiers
            $modifiers = $this->db->fetchAll("
                SELECT 
                    pm.*,
                    GROUP_CONCAT(mi.name_ar ORDER BY mi.sort_order) as modifier_names
                FROM product_modifiers pm
                LEFT JOIN modifier_items mi ON pm.id = mi.modifier_id
                WHERE pm.product_id = ?
                GROUP BY pm.id
                ORDER BY pm.sort_order
            ", [$id]);
            
            // Get product prices (for variants)
            $prices = $this->db->fetchAll("
                SELECT * FROM product_prices 
                WHERE product_id = ? 
                ORDER BY sort_order
            ", [$id]);
            
            // Get sales statistics
            $stats = $this->db->fetchOne("
                SELECT 
                    COUNT(*) as total_orders,
                    SUM(oi.quantity) as total_quantity,
                    SUM(oi.total_price) as total_revenue,
                    AVG(oi.unit_price) as avg_price
                FROM order_items oi
                JOIN orders o ON oi.order_id = o.id
                WHERE oi.product_id = ?
                AND o.status != 'cancelled'
                AND o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ", [$id]);
            
            $data = [
                'product' => $product,
                'modifiers' => $modifiers,
                'prices' => $prices,
                'stats' => $stats
            ];
            
            return Response::view('products.show', $data);
            
        } catch (\Exception $e) {
            Log::error('Product show error: ' . $e->getMessage());
            Session::addErrorMessage('حدث خطأ في تحميل بيانات المنتج');
            return redirect('/products');
        }
    }

    /**
     * Show edit product form
     */
    public function edit(Request $request, $id)
    {
        // Check permission
        if (!Auth::can('products.manage')) {
            abort(403);
        }
        
        try {
            $product = $this->db->fetchOne("
                SELECT * FROM products 
                WHERE id = ? AND deleted_at IS NULL
            ", [$id]);
            
            if (!$product) {
                abort(404);
            }
            
            // Get categories
            $categories = $this->db->fetchAll("
                SELECT id, name_ar, name_en 
                FROM categories 
                WHERE is_active = 1 
                ORDER BY sort_order, name_ar
            ");
            
            // Get product modifiers
            $modifiers = $this->db->fetchAll("
                SELECT pm.*, mi.* 
                FROM product_modifiers pm
                LEFT JOIN modifier_items mi ON pm.id = mi.modifier_id
                WHERE pm.product_id = ?
                ORDER BY pm.sort_order, mi.sort_order
            ", [$id]);
            
            return Response::view('products.edit', [
                'product' => $product,
                'categories' => $categories,
                'modifiers' => $modifiers
            ]);
            
        } catch (\Exception $e) {
            Log::error('Product edit error: ' . $e->getMessage());
            Session::addErrorMessage('حدث خطأ في تحميل نموذج التعديل');
            return redirect('/products');
        }
    }

    /**
     * Update product
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
                'name_en' => 'required|min:2|max:255',
                'category_id' => 'required|integer',
                'base_price' => 'required|numeric|min:0',
                'type' => 'required|in:item,combo,service'
            ]);
            
            if (!$validator->validate()) {
                $errors = $validator->getErrors();
                Session::addErrorMessage('يرجى تصحيح الأخطاء التالية');
                Session::flashInput($request->post());
                return redirect("/products/{$id}/edit");
            }
            
            // Check if product exists
            $product = $this->db->fetchOne("SELECT * FROM products WHERE id = ?", [$id]);
            if (!$product) {
                abort(404);
            }
            
            // Handle image upload
            $imagePath = $product->image;
            if ($request->file('image')) {
                $imagePath = $this->handleImageUpload($request->file('image'));
                
                // Delete old image if exists
                if ($product->image && file_exists(PUBLIC_PATH . $product->image)) {
                    unlink(PUBLIC_PATH . $product->image);
                }
            }
            
            // Update product
            $updateData = [
                'category_id' => $request->post('category_id'),
                'sku' => $request->post('sku'),
                'name_ar' => $request->post('name_ar'),
                'name_en' => $request->post('name_en'),
                'description_ar' => $request->post('description_ar'),
                'description_en' => $request->post('description_en'),
                'image' => $imagePath,
                'type' => $request->post('type'),
                'base_price' => $request->post('base_price'),
                'cost_price' => $request->post('cost_price', 0),
                'preparation_time' => $request->post('preparation_time', 0),
                'is_available' => $request->post('is_available', 1) ? 1 : 0,
                'is_featured' => $request->post('is_featured', 0) ? 1 : 0,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $this->db->update('products', $updateData, 'id = ?', [$id]);
            
            // Handle product modifiers
            $this->saveProductModifiers($id, $request->post('modifiers', []));
            
            // Log action
            Log::business('product_updated', [
                'product_id' => $id,
                'product_name' => $request->post('name_ar')
            ]);
            
            Session::addSuccessMessage('تم تحديث المنتج بنجاح');
            return redirect("/products/{$id}");
            
        } catch (\Exception $e) {
            Log::error('Product update error: ' . $e->getMessage());
            Session::addErrorMessage('حدث خطأ أثناء تحديث المنتج');
            return redirect("/products/{$id}/edit");
        }
    }

    /**
     * Delete product (soft delete)
     */
    public function destroy(Request $request, $id)
    {
        try {
            // Check permission
            if (!Auth::can('products.manage')) {
                abort(403);
            }
            
            $product = $this->db->fetchOne("SELECT * FROM products WHERE id = ?", [$id]);
            if (!$product) {
                abort(404);
            }
            
            // Check if product is used in orders
            $usage = $this->db->fetchOne("
                SELECT COUNT(*) as count 
                FROM order_items oi
                JOIN orders o ON oi.order_id = o.id
                WHERE oi.product_id = ? AND o.status != 'cancelled'
            ", [$id]);
            
            if ($usage && $usage->count > 0) {
                Session::addErrorMessage('لا يمكن حذف هذا المنتج لأنه مستخدم في طلبات');
                return redirect('/products');
            }
            
            // Soft delete
            $this->db->update('products', [
                'deleted_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$id]);
            
            // Log action
            Log::business('product_deleted', [
                'product_id' => $id,
                'product_name' => $product->name_ar
            ]);
            
            Session::addSuccessMessage('تم حذف المنتج بنجاح');
            return redirect('/products');
            
        } catch (\Exception $e) {
            Log::error('Product delete error: ' . $e->getMessage());
            Session::addErrorMessage('حدث خطأ أثناء حذف المنتج');
            return redirect('/products');
        }
    }

    /**
     * Toggle product availability
     */
    public function toggleAvailability(Request $request, $id)
    {
        try {
            // Check permission
            if (!Auth::can('products.manage')) {
                abort(403);
            }
            
            $product = $this->db->fetchOne("SELECT * FROM products WHERE id = ?", [$id]);
            if (!$product) {
                return Response::json(['error' => 'المنتج غير موجود'], 404);
            }
            
            $newStatus = $product->is_available ? 0 : 1;
            
            $this->db->update('products', [
                'is_available' => $newStatus,
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$id]);
            
            // Log action
            Log::business('product_toggle_availability', [
                'product_id' => $id,
                'new_status' => $newStatus ? 'available' : 'unavailable'
            ]);
            
            return Response::json([
                'success' => true,
                'is_available' => $newStatus,
                'message' => $newStatus ? 'تم تفعيل المنتج' : 'تم إلغاء تفعيل المنتج'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Product toggle error: ' . $e->getMessage());
            return Response::json(['error' => 'حدث خطأ'], 500);
        }
    }

    /**
     * Search products (for AJAX)
     */
    public function search(Request $request)
    {
        try {
            $term = $request->get('term', '');
            $category = $request->get('category', '');
            $limit = min(50, max(10, (int)$request->get('limit', 20)));
            
            $query = "
                SELECT 
                    p.id,
                    p.name_ar,
                    p.name_en,
                    p.base_price,
                    p.image,
                    p.type,
                    c.name_ar as category_name
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE p.is_available = 1 
                AND p.deleted_at IS NULL
            ";
            
            $params = [];
            
            if (!empty($term)) {
                $query .= " AND (p.name_ar LIKE ? OR p.name_en LIKE ? OR p.sku LIKE ?)";
                $searchTerm = "%{$term}%";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            if (!empty($category)) {
                $query .= " AND p.category_id = ?";
                $params[] = $category;
            }
            
            $query .= " ORDER BY p.is_featured DESC, p.name_ar LIMIT ?";
            $params[] = $limit;
            
            $products = $this->db->fetchAll($query, $params);
            
            return Response::json($products);
            
        } catch (\Exception $e) {
            Log::error('Product search error: ' . $e->getMessage());
            return Response::json(['error' => 'خطأ في البحث'], 500);
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
        $maxSize = 5 * 1024 * 1024; // 5MB
        
        // Validate file type
        if (!in_array($file['type'], $allowedTypes)) {
            throw new \Exception('نوع الملف غير مدعوم. يرجى استخدام JPG أو PNG أو WebP');
        }
        
        // Validate file size
        if ($file['size'] > $maxSize) {
            throw new \Exception('حجم الملف كبير جداً. الحد الأقصى 5 ميجابايت');
        }
        
        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'product_' . uniqid() . '.' . $extension;
        $uploadPath = PUBLIC_PATH . '/uploads/products/' . $filename;
        
        // Create directory if not exists
        $dir = dirname($uploadPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
            throw new \Exception('فشل في رفع الصورة');
        }
        
        return '/uploads/products/' . $filename;
    }

    /**
     * Save product modifiers
     */
    protected function saveProductModifiers($productId, $modifiersData)
    {
        // Delete existing modifiers
        $this->db->delete('product_modifiers', 'product_id = ?', [$productId]);
        
        if (empty($modifiersData)) {
            return;
        }
        
        foreach ($modifiersData as $modifierData) {
            if (empty($modifierData['name_ar'])) {
                continue;
            }
            
            $modifierId = $this->db->insert('product_modifiers', [
                'product_id' => $productId,
                'name_ar' => $modifierData['name_ar'],
                'name_en' => $modifierData['name_en'] ?? $modifierData['name_ar'],
                'type' => $modifierData['type'] ?? 'single',
                'min_select' => $modifierData['min_select'] ?? 0,
                'max_select' => $modifierData['max_select'] ?? 1,
                'sort_order' => $modifierData['sort_order'] ?? 0
            ]);
            
            // Save modifier items if provided
            if (!empty($modifierData['items'])) {
                foreach ($modifierData['items'] as $itemData) {
                    if (empty($itemData['name_ar'])) {
                        continue;
                    }
                    
                    $this->db->insert('modifier_items', [
                        'modifier_id' => $modifierId,
                        'name_ar' => $itemData['name_ar'],
                        'name_en' => $itemData['name_en'] ?? $itemData['name_ar'],
                        'price_modifier' => $itemData['price_modifier'] ?? 0,
                        'is_default' => $itemData['is_default'] ?? 0,
                        'sort_order' => $itemData['sort_order'] ?? 0
                    ]);
                }
            }
        }
    }
}