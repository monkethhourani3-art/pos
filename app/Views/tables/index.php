<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الطاولات - Restaurant POS</title>
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

        .areas-layout {
            display: flex;
            flex-direction: column;
            gap: 30px;
        }

        .area-section {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .area-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .area-name {
            font-size: 20px;
            font-weight: 600;
            margin: 0;
        }

        .area-stats {
            display: flex;
            gap: 20px;
            font-size: 14px;
            opacity: 0.9;
        }

        .tables-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
            padding: 20px;
            min-height: 200px;
        }

        .table-card {
            background: white;
            border: 3px solid #e9ecef;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            min-height: 120px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .table-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
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

        .table-card.out_of_service {
            border-color: #e74c3c;
            background: #f8d7da;
        }

        .table-number {
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .table-name {
            font-size: 14px;
            color: #6c757d;
            margin-bottom: 8px;
        }

        .table-capacity {
            font-size: 12px;
            color: #6c757d;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }

        .table-status-indicator {
            position: absolute;
            top: 8px;
            right: 8px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #6c757d;
        }

        .table-card.available .table-status-indicator {
            background: #27ae60;
        }

        .table-card.occupied .table-status-indicator {
            background: #f39c12;
        }

        .table-card.reserved .table-status-indicator {
            background: #17a2b8;
        }

        .table-card.cleaning .table-status-indicator {
            background: #6c757d;
        }

        .table-card.out_of_service .table-status-indicator {
            background: #e74c3c;
        }

        .order-count-badge {
            position: absolute;
            top: -5px;
            left: -5px;
            background: #e74c3c;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 12px;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .status-legend {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }

        .legend-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 15px;
            color: #2c3e50;
        }

        .legend-items {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }

        .legend-color {
            width: 16px;
            height: 16px;
            border-radius: 3px;
        }

        .legend-color.available {
            background: #27ae60;
        }

        .legend-color.occupied {
            background: #f39c12;
        }

        .legend-color.reserved {
            background: #17a2b8;
        }

        .legend-color.cleaning {
            background: #6c757d;
        }

        .legend-color.out_of_service {
            background: #e74c3c;
        }

        .quick-actions {
            position: fixed;
            bottom: 20px;
            right: 20px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            z-index: 100;
        }

        .quick-action-btn {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            border: none;
            background: #3498db;
            color: white;
            font-size: 20px;
            cursor: pointer;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
        }

        .quick-action-btn:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }

        .empty-area {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }

        .empty-area-icon {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .tables-grid {
                grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
                gap: 10px;
                padding: 15px;
            }

            .area-header {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }

            .area-stats {
                flex-direction: column;
                gap: 5px;
            }

            .quick-actions {
                bottom: 10px;
                right: 10px;
            }

            .quick-action-btn {
                width: 45px;
                height: 45px;
                font-size: 18px;
            }
        }
    </style>
</head>
<body>
    <div class="container" style="padding: 20px;">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1 class="page-title">إدارة طاولات المطعم</h1>
                <p style="margin: 5px 0 0 0; color: #6c757d;">مخطط الطاولات وحالة كل طاولة في الوقت الفعلي</p>
            </div>
            
            <div style="display: flex; gap: 10px;">
                <button class="btn btn-primary" onclick="refreshTables()">
                    تحديث
                </button>
            </div>
        </div>

        <!-- Status Legend -->
        <div class="status-legend">
            <div class="legend-title">دليل الحالات</div>
            <div class="legend-items">
                <div class="legend-item">
                    <div class="legend-color available"></div>
                    <span>متاحة</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color occupied"></div>
                    <span>مشغولة</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color reserved"></div>
                    <span>محجوزة</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color cleaning"></div>
                    <span>جاري التنظيف</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color out_of_service"></div>
                    <span>خارج الخدمة</span>
                </div>
            </div>
        </div>

        <!-- Areas Layout -->
        <div class="areas-layout">
            <?php if (!empty($tablesByArea)): ?>
                <?php foreach ($tablesByArea as $areaKey => $areaData): ?>
                    <?php if ($areaKey !== 'no_area'): ?>
                        <div class="area-section">
                            <div class="area-header">
                                <h3 class="area-name"><?php echo e($areaData['area']->area_display_name); ?></h3>
                                <div class="area-stats">
                                    <span>🪑 <?php echo count($areaData['tables']); ?> طاولة</span>
                                    <span>👥 <?php echo array_sum(array_column($areaData['tables'], 'capacity')); ?> مقعد</span>
                                    <span>✅ <?php echo count(array_filter($areaData['tables'], function($t) { return $t->status === 'available'; })); ?> متاحة</span>
                                </div>
                            </div>
                            
                            <?php if (!empty($areaData['tables'])): ?>
                                <div class="tables-grid">
                                    <?php foreach ($areaData['tables'] as $table): ?>
                                        <div class="table-card <?php echo e($table->status); ?>" 
                                             data-table-id="<?php echo $table->id; ?>"
                                             onclick="toggleTableStatus(<?php echo $table->id; ?>)">
                                            
                                            <div class="table-status-indicator"></div>
                                            
                                            <?php if ($table->active_orders > 0): ?>
                                                <div class="order-count-badge"><?php echo $table->active_orders; ?></div>
                                            <?php endif; ?>
                                            
                                            <div class="table-number"><?php echo e($table->table_number); ?></div>
                                            <div class="table-name"><?php echo e($table->table_name); ?></div>
                                            <div class="table-capacity">
                                                <span>👥</span>
                                                <span><?php echo $table->capacity; ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-area">
                                    <div class="empty-area-icon">🪑</div>
                                    <div>لا توجد طاولات في هذه المنطقة</div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-area">
                    <div class="empty-area-icon">🪑</div>
                    <div class="empty-area-title">لا توجد مناطق أو طاولات</div>
                    <div style="margin-top: 15px;">
                        <button class="btn btn-primary" onclick="window.location.href='/restaurant/areas'">
                            إضافة منطقة جديدة
                        </button>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Tables without area -->
            <?php if (isset($tablesByArea['no_area'])): ?>
                <div class="area-section">
                    <div class="area-header">
                        <h3 class="area-name">طاولات بدون منطقة</h3>
                        <div class="area-stats">
                            <span>🪑 <?php echo count($tablesByArea['no_area']['tables']); ?> طاولة</span>
                        </div>
                    </div>
                    
                    <?php if (!empty($tablesByArea['no_area']['tables'])): ?>
                        <div class="tables-grid">
                            <?php foreach ($tablesByArea['no_area']['tables'] as $table): ?>
                                <div class="table-card <?php echo e($table->status); ?>" 
                                     data-table-id="<?php echo $table->id; ?>"
                                     onclick="toggleTableStatus(<?php echo $table->id; ?>)">
                                    
                                    <div class="table-status-indicator"></div>
                                    
                                    <?php if ($table->active_orders > 0): ?>
                                        <div class="order-count-badge"><?php echo $table->active_orders; ?></div>
                                    <?php endif; ?>
                                    
                                    <div class="table-number"><?php echo e($table->table_number); ?></div>
                                    <div class="table-name"><?php echo e($table->table_name); ?></div>
                                    <div class="table-capacity">
                                        <span>👥</span>
                                        <span><?php echo $table->capacity; ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <button class="quick-action-btn" onclick="refreshTables()" title="تحديث">
                🔄
            </button>
            <?php if (auth()->can('products.manage')): ?>
            <button class="quick-action-btn" onclick="addTable()" title="إضافة طاولة">
                ➕
            </button>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Auto-refresh every 10 seconds
        let refreshInterval = null;

        function startAutoRefresh() {
            refreshInterval = setInterval(refreshTables, 10000);
        }

        function stopAutoRefresh() {
            if (refreshInterval) {
                clearInterval(refreshInterval);
                refreshInterval = null;
            }
        }

        // Toggle table status on click
        function toggleTableStatus(tableId) {
            const tableCard = document.querySelector(`[data-table-id="${tableId}"]`);
            const currentStatus = tableCard.className.match(/status-(\w+)/)?.[1] || 'available';
            
            const statusCycle = ['available', 'occupied', 'reserved', 'cleaning', 'out_of_service'];
            const currentIndex = statusCycle.indexOf(currentStatus);
            const nextIndex = (currentIndex + 1) % statusCycle.length;
            const newStatus = statusCycle[nextIndex];
            
            updateTableStatus(tableId, newStatus);
        }

        // Update table status via AJAX
        function updateTableStatus(tableId, status) {
            const formData = new FormData();
            formData.append('status', status);
            formData.append('_token', document.querySelector('meta[name="csrf-token"]')?.content || '');
            
            fetch(`/restaurant/tables/${tableId}/status`, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    refreshTables();
                } else {
                    alert('خطأ: ' + (data.message || 'فشل في تحديث حالة الطاولة'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('حدث خطأ في الاتصال');
            });
        }

        // Refresh tables display
        function refreshTables() {
            fetch('/restaurant/tables/status', {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                updateTableDisplay(data);
            })
            .catch(error => {
                console.error('Error refreshing tables:', error);
            });
        }

        // Update table display
        function updateTableDisplay(tables) {
            tables.forEach(table => {
                const tableCard = document.querySelector(`[data-table-id="${table.id}"]`);
                if (tableCard) {
                    // Remove existing status classes
                    tableCard.className = tableCard.className.replace(/status-\w+/g, '').trim();
                    // Add new status class
                    tableCard.classList.add('table-card', table.status);
                    
                    // Update order count badge
                    const existingBadge = tableCard.querySelector('.order-count-badge');
                    if (table.active_orders > 0) {
                        if (existingBadge) {
                            existingBadge.textContent = table.active_orders;
                        } else {
                            const badge = document.createElement('div');
                            badge.className = 'order-count-badge';
                            badge.textContent = table.active_orders;
                            tableCard.appendChild(badge);
                        }
                    } else if (existingBadge) {
                        existingBadge.remove();
                    }
                }
            });
        }

        // Add table function (placeholder)
        function addTable() {
            alert('ميزة إضافة طاولة جديدة قيد التطوير. سيتم إضافتها قريباً.');
        }

        // Initialize on load
        document.addEventListener('DOMContentLoaded', function() {
            startAutoRefresh();
            
            // Pause auto-refresh when page is not visible
            document.addEventListener('visibilitychange', function() {
                if (document.hidden) {
                    stopAutoRefresh();
                } else {
                    startAutoRefresh();
                    refreshTables();
                }
            });
        });

        // Cleanup on page unload
        window.addEventListener('beforeunload', function() {
            stopAutoRefresh();
        });
    </script>
</body>
</html>