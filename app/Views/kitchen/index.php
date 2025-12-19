<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة المطبخ - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?= ASSETS_URL ?>/css/kitchen.css" rel="stylesheet">
</head>
<body>
    <div class="kitchen-container">
        <!-- Header -->
        <div class="kitchen-header">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h3 class="mb-0">
                        <i class="fas fa-utensils me-2"></i>
                        لوحة المطبخ
                    </h3>
                    <small class="text-muted">
                        مرحباً <?= htmlspecialchars($user->name) ?> - 
                        آخر تحديث: <span id="lastUpdate"><?= date('H:i:s') ?></span>
                    </small>
                </div>
                <div class="col-md-6 text-end">
                    <div class="btn-group">
                        <button type="button" class="btn btn-outline-info" onclick="toggleAutoRefresh()" id="refreshBtn">
                            <i class="fas fa-sync-alt"></i> تحديث تلقائي
                        </button>
                        <button type="button" class="btn btn-outline-primary" onclick="showMetrics()">
                            <i class="fas fa-chart-bar"></i> الإحصائيات
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="toggleFullscreen()">
                            <i class="fas fa-expand"></i> ملء الشاشة
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kitchen Status Overview -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-3">
                                <div class="kpi-card confirmed">
                                    <i class="fas fa-clock"></i>
                                    <h4><?= count($orders_by_status['confirmed'] ?? []) ?></h4>
                                    <p>في الانتظار</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="kpi-card preparing">
                                    <i class="fas fa-fire"></i>
                                    <h4><?= count($orders_by_status['preparing'] ?? []) ?></h4>
                                    <p>قيد التحضير</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="kpi-card ready">
                                    <i class="fas fa-check-circle"></i>
                                    <h4><?= count($orders_by_status['ready'] ?? []) ?></h4>
                                    <p>جاهز للتسليم</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="kpi-card total">
                                    <i class="fas fa-shopping-cart"></i>
                                    <h4><?= count($orders_by_status['confirmed']) + count($orders_by_status['preparing']) + count($orders_by_status['ready']) ?></h4>
                                    <p>إجمالي الطلبات</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Orders by Status -->
        <div class="row">
            <!-- Confirmed Orders -->
            <div class="col-md-4">
                <div class="card order-column">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0">
                            <i class="fas fa-clock me-2"></i>
                            في الانتظار (<?= count($orders_by_status['confirmed'] ?? []) ?>)
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="orders-list" id="confirmedOrders">
                            <?php if (!empty($orders_by_status['confirmed'])): ?>
                                <?php foreach ($orders_by_status['confirmed'] as $order): ?>
                                    <div class="order-card confirmed" data-order-id="<?= $order->id ?>">
                                        <div class="order-header">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <h6 class="mb-1">طاولة <?= htmlspecialchars($order->table_number) ?></h6>
                                                    <small class="text-muted">
                                                        <?= htmlspecialchars($order->user_name) ?> • 
                                                        <?= date('H:i', strtotime($order->created_at)) ?>
                                                    </small>
                                                </div>
                                                <div class="waiting-time">
                                                    <span class="badge bg-danger">
                                                        <?= $order->waiting_minutes ?> د
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="order-items">
                                            <?php foreach ($order->items as $item): ?>
                                                <div class="order-item">
                                                    <div class="item-info">
                                                        <span class="item-name"><?= htmlspecialchars($item->product_name) ?></span>
                                                        <span class="item-quantity">×<?= $item->quantity ?></span>
                                                        <?php if ($item->notes): ?>
                                                            <div class="item-notes">
                                                                <i class="fas fa-sticky-note me-1"></i>
                                                                <?= htmlspecialchars($item->notes) ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        
                                        <div class="order-actions">
                                            <button class="btn btn-sm btn-success w-100" onclick="startPreparing(<?= $order->id ?>)">
                                                <i class="fas fa-play me-1"></i>
                                                بدء التحضير
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="no-orders">
                                    <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                                    <p class="text-muted">لا توجد طلبات في الانتظار</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Preparing Orders -->
            <div class="col-md-4">
                <div class="card order-column">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-fire me-2"></i>
                            قيد التحضير (<?= count($orders_by_status['preparing'] ?? []) ?>)
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="orders-list" id="preparingOrders">
                            <?php if (!empty($orders_by_status['preparing'])): ?>
                                <?php foreach ($orders_by_status['preparing'] as $order): ?>
                                    <div class="order-card preparing" data-order-id="<?= $order->id ?>">
                                        <div class="order-header">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <h6 class="mb-1">طاولة <?= htmlspecialchars($order->table_number) ?></h6>
                                                    <small class="text-muted">
                                                        <?= htmlspecialchars($order->user_name) ?> • 
                                                        <?= date('H:i', strtotime($order->created_at)) ?>
                                                    </small>
                                                </div>
                                                <div class="preparing-time">
                                                    <span class="badge bg-info">
                                                        <?= $order->waiting_minutes ?> د
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="order-items">
                                            <?php foreach ($order->items as $item): ?>
                                                <div class="order-item" data-item-id="<?= $item->id ?>">
                                                    <div class="item-info">
                                                        <span class="item-name"><?= htmlspecialchars($item->product_name) ?></span>
                                                        <span class="item-quantity">×<?= $item->quantity ?></span>
                                                        <?php if ($item->kitchen_notes): ?>
                                                            <div class="item-notes kitchen-notes">
                                                                <i class="fas fa-utensils me-1"></i>
                                                                <?= htmlspecialchars($item->kitchen_notes) ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="item-actions">
                                                        <?php if ($item->status === 'ready'): ?>
                                                            <span class="badge bg-success">
                                                                <i class="fas fa-check"></i> جاهز
                                                            </span>
                                                        <?php else: ?>
                                                            <button class="btn btn-sm btn-outline-success" onclick="markItemReady(<?= $item->id ?>)">
                                                                <i class="fas fa-check"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        
                                        <div class="order-actions">
                                            <button class="btn btn-sm btn-success w-100" onclick="markOrderReady(<?= $order->id ?>)">
                                                <i class="fas fa-check-double me-1"></i>
                                                تحديد الطلب كجاهز
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="no-orders">
                                    <i class="fas fa-fire fa-3x text-primary mb-3"></i>
                                    <p class="text-muted">لا توجد طلبات قيد التحضير</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Ready Orders -->
            <div class="col-md-4">
                <div class="card order-column">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-check-circle me-2"></i>
                            جاهز للتسليم (<?= count($orders_by_status['ready'] ?? []) ?>)
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="orders-list" id="readyOrders">
                            <?php if (!empty($orders_by_status['ready'])): ?>
                                <?php foreach ($orders_by_status['ready'] as $order): ?>
                                    <div class="order-card ready" data-order-id="<?= $order->id ?>">
                                        <div class="order-header">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <h6 class="mb-1">طاولة <?= htmlspecialchars($order->table_number) ?></h6>
                                                    <small class="text-muted">
                                                        <?= htmlspecialchars($order->user_name) ?> • 
                                                        جاهز منذ: <?= $order->waiting_minutes ?> د
                                                    </small>
                                                </div>
                                                <div class="ready-time">
                                                    <span class="badge bg-success">
                                                        <i class="fas fa-clock"></i> جاهز
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="order-items">
                                            <?php foreach ($order->items as $item): ?>
                                                <div class="order-item">
                                                    <div class="item-info">
                                                        <span class="item-name"><?= htmlspecialchars($item->product_name) ?></span>
                                                        <span class="item-quantity">×<?= $item->quantity ?></span>
                                                    </div>
                                                    <div class="item-status">
                                                        <span class="badge bg-success">
                                                            <i class="fas fa-check"></i>
                                                        </span>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        
                                        <div class="order-actions">
                                            <button class="btn btn-sm btn-outline-primary w-100" onclick="markOrderServed(<?= $order->id ?>)">
                                                <i class="fas fa-truck me-1"></i>
                                                تم التسليم
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="no-orders">
                                    <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                                    <p class="text-muted">لا توجد طلبات جاهزة</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Kitchen Metrics Modal -->
    <div class="modal fade" id="metricsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">إحصائيات المطبخ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="metricsContent">
                        <!-- Metrics will be loaded here -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Item Notes Modal -->
    <div class="modal fade" id="itemNotesModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">ملاحظات المطبخ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">ملاحظة للعنصر</label>
                        <textarea class="form-control" id="kitchenNotes" rows="3" 
                                  placeholder="اكتب ملاحظة للمطبخ..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="button" class="btn btn-primary" onclick="saveItemNotes()">حفظ</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Issue Report Modal -->
    <div class="modal fade" id="issueModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">الإبلاغ عن مشكلة</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">نوع المشكلة</label>
                        <select class="form-select" id="issueType">
                            <option value="missing_ingredient">مكون مفقود</option>
                            <option value="equipment_issue">مشكلة في المعدات</option>
                            <option value="quality_issue">مشكلة في الجودة</option>
                            <option value="other">أخرى</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">وصف المشكلة</label>
                        <textarea class="form-control" id="issueDescription" rows="3" 
                                  placeholder="اكتب وصف المشكلة..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="button" class="btn btn-danger" onclick="reportIssue()">الإبلاغ</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= ASSETS_URL ?>/js/kitchen.js"></script>
    
    <script>
        // Initialize Kitchen Dashboard
        document.addEventListener('DOMContentLoaded', function() {
            setupAutoRefresh();
            setupKeyboardShortcuts();
            setupSoundNotifications();
        });

        // Global variables
        let autoRefreshEnabled = <?= $auto_refresh ? 'true' : 'false' ?>;
        let refreshInterval = <?= $refresh_interval ?> * 1000;
        let currentItemId = null;
        let refreshTimer = null;

        // Auto-refresh functionality
        function setupAutoRefresh() {
            if (autoRefreshEnabled) {
                startAutoRefresh();
                document.getElementById('refreshBtn').classList.add('active');
            }
        }

        function toggleAutoRefresh() {
            autoRefreshEnabled = !autoRefreshEnabled;
            const btn = document.getElementById('refreshBtn');
            
            if (autoRefreshEnabled) {
                startAutoRefresh();
                btn.classList.add('active');
                btn.innerHTML = '<i class="fas fa-pause"></i> إيقاف التحديث';
            } else {
                stopAutoRefresh();
                btn.classList.remove('active');
                btn.innerHTML = '<i class="fas fa-sync-alt"></i> تحديث تلقائي';
            }
        }

        function startAutoRefresh() {
            refreshTimer = setInterval(loadKitchenData, refreshInterval);
        }

        function stopAutoRefresh() {
            if (refreshTimer) {
                clearInterval(refreshTimer);
                refreshTimer = null;
            }
        }

        // Load kitchen data
        async function loadKitchenData() {
            try {
                const lastUpdate = document.getElementById('lastUpdate').textContent;
                const response = await fetch(`/kitchen/data?last_update=${encodeURIComponent(lastUpdate)}`);
                const data = await response.json();
                
                if (data.success) {
                    updateKitchenDisplay(data.data);
                    document.getElementById('lastUpdate').textContent = data.data.timestamp;
                    
                    // Play sound notification for new orders
                    if (data.data.new_orders.length > 0) {
                        playNotificationSound();
                    }
                }
            } catch (error) {
                console.error('Error loading kitchen data:', error);
            }
        }

        // Update kitchen display
        function updateKitchenDisplay(data) {
            updateOrderColumn('confirmedOrders', data.updated_orders.filter(o => o.status === 'confirmed'), 'confirmed');
            updateOrderColumn('preparingOrders', data.updated_orders.filter(o => o.status === 'preparing'), 'preparing');
            updateOrderColumn('readyOrders', data.updated_orders.filter(o => o.status === 'ready'), 'ready');
            
            // Update KPI counters
            updateKPICounters(data.updated_orders);
        }

        // Update individual order column
        function updateOrderColumn(columnId, orders, status) {
            const container = document.getElementById(columnId);
            
            if (orders.length === 0) {
                container.innerHTML = `
                    <div class="no-orders">
                        <i class="fas fa-check-circle fa-3x text-${status === 'confirmed' ? 'warning' : status === 'preparing' ? 'primary' : 'success'} mb-3"></i>
                        <p class="text-muted">لا توجد طلبات${getStatusText(status)}</p>
                    </div>
                `;
                return;
            }

            let html = '';
            orders.forEach(order => {
                html += generateOrderCard(order, status);
            });
            
            container.innerHTML = html;
        }

        // Generate order card HTML
        function generateOrderCard(order, status) {
            let actionsHtml = '';
            
            if (status === 'confirmed') {
                actionsHtml = `
                    <button class="btn btn-sm btn-success w-100" onclick="startPreparing(${order.id})">
                        <i class="fas fa-play me-1"></i>
                        بدء التحضير
                    </button>
                `;
            } else if (status === 'preparing') {
                actionsHtml = `
                    <button class="btn btn-sm btn-success w-100" onclick="markOrderReady(${order.id})">
                        <i class="fas fa-check-double me-1"></i>
                        تحديد الطلب كجاهز
                    </button>
                `;
            } else if (status === 'ready') {
                actionsHtml = `
                    <button class="btn btn-sm btn-outline-primary w-100" onclick="markOrderServed(${order.id})">
                        <i class="fas fa-truck me-1"></i>
                        تم التسليم
                    </button>
                `;
            }

            return `
                <div class="order-card ${status}" data-order-id="${order.id}">
                    <div class="order-header">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="mb-1">طاولة ${order.table_number}</h6>
                                <small class="text-muted">
                                    ${order.user_name} • 
                                    ${new Date(order.created_at).toLocaleTimeString('ar-SA')}
                                </small>
                            </div>
                            <div class="waiting-time">
                                <span class="badge bg-${status === 'confirmed' ? 'danger' : status === 'preparing' ? 'info' : 'success'}">
                                    ${order.waiting_minutes} د
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="order-items">
                        ${order.items.map(item => `
                            <div class="order-item" data-item-id="${item.id}">
                                <div class="item-info">
                                    <span class="item-name">${item.product_name}</span>
                                    <span class="item-quantity">×${item.quantity}</span>
                                    ${item.kitchen_notes ? `
                                        <div class="item-notes kitchen-notes">
                                            <i class="fas fa-utensils me-1"></i>
                                            ${item.kitchen_notes}
                                        </div>
                                    ` : ''}
                                </div>
                                ${status === 'preparing' && item.status !== 'ready' ? `
                                    <div class="item-actions">
                                        <button class="btn btn-sm btn-outline-success" onclick="markItemReady(${item.id})">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    </div>
                                ` : ''}
                            </div>
                        `).join('')}
                    </div>
                    
                    <div class="order-actions">
                        ${actionsHtml}
                    </div>
                </div>
            `;
        }

        // Update KPI counters
        function updateKPICounters(orders) {
            const counts = {
                confirmed: orders.filter(o => o.status === 'confirmed').length,
                preparing: orders.filter(o => o.status === 'preparing').length,
                ready: orders.filter(o => o.status === 'ready').length,
                total: orders.filter(o => ['confirmed', 'preparing', 'ready'].includes(o.status)).length
            };

            // Update the display
            const kpiCards = document.querySelectorAll('.kpi-card');
            kpiCards[0].querySelector('h4').textContent = counts.confirmed;
            kpiCards[1].querySelector('h4').textContent = counts.preparing;
            kpiCards[2].querySelector('h4').textContent = counts.ready;
            kpiCards[3].querySelector('h4').textContent = counts.total;
        }

        // Get status text in Arabic
        function getStatusText(status) {
            const statusMap = {
                'confirmed': ' في الانتظار',
                'preparing': ' قيد التحضير',
                'ready': ' جاهزة للتسليم'
            };
            return statusMap[status] || '';
        }

        // Kitchen actions
        async function startPreparing(orderId) {
            try {
                const response = await fetch(`/kitchen/start-preparing/${orderId}`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-Token': getCsrfToken()
                    }
                });

                const data = await response.json();
                
                if (data.success) {
                    showNotification('تم بدء تحضير الطلب', 'success');
                    loadKitchenData();
                } else {
                    showNotification(data.message || 'خطأ في بدء التحضير', 'error');
                }
            } catch (error) {
                showNotification('خطأ في الاتصال', 'error');
            }
        }

        async function markItemReady(itemId) {
            try {
                const response = await fetch(`/kitchen/mark-item-ready/${itemId}`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-Token': getCsrfToken()
                    }
                });

                const data = await response.json();
                
                if (data.success) {
                    showNotification('تم تحديد العنصر كجاهز', 'success');
                    loadKitchenData();
                } else {
                    showNotification(data.message || 'خطأ في تحديد العنصر', 'error');
                }
            } catch (error) {
                showNotification('خطأ في الاتصال', 'error');
            }
        }

        async function markOrderReady(orderId) {
            if (!confirm('هل أنت متأكد من أن الطلب جاهز؟')) return;

            try {
                const response = await fetch(`/kitchen/mark-order-ready/${orderId}`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-Token': getCsrfToken()
                    }
                });

                const data = await response.json();
                
                if (data.success) {
                    showNotification('تم تحديد الطلب كجاهز', 'success');
                    loadKitchenData();
                } else {
                    showNotification(data.message || 'خطأ في تحديد الطلب', 'error');
                }
            } catch (error) {
                showNotification('خطأ في الاتصال', 'error');
            }
        }

        async function markOrderServed(orderId) {
            if (!confirm('هل تم تسليم الطلب؟')) return;

            try {
                const response = await fetch(`/kitchen/mark-served/${orderId}`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-Token': getCsrfToken()
                    }
                });

                const data = await response.json();
                
                if (data.success) {
                    showNotification('تم تحديد الطلب كمُسلم', 'success');
                    loadKitchenData();
                } else {
                    showNotification(data.message || 'خطأ في تحديد الطلب', 'error');
                }
            } catch (error) {
                showNotification('خطأ في الاتصال', 'error');
            }
        }

        // Show metrics
        async function showMetrics() {
            try {
                const response = await fetch('/kitchen/metrics');
                const data = await response.json();
                
                if (data.success) {
                    displayMetrics(data.metrics);
                    const modal = new bootstrap.Modal(document.getElementById('metricsModal'));
                    modal.show();
                }
            } catch (error) {
                showNotification('خطأ في تحميل الإحصائيات', 'error');
            }
        }

        // Display metrics
        function displayMetrics(metrics) {
            const container = document.getElementById('metricsContent');
            container.innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <h6>إحصائيات اليوم</h6>
                        <ul class="list-group">
                            <li class="list-group-item d-flex justify-content-between">
                                إجمالي الطلبات
                                <span class="badge bg-primary rounded-pill">${metrics.total_orders || 0}</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                متوسط وقت التحضير
                                <span class="badge bg-info rounded-pill">${Math.round(metrics.avg_preparation_time || 0)} دقيقة</span>
                            </li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6>الحالة الحالية</h6>
                        <ul class="list-group">
                            <li class="list-group-item d-flex justify-content-between">
                                طلبات جاهزة
                                <span class="badge bg-success rounded-pill">${metrics.orders_ready || 0}</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                طلبات قيد التحضير
                                <span class="badge bg-warning rounded-pill">${metrics.orders_preparing || 0}</span>
                            </li>
                        </ul>
                    </div>
                </div>
            `;
        }

        // Toggle fullscreen
        function toggleFullscreen() {
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen();
            } else {
                document.exitFullscreen();
            }
        }

        // Sound notifications
        function setupSoundNotifications() {
            // Create audio context for notifications
            if (typeof AudioContext !== 'undefined') {
                window.audioContext = new AudioContext();
            }
        }

        function playNotificationSound() {
            if (window.audioContext) {
                // Play a simple beep sound
                const oscillator = window.audioContext.createOscillator();
                const gainNode = window.audioContext.createGain();
                
                oscillator.connect(gainNode);
                gainNode.connect(window.audioContext.destination);
                
                oscillator.frequency.value = 800;
                oscillator.type = 'sine';
                
                gainNode.gain.setValueAtTime(0.3, window.audioContext.currentTime);
                gainNode.gain.exponentialRampToValueAtTime(0.01, window.audioContext.currentTime + 0.5);
                
                oscillator.start(window.audioContext.currentTime);
                oscillator.stop(window.audioContext.currentTime + 0.5);
            }
        }

        // Utility functions
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

        function setupKeyboardShortcuts() {
            document.addEventListener('keydown', function(e) {
                // R to toggle refresh
                if (e.key === 'r' && e.ctrlKey) {
                    e.preventDefault();
                    toggleAutoRefresh();
                }
                
                // F to toggle fullscreen
                if (e.key === 'f' && e.ctrlKey) {
                    e.preventDefault();
                    toggleFullscreen();
                }
                
                // M to show metrics
                if (e.key === 'm' && e.ctrlKey) {
                    e.preventDefault();
                    showMetrics();
                }
            });
        }

        // Page visibility change
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                stopAutoRefresh();
            } else if (autoRefreshEnabled) {
                startAutoRefresh();
                loadKitchenData(); // Immediate refresh when page becomes visible
            }
        });
    </script>
</body>
</html>