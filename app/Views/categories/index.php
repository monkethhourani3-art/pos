<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الفئات - Restaurant POS</title>
    <link rel="stylesheet" href="<?php echo asset('css/app.css'); ?>">
    <style>
        .page-header {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-title {
            font-size: 24px;
            font-weight: 600;
            color: #2c3e50;
            margin: 0;
        }

        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .category-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.3s ease;
        }

        .category-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        .category-image {
            width: 100%;
            height: 150px;
            object-fit: cover;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            color: #6c757d;
        }

        .category-content {
            padding: 20px;
        }

        .category-name {
            font-size: 18px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .category-name-en {
            font-size: 14px;
            color: #6c757d;
            margin-bottom: 10px;
        }

        .category-description {
            font-size: 14px;
            color: #6c757d;
            line-height: 1.5;
            margin-bottom: 15px;
        }

        .category-stats {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            font-size: 14px;
        }

        .stat-item {
            text-align: center;
        }

        .stat-value {
            font-weight: bold;
            color: #3498db;
            display: block;
        }

        .stat-label {
            color: #6c757d;
            font-size: 12px;
        }

        .category-actions {
            display: flex;
            gap: 10px;
        }

        .btn-action {
            flex: 1;
            padding: 8px;
            font-size: 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-edit {
            background: #3498db;
            color: white;
        }

        .btn-edit:hover {
            background: #2980b9;
        }

        .btn-delete {
            background: #e74c3c;
            color: white;
        }

        .btn-delete:hover {
            background: #c0392b;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            border-radius: 8px;
            padding: 30px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            margin-bottom: 20px;
        }

        .modal-title {
            font-size: 20px;
            font-weight: 600;
            color: #2c3e50;
            margin: 0;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }

        .form-control {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 6px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        .form-control.error {
            border-color: #e74c3c;
        }

        .image-preview {
            width: 100%;
            height: 200px;
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-top: 10px;
            overflow: hidden;
        }

        .image-preview img {
            max-width: 100%;
            max-height: 100%;
            object-fit: cover;
        }

        .image-preview .placeholder {
            font-size: 48px;
            color: #6c757d;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }

        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .empty-state-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .empty-state-description {
            font-size: 14px;
            margin-bottom: 30px;
        }

        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .categories-grid {
                grid-template-columns: 1fr;
            }

            .category-actions {
                flex-direction: column;
            }
        }

        .sortable-list {
            list-style: none;
            padding: 0;
        }

        .sortable-item {
            background: white;
            margin-bottom: 10px;
            padding: 15px;
            border-radius: 6px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            cursor: move;
            transition: all 0.3s ease;
        }

        .sortable-item:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        .sortable-item.dragging {
            opacity: 0.5;
            transform: rotate(5deg);
        }

        .sortable-handle {
            color: #6c757d;
            margin-left: 10px;
        }
    </style>
</head>
<body>
    <div class="container" style="padding: 20px;">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1 class="page-title">إدارة فئات المنتجات</h1>
                <p style="margin: 5px 0 0 0; color: #6c757d;">إدارة وتنظيم فئات المنتجات في المطعم</p>
            </div>
            
            <div style="display: flex; gap: 10px;">
                <button class="btn btn-primary" onclick="openAddModal()">
                    إضافة فئة جديدة
                </button>
            </div>
        </div>

        <!-- Categories Grid -->
        <?php if (!empty($categories)): ?>
        <div class="categories-grid">
            <?php foreach ($categories as $category): ?>
            <div class="category-card" data-category-id="<?php echo $category->id; ?>">
                <?php if ($category->image): ?>
                    <img src="<?php echo asset($category->image); ?>" alt="<?php echo e($category->display_name); ?>" class="category-image">
                <?php else: ?>
                    <div class="category-image">
                        🍽️
                    </div>
                <?php endif; ?>
                
                <div class="category-content">
                    <div class="category-name"><?php echo e($category->display_name); ?></div>
                    <div class="category-name-en"><?php echo e($category->name); ?></div>
                    
                    <?php if ($category->description): ?>
                        <div class="category-description"><?php echo e($category->description); ?></div>
                    <?php endif; ?>
                    
                    <div class="category-stats">
                        <div class="stat-item">
                            <span class="stat-value"><?php echo $category->products_count; ?></span>
                            <span class="stat-label">إجمالي المنتجات</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?php echo $category->available_products; ?></span>
                            <span class="stat-label">منتج متاح</span>
                        </div>
                    </div>
                    
                    <div class="category-actions">
                        <button class="btn-action btn-edit" onclick="editCategory(<?php echo $category->id; ?>)">
                            تعديل
                        </button>
                        <button class="btn-action btn-delete" onclick="deleteCategory(<?php echo $category->id; ?>)">
                            حذف
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <!-- Empty State -->
        <div class="empty-state">
            <div class="empty-state-icon">🍽️</div>
            <div class="empty-state-title">لا توجد فئات منتجات</div>
            <div class="empty-state-description">ابدأ بإضافة فئة جديدة لتنظيم منتجاتك</div>
            <button class="btn btn-primary" onclick="openAddModal()">
                إضافة فئة جديدة
            </button>
        </div>
        <?php endif; ?>

        <!-- Add/Edit Category Modal -->
        <div class="modal" id="categoryModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title" id="modalTitle">إضافة فئة جديدة</h3>
                </div>
                
                <form id="categoryForm" enctype="multipart/form-data">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="category_id" id="categoryId">
                    
                    <div class="form-group">
                        <label class="form-label" for="nameAr">اسم الفئة بالعربية *</label>
                        <input type="text" class="form-control" id="nameAr" name="name_ar" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="nameEn">اسم الفئة بالإنجليزية *</label>
                        <input type="text" class="form-control" id="nameEn" name="name_en" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="description">الوصف</label>
                        <textarea class="form-control" id="description" name="description_ar" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="image">صورة الفئة</label>
                        <input type="file" class="form-control" id="image" name="image" accept="image/*" onchange="previewImage(this)">
                        <div class="image-preview" id="imagePreview">
                            <div class="placeholder">📷</div>
                        </div>
                        <small style="color: #6c757d;">الحد الأقصى: 2 ميجابايت • الأنواع المدعومة: JPG, PNG, WebP</small>
                    </div>
                    
                    <div style="display: flex; gap: 10px; margin-top: 30px;">
                        <button type="button" class="btn btn-light" onclick="closeModal()" style="flex: 1;">
                            إلغاء
                        </button>
                        <button type="submit" class="btn btn-primary" style="flex: 1;">
                            حفظ
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        let isEditMode = false;

        // Open add modal
        function openAddModal() {
            isEditMode = false;
            document.getElementById('modalTitle').textContent = 'إضافة فئة جديدة';
            document.getElementById('categoryForm').reset();
            document.getElementById('categoryId').value = '';
            document.getElementById('imagePreview').innerHTML = '<div class="placeholder">📷</div>';
            document.getElementById('categoryModal').classList.add('show');
        }

        // Edit category
        function editCategory(categoryId) {
            // This would typically fetch category data via AJAX
            // For now, we'll show a simple alert
            alert('ميزة التعديل قيد التطوير. سيتم إضافتها قريباً.');
        }

        // Delete category
        function deleteCategory(categoryId) {
            if (confirm('هل أنت متأكد من حذف هذه الفئة؟')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '/categories/' + categoryId;
                form.innerHTML = `
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="_method" value="DELETE">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Close modal
        function closeModal() {
            document.getElementById('categoryModal').classList.remove('show');
        }

        // Preview image
        function previewImage(input) {
            const preview = document.getElementById('imagePreview');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.innerHTML = `<img src="${e.target.result}" alt="معاينة الصورة">`;
                };
                
                reader.readAsDataURL(input.files[0]);
            } else {
                preview.innerHTML = '<div class="placeholder">📷</div>';
            }
        }

        // Handle form submission
        document.getElementById('categoryForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const categoryId = document.getElementById('categoryId').value;
            const url = categoryId ? `/categories/${categoryId}` : '/categories';
            const method = categoryId ? 'PUT' : 'POST';
            
            // Add method field for PUT requests
            if (categoryId) {
                formData.append('_method', 'PUT');
            }
            
            fetch(url, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('حدث خطأ: ' + (data.message || 'خطأ غير معروف'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('حدث خطأ في الاتصال');
            });
        });

        // Close modal when clicking outside
        document.getElementById('categoryModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        // Initialize drag and drop for reordering
        function initDragAndDrop() {
            const categoryCards = document.querySelectorAll('.category-card');
            
            categoryCards.forEach(card => {
                card.draggable = true;
                
                card.addEventListener('dragstart', function(e) {
                    e.dataTransfer.setData('text/plain', this.dataset.categoryId);
                    this.classList.add('dragging');
                });
                
                card.addEventListener('dragend', function(e) {
                    this.classList.remove('dragging');
                });
                
                card.addEventListener('dragover', function(e) {
                    e.preventDefault();
                });
                
                card.addEventListener('drop', function(e) {
                    e.preventDefault();
                    const draggedId = e.dataTransfer.getData('text/plain');
                    const targetId = this.dataset.categoryId;
                    
                    if (draggedId !== targetId) {
                        // Reorder categories
                        reorderCategories(draggedId, targetId);
                    }
                });
            });
        }

        // Reorder categories
        function reorderCategories(draggedId, targetId) {
            const formData = new FormData();
            formData.append('categories[]', draggedId);
            formData.append('categories[]', targetId);
            
            fetch('/categories/reorder', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('حدث خطأ في إعادة الترتيب');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('حدث خطأ في الاتصال');
            });
        }

        // Initialize on load
        document.addEventListener('DOMContentLoaded', function() {
            // initDragAndDrop(); // Enable when drag and drop is ready
        });
    </script>
</body>
</html>