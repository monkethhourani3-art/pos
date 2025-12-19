<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة المخزون - Restaurant POS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="/assets/css/inventory.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid py-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h3 mb-0">
                            <i class="fas fa-boxes me-2 text-primary"></i>
                            إدارة المخزون
                        </h1>
                        <p class="text-muted mb-0">إدارة أصناف المخزون والمخزون</p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="/inventory/create" class="btn btn-primary">
                            <i class="fas fa-plus me-1"></i>
                            إضافة صنف جديد
                        </a>
                        <div class="dropdown">
                            <button class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="/inventory/statistics">
                                    <i class="fas fa-chart-bar me-2"></i>الإحصائيات
                                </a></li>
                                <li><a class="dropdown-item" href="/inventory/low-stock">
                                    <i class="fas fa-exclamation-triangle me-2"></i>الأصناف منخفضة المخزون
                                </a></li>
                                <li><a class="dropdown-item" href="/inventory/expiring">
                                    <i class="fas fa-calendar-times me-2"></i>الأصناف منتهية الصلاحية
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="/inventory/export">
                                    <i class="fas fa-download me-2"></i>تصدير البيانات
                                </a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon bg-primary">
                        <i class="fas fa-boxes"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= number_format($statistics['total_items'] ?? 0) ?></h3>
                        <p>إجمالي الأصناف</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon bg-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= number_format($statistics['low_stock_items'] ?? 0) ?></h3>
                        <p>أصناف منخفضة المخزون</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon bg-success">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= number_format($statistics['total_value'] ?? 0, 2) ?> ر.س</h3>
                        <p>قيمة المخزون</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon bg-info">
                        <i class="fas fa-sync-alt"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= number_format($statistics['average_cost'] ?? 0, 2) ?> ر.س</h3>
                        <p>متوسط التكلفة</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form id="inventoryFiltersForm" class="row g-3">
                    <div class="col-md-3">
                        <label for="searchInput" class="form-label">البحث</label>
                        <input type="text" class="form-control" id="searchInput" name="search" 
                               value="<?= htmlspecialchars($filters['search'] ?? '') ?>" 
                               placeholder="البحث في الأصناف...">
                    </div>
                    <div class="col-md-2">
                        <label for="categoryFilter" class="form-label">الفئة</label>
                        <select class="form-select" id="categoryFilter" name="category_id">
                            <option value="">جميع الفئات</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= $category['id'] ?>" 
                                        <?= ($filters['category_id'] ?? '') == $category['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($category['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="supplierFilter" class="form-label">المورد</label>
                        <select class="form-select" id="supplierFilter" name="supplier_id">
                            <option value="">جميع الموردين</option>
                            <?php foreach ($suppliers as $supplier): ?>
                                <option value="<?= $supplier['id'] ?>" 
                                        <?= ($filters['supplier_id'] ?? '') == $supplier['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($supplier['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="lowStockFilter" class="form-label">حالة المخزون</label>
                        <select class="form-select" id="lowStockFilter" name="low_stock">
                            <option value="">جميع الأصناف</option>
                            <option value="1" <?= ($filters['low_stock'] ?? '') == '1' ? 'selected' : '' ?>>
                                منخفضة المخزون فقط
                            </option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <div class="d-flex gap-2 w-100">
                            <button type="submit" class="btn btn-primary flex-fill">
                                <i class="fas fa-search me-1"></i>بحث
                            </button>
                            <button type="button" class="btn btn-outline-secondary" onclick="clearFilters()">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Inventory Items -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    قائمة الأصناف
                    <span class="badge bg-primary ms-2"><?= $pagination['total_items'] ?></span>
                </h5>
                <div class="d-flex align-items-center gap-2">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="selectAllItems">
                        <label class="form-check-label" for="selectAllItems">تحديد الكل</label>
                    </div>
                    <button class="btn btn-sm btn-outline-primary" onclick="refreshData()">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (empty($inventory_items)): ?>
                    <div class="no-items text-center py-5">
                        <i class="fas fa-boxes text-muted" style="font-size: 4rem;"></i>
                        <h4 class="mt-3 text-muted">لا توجد أصناف</h4>
                        <p class="text-muted">لم يتم العثور على أصناف تطابق المعايير المحددة</p>
                        <a href="/inventory/create" class="btn btn-primary">
                            <i class="fas fa-plus me-1"></i>إضافة صنف جديد
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th width="40">
                                        <input class="form-check-input" type="checkbox" id="selectAllItems">
                                    </th>
                                    <th>الصنف</th>
                                    <th>الفئة</th>
                                    <th>المورد</th>
                                    <th>الكمية</th>
                                    <th>الوحدة</th>
                                    <th>التكلفة</th>
                                    <th>سعر البيع</th>
                                    <th>القيمة</th>
                                    <th>الحالة</th>
                                    <th width="120">الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($inventory_items as $item): ?>
                                    <tr class="inventory-item-row" data-item-id="<?= $item['id'] ?>">
                                        <td>
                                            <input class="form-check-input select-item" type="checkbox" 
                                                   value="<?= $item['id'] ?>">
                                        </td>
                                        <td>
                                            <div class="item-info">
                                                <div class="item-name">
                                                    <strong><?= htmlspecialchars($item['name']) ?></strong>
                                                    <?php if (!empty($item['barcode'])): ?>
                                                        <small class="text-muted">#<?= htmlspecialchars($item['barcode']) ?></small>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if (!empty($item['location'])): ?>
                                                    <small class="text-muted">
                                                        <i class="fas fa-map-marker-alt me-1"></i>
                                                        <?= htmlspecialchars($item['location']) ?>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?= htmlspecialchars($item['category_name'] ?? 'غير محدد') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($item['supplier_name'] ?? 'غير محدد') ?>
                                        </td>
                                        <td>
                                            <span class="quantity-display" data-quantity="<?= $item['quantity'] ?>">
                                                <?= number_format($item['quantity'], 2) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($item['unit_symbol'] ?? '') ?>
                                        </td>
                                        <td class="text-success">
                                            <?= number_format($item['unit_cost'], 2) ?> ر.س
                                        </td>
                                        <td class="text-primary">
                                            <?= number_format($item['selling_price'], 2) ?> ر.س
                                        </td>
                                        <td class="fw-bold">
                                            <?= number_format($item['total_value'], 2) ?> ر.س
                                        </td>
                                        <td>
                                            <?php
                                            $stockClass = '';
                                            $stockIcon = '';
                                            $stockText = '';
                                            
                                            switch ($item['stock_status']) {
                                                case 'low':
                                                    $stockClass = 'bg-danger';
                                                    $stockIcon = 'fa-exclamation-triangle';
                                                    $stockText = 'منخفض';
                                                    break;
                                                case 'medium':
                                                    $stockClass = 'bg-warning';
                                                    $stockIcon = 'fa-exclamation-circle';
                                                    $stockText = 'متوسط';
                                                    break;
                                                default:
                                                    $stockClass = 'bg-success';
                                                    $stockIcon = 'fa-check-circle';
                                                    $stockText = 'كافي';
                                            }
                                            ?>
                                            <span class="badge <?= $stockClass ?>">
                                                <i class="fas <?= $stockIcon ?> me-1"></i>
                                                <?= $stockText ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary" 
                                                        onclick="viewItem(<?= $item['id'] ?>)" 
                                                        title="عرض">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-outline-secondary" 
                                                        onclick="editItem(<?= $item['id'] ?>)" 
                                                        title="تعديل">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <div class="btn-group">
                                                    <button class="btn btn-outline-info dropdown-toggle" 
                                                            data-bs-toggle="dropdown" title="المزيد">
                                                        <i class="fas fa-ellipsis-h"></i>
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <li>
                                                            <a class="dropdown-item" href="#" 
                                                               onclick="updateQuantity(<?= $item['id'] ?>)">
                                                                <i class="fas fa-plus-minus me-2"></i>تحديث الكمية
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <a class="dropdown-item" href="/inventory/<?= $item['id'] ?>/movements">
                                                                <i class="fas fa-history me-2"></i>سجل الحركات
                                                            </a>
                                                        </li>
                                                        <li><hr class="dropdown-divider"></li>
                                                        <li>
                                                            <a class="dropdown-item text-danger" href="#" 
                                                               onclick="deleteItem(<?= $item['id'] ?>)">
                                                                <i class="fas fa-trash me-2"></i>حذف
                                                            </a>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($pagination['total_pages'] > 1): ?>
                <div class="card-footer">
                    <nav aria-label="تنقل بين الصفحات">
                        <ul class="pagination justify-content-center mb-0">
                            <?php if ($pagination['current_page'] > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?= $pagination['current_page'] - 1 ?>">
                                        <i class="fas fa-chevron-right"></i> السابق
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php
                            $startPage = max(1, $pagination['current_page'] - 2);
                            $endPage = min($pagination['total_pages'], $pagination['current_page'] + 2);
                            
                            if ($startPage > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=1">1</a>
                                </li>
                                <?php if ($startPage > 2): ?>
                                    <li class="page-item disabled"><span class="page-link">...</span></li>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                <li class="page-item <?= $i == $pagination['current_page'] ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($endPage < $pagination['total_pages']): ?>
                                <?php if ($endPage < $pagination['total_pages'] - 1): ?>
                                    <li class="page-item disabled"><span class="page-link">...</span></li>
                                <?php endif; ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?= $pagination['total_pages'] ?>">
                                        <?= $pagination['total_pages'] ?>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php if ($pagination['current_page'] < $pagination['total_pages']): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?= $pagination['current_page'] + 1 ?>">
                                        التالي <i class="fas fa-chevron-left"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </div>

        <!-- Bulk Actions -->
        <div class="bulk-actions mt-3" style="display: none;">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong>الإجراءات المجمعة:</strong>
                            <span class="selected-count">0</span> عنصر محدد
                        </div>
                        <div class="btn-group">
                            <button class="btn btn-outline-primary" onclick="bulkUpdateQuantity()">
                                <i class="fas fa-plus-minus me-1"></i>تحديث الكمية
                            </button>
                            <button class="btn btn-outline-info" onclick="bulkExport()">
                                <i class="fas fa-download me-1"></i>تصدير المحدد
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Update Quantity Modal -->
    <div class="modal fade" id="quantityModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">تحديث الكمية</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="quantityForm">
                        <input type="hidden" id="quantityItemId">
                        <div class="mb-3">
                            <label for="quantityValue" class="form-label">الكمية الجديدة</label>
                            <input type="number" class="form-control" id="quantityValue" 
                                   min="0" step="0.01" required>
                        </div>
                        <div class="mb-3">
                            <label for="quantityType" class="form-label">نوع التحديث</label>
                            <select class="form-select" id="quantityType" required>
                                <option value="set">تحديد القيمة</option>
                                <option value="add">إضافة</option>
                                <option value="subtract">طرح</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="quantityReason" class="form-label">السبب</label>
                            <textarea class="form-control" id="quantityReason" rows="2" 
                                      placeholder="سبب تحديث الكمية..."></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="button" class="btn btn-primary" onclick="submitQuantityUpdate()">تحديث</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/assets/js/inventory.js"></script>
    <script>
        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            if (window.inventoryManager) {
                window.inventoryManager.init();
            }
        });

        // Utility functions
        function clearFilters() {
            document.getElementById('inventoryFiltersForm').reset();
            document.getElementById('inventoryFiltersForm').submit();
        }

        function refreshData() {
            location.reload();
        }

        function viewItem(id) {
            window.location.href = `/inventory/${id}`;
        }

        function editItem(id) {
            window.location.href = `/inventory/${id}/edit`;
        }

        function updateQuantity(id) {
            document.getElementById('quantityItemId').value = id;
            new bootstrap.Modal(document.getElementById('quantityModal')).show();
        }

        function deleteItem(id) {
            if (confirm('هل أنت متأكد من حذف هذا الصنف؟')) {
                fetch(`/inventory/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Content-Type': 'application/json'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.message || 'فشل في حذف الصنف');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('حدث خطأ أثناء حذف الصنف');
                });
            }
        }

        function submitQuantityUpdate() {
            const form = document.getElementById('quantityForm');
            const itemId = document.getElementById('quantityItemId').value;
            const quantity = parseFloat(document.getElementById('quantityValue').value);
            const type = document.getElementById('quantityType').value;
            const reason = document.getElementById('quantityReason').value;

            if (!quantity || quantity < 0) {
                alert('يرجى إدخال كمية صحيحة');
                return;
            }

            fetch(`/inventory/${itemId}/quantity`, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    quantity: quantity,
                    type: type,
                    reason: reason
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('quantityModal')).hide();
                    location.reload();
                } else {
                    alert(data.error || 'فشل في تحديث الكمية');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('حدث خطأ أثناء تحديث الكمية');
            });
        }
    </script>
</body>
</html>