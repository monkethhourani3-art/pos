/**
 * Dashboard Management JavaScript
 * Restaurant POS System - Phase 6
 * Advanced dashboard with real-time updates and interactive charts
 */

class DashboardManager {
    constructor() {
        this.salesChart = null;
        this.paymentMethodsChart = null;
        this.realtimeTimer = null;
        this.currentPeriod = 'daily';
        this.charts = {};
        this.isLoading = false;
        
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.initializeCharts();
        this.startRealtimeUpdates();
        this.loadInitialData();
        
        console.log('Dashboard Manager initialized');
    }

    setupEventListeners() {
        // Date range change
        document.addEventListener('change', (e) => {
            if (e.target.matches('#dateFrom, #dateTo')) {
                this.updateDateRange();
            }
        });

        // Chart period buttons
        document.addEventListener('click', (e) => {
            if (e.target.matches('.chart-controls .btn')) {
                this.updateSalesChart(e.target.textContent.toLowerCase());
            }
        });

        // Window focus events for realtime updates
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.stopRealtimeUpdates();
            } else {
                this.startRealtimeUpdates();
                this.refresh();
            }
        });

        // Resize events for chart responsiveness
        window.addEventListener('resize', () => {
            this.resizeCharts();
        });
    }

    initializeCharts() {
        this.initializeSalesChart();
        this.initializePaymentMethodsChart();
    }

    initializeSalesChart() {
        const ctx = document.getElementById('salesChart');
        if (!ctx) return;

        // Sample data - would be replaced with real API data
        const salesData = {
            labels: ['الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'],
            datasets: [{
                label: 'المبيعات (ر.س)',
                data: [1200, 1900, 3000, 2500, 2200, 3000, 2800],
                borderColor: '#667eea',
                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4
            }]
        };

        this.salesChart = new Chart(ctx, {
            type: 'line',
            data: salesData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: '#667eea',
                        borderWidth: 1,
                        callbacks: {
                            label: function(context) {
                                return 'المبيعات: ' + context.parsed.y.toLocaleString() + ' ر.س';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        },
                        ticks: {
                            callback: function(value) {
                                return value.toLocaleString() + ' ر.س';
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                animation: {
                    duration: 1000,
                    easing: 'easeInOutQuart'
                }
            }
        });

        this.charts.sales = this.salesChart;
    }

    initializePaymentMethodsChart() {
        const ctx = document.getElementById('paymentMethodsChart');
        if (!ctx) return;

        // Sample data - would be replaced with real API data
        const paymentData = {
            labels: ['نقدي', 'بطاقة ائتمان', 'تحويل بنكي'],
            datasets: [{
                data: [45, 35, 20],
                backgroundColor: [
                    '#28a745',
                    '#007bff',
                    '#ffc107'
                ],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        };

        this.paymentMethodsChart = new Chart(ctx, {
            type: 'doughnut',
            data: paymentData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            padding: 20,
                            font: {
                                size: 12
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        callbacks: {
                            label: function(context) {
                                return context.label + ': ' + context.parsed + '%';
                            }
                        }
                    }
                },
                animation: {
                    animateRotate: true,
                    animateScale: true,
                    duration: 1000
                },
                cutout: '60%'
            }
        });

        this.charts.paymentMethods = this.paymentMethodsChart;
    }

    async loadInitialData() {
        try {
            this.showLoading(true);
            
            // Load dashboard data
            const dashboardData = await this.fetchDashboardData();
            this.updateDashboardStats(dashboardData);
            
            // Load chart data
            await this.loadChartData();
            
            // Load real-time data
            await this.loadRealtimeData();

        } catch (error) {
            console.error('Error loading initial data:', error);
            this.showError('فشل في تحميل البيانات');
        } finally {
            this.showLoading(false);
        }
    }

    async fetchDashboardData() {
        const dateFrom = document.getElementById('dateFrom').value;
        const dateTo = document.getElementById('dateTo').value;
        
        const params = new URLSearchParams({
            date_from: dateFrom,
            date_to: dateTo
        });

        const response = await fetch(`/dashboard?${params}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        });

        if (!response.ok) {
            throw new Error('Failed to fetch dashboard data');
        }

        return await response.json();
    }

    async loadChartData() {
        try {
            const chartTypes = ['daily_sales', 'hourly_sales', 'top_products', 'payment_methods'];
            
            for (const chartType of chartTypes) {
                const response = await fetch(`/dashboard/widget?widget_type=${chartType}`, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                });

                if (response.ok) {
                    const data = await response.json();
                    this.updateChart(chartType, data.data);
                }
            }

        } catch (error) {
            console.error('Error loading chart data:', error);
        }
    }

    updateChart(chartType, data) {
        switch (chartType) {
            case 'daily_sales':
                this.updateSalesChartData(data);
                break;
            case 'payment_methods':
                this.updatePaymentMethodsChartData(data);
                break;
            case 'top_products':
                this.updateTopProductsTable(data);
                break;
        }
    }

    updateSalesChartData(data) {
        if (!this.salesChart || !data.labels) return;

        this.salesChart.data.labels = data.labels;
        this.salesChart.data.datasets[0].data = data.data;
        this.salesChart.update('active');
    }

    updatePaymentMethodsChartData(data) {
        if (!this.paymentMethodsChart || !data.labels) return;

        this.paymentMethodsChart.data.labels = data.labels;
        this.paymentMethodsChart.data.datasets[0].data = data.data;
        this.paymentMethodsChart.update('active');
    }

    updateTopProductsTable(data) {
        const tbody = document.querySelector('.data-table-card tbody');
        if (!tbody || !data.top_products) return;

        const productsHtml = data.top_products.slice(0, 5).map(product => `
            <tr>
                <td>
                    <div class="product-info">
                        <strong>${this.escapeHtml(product.name)}</strong>
                    </div>
                </td>
                <td>${this.formatNumber(product.total_sold)}</td>
                <td class="text-success">
                    ${this.formatNumber(product.total_revenue, 2)} ر.س
                </td>
            </tr>
        `).join('');

        tbody.innerHTML = productsHtml;
    }

    updateDashboardStats(data) {
        if (!data.dashboard_stats) return;

        const stats = data.dashboard_stats;
        
        // Update KPI cards
        this.updateKpiCard('.revenue-card .kpi-value', stats.sales?.total_sales, 2, ' ر.س');
        this.updateKpiCard('.orders-card .kpi-value', stats.sales?.total_orders);
        this.updateKpiCard('.avg-order-card .kpi-value', stats.sales?.average_order_value, 2, ' ر.س');
        this.updateKpiCard('.inventory-card .kpi-value', stats.inventory?.low_stock_items);

        // Update alerts
        if (data.alerts) {
            this.updateAlerts(data.alerts);
        }
    }

    updateKpiCard(selector, value, decimals = 0, suffix = '') {
        const element = document.querySelector(selector);
        if (element) {
            element.textContent = this.formatNumber(value, decimals) + suffix;
        }
    }

    updateAlerts(alerts) {
        const alertsContainer = document.querySelector('.alerts-container');
        if (!alertsContainer) return;

        const alertsHtml = alerts.map(alert => `
            <div class="alert alert-${alert.type === 'danger' ? 'danger' : (alert.type === 'warning' ? 'warning' : 'info')} alert-dismissible fade show" 
                 role="alert">
                <div class="alert-content">
                    <div class="alert-title">
                        <i class="fas fa-${alert.type === 'danger' ? 'exclamation-circle' : (alert.type === 'warning' ? 'exclamation-triangle' : 'info-circle')} me-2"></i>
                        ${this.escapeHtml(alert.title)}
                    </div>
                    <div class="alert-message">${this.escapeHtml(alert.message)}</div>
                </div>
                <div class="alert-actions">
                    <a href="${this.escapeHtml(alert.link)}" class="btn btn-sm btn-outline-primary">
                        عرض التفاصيل
                    </a>
                    <button type="button" class="btn-close" 
                            onclick="markAlertRead('${alert.id}')"></button>
                </div>
            </div>
        `).join('');

        alertsContainer.innerHTML = alertsHtml;
    }

    async loadRealtimeData() {
        try {
            const response = await fetch('/dashboard/realtime', {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });

            if (response.ok) {
                const data = await response.json();
                this.updateRealtimeDisplay(data.data);
            }

        } catch (error) {
            console.error('Error loading realtime data:', error);
        }
    }

    updateRealtimeDisplay(data) {
        // Update last update timestamp
        const lastUpdateElement = document.getElementById('lastUpdate');
        if (lastUpdateElement && data.timestamp) {
            lastUpdateElement.textContent = new Date(data.timestamp).toLocaleTimeString('ar-SA');
        }

        // Update real-time counters if they exist
        this.updateKpiCard('.realtime-orders .kpi-value', data.today_orders);
        this.updateKpiCard('.realtime-payments .kpi-value', data.today_payments, 2, ' ر.س');
        this.updateKpiCard('.realtime-active-orders .kpi-value', data.active_orders);
        this.updateKpiCard('.realtime-low-stock .kpi-value', data.low_stock_items);
    }

    updateDateRange() {
        this.refresh();
    }

    async refresh() {
        await this.loadInitialData();
        this.showSuccess('تم تحديث البيانات');
    }

    updateSalesChart(period) {
        // Update active button
        document.querySelectorAll('.chart-controls .btn').forEach(btn => {
            btn.classList.remove('active');
        });
        event.target.classList.add('active');

        this.currentPeriod = period;
        this.loadChartDataForPeriod(period);
    }

    async loadChartDataForPeriod(period) {
        try {
            const response = await fetch(`/dashboard/widget?widget_type=sales_summary&period=${period}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });

            if (response.ok) {
                const data = await response.json();
                this.updateSalesChartData(data.data);
            }

        } catch (error) {
            console.error('Error loading chart data for period:', error);
        }
    }

    export() {
        const dateFrom = document.getElementById('dateFrom').value;
        const dateTo = document.getElementById('dateTo').value;
        
        const link = document.createElement('a');
        link.href = `/dashboard/export?format=csv&date_from=${dateFrom}&date_to=${dateTo}`;
        link.download = `dashboard-export-${dateFrom}-${dateTo}.csv`;
        link.click();
    }

    startRealtimeUpdates() {
        // Update every 30 seconds
        this.realtimeTimer = setInterval(() => {
            this.loadRealtimeData();
        }, 30000);
    }

    stopRealtimeUpdates() {
        if (this.realtimeTimer) {
            clearInterval(this.realtimeTimer);
            this.realtimeTimer = null;
        }
    }

    resizeCharts() {
        Object.values(this.charts).forEach(chart => {
            if (chart && typeof chart.resize === 'function') {
                chart.resize();
            }
        });
    }

    showLoading(show) {
        const overlay = document.getElementById('loadingOverlay');
        if (overlay) {
            overlay.style.display = show ? 'flex' : 'none';
        }
        this.isLoading = show;
    }

    showSuccess(message) {
        this.showNotification(message, 'success');
    }

    showError(message) {
        this.showNotification(message, 'error');
    }

    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.textContent = message;
        
        // Position notifications
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            min-width: 300px;
            animation: slideInRight 0.3s ease;
        `;

        if (type === 'success') {
            notification.style.background = 'linear-gradient(135deg, #28a745, #20c997)';
        } else if (type === 'error') {
            notification.style.background = 'linear-gradient(135deg, #dc3545, #e74c3c)';
        } else {
            notification.style.background = 'linear-gradient(135deg, #17a2b8, #3498db)';
        }

        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.style.animation = 'slideOutRight 0.3s ease';
            setTimeout(() => {
                notification.remove();
            }, 300);
        }, 3000);
    }

    // Utility methods
    formatNumber(number, decimals = 0) {
        if (number === null || number === undefined) return '0';
        return new Intl.NumberFormat('ar-SA', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        }).format(number);
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Animation utilities
    animateValue(element, start, end, duration = 1000) {
        const startTime = performance.now();
        
        const animate = (currentTime) => {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            
            // Easing function
            const easeOutQuart = 1 - Math.pow(1 - progress, 4);
            const current = start + (end - start) * easeOutQuart;
            
            element.textContent = this.formatNumber(current, 2);
            
            if (progress < 1) {
                requestAnimationFrame(animate);
            }
        };
        
        requestAnimationFrame(animate);
    }

    // Error handling
    handleError(error, context = '') {
        console.error(`Dashboard Error (${context}):`, error);
        
        // Show user-friendly error message
        this.showError('حدث خطأ في تحميل البيانات');
        
        // Could implement error reporting here
        if (window.errorReporting) {
            window.errorReporting.captureException(error, {
                context: 'dashboard',
                details: context
            });
        }
    }

    // Performance monitoring
    startPerformanceTimer(name) {
        performance.mark(`${name}-start`);
    }

    endPerformanceTimer(name) {
        performance.mark(`${name}-end`);
        performance.measure(name, `${name}-start`, `${name}-end`);
        
        const measure = performance.getEntriesByName(name)[0];
        console.log(`Dashboard Performance - ${name}:`, measure.duration + 'ms');
    }

    // Cleanup
    destroy() {
        this.stopRealtimeUpdates();
        
        // Destroy charts
        Object.values(this.charts).forEach(chart => {
            if (chart && typeof chart.destroy === 'function') {
                chart.destroy();
            }
        });
        
        // Clear references
        this.charts = {};
        this.salesChart = null;
        this.paymentMethodsChart = null;
    }
}

// Global utility functions
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
    })
    .catch(error => {
        console.error('Error marking alert as read:', error);
    });
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Check if we're on the dashboard page
    if (document.querySelector('.dashboard-container')) {
        window.dashboardManager = new DashboardManager();
    }
});

// Cleanup on page unload
window.addEventListener('beforeunload', function() {
    if (window.dashboardManager) {
        window.dashboardManager.destroy();
    }
});

// Export for module use
if (typeof module !== 'undefined' && module.exports) {
    module.exports = DashboardManager;
}