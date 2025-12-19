<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الطلبات - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?= ASSETS_URL ?>/css/admin.css" rel="stylesheet">
</head>
<body>
    <div class="admin-container">
        <!-- Header -->
        <div class="admin-header">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h3 class="mb-0">
                        <i class="fas fa-shopping-cart me-2"></i>
                        إدارة الطلبات
                    </h3>
                    <small class="text-muted">
                        إجمالي الطلبات: <?= $total_orders ?>
                    </small>
                </div>
                <div class="col-md-6 text-end">
                    <div class="btn-group">
                        <a href="/orders/create" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i> طلب جديد
                        </a>
                        <button type="button" class="btn btn-outline-secondary" onclick="exportOrders()">
                            <i class="fas fa-download me-2"></i> تصدير
                        </button>
                        <button type="button" class="btn btn-outline-info" onclick="showStatistics()">
                            <i class="fas fa-chart-bar me-2"></i> الإحصائيات
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3" id="filtersForm">
                    <div class="col-md-3">
                        <label class="form-label">حالة الطلب</label>
                        <select name="status" class="form-select">
                            <option value="">جميع الحالات</option>
                            <option value="pending" <?= $filters['status'] === 'pending' ? 'selected' : '' ?>>في الانتظار</option>
                            <option value="confirmed" <?= $filters['status'] === 'confirmed' ? 'selected' : '' ?>>مؤكد</option>
                            <option value="preparing" <?= $filters['status'] === 'preparing' ? 'selected' : '' ?>>قيد التحضير</option>
                            <option value="ready" <?= $filters['status'] === 'ready' ? 'selected' : '' ?>>جاهز</option>
                            <option value="served" <?= $filters['status'] === 'served' ? 'selected' : '' ?>>مُسلم</option>
                            <option value="paid" <?= $filters['status'] === 'paid' ? 'selected' : '' ?>>مدفوع</option>
                            <option value="cancelled" <?= $filters['status'] === 'cancelled' ? 'selected' : '' ?>>ملغي</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">الطاولة</label>
                        <select name="table_id" class="form-select">
                            <option value="">جميع الطاولات</option>
                            <?php foreach ($tables as $table): ?>
                                <option value="<?= $table->id ?>" <?= $filters['table_id'] == $table->id ? 'selected' : '' ?>>
                                    طاولة <?= htmlspecialchars($table->table_number) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">من تاريخ</label>
                        <input type="date" name="date_from" class="form-control" value="<?= $filters['date_from'] ?? '' ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">إلى تاريخ</label>
                        <input type="date" name="date_to" class="form-control" value="<?= $filters['date_to'] ?? '' ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-1"></i> بحث
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Orders List -->
        <div class="card">
            <div class="card-header">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h5 class="mb-0">قائمة الطلبات</h5>
                    </div>
                    <div class="col-md-6 text-end">
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-secondary" onclick="selectAll()">
                                <i class="fas fa-check-square me-1"></i> تحديد الكل
                            </button>
                            <button type="button" class="btn btn-outline-warning" onclick="bulkCancel()" id="bulkCancelBtn" disabled>
                                <i class="fas fa-times me-1"></i> إلغاء المحدد
                            </button>
                            <button type="button" class="btn btn-outline-success" onclick="bulkPrint()" id="bulkPrintBtn" disabled>
                                <i class="fas fa-print me-1"></i> طباعة المحدد
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (empty($orders)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <p class="text-muted">لا توجد طلبات</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th width="30">
                                        <input type="checkbox" class="form-check-input" id="selectAllCheckbox" onchange="toggleSelectAll()">
                                    </th>
                                    <th>رقم الطلب</th>
                                    <th>الطاولة</th>
                                    <th>الموظف</th>
                                    <th>الحالة</th>
                                    <th>المبلغ</th>
                                    <th>التاريخ</th>
                                    <th width="120">الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" class="form-check-input order-checkbox" 
                                                   value="<?= $order->id ?>" onchange="updateBulkButtons()">
                                        </td>
                                        <td>
                                            <strong>#<?= $order->id ?></strong>
                                            <?php if ($order->notes): ?>
                                                <br><small class="text-muted">
                                                    <i class="fas fa-sticky-note me-1"></i>
                                                    <?= htmlspecialchars(substr($order->notes, 0, 30)) ?>...
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($order->table_number): ?>
                                                <i class="fas fa-table me-1"></i>
                                                طاولة <?= htmlspecialchars($order->table_number) ?>
                                            <?php else: ?>
                                                <span class="text-muted">غير محدد</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($order->user_name ?? 'غير محدد') ?></td>
                                        <td>
                                            <?= getStatusBadge($order->status) ?>
                                        </td>
                                        <td>
                                            <strong><?= number_format($order->total_amount, 2) ?> ريال</strong>
                                        </td>
                                        <td>
                                            <small>
                                                <?= date('Y-m-d H:i', strtotime($order->created_at)) ?>
                                                <br>
                                                <span class="text-muted">
                                                    <?= timeAgo($order->created_at) ?>
                                                </span>
                                            </small>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="/orders/<?= $order->id ?>" class="btn btn-outline-primary" title="عرض التفاصيل">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <button type="button" class="btn btn-outline-success" onclick="printReceipt(<?= $order->id ?>)" title="طباعة الفاتورة">
                                                    <i class="fas fa-print"></i>
                                                </button>
                                                <div class="btn-group" role="group">
                                                    <button type="button" class="btn btn-outline-secondary dropdown-toggle" 
                                                            data-bs-toggle="dropdown" title="المزيد">
                                                        <i class="fas fa-ellipsis-v"></i>
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <li><a class="dropdown-item" href="/orders/<?= $order->id ?>/edit">
                                                            <i class="fas fa-edit me-2"></i>تعديل
                                                        </a></li>
                                                        <?php if ($order->status !== 'cancelled' && $order->status !== 'paid'): ?>
                                                            <li><a class="dropdown-item" href="#" onclick="changeStatus(<?= $order->id ?>)">
                                                                <i class="fas fa-exchange-alt me-2"></i>تغيير الحالة
                                                            </a></li>
                                                        <?php endif; ?>
                                                        <?php if ($order->status !== 'cancelled'): ?>
                                                            <li><hr class="dropdown-divider"></li>
                                                            <li><a class="dropdown-item text-danger" href="#" onclick="cancelOrder(<?= $order->id ?>)">
                                                                <i class="fas fa-times me-2"></i>إلغاء الطلب
                                                            </a></li>
                                                        <?php endif; ?>
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
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <nav aria-label="تنقل الصفحات" class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php if ($current_page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $current_page - 1 ?>&<?= http_build_query(array_filter($filters)) ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++): ?>
                        <li class="page-item <?= $i === $current_page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>&<?= http_build_query(array_filter($filters)) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($current_page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $current_page + 1 ?>&<?= http_build_query(array_filter($filters)) ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>

    <!-- Status Change Modal -->
    <div class="modal fade" id="statusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">تغيير حالة الطلب</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="statusForm">
                    <div class="modal-body">
                        <input type="hidden" id="orderId" name="order_id">
                        <div class="mb-3">
                            <label class="form-label">الحالة الجديدة</label>
                            <select name="status" class="form-select" required>
                                <option value="pending">في الانتظار</option>
                                <option value="confirmed">مؤكد</option>
                                <option value="preparing">قيد التحضير</option>
                                <option value="ready">جاهز</option>
                                <option value="served">مُسلم</option>
                                <option value="paid">مدفوع</option>
                                <option value="cancelled">ملغي</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">ملاحظات (اختياري)</label>
                            <textarea name="notes" class="form-control" rows="3" 
                                      placeholder="اكتب ملاحظة حول تغيير الحالة..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-primary">تحديث الحالة</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Cancel Order Modal -->
    <div class="modal fade" id="cancelModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">إلغاء الطلب</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="cancelForm">
                    <div class="modal-body">
                        <input type="hidden" id="cancelOrderId" name="order_id">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            هل أنت متأكد من إلغاء هذا الطلب؟
                        </div>
                        <div class="mb-3">
                            <label class="form-label">سبب الإلغاء <span class="text-danger">*</span></label>
                            <textarea name="reason" class="form-control" rows="3" 
                                      placeholder="اكتب سبب إلغاء الطلب..." required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">تراجع</button>
                        <button type="submit" class="btn btn-danger">إلغاء الطلب</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Statistics Modal -->
    <div class="modal fade" id="statisticsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">إحصائيات الطلبات</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="statisticsContent">
                        <!-- Statistics will be loaded here -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= ASSETS_URL ?>/js/orders.js"></script>
    
    <script>
        // Orders management functionality
        document.addEventListener('DOMContentLoaded', function() {
            setupEventListeners();
        });

        function setupEventListeners() {
            // Status form submission
            document.getElementById('statusForm').addEventListener('submit', function(e) {
                e.preventDefault();
                updateOrderStatus();
            });

            // Cancel form submission
            document.getElementById('cancelForm').addEventListener('submit', function(e) {
                e.preventDefault();
                cancelOrderSubmit();
            });

            // Filters form
            document.getElementById('filtersForm').addEventListener('submit', function(e) {
                // Add loading state
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> جاري البحث...';
                submitBtn.disabled = true;
            });
        }

        function changeStatus(orderId) {
            document.getElementById('orderId').value = orderId;
            const modal = new bootstrap.Modal(document.getElementById('statusModal'));
            modal.show();
        }

        async function updateOrderStatus() {
            const form = document.getElementById('statusForm');
            const formData = new FormData(form);
            const orderId = formData.get('order_id');
            
            try {
                const response = await fetch(`/orders/${orderId}/status`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-Token': getCsrfToken()
                    },
                    body: formData
                });

                const data = await response.json();
                
                if (data.success) {
                    showNotification('تم تحديث حالة الطلب بنجاح', 'success');
                    location.reload();
                } else {
                    showNotification(data.message || 'خطأ في تحديث حالة الطلب', 'error');
                }
            } catch (error) {
                showNotification('خطأ في الاتصال', 'error');
            }
        }

        function cancelOrder(orderId) {
            document.getElementById('cancelOrderId').value = orderId;
            const modal = new bootstrap.Modal(document.getElementById('cancelModal'));
            modal.show();
        }

        async function cancelOrderSubmit() {
            const form = document.getElementById('cancelForm');
            const formData = new FormData(form);
            const orderId = formData.get('order_id');
            
            try {
                const response = await fetch(`/orders/${orderId}/cancel`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-Token': getCsrfToken()
                    },
                    body: formData
                });

                const data = await response.json();
                
                if (data.success) {
                    showNotification('تم إلغاء الطلب بنجاح', 'success');
                    location.reload();
                } else {
                    showNotification(data.message || 'خطأ في إلغاء الطلب', 'error');
                }
            } catch (error) {
                showNotification('خطأ في الاتصال', 'error');
            }
        }

        function printReceipt(orderId) {
            window.open(`/orders/${orderId}/print`, '_blank');
        }

        // Bulk operations
        function toggleSelectAll() {
            const selectAllCheckbox = document.getElementById('selectAllCheckbox');
            const orderCheckboxes = document.querySelectorAll('.order-checkbox');
            
            orderCheckboxes.forEach(checkbox => {
                checkbox.checked = selectAllCheckbox.checked;
            });
            
            updateBulkButtons();
        }

        function selectAll() {
            document.getElementById('selectAllCheckbox').checked = true;
            toggleSelectAll();
        }

        function updateBulkButtons() {
            const selectedCheckboxes = document.querySelectorAll('.order-checkbox:checked');
            const bulkCancelBtn = document.getElementById('bulkCancelBtn');
            const bulkPrintBtn = document.getElementById('bulkPrintBtn');
            
            const hasSelection = selectedCheckboxes.length > 0;
            
            bulkCancelBtn.disabled = !hasSelection;
            bulkPrintBtn.disabled = !hasSelection;
            
            // Update select all checkbox state
            const selectAllCheckbox = document.getElementById('selectAllCheckbox');
            const totalCheckboxes = document.querySelectorAll('.order-checkbox').length;
            selectAllCheckbox.checked = selectedCheckboxes.length === totalCheckboxes;
            selectAllCheckbox.indeterminate = selectedCheckboxes.length > 0 && selectedCheckboxes.length < totalCheckboxes;
        }

        async function bulkCancel() {
            const selectedCheckboxes = document.querySelectorAll('.order-checkbox:checked');
            const orderIds = Array.from(selectedCheckboxes).map(cb => cb.value);
            
            if (orderIds.length === 0) return;
            
            if (!confirm(`هل أنت متأكد من إلغاء ${orderIds.length} طلب؟`)) return;
            
            try {
                const response = await fetch('/orders/bulk-cancel', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': getCsrfToken()
                    },
                    body: JSON.stringify({ order_ids: orderIds })
                });

                const data = await response.json();
                
                if (data.success) {
                    showNotification(`تم إلغاء ${orderIds.length} طلب بنجاح`, 'success');
                    location.reload();
                } else {
                    showNotification(data.message || 'خطأ في إلغاء الطلبات', 'error');
                }
            } catch (error) {
                showNotification('خطأ في الاتصال', 'error');
            }
        }

        async function bulkPrint() {
            const selectedCheckboxes = document.querySelectorAll('.order-checkbox:checked');
            const orderIds = Array.from(selectedCheckboxes).map(cb => cb.value);
            
            if (orderIds.length === 0) return;
            
            // Open print windows for selected orders
            orderIds.forEach(orderId => {
                window.open(`/orders/${orderId}/print`, '_blank');
            });
        }

        async function exportOrders() {
            const form = document.getElementById('filtersForm');
            const formData = new FormData(form);
            
            // Add export parameter
            formData.append('export', 'excel');
            
            // Create download link
            const params = new URLSearchParams(formData);
            window.open(`/orders/export?${params.toString()}`, '_blank');
        }

        async function showStatistics() {
            try {
                const form = document.getElementById('filtersForm');
                const formData = new FormData(form);
                
                const response = await fetch('/orders/statistics', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': getCsrfToken()
                    },
                    body: JSON.stringify(Object.fromEntries(formData))
                });

                const data = await response.json();
                
                if (data.success) {
                    displayStatistics(data.statistics);
                    const modal = new bootstrap.Modal(document.getElementById('statisticsModal'));
                    modal.show();
                }
            } catch (error) {
                showNotification('خطأ في تحميل الإحصائيات', 'error');
            }
        }

        function displayStatistics(stats) {
            const container = document.getElementById('statisticsContent');
            container.innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <h6>ملخص الطلبات</h6>
                        <ul class="list-group">
                            <li class="list-group-item d-flex justify-content-between">
                                إجمالي الطلبات
                                <span class="badge bg-primary rounded-pill">${stats.total_orders || 0}</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                الطلبات المكتملة
                                <span class="badge bg-success rounded-pill">${stats.completed_orders || 0}</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                الطلبات الملغية
                                <span class="badge bg-danger rounded-pill">${stats.cancelled_orders || 0}</span>
                            </li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6>الإيرادات</h6>
                        <ul class="list-group">
                            <li class="list-group-item d-flex justify-content-between">
                                إجمالي الإيرادات
                                <span class="badge bg-success rounded-pill">${(stats.total_revenue || 0).toFixed(2)} ريال</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                متوسط قيمة الطلب
                                <span class="badge bg-info rounded-pill">${(stats.average_order_value || 0).toFixed(2)} ريال</span>
                            </li>
                        </ul>
                    </div>
                </div>
            `;
        }

        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show position-fixed`;
            notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            notification.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 3000);
        }

        function getCsrfToken() {
            return document.querySelector('meta[name="csrf-token"]')?.content || '';
        }
    </script>
</body>
</html>

<?php
// Helper functions
function getStatusBadge($status) {
    $badges = [
        'pending' => '<span class="badge bg-secondary">في الانتظار</span>',
        'confirmed' => '<span class="badge bg-warning">مؤكد</span>',
        'preparing' => '<span class="badge bg-info">قيد التحضير</span>',
        'ready' => '<span class="badge bg-primary">جاهز</span>',
        'served' => '<span class="badge bg-success">مُسلم</span>',
        'paid' => '<span class="badge bg-success">مدفوع</span>',
        'cancelled' => '<span class="badge bg-danger">ملغي</span>'
    ];
    
    return $badges[$status] ?? '<span class="badge bg-light text-dark">غير محدد</span>';
}

function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'منذ لحظات';
    if ($time < 3600) return 'منذ ' . floor($time/60) . ' دقيقة';
    if ($time < 86400) return 'منذ ' . floor($time/3600) . ' ساعة';
    if ($time < 2592000) return 'منذ ' . floor($time/86400) . ' يوم';
    
    return date('Y-m-d', strtotime($datetime));
}
?>