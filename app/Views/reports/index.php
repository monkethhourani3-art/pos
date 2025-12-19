<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>التقارير والتحليلات - Restaurant POS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/chart.js" rel="stylesheet">
    <link href="/assets/css/reports.css" rel="stylesheet">
</head>
<body>
    <div class="reports-container">
        <!-- Header -->
        <div class="reports-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="reports-title">
                        <i class="fas fa-chart-bar me-2"></i>
                        التقارير والتحليلات
                    </h1>
                    <p class="reports-subtitle">تحليل شامل لأداء المطعم والإحصائيات</p>
                </div>
                <div class="col-md-4">
                    <div class="reports-controls text-end">
                        <div class="date-range-selector mb-2">
                            <input type="date" id="dateFrom" class="form-control form-control-sm" 
                                   value="<?= $date_from ?? date('Y-m-01') ?>" onchange="updateReports()">
                            <span class="mx-2">إلى</span>
                            <input type="date" id="dateTo" class="form-control form-control-sm" 
                                   value="<?= $date_to ?? date('Y-m-d') ?>" onchange="updateReports()">
                        </div>
                        <div class="control-buttons">
                            <button class="btn btn-sm btn-outline-primary" onclick="refreshReports()">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-success" onclick="exportReports()">
                                <i class="fas fa-download"></i> تصدير
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reports Navigation -->
        <div class="reports-nav mb-4">
            <div class="nav-tabs">
                <button class="nav-tab active" data-report="overview">
                    <i class="fas fa-tachometer-alt me-2"></i>نظرة عامة
                </button>
                <button class="nav-tab" data-report="sales">
                    <i class="fas fa-chart-line me-2"></i>تقرير المبيعات
                </button>
                <button class="nav-tab" data-report="products">
                    <i class="fas fa-utensils me-2"></i>تقرير المنتجات
                </button>
                <button class="nav-tab" data-report="users">
                    <i class="fas fa-users me-2"></i>تقرير المستخدمين
                </button>
                <button class="nav-tab" data-report="shifts">
                    <i class="fas fa-clock me-2"></i>تقرير الورديات
                </button>
                <button class="nav-tab" data-report="cash">
                    <i class="fas fa-cash-register me-2"></i>تقرير النقدية
                </button>
                <button class="nav-tab" data-report="tax">
                    <i class="fas fa-percentage me-2"></i>تقرير الضرائب
                </button>
            </div>
        </div>

        <!-- Overview Report (Default) -->
        <div id="overview-report" class="report-section active">
            <!-- KPI Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="kpi-card total-sales-card">
                        <div class="kpi-icon">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                        <div class="kpi-content">
                            <div class="kpi-value">
                                <?= number_format($overview_stats['total_sales'] ?? 0, 2) ?> ر.س
                            </div>
                            <div class="kpi-label">إجمالي المبيعات</div>
                            <div class="kpi-trend <?= ($overview_stats['sales_growth'] ?? 0) >= 0 ? 'up' : 'down' ?>">
                                <i class="fas fa-<?= ($overview_stats['sales_growth'] ?? 0) >= 0 ? 'arrow-up' : 'arrow-down' ?>"></i> 
                                <?= number_format(abs($overview_stats['sales_growth'] ?? 0), 1) ?>%
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
                                <?= number_format($overview_stats['total_orders'] ?? 0) ?>
                            </div>
                            <div class="kpi-label">إجمالي الطلبات</div>
                            <div class="kpi-trend <?= ($overview_stats['orders_growth'] ?? 0) >= 0 ? 'up' : 'down' ?>">
                                <i class="fas fa-<?= ($overview_stats['orders_growth'] ?? 0) >= 0 ? 'arrow-up' : 'arrow-down' ?>"></i> 
                                <?= number_format(abs($overview_stats['orders_growth'] ?? 0), 1) ?>%
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
                                <?= number_format($overview_stats['average_order_value'] ?? 0, 2) ?> ر.س
                            </div>
                            <div class="kpi-label">متوسط قيمة الطلب</div>
                            <div class="kpi-trend <?= ($overview_stats['avg_growth'] ?? 0) >= 0 ? 'up' : 'down' ?>">
                                <i class="fas fa-<?= ($overview_stats['avg_growth'] ?? 0) >= 0 ? 'arrow-up' : 'arrow-down' ?>"></i> 
                                <?= number_format(abs($overview_stats['avg_growth'] ?? 0), 1) ?>%
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="kpi-card customers-card">
                        <div class="kpi-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="kpi-content">
                            <div class="kpi-value">
                                <?= number_format($overview_stats['unique_customers'] ?? 0) ?>
                            </div>
                            <div class="kpi-label">العملاء الفريدين</div>
                            <div class="kpi-trend <?= ($overview_stats['customers_growth'] ?? 0) >= 0 ? 'up' : 'down' ?>">
                                <i class="fas fa-<?= ($overview_stats['customers_growth'] ?? 0) >= 0 ? 'arrow-up' : 'arrow-down' ?>"></i> 
                                <?= number_format(abs($overview_stats['customers_growth'] ?? 0), 1) ?>%
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="row mb-4">
                <div class="col-md-8">
                    <div class="chart-card">
                        <div class="chart-header">
                            <h5>
                                <i class="fas fa-chart-line me-2"></i>
                                اتجاهات المبيعات
                            </h5>
                            <div class="chart-controls">
                                <button class="btn btn-sm btn-outline-secondary active" 
                                        onclick="updateSalesTrend('daily')">يومي</button>
                                <button class="btn btn-sm btn-outline-secondary" 
                                        onclick="updateSalesTrend('weekly')">أسبوعي</button>
                                <button class="btn btn-sm btn-outline-secondary" 
                                        onclick="updateSalesTrend('monthly')">شهري</button>
                            </div>
                        </div>
                        <div class="chart-container">
                            <canvas id="salesTrendChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="chart-card">
                        <div class="chart-header">
                            <h5>
                                <i class="fas fa-chart-pie me-2"></i>
                                توزيع المبيعات
                            </h5>
                        </div>
                        <div class="chart-container">
                            <canvas id="salesDistributionChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top Products and Performance -->
            <div class="row">
                <div class="col-md-6">
                    <div class="data-table-card">
                        <div class="table-header">
                            <h5>
                                <i class="fas fa-star me-2"></i>
                                أفضل المنتجات مبيعاً
                            </h5>
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
                                    <?php foreach ($overview_stats['top_products'] ?? [] as $product): ?>
                                        <tr>
                                            <td>
                                                <div class="product-info">
                                                    <strong><?= htmlspecialchars($product['name']) ?></strong>
                                                </div>
                                            </td>
                                            <td><?= number_format($product['quantity']) ?></td>
                                            <td class="text-success">
                                                <?= number_format($product['revenue'], 2) ?> ر.س
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
                                <i class="fas fa-chart-bar me-2"></i>
                                أداء الموظفين
                            </h5>
                        </div>
                        <div class="table-container">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>الموظف</th>
                                        <th>الطلبات</th>
                                        <th>المبيعات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($overview_stats['top_staff'] ?? [] as $staff): ?>
                                        <tr>
                                            <td>
                                                <div class="staff-info">
                                                    <strong><?= htmlspecialchars($staff['name']) ?></strong>
                                                </div>
                                            </td>
                                            <td><?= number_format($staff['orders_count']) ?></td>
                                            <td class="text-primary">
                                                <?= number_format($staff['sales'], 2) ?> ر.س
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sales Report -->
        <div id="sales-report" class="report-section">
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="chart-card">
                        <div class="chart-header">
                            <h5>
                                <i class="fas fa-chart-line me-2"></i>
                                تحليل المبيعات التفصيلي
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
                            <canvas id="detailedSalesChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="data-table-card">
                        <div class="table-header">
                            <h5>
                                <i class="fas fa-table me-2"></i>
                                تفاصيل المبيعات
                            </h5>
                            <button class="btn btn-sm btn-outline-success" onclick="exportSalesData()">
                                <i class="fas fa-download"></i> تصدير
                            </button>
                        </div>
                        <div class="table-container">
                            <table class="table table-striped" id="salesTable">
                                <thead>
                                    <tr>
                                        <th>التاريخ</th>
                                        <th>عدد الطلبات</th>
                                        <th>إجمالي المبيعات</th>
                                        <th>متوسط الطلب</th>
                                        <th>العملاء</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($sales_data ?? [] as $day): ?>
                                        <tr>
                                            <td><?= date('Y-m-d', strtotime($day['date'])) ?></td>
                                            <td><?= number_format($day['orders']) ?></td>
                                            <td class="text-success">
                                                <?= number_format($day['total'], 2) ?> ر.س
                                            </td>
                                            <td><?= number_format($day['average'], 2) ?> ر.س</td>
                                            <td><?= number_format($day['customers']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Products Report -->
        <div id="products-report" class="report-section">
            <div class="row mb-4">
                <div class="col-md-8">
                    <div class="chart-card">
                        <div class="chart-header">
                            <h5>
                                <i class="fas fa-chart-bar me-2"></i>
                                أداء المنتجات
                            </h5>
                        </div>
                        <div class="chart-container">
                            <canvas id="productsChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="chart-card">
                        <div class="chart-header">
                            <h5>
                                <i class="fas fa-chart-pie me-2"></i>
                                مبيعات حسب الفئة
                            </h5>
                        </div>
                        <div class="chart-container">
                            <canvas id="categoryChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="data-table-card">
                        <div class="table-header">
                            <h5>
                                <i class="fas fa-utensils me-2"></i>
                                تقرير المنتجات المفصل
                            </h5>
                            <button class="btn btn-sm btn-outline-success" onclick="exportProductsData()">
                                <i class="fas fa-download"></i> تصدير
                            </button>
                        </div>
                        <div class="table-container">
                            <table class="table table-striped" id="productsTable">
                                <thead>
                                    <tr>
                                        <th>المنتج</th>
                                        <th>الفئة</th>
                                        <th>الكمية المباعة</th>
                                        <th>الإيرادات</th>
                                        <th>متوسط السعر</th>
                                        <th>الهامش</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($products_data ?? [] as $product): ?>
                                        <tr>
                                            <td>
                                                <div class="product-info">
                                                    <strong><?= htmlspecialchars($product['name']) ?></strong>
                                                </div>
                                            </td>
                                            <td><?= htmlspecialchars($product['category']) ?></td>
                                            <td><?= number_format($product['quantity']) ?></td>
                                            <td class="text-success">
                                                <?= number_format($product['revenue'], 2) ?> ر.س
                                            </td>
                                            <td><?= number_format($product['avg_price'], 2) ?> ر.س</td>
                                            <td class="<?= $product['margin'] > 50 ? 'text-success' : 'text-warning' ?>">
                                                <?= number_format($product['margin'], 1) ?>%
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Additional report sections (users, shifts, cash, tax) would follow similar patterns -->
        <div id="users-report" class="report-section">
            <div class="text-center py-5">
                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                <h4>تقرير المستخدمين</h4>
                <p class="text-muted">سيتم تطوير هذا التقرير قريباً</p>
            </div>
        </div>

        <div id="shifts-report" class="report-section">
            <div class="text-center py-5">
                <i class="fas fa-clock fa-3x text-muted mb-3"></i>
                <h4>تقرير الورديات</h4>
                <p class="text-muted">سيتم تطوير هذا التقرير قريباً</p>
            </div>
        </div>

        <div id="cash-report" class="report-section">
            <div class="text-center py-5">
                <i class="fas fa-cash-register fa-3x text-muted mb-3"></i>
                <h4>تقرير النقدية</h4>
                <p class="text-muted">سيتم تطوير هذا التقرير قريباً</p>
            </div>
        </div>

        <div id="tax-report" class="report-section">
            <div class="text-center py-5">
                <i class="fas fa-percentage fa-3x text-muted mb-3"></i>
                <h4>تقرير الضرائب</h4>
                <p class="text-muted">سيتم تطوير هذا التقرير قريباً</p>
            </div>
        </div>

        <!-- Loading Overlay -->
        <div id="loadingOverlay" class="loading-overlay" style="display: none;">
            <div class="loading-content">
                <div class="spinner"></div>
                <div class="loading-text">جاري تحميل التقرير...</div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="/assets/js/reports.js"></script>
    <script>
        // Initialize reports
        document.addEventListener('DOMContentLoaded', function() {
            ReportsManager.init();
        });

        // Global functions
        function updateReports() {
            if (window.reportsManager) {
                window.reportsManager.updateDateRange();
            }
        }

        function refreshReports() {
            if (window.reportsManager) {
                window.reportsManager.refresh();
            }
        }

        function exportReports() {
            if (window.reportsManager) {
                window.reportsManager.export();
            }
        }

        function updateSalesTrend(period) {
            if (window.reportsManager) {
                window.reportsManager.updateSalesTrend(period);
            }
        }

        function updateSalesChart(period) {
            if (window.reportsManager) {
                window.reportsManager.updateSalesChart(period);
            }
        }

        function exportSalesData() {
            if (window.reportsManager) {
                window.reportsManager.exportSalesData();
            }
        }

        function exportProductsData() {
            if (window.reportsManager) {
                window.reportsManager.exportProductsData();
            }
        }

        // Navigation tab switching
        document.querySelectorAll('.nav-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                // Remove active class from all tabs and sections
                document.querySelectorAll('.nav-tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.report-section').forEach(s => s.classList.remove('active'));
                
                // Add active class to clicked tab and corresponding section
                this.classList.add('active');
                const reportType = this.getAttribute('data-report');
                document.getElementById(reportType + '-report').classList.add('active');
            });
        });
    </script>
</body>
</html>