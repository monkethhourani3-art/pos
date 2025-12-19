/**
 * Reports.js - إدارة التقارير والتحليلات
 * Restaurant POS System
 */

class ReportsManager {
    constructor() {
        this.charts = {};
        this.currentReport = 'overview';
        this.dateFrom = document.getElementById('dateFrom')?.value || this.getFirstDayOfMonth();
        this.dateTo = document.getElementById('dateTo')?.value || this.getTodayDate();
        this.isLoading = false;
    }

    /**
     * تهيئة مدير التقارير
     */
    init() {
        this.setupEventListeners();
        this.initializeCharts();
        this.loadInitialData();
        this.startAutoRefresh();
    }

    /**
     * إعداد مستمعي الأحداث
     */
    setupEventListeners() {
        // تحديث النطاق الزمني
        const dateInputs = document.querySelectorAll('#dateFrom, #dateTo');
        dateInputs.forEach(input => {
            input.addEventListener('change', () => {
                this.dateFrom = document.getElementById('dateFrom')?.value || this.dateFrom;
                this.dateTo = document.getElementById('dateTo')?.value || this.dateTo;
                this.refreshCurrentReport();
            });
        });

        // تحديث التنقل بين التقارير
        document.querySelectorAll('.nav-tab').forEach(tab => {
            tab.addEventListener('click', (e) => {
                const reportType = e.currentTarget.getAttribute('data-report');
                this.switchReport(reportType);
            });
        });

        // إعادة تحديث تلقائية كل 30 ثانية
        this.setupAutoRefresh();
    }

    /**
     * تهيئة الرسوم البيانية
     */
    initializeCharts() {
        // رسم بياني لاتجاهات المبيعات
        const salesTrendCtx = document.getElementById('salesTrendChart');
        if (salesTrendCtx) {
            this.charts.salesTrend = new Chart(salesTrendCtx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'المبيعات',
                        data: [],
                        borderColor: '#007bff',
                        backgroundColor: 'rgba(0, 123, 255, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return value + ' ر.س';
                                }
                            }
                        }
                    }
                }
            });
        }

        // رسم بياني دائري لتوزيع المبيعات
        const salesDistCtx = document.getElementById('salesDistributionChart');
        if (salesDistCtx) {
            this.charts.salesDistribution = new Chart(salesDistCtx, {
                type: 'doughnut',
                data: {
                    labels: ['نقداً', 'بطاقة ائتمان', 'بطاقة مدين', 'تحويل'],
                    datasets: [{
                        data: [],
                        backgroundColor: [
                            '#28a745',
                            '#007bff',
                            '#ffc107',
                            '#dc3545'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }

        // رسم بياني للمبيعات التفصيلي
        const detailedSalesCtx = document.getElementById('detailedSalesChart');
        if (detailedSalesCtx) {
            this.charts.detailedSales = new Chart(detailedSalesCtx, {
                type: 'bar',
                data: {
                    labels: [],
                    datasets: [
                        {
                            label: 'المبيعات',
                            data: [],
                            backgroundColor: '#007bff',
                            borderColor: '#0056b3',
                            borderWidth: 1
                        },
                        {
                            label: 'الطلبات',
                            data: [],
                            backgroundColor: '#28a745',
                            borderColor: '#1e7e34',
                            borderWidth: 1,
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return value + ' ر.س';
                                }
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            beginAtZero: true,
                            grid: {
                                drawOnChartArea: false
                            }
                        }
                    }
                }
            });
        }

        // رسم بياني لأداء المنتجات
        const productsCtx = document.getElementById('productsChart');
        if (productsCtx) {
            this.charts.products = new Chart(productsCtx, {
                type: 'bar',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'الإيرادات',
                        data: [],
                        backgroundColor: '#ffc107',
                        borderColor: '#e0a800',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return value + ' ر.س';
                                }
                            }
                        }
                    }
                }
            });
        }

        // رسم بياني دائري للفئات
        const categoryCtx = document.getElementById('categoryChart');
        if (categoryCtx) {
            this.charts.category = new Chart(categoryCtx, {
                type: 'pie',
                data: {
                    labels: [],
                    datasets: [{
                        data: [],
                        backgroundColor: [
                            '#007bff',
                            '#28a745',
                            '#ffc107',
                            '#dc3545',
                            '#6f42c1',
                            '#fd7e14'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }
    }

    /**
     * تحميل البيانات الأولية
     */
    async loadInitialData() {
        await this.loadOverviewData();
        this.updateCharts();
    }

    /**
     * تحميل بيانات النظرة العامة
     */
    async loadOverviewData() {
        try {
            const response = await fetch(`/api/reports/overview?date_from=${this.dateFrom}&date_to=${this.dateTo}`);
            const data = await response.json();
            
            if (data.success) {
                this.overviewData = data.data;
                this.updateOverviewDisplay();
            }
        } catch (error) {
            console.error('خطأ في تحميل بيانات النظرة العامة:', error);
        }
    }

    /**
     * تحديث عرض النظرة العامة
     */
    updateOverviewDisplay() {
        if (!this.overviewData) return;

        // تحديث KPI cards
        this.updateKPICards();
        
        // تحديث الرسوم البيانية
        this.updateCharts();
    }

    /**
     * تحديث بطاقات KPI
     */
    updateKPICards() {
        const stats = this.overviewData.stats || {};
        
        // تحديث إجمالي المبيعات
        const totalSalesElement = document.querySelector('.total-sales-card .kpi-value');
        if (totalSalesElement) {
            totalSalesElement.textContent = this.formatCurrency(stats.total_sales || 0);
        }

        // تحديث عدد الطلبات
        const ordersElement = document.querySelector('.orders-card .kpi-value');
        if (ordersElement) {
            ordersElement.textContent = this.formatNumber(stats.total_orders || 0);
        }

        // تحديث متوسط الطلب
        const avgOrderElement = document.querySelector('.avg-order-card .kpi-value');
        if (avgOrderElement) {
            avgOrderElement.textContent = this.formatCurrency(stats.average_order_value || 0);
        }

        // تحديث العملاء
        const customersElement = document.querySelector('.customers-card .kpi-value');
        if (customersElement) {
            customersElement.textContent = this.formatNumber(stats.unique_customers || 0);
        }
    }

    /**
     * تحديث الرسوم البيانية
     */
    updateCharts() {
        if (!this.overviewData) return;

        // تحديث رسم اتجاهات المبيعات
        if (this.charts.salesTrend && this.overviewData.sales_trend) {
            const trendData = this.overviewData.sales_trend;
            this.charts.salesTrend.data.labels = trendData.labels || [];
            this.charts.salesTrend.data.datasets[0].data = trendData.values || [];
            this.charts.salesTrend.update();
        }

        // تحديث رسم توزيع المبيعات
        if (this.charts.salesDistribution && this.overviewData.payment_methods) {
            const paymentData = this.overviewData.payment_methods;
            this.charts.salesDistribution.data.datasets[0].data = paymentData.values || [];
            this.charts.salesDistribution.update();
        }
    }

    /**
     * تبديل التقرير
     */
    async switchReport(reportType) {
        if (this.currentReport === reportType) return;

        this.showLoading();
        
        // تحديث التبويب النشط
        document.querySelectorAll('.nav-tab').forEach(tab => {
            tab.classList.remove('active');
        });
        document.querySelector(`[data-report="${reportType}"]`).classList.add('active');

        // تحديث القسم النشط
        document.querySelectorAll('.report-section').forEach(section => {
            section.classList.remove('active');
        });
        document.getElementById(`${reportType}-report`).classList.add('active');

        this.currentReport = reportType;

        // تحميل بيانات التقرير المحدد
        await this.loadReportData(reportType);
        
        this.hideLoading();
    }

    /**
     * تحميل بيانات تقرير معين
     */
    async loadReportData(reportType) {
        try {
            let url = `/api/reports/${reportType}?date_from=${this.dateFrom}&date_to=${this.dateTo}`;
            const response = await fetch(url);
            const data = await response.json();

            if (data.success) {
                this[`${reportType}Data`] = data.data;
                this.updateReportDisplay(reportType);
            }
        } catch (error) {
            console.error(`خطأ في تحميل بيانات تقرير ${reportType}:`, error);
        }
    }

    /**
     * تحديث عرض تقرير معين
     */
    updateReportDisplay(reportType) {
        switch (reportType) {
            case 'sales':
                this.updateSalesReport();
                break;
            case 'products':
                this.updateProductsReport();
                break;
            case 'users':
            case 'shifts':
            case 'cash':
            case 'tax':
                // سيتم تطويرها لاحقاً
                break;
        }
    }

    /**
     * تحديث تقرير المبيعات
     */
    updateSalesReport() {
        if (!this.salesData) return;

        // تحديث الرسم البياني التفصيلي
        if (this.charts.detailedSales && this.salesData.daily_data) {
            const dailyData = this.salesData.daily_data;
            this.charts.detailedSales.data.labels = dailyData.labels || [];
            this.charts.detailedSales.data.datasets[0].data = dailyData.sales || [];
            this.charts.detailedSales.data.datasets[1].data = dailyData.orders || [];
            this.charts.detailedSales.update();
        }

        // تحديث جدول المبيعات
        this.updateSalesTable();
    }

    /**
     * تحديث جدول المبيعات
     */
    updateSalesTable() {
        const tableBody = document.querySelector('#salesTable tbody');
        if (!tableBody || !this.salesData.daily_data) return;

        const data = this.salesData.daily_data;
        tableBody.innerHTML = '';

        data.labels?.forEach((label, index) => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${label}</td>
                <td>${this.formatNumber(data.orders[index] || 0)}</td>
                <td class="text-success">${this.formatCurrency(data.sales[index] || 0)}</td>
                <td>${this.formatCurrency((data.sales[index] || 0) / Math.max(data.orders[index] || 1, 1))}</td>
                <td>${this.formatNumber(data.customers[index] || 0)}</td>
            `;
            tableBody.appendChild(row);
        });
    }

    /**
     * تحديث تقرير المنتجات
     */
    updateProductsReport() {
        if (!this.productsData) return;

        // تحديث رسم أداء المنتجات
        if (this.charts.products && this.productsData.top_products) {
            const topProducts = this.productsData.top_products;
            this.charts.products.data.labels = topProducts.map(p => p.name) || [];
            this.charts.products.data.datasets[0].data = topProducts.map(p => p.revenue) || [];
            this.charts.products.update();
        }

        // تحديث رسم الفئات
        if (this.charts.category && this.productsData.categories) {
            const categories = this.productsData.categories;
            this.charts.category.data.labels = categories.map(c => c.name) || [];
            this.charts.category.data.datasets[0].data = categories.map(c => c.revenue) || [];
            this.charts.category.update();
        }

        // تحديث جدول المنتجات
        this.updateProductsTable();
    }

    /**
     * تحديث جدول المنتجات
     */
    updateProductsTable() {
        const tableBody = document.querySelector('#productsTable tbody');
        if (!tableBody || !this.productsData.products) return;

        const products = this.productsData.products;
        tableBody.innerHTML = '';

        products.forEach(product => {
            const row = document.createElement('tr');
            const marginClass = product.margin > 50 ? 'text-success' : 'text-warning';
            
            row.innerHTML = `
                <td>
                    <div class="product-info">
                        <strong>${product.name}</strong>
                    </div>
                </td>
                <td>${product.category}</td>
                <td>${this.formatNumber(product.quantity)}</td>
                <td class="text-success">${this.formatCurrency(product.revenue)}</td>
                <td>${this.formatCurrency(product.avg_price)}</td>
                <td class="${marginClass}">${product.margin.toFixed(1)}%</td>
            `;
            tableBody.appendChild(row);
        });
    }

    /**
     * تحديث نطاق التاريخ
     */
    updateDateRange() {
        this.refreshCurrentReport();
    }

    /**
     * تحديث التقرير الحالي
     */
    async refreshCurrentReport() {
        this.showLoading();
        await this.loadReportData(this.currentReport);
        this.hideLoading();
    }

    /**
     * تحديث رسم اتجاهات المبيعات
     */
    updateSalesTrend(period) {
        // تحديث الأزرار النشطة
        document.querySelectorAll('#overview-report .chart-controls .btn').forEach(btn => {
            btn.classList.remove('active');
        });
        document.querySelector(`#overview-report .chart-controls .btn[onclick="updateSalesTrend('${period}')"]`).classList.add('active');

        // تحديث البيانات
        if (this.charts.salesTrend && this.overviewData) {
            const trendData = this.overviewData[`${period}_trend`];
            if (trendData) {
                this.charts.sartsTrend.data.labels = trendData.labels || [];
                this.charts.salesTrend.data.datasets[0].data = trendData.values || [];
                this.charts.salesTrend.update();
            }
        }
    }

    /**
     * تحديث رسم المبيعات التفصيلي
     */
    updateSalesChart(period) {
        // تحديث الأزرار النشطة
        document.querySelectorAll('#sales-report .chart-controls .btn').forEach(btn => {
            btn.classList.remove('active');
        });
        document.querySelector(`#sales-report .chart-controls .btn[onclick="updateSalesChart('${period}')"]`).classList.add('active');

        // تحميل بيانات الفترة الجديدة
        this.loadSalesData(period);
    }

    /**
     * تحميل بيانات المبيعات
     */
    async loadSalesData(period) {
        try {
            const response = await fetch(`/api/reports/sales?date_from=${this.dateFrom}&date_to=${this.dateTo}&period=${period}`);
            const data = await response.json();

            if (data.success && this.charts.detailedSales) {
                const salesData = data.data;
                this.charts.detailedSales.data.labels = salesData.labels || [];
                this.charts.detailedSales.data.datasets[0].data = salesData.sales || [];
                this.charts.detailedSales.data.datasets[1].data = salesData.orders || [];
                this.charts.detailedSales.update();
            }
        } catch (error) {
            console.error('خطأ في تحميل بيانات المبيعات:', error);
        }
    }

    /**
     * تصدير التقارير
     */
    async export() {
        try {
            const response = await fetch(`/api/reports/export?date_from=${this.dateFrom}&date_to=${this.dateTo}&report=${this.currentReport}`);
            const blob = await response.blob();
            
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `تقرير_${this.currentReport}_${this.dateFrom}_${this.dateTo}.xlsx`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        } catch (error) {
            console.error('خطأ في تصدير التقرير:', error);
            alert('حدث خطأ أثناء تصدير التقرير');
        }
    }

    /**
     * تصدير بيانات المبيعات
     */
    async exportSalesData() {
        await this.exportData('sales');
    }

    /**
     * تصدير بيانات المنتجات
     */
    async exportProductsData() {
        await this.exportData('products');
    }

    /**
     * تصدير البيانات
     */
    async exportData(type) {
        try {
            const response = await fetch(`/api/reports/export/${type}?date_from=${this.dateFrom}&date_to=${this.dateTo}`);
            const blob = await response.blob();
            
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `بيانات_${type}_${this.dateFrom}_${this.dateTo}.xlsx`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        } catch (error) {
            console.error(`خطأ في تصدير بيانات ${type}:`, error);
            alert('حدث خطأ أثناء تصدير البيانات');
        }
    }

    /**
     * إعداد التحديث التلقائي
     */
    setupAutoRefresh() {
        setInterval(() => {
            if (!this.isLoading) {
                this.refreshCurrentReport();
            }
        }, 30000); // كل 30 ثانية
    }

    /**
     * بدء التحديث التلقائي
     */
    startAutoRefresh() {
        // سيتم تفعيله في setupAutoRefresh
    }

    /**
     * إظهار مؤشر التحميل
     */
    showLoading() {
        this.isLoading = true;
        const overlay = document.getElementById('loadingOverlay');
        if (overlay) {
            overlay.style.display = 'flex';
        }
    }

    /**
     * إخفاء مؤشر التحميل
     */
    hideLoading() {
        this.isLoading = false;
        const overlay = document.getElementById('loadingOverlay');
        if (overlay) {
            overlay.style.display = 'none';
        }
    }

    /**
     * تنسيق العملة
     */
    formatCurrency(amount) {
        return new Intl.NumberFormat('ar-SA', {
            style: 'currency',
            currency: 'SAR',
            minimumFractionDigits: 2
        }).format(amount);
    }

    /**
     * تنسيق الأرقام
     */
    formatNumber(number) {
        return new Intl.NumberFormat('ar-SA').format(number);
    }

    /**
     * الحصول على تاريخ اليوم
     */
    getTodayDate() {
        return new Date().toISOString().split('T')[0];
    }

    /**
     * الحصول على أول يوم في الشهر
     */
    getFirstDayOfMonth() {
        const now = new Date();
        return new Date(now.getFullYear(), now.getMonth(), 1).toISOString().split('T')[0];
    }
}

// إنشاء مثيل عام من مدير التقارير
window.ReportsManager = ReportsManager;

// دوال عامة للاستخدام في HTML
window.reportsManager = new ReportsManager();

// تصدير الكلاس للاستخدام في وحدات أخرى
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ReportsManager;
}