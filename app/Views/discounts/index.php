<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الخصومات - Restaurant POS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="/assets/css/discounts.css" rel="stylesheet">
</head>
<body>
    <div class="discounts-container">
        <!-- Header -->
        <div class="discounts-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="discounts-title">
                        <i class="fas fa-tags me-2"></i>
                        إدارة الخصومات
                    </h1>
                    <p class="discounts-subtitle">إنشاء وإدارة خصومات وعروض المطعم</p>
                </div>
                <div class="col-md-4">
                    <div class="discounts-controls text-end">
                        <a href="/discounts/create" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>إضافة خصم جديد
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card total-discounts">
                    <div class="stat-icon">
                        <i class="fas fa-tags"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?= $statistics['total_discounts'] ?? 0 ?></div>
                        <div class="stat-label">إجمالي الخصومات</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card active-discounts">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?= $statistics['active_discounts'] ?? 0 ?></div>
                        <div class="stat-label">الخصومات النشطة</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card usage-count">
                    <div class="stat-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?= number_format($statistics['total_usage'] ?? 0) ?></div>
                        <div class="stat-label">إجمالي الاستخدام</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card expired-discounts">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?= $statistics['expired_discounts'] ?? 0 ?></div>
                        <div class="stat-label">الخصومات المنتهية</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-section mb-4">
            <div class="card">
                <div class="card-body">
                    <form method="GET" action="/discounts" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">البحث</label>
                            <input type="text" class="form-control" name="search" 
                                   value="<?= htmlspecialchars($filters['search'] ?? '') ?>" 
                                   placeholder="البحث بالاسم أو الكود...">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">نوع الخصم</label>
                            <select class="form-select" name="type">
                                <option value="">جميع الأنواع</option>
                                <option value="percentage" <?= ($filters['type'] ?? '') === 'percentage' ? 'selected' : '' ?>>
                                    نسبة مئوية
                                </option>
                                <option value="fixed" <?= ($filters['type'] ?? '') === 'fixed' ? 'selected' : '' ?>>
                                    مبلغ ثابت
                                </option>
                                <option value="buy_x_get_y" <?= ($filters['type'] ?? '') === 'buy_x_get_y' ? 'selected' : '' ?>>
                                    اشتري X واحصل على Y
                                </option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">الحالة</label>
                            <select class="form-select" name="is_active">
                                <option value="">جميع الحالات</option>
                                <option value="1" <?= ($filters['is_active'] ?? '') === '1' ? 'selected' : '' ?>>
                                    نشط
                                </option>
                                <option value="0" <?= ($filters['is_active'] ?? '') === '0' ? 'selected' : '' ?>>
                                    غير نشط
                                </option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">من تاريخ</label>
                            <input type="date" class="form-control" name="date_from" 
                                   value="<?= $filters['date_from'] ?? '' ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">إلى تاريخ</label>
                            <input type="date" class="form-control" name="date_to" 
                                   value="<?= $filters['date_to'] ?? '' ?>">
                        </div>
                        <div class="col-md-1">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" class="btn btn-primary d-block w-100">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Discounts Table -->
        <div class="discounts-table-section">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-table me-2"></i>
                        قائمة الخصومات
                    </h5>
                    <div class="table-actions">
                        <button class="btn btn-sm btn-outline-success" onclick="exportDiscounts()">
                            <i class="fas fa-download"></i> تصدير
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>الكود</th>
                                    <th>الاسم</th>
                                    <th>نوع الخصم</th>
                                    <th>القيمة</th>
                                    <th>الاستخدام</th>
                                    <th>الصلاحية</th>
                                    <th>الحالة</th>
                                    <th>الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($discounts as $discount): ?>
                                    <tr>
                                        <td>
                                            <code class="discount-code"><?= htmlspecialchars($discount->code) ?></code>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($discount->name) ?></strong>
                                        </td>
                                        <td>
                                            <?php
                                            $typeLabels = [
                                                'percentage' => 'نسبة مئوية',
                                                'fixed' => 'مبلغ ثابت',
                                                'buy_x_get_y' => 'اشتري واحصل'
                                            ];
                                            ?>
                                            <span class="badge bg-secondary">
                                                <?= $typeLabels[$discount->type] ?? $discount->type ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($discount->type === 'percentage'): ?>
                                                <?= number_format($discount->value, 1) ?>%
                                            <?php else: ?>
                                                <?= number_format($discount->value, 2) ?> ر.س
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="usage-info">
                                                <span class="usage-count"><?= number_format($discount->used_count) ?></span>
                                                <?php if ($discount->usage_limit): ?>
                                                    <small class="text-muted">/ <?= number_format($discount->usage_limit) ?></small>
                                                <?php else: ?>
                                                    <small class="text-muted">/ ∞</small>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($discount->usage_limit): ?>
                                                <?php $usagePercentage = ($discount->used_count / $discount->usage_limit) * 100; ?>
                                                <div class="usage-bar">
                                                    <div class="usage-progress" style="width: <?= $usagePercentage ?>%"></div>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="validity-info">
                                                <small>
                                                    من: <?= date('Y-m-d', strtotime($discount->valid_from)) ?>
                                                    <br>
                                                    إلى: <?= date('Y-m-d', strtotime($discount->valid_until)) ?>
                                                </small>
                                                <?php if (strtotime($discount->valid_until) < time()): ?>
                                                    <span class="badge bg-danger ms-1">منتهي</span>
                                                <?php elseif (strtotime($discount->valid_until) < strtotime('+7 days')): ?>
                                                    <span class="badge bg-warning ms-1">قريب الانتهاء</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" 
                                                       <?= $discount->is_active ? 'checked' : '' ?>
                                                       onchange="toggleDiscountStatus(<?= $discount->id ?>, this.checked)">
                                                <label class="form-check-label">
                                                    <?= $discount->is_active ? 'نشط' : 'معطل' ?>
                                                </label>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="/discounts/<?= $discount->id ?>" 
                                                   class="btn btn-sm btn-outline-primary" title="عرض">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="/discounts/<?= $discount->id ?>/edit" 
                                                   class="btn btn-sm btn-outline-warning" title="تعديل">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <?php if ($discount->used_count == 0): ?>
                                                    <button class="btn btn-sm btn-outline-danger" 
                                                            onclick="deleteDiscount(<?= $discount->id ?>)" title="حذف">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if (empty($discounts)): ?>
                        <div class="empty-state text-center py-5">
                            <i class="fas fa-tags fa-3x text-muted mb-3"></i>
                            <h5>لا توجد خصومات</h5>
                            <p class="text-muted">ابدأ بإنشاء خصم جديد</p>
                            <a href="/discounts/create" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i>إضافة خصم جديد
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($discounts) && $pagination['last_page'] > 1): ?>
                    <div class="card-footer">
                        <nav aria-label="تنقل الصفحات">
                            <ul class="pagination justify-content-center mb-0">
                                <?php if ($pagination['current_page'] > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $pagination['current_page'] - 1])) ?>">
                                            السابق
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php
                                $startPage = max(1, $pagination['current_page'] - 2);
                                $endPage = min($pagination['last_page'], $pagination['current_page'] + 2);
                                
                                for ($i = $startPage; $i <= $endPage; $i++):
                                ?>
                                    <li class="page-item <?= $i === $pagination['current_page'] ? 'active' : '' ?>">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                                            <?= $i ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($pagination['current_page'] < $pagination['last_page']): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $pagination['current_page'] + 1])) ?>">
                                            التالي
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/assets/js/discounts.js"></script>
    <script>
        // Initialize discounts management
        document.addEventListener('DOMContentLoaded', function() {
            DiscountsManager.init();
        });

        // Global functions
        function toggleDiscountStatus(id, isActive) {
            if (window.discountsManager) {
                window.discountsManager.toggleStatus(id, isActive);
            }
        }

        function deleteDiscount(id) {
            if (confirm('هل أنت متأكد من حذف هذا الخصم؟')) {
                if (window.discountsManager) {
                    window.discountsManager.delete(id);
                }
            }
        }

        function exportDiscounts() {
            if (window.discountsManager) {
                window.discountsManager.export();
            }
        }
    </script>
</body>
</html>