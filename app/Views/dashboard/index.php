<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة التحكم - Restaurant POS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/chart.js" rel="stylesheet">
    <link href="/assets/css/dashboard.css" rel="stylesheet">
</head>
<body>
    <div class="dashboard-container">
        <!-- Header -->
        <div class="dashboard-header">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h1 class="dashboard-title">
                        <i class="fas fa-tachometer-alt me-2"></i>
                        لوحة التحكم
                    </h1>
                    <p class="dashboard-subtitle">مرحباً بك في نظام إدارة المطعم</p>
                </div>
                <div class="col-md-6">
                    <div class="dashboard-controls text-end">
                        <div class="date-range-selector">
                            <input type="date" id="dateFrom" class="form-control form-control-sm" 
                                   value="<?= $date_from ?>" onchange="updateDashboard()">
                            <span class="mx-2">إلى</span>
                            <input type="date" id="dateTo" class="form-control form-control-sm" 
                                   value="<?= $date_to ?>" onchange="updateDashboard()">
                        </div>
                        <div class="control-buttons mt-2">
                            <button class="btn btn-sm btn-outline-primary" onclick="refreshDashboard()">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                                    data-bs-toggle="dropdown">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="/reports">
                                    <i class="fas fa-chart-bar me-2"></i>التقارير
                                </a></li>
                                <li><a class="dropdown-item" href="#" onclick="exportDashboard()">
                                    <i class="fas fa-download me-2"></i>تصدير البيانات
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="#" onclick="customizeLayout()">
                                    <i class="fas fa-cog me-2"></i>تخصيص التخطيط
                                </a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="kpi-card revenue-card">
                    <div class="kpi-icon">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="kpi-content">
                        <div class="kpi-value">
                            <?= number_format($dashboard_stats['sales']['total_sales'] ?? 0, 2) ?> ر.س
                        </div>
                        <div class="kpi-label">إجمالي المبيعات</div>
                        <div class="kpi-trend up">
                            <i class="fas fa-arrow-up"></i> +12.5%
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="kpi-card orders-card">
                    <div class="kpi-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="kpi-content">
                        <div class="kpi-value">
                            <?= number_format($dashboard_stats['sales']['total_orders'] ?? 0) ?>
                        </div>
                        <div class="kpi-label">إجمالي الطلبات</div>
                        <div class="kpi-trend up">
                            <i class="fas fa-arrow-up"></i> +8.2%
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="kpi-card avg-order-card">
                    <div class="kpi-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="kpi-content">
                        <div class="kpi-value">
                            <?= number_format($dashboard_stats['sales']['average_order_value'] ?? 0, 2) ?> ر.س
                        </div>
                        <div class="kpi-label">متوسط قيمة الطلب</div>
                        <div class="kpi-trend stable">
                            <i class="fas fa-minus"></i> 0.0%
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="kpi-card inventory-card">
                    <div class="kpi-icon">
                        <i class="fas fa-boxes"></i>
                    </div>
                    <div class="kpi-content">
                        <div class="kpi-value">
                            <?= number_format($dashboard_stats['inventory']['low_stock_items'] ?? 0) ?>
                        </div>
                        <div class="kpi-label">أصناف منخفضة المخزون</div>
                        <div class="kpi-trend down">
                            <i class=""></i> -fas fa-arrow-down2.1%
                        </div>
                    </div>
                </div>
        </div>

            </div>
        <!-- Alerts Section -->
        <?php if (!empty($alerts)): ?>
            <div class="alerts-section mb-4">
                <div class="alert-section-header">
                    <h5>
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        التنبيهات والإشعارات
                    </h5>
                </div>
                <div class="alerts-container">
                    <?php foreach ($alerts as $alert): ?>
                        <div class="alert alert-<?= $alert['type'] === 'danger' ? 'danger' : ($alert['type'] === 'warning' ? 'warning' : 'info') ?> alert-dismissible fade show" 
                             role="alert">
                            <div class="alert-content">
                                <div class="alert-title">
                                    <i class="fas fa-<?= $alert['type'] === 'danger' ? 'exclamation-circle' : ($alert['type'] === 'warning' ? 'exclamation-triangle' : 'info-circle') ?> me-2"></i>
                                    <?= htmlspecialchars($alert['title']) ?>
                                </div>
                                <div class="alert-message"><?= htmlspecialchars($alert['message']) ?></div>
                            </div>
                            <div class="alert-actions">
                                <a href="<?= htmlspecialchars($alert['link']) ?>" class="btn btn-sm btn-outline-primary">
                                    عرض التفاصيل
                                </a>
                                <button type="button" class="btn-close" 
                                        onclick="markAlertRead('<?= $alert['id'] ?>')"></button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Charts Row -->
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="chart-card">
                    <div class="chart-header">
                        <h5>
                            <i class="fas fa-chart-line me-2"></i>
                            المبيعات اليومية
                        </h5>
                        <div class="chart-controls">
                            <button class="btn btn-sm btn-outline-secondary active" 
                                    onclick="updateSalesChart('daily')">يومي</button>
                            <button class="btn btn-sm btn-outline-secondary" 
                                    onclick="updateSalesChart('weekly')">أسبوعي</button>
                            <button class="btn btn-sm btn-outline-secondary" 
                                    onclick="updateSalesChart('monthly')">شهري</button>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="salesChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="chart-card">
                    <div class="chart-header">
                        <h5>
                            <i class="fas fa-chart-pie me-2"></i>
                            طرق الدفع
                        </h5>
                    </div>
                    <div class="chart-container">
                        <canvas id="paymentMethodsChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Data Tables Row -->
        <div class="row">
            <div class="col-md-6">
                <div class="data-table-card">
                    <div class="table-header">
                        <h5>
                            <i class="fas fa-star me-2"></i>
                            أفضل المنتجات مبيعاً
                        </h5>
                        <a href="/reports/sales" class="btn btn-sm btn-outline-primary">
                            عرض التفاصيل
                        </a>
                    </div>
                    <div class="table-container">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>المنتج</th>
                                    <th>الكمية</th>
                                    <th>الإيرادات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($dashboard_stats['top_products'] ?? [], 0, 5) as $product): ?>
                                    <tr>
                                        <td>
                                            <div class="product-info">
                                                <strong><?= htmlspecialchars($product['name']) ?></strong>
                                            </div>
                                        </td>
                                        <td><?= number_format($product['total_sold']) ?></td>
                                        <td class="text-success">
                                            <?= number_format($product['total_revenue'], 2) ?> ر.س
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="data-table-card">
                    <div class="table-header">
                        <h5>
                            <i class="fas fa-clock me-2"></i>
                            الطلبات الحديثة
                        </h5>
                        <a href="/orders" class="btn btn-sm btn-outline-primary">
                            عرض الكل
                        </a>
                    </div>
                    <div class="table-container">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>رقم الطلب</th>
                                    <th>الطاولة</th>
                                    <th>المبلغ</th>
                                    <th>الحالة</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>#12345</td>
                                    <td>طاولة 5</td>
                                    <td>150 ر.س</td>
                                    <td><span class="badge bg-success">مكتمل</span></td>
                                </tr>
                                <tr>
                                    <td>#12344</td>
                                    <td>طاولة 3</td>
                                    <td>85 ر.س</td>
                                    <td><span class="badge bg-warning">قيد التحضير</span></td>
                                </tr>
                                <tr>
                                    <td>#12343</td>
                                    <td>طاولة 1</td>
                                    <td>220 ر.س</td>
                                    <td><span class="badge bg-info">جاهز</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Real-time Status -->
        <div class="realtime-status">
            <div class="status-item">
                <i class="fas fa-circle text-success"></i>
                <span>النظام متصل</span>
            </div>
            <div class="status-item">
                <i class="fas fa-sync-alt fa-spin"></i>
                <span>آخر تحديث: <span id="lastUpdate"><?= date('H:i:s') ?></span></span>
            </div>
            <div class="status-item">
                <i class="fas fa-database"></i>
                <span>قاعدة البيانات: متصلة</span>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="loading-overlay" style="display: none;">
        <div class="loading-content">
            <div class="spinner"></div>
            <div class="loading-text">جاري التحميل...</div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="/assets/js/dashboard.js"></script>
    <script>
        // Initialize dashboard
        document.addEventListener('DOMContentLoaded', function() {
            DashboardManager.init();
        });

        // Global functions
        function updateDashboard() {
            if (window.dashboardManager) {
                window.dashboardManager.updateDateRange();
            }
        }

        function refreshDashboard() {
            if (window.dashboardManager) {
                window.dashboardManager.refresh();
            }
        }

        function updateSalesChart(period) {
            if (window.dashboardManager) {
                window.dashboardManager.updateSalesChart(period);
            }
        }

        function exportDashboard() {
            if (window.dashboardManager) {
                window.dashboardManager.export();
            }
        }

        function customizeLayout() {
            // This would open a layout customization modal
            alert('سيتم إضافة خاصية تخصيص التخطيط قريباً');
        }

        function markAlertRead(alertId) {
            fetch(`/dashboard/alerts/${alertId}/read`, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            });
        }
    </script>
</body>
</html>
        }
        
        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
            font-weight: bold;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
        }
        
        .stat-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 15px;
        }
        
        .stat-title {
            font-size: 14px;
            color: #6c757d;
            font-weight: 500;
        }
        
        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }
        
        .stat-value {
            font-size: 28px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .stat-change {
            font-size: 12px;
            color: #6c757d;
        }
        
        .stat-change.positive {
            color: #27ae60;
        }
        
        .stat-change.negative {
            color: #e74c3c;
        }
        
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .content-section {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .section-header {
            padding: 20px;
            border-bottom: 1px solid #dee2e6;
            background: #f8f9fa;
        }
        
        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: #2c3e50;
            margin: 0;
        }
        
        .section-body {
            padding: 20px;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .table-status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 10px;
        }
        
        .table-card {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 6px;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .table-card:hover {
            border-color: #3498db;
        }
        
        .table-card.available {
            border-color: #27ae60;
            background: #d4edda;
        }
        
        .table-card.occupied {
            border-color: #f39c12;
            background: #fff3cd;
        }
        
        .table-card.reserved {
            border-color: #17a2b8;
            background: #d1ecf1;
        }
        
        .table-card.cleaning {
            border-color: #6c757d;
            background: #e2e6ea;
        }
        
        .table-number {
            font-size: 18px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .table-capacity {
            font-size: 12px;
            color: #6c757d;
        }
        
        .order-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .order-item:last-child {
            border-bottom: none;
        }
        
        .order-info {
            flex: 1;
        }
        
        .order-number {
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .order-meta {
            font-size: 12px;
            color: #6c757d;
        }
        
        .order-status {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .order-status.draft {
            background: #e2e6ea;
            color: #6c757d;
        }
        
        .order-status.sent_to_kitchen {
            background: #cce5ff;
            color: #004085;
        }
        
        .order-status.preparing {
            background: #fff3cd;
            color: #856404;
        }
        
        .order-status.ready {
            background: #d4edda;
            color: #155724;
        }
        
        .order-status.served {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .chart-container {
            height: 300px;
            margin: 20px 0;
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .quick-action {
            background: white;
            border: none;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            color: #2c3e50;
        }
        
        .quick-action:hover {
            background: #f8f9fa;
            transform: translateY(-2px);
            text-decoration: none;
            color: #2c3e50;
        }
        
        .quick-action-icon {
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .quick-action-title {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .quick-action-desc {
            font-size: 12px;
            color: #6c757d;
        }
        
        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .dashboard-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <!-- Dashboard Header -->
        <div class="dashboard-header">
            <div class="welcome-section">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($user->first_name ?? 'A', 0, 1)); ?>
                </div>
                <div>
                    <h1 style="margin: 0; font-size: 24px;">مرحباً <?php echo e($user->first_name ?? ''); ?>!</h1>
                    <p style="margin: 0; color: #6c757d;">لوحة التحكم الرئيسية</p>
                </div>
            </div>
            
            <div class="quick-actions">
                <a href="/pos" class="quick-action">
                    <div class="quick-action-icon">💳</div>
                    <div class="quick-action-title">نقاط البيع</div>
                    <div class="quick-action-desc">إنشاء طلب جديد</div>
                </a>
                
                <a href="/orders" class="quick-action">
                    <div class="quick-action-icon">📋</div>
                    <div class="quick-action-title">الطلبات</div>
                    <div class="quick-action-desc">عرض جميع الطلبات</div>
                </a>
                
                <a href="/products" class="quick-action">
                    <div class="quick-action-icon">🍽️</div>
                    <div class="quick-action-title">المنتجات</div>
                    <div class="quick-action-desc">إدارة المنتجات</div>
                </a>
                
                <?php if (auth()->can('reports.view')): ?>
                <a href="/reports" class="quick-action">
                    <div class="quick-action-icon">📊</div>
                    <div class="quick-action-title">التقارير</div>
                    <div class="quick-action-desc">عرض التقارير</div>
                </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Error Alert -->
        <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <?php echo e($error); ?>
        </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <!-- Today's Sales -->
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">مبيعات اليوم</div>
                    <div class="stat-icon" style="background: #e8f5e8; color: #27ae60;">💰</div>
                </div>
                <div class="stat-value"><?php echo format_currency($stats['today_sales']['total']); ?></div>
                <div class="stat-change">
                    <?php echo $stats['today_sales']['orders']; ?> طلب • متوسط <?php echo format_currency($stats['today_sales']['avg_order']); ?>
                </div>
            </div>

            <!-- Active Orders -->
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">الطلبات النشطة</div>
                    <div class="stat-icon" style="background: #fff3cd; color: #f39c12;">⏳</div>
                </div>
                <div class="stat-value"><?php echo $stats['active_orders']; ?></div>
                <div class="stat-change">قيد التحضير والتسليم</div>
            </div>

            <!-- Monthly Sales -->
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">مبيعات الشهر</div>
                    <div class="stat-icon" style="background: #d1ecf1; color: #17a2b8;">📈</div>
                </div>
                <div class="stat-value"><?php echo format_currency($stats['month_sales']['total']); ?></div>
                <div class="stat-change">
                    <?php echo $stats['month_sales']['orders']; ?> طلب
                </div>
            </div>

            <!-- Tables Status -->
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">حالة الطاولات</div>
                    <div class="stat-icon" style="background: #f8d7da; color: #e74c3c;">🪑</div>
                </div>
                <div class="stat-value"><?php echo $stats['available_tables']; ?>/<?php echo $stats['total_tables']; ?></div>
                <div class="stat-change">متاحة من إجمالي <?php echo $stats['total_tables']; ?> طاولة</div>
            </div>

            <!-- Products Count -->
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">المنتجات المتاحة</div>
                    <div class="stat-icon" style="background: #e2e3e5; color: #6c757d;">🍽️</div>
                </div>
                <div class="stat-value"><?php echo $stats['total_products']; ?></div>
                <div class="stat-change">منتج متاح</div>
            </div>

            <!-- Total Customers -->
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">عملاء اليوم</div>
                    <div class="stat-icon" style="background: #e8d5e8; color: #8e44ad;">👥</div>
                </div>
                <div class="stat-value"><?php echo $stats['total_customers']; ?></div>
                <div class="stat-change">عميل اليوم</div>
            </div>
        </div>

        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Recent Orders -->
            <div class="content-section">
                <div class="section-header">
                    <h3 class="section-title">الطلبات الأخيرة</h3>
                </div>
                <div class="section-body">
                    <?php if (!empty($recentOrders)): ?>
                        <?php foreach ($recentOrders as $order): ?>
                        <div class="order-item">
                            <div class="order-info">
                                <div class="order-number">#<?php echo e($order->order_number); ?></div>
                                <div class="order-meta">
                                    <?php if ($order->table_number): ?>
                                        طاولة <?php echo e($order->table_number); ?> •
                                    <?php endif; ?>
                                    <?php echo e($order->waiter_name ?? 'غير محدد'); ?> •
                                    <?php echo format_datetime($order->created_at); ?>
                                </div>
                            </div>
                            <div class="order-status <?php echo e($order->status); ?>">
                                <?php
                                $statusLabels = [
                                    'draft' => 'مسودة',
                                    'sent_to_kitchen' => 'أرسل للمطبخ',
                                    'preparing' => 'قيد التحضير',
                                    'ready' => 'جاهز',
                                    'served' => 'تم التقديم',
                                    'out_for_delivery' => 'في التوصيل',
                                    'closed' => 'مغلق',
                                    'cancelled' => 'ملغي'
                                ];
                                echo $statusLabels[$order->status] ?? $order->status;
                                ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="text-align: center; color: #6c757d; padding: 20px;">لا توجد طلبات حالياً</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Active Tables -->
            <div class="content-section">
                <div class="section-header">
                    <h3 class="section-title">حالة الطاولات</h3>
                </div>
                <div class="section-body">
                    <?php if (!empty($activeTables)): ?>
                        <div class="table-status-grid">
                            <?php foreach ($activeTables as $table): ?>
                            <div class="table-card <?php echo e($table->status); ?>" 
                                 title="<?php echo e($table->area_name); ?> - <?php echo e($table->status); ?>">
                                <div class="table-number"><?php echo e($table->table_number); ?></div>
                                <div class="table-capacity"><?php echo e($table->capacity); ?> أشخاص</div>
                                <?php if ($table->active_orders > 0): ?>
                                <div style="font-size: 10px; margin-top: 5px; color: #e74c3c;">
                                    <?php echo $table->active_orders; ?> طلب
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p style="text-align: center; color: #6c757d; padding: 20px;">لا توجد طاولات حالياً</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Popular Products -->
        <?php if (!empty($popularProducts)): ?>
        <div class="content-section" style="margin-top: 20px;">
            <div class="section-header">
                <h3 class="section-title">المنتجات الأكثر مبيعاً</h3>
            </div>
            <div class="section-body">
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 15px;">
                    <?php foreach ($popularProducts as $product): ?>
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 6px; border: 1px solid #dee2e6;">
                        <div style="font-weight: bold; margin-bottom: 5px;"><?php echo e($product->name_ar); ?></div>
                        <div style="font-size: 12px; color: #6c757d; margin-bottom: 10px;">
                            <?php echo e($product->total_sold); ?> مبيع • <?php echo format_currency($product->total_revenue); ?>
                        </div>
                        <div style="font-weight: bold; color: #27ae60;"><?php echo format_currency($product->base_price); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Current Shift Info -->
        <?php if ($currentShift): ?>
        <div class="content-section" style="margin-top: 20px;">
            <div class="section-header">
                <h3 class="section-title">الوردية الحالية</h3>
            </div>
            <div class="section-body">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                    <div>
                        <div style="font-size: 12px; color: #6c757d;">مفتوحة بواسطة</div>
                        <div style="font-weight: bold;"><?php echo e($currentShift->opened_by_name); ?></div>
                    </div>
                    <div>
                        <div style="font-size: 12px; color: #6c757d;">وقت الفتح</div>
                        <div style="font-weight: bold;"><?php echo format_datetime($currentShift->opened_at); ?></div>
                    </div>
                    <div>
                        <div style="font-size: 12px; color: #6c757d;">النقد الافتتاحي</div>
                        <div style="font-weight: bold; color: #27ae60;"><?php echo format_currency($currentShift->opening_cash); ?></div>
                    </div>
                    <div>
                        <div style="font-size: 12px; color: #6c757d;">المبيعات</div>
                        <div style="font-weight: bold; color: #3498db;"><?php echo format_currency($currentShift->total_sales); ?></div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Auto-refresh dashboard every 30 seconds
        setInterval(function() {
            location.reload();
        }, 30000);

        // Initialize charts
        function initCharts() {
            // Sales Chart
            const ctx = document.getElementById('salesChart');
            if (ctx) {
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: <?php echo json_encode(array_map(function($hour) {
                            return sprintf('%02d:00', $hour);
                        }, range(0, 23))); ?>,
                        datasets: [{
                            label: 'المبيعات',
                            data: <?php echo json_encode(array_fill(0, 24, 0)); ?>,
                            borderColor: 'rgb(52, 152, 219)',
                            backgroundColor: 'rgba(52, 152, 219, 0.2)',
                            tension: 0.1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            }
        }

        // Initialize on load
        document.addEventListener('DOMContentLoaded', function() {
            initCharts();
        });
    </script>
</body>
</html>