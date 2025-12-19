/**
 * Kitchen Display System (KDS) JavaScript
 * Handles Kitchen interface interactions and real-time updates
 */

class KitchenSystem {
    constructor() {
        this.autoRefreshEnabled = true;
        this.refreshInterval = 30000; // 30 seconds
        this.refreshTimer = null;
        this.currentItemId = null;
        this.soundEnabled = true;
        this.audioContext = null;
        
        this.init();
    }

    init() {
        this.setupAudioContext();
        this.setupEventListeners();
        this.setupKeyboardShortcuts();
        this.startAutoRefresh();
        this.loadKitchenData();
    }

    setupAudioContext() {
        try {
            if (typeof AudioContext !== 'undefined') {
                this.audioContext = new AudioContext();
            } else if (typeof webkitAudioContext !== 'undefined') {
                this.audioContext = new webkitAudioContext();
            }
        } catch (error) {
            console.warn('Audio context not supported:', error);
        }
    }

    setupEventListeners() {
        // Auto-refresh toggle
        document.getElementById('refreshBtn')?.addEventListener('click', () => {
            this.toggleAutoRefresh();
        });

        // Form submissions
        document.getElementById('statusForm')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.updateOrderStatus();
        });

        // Item notes modal
        document.getElementById('itemNotesModal')?.addEventListener('show.bs.modal', (e) => {
            const button = e.relatedTarget;
            this.currentItemId = button.dataset.itemId;
        });

        // Issue modal
        document.getElementById('issueModal')?.addEventListener('show.bs.modal', (e) => {
            const button = e.relatedTarget;
            this.currentItemId = button.dataset.itemId;
        });

        // Search input for products
        document.getElementById('searchInput')?.addEventListener('input', (e) => {
            this.debounce(() => this.searchProducts(e.target.value), 300)();
        });
    }

    setupKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // R to toggle refresh
            if (e.key === 'r' && e.ctrlKey) {
                e.preventDefault();
                this.toggleAutoRefresh();
            }
            
            // F to toggle fullscreen
            if (e.key === 'f' && e.ctrlKey) {
                e.preventDefault();
                this.toggleFullscreen();
            }
            
            // M to show metrics
            if (e.key === 'm' && e.ctrlKey) {
                e.preventDefault();
                this.showMetrics();
            }

            // Space to refresh
            if (e.key === ' ' && e.ctrlKey) {
                e.preventDefault();
                this.loadKitchenData();
            }

            // S to toggle sound
            if (e.key === 's' && e.ctrlKey) {
                e.preventDefault();
                this.toggleSound();
            }
        });
    }

    async loadKitchenData() {
        if (!this.autoRefreshEnabled) return;

        try {
            const lastUpdate = document.getElementById('lastUpdate')?.textContent || '';
            const response = await fetch(`/kitchen/data?last_update=${encodeURIComponent(lastUpdate)}`);
            const data = await response.json();
            
            if (data.success) {
                this.updateKitchenDisplay(data.data);
                this.updateLastUpdateTime(data.data.timestamp);
                
                // Play sound notification for new orders
                if (data.data.new_orders && data.data.new_orders.length > 0) {
                    this.playNotificationSound();
                    this.showNewOrderNotification(data.data.new_orders.length);
                }
            }
        } catch (error) {
            console.error('Error loading kitchen data:', error);
            this.showNotification('خطأ في تحميل بيانات المطبخ', 'error');
        }
    }

    updateKitchenDisplay(data) {
        this.updateOrderColumn('confirmedOrders', data.updated_orders?.filter(o => o.status === 'confirmed') || [], 'confirmed');
        this.updateOrderColumn('preparingOrders', data.updated_orders?.filter(o => o.status === 'preparing') || [], 'preparing');
        this.updateOrderColumn('readyOrders', data.updated_orders?.filter(o => o.status === 'ready') || [], 'ready');
        
        this.updateKPICounters(data.updated_orders || []);
    }

    updateOrderColumn(columnId, orders, status) {
        const container = document.getElementById(columnId);
        if (!container) return;
        
        if (orders.length === 0) {
            container.innerHTML = this.getNoOrdersHTML(status);
            return;
        }

        let html = '';
        orders.forEach(order => {
            html += this.generateOrderCard(order, status);
        });
        
        container.innerHTML = html;
        
        // Add entrance animations
        container.querySelectorAll('.order-card').forEach((card, index) => {
            card.style.animationDelay = `${index * 0.1}s`;
            card.classList.add('fadeIn');
        });
    }

    getNoOrdersHTML(status) {
        const icons = {
            'confirmed': 'fa-clock',
            'preparing': 'fa-fire',
            'ready': 'fa-check-circle'
        };
        
        const colors = {
            'confirmed': 'warning',
            'preparing': 'info',
            'ready': 'success'
        };
        
        const messages = {
            'confirmed': 'لا توجد طلبات في الانتظار',
            'preparing': 'لا توجد طلبات قيد التحضير',
            'ready': 'لا توجد طلبات جاهزة'
        };

        return `
            <div class="no-orders">
                <i class="fas ${icons[status]} fa-3x text-${colors[status]} mb-3"></i>
                <p class="text-muted">${messages[status]}</p>
            </div>
        `;
    }

    generateOrderCard(order, status) {
        const actionsHtml = this.getOrderActionsHtml(order, status);
        const timeBadge = this.getTimeBadge(order, status);
        
        return `
            <div class="order-card ${status}" data-order-id="${order.id}">
                <div class="order-header">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="mb-1">طاولة ${this.escapeHtml(order.table_number)}</h6>
                            <small class="text-muted">
                                ${this.escapeHtml(order.user_name)} • 
                                ${this.formatTime(order.created_at)}
                            </small>
                        </div>
                        <div class="waiting-time">
                            ${timeBadge}
                        </div>
                    </div>
                </div>
                
                <div class="order-items">
                    ${order.items.map(item => this.generateOrderItemHtml(item, status)).join('')}
                </div>
                
                <div class="order-actions">
                    ${actionsHtml}
                </div>
            </div>
        `;
    }

    generateOrderItemHtml(item, orderStatus) {
        const kitchenNotesHtml = item.kitchen_notes ? `
            <div class="item-notes kitchen-notes">
                <i class="fas fa-utensils me-1"></i>
                ${this.escapeHtml(item.kitchen_notes)}
            </div>
        ` : '';

        const actionsHtml = (orderStatus === 'preparing' && item.status !== 'ready') ? `
            <div class="item-actions">
                <button class="btn btn-sm btn-outline-success" onclick="kitchenSystem.markItemReady(${item.id})">
                    <i class="fas fa-check"></i>
                </button>
                <button class="btn btn-sm btn-outline-warning" onclick="kitchenSystem.showItemNotes(${item.id})">
                    <i class="fas fa-sticky-note"></i>
                </button>
                <button class="btn btn-sm btn-outline-danger" onclick="kitchenSystem.showIssueModal(${item.id})">
                    <i class="fas fa-exclamation-triangle"></i>
                </button>
            </div>
        ` : '';

        return `
            <div class="order-item" data-item-id="${item.id}">
                <div class="item-info">
                    <span class="item-name">${this.escapeHtml(item.product_name)}</span>
                    <span class="item-quantity">×${item.quantity}</span>
                    ${kitchenNotesHtml}
                </div>
                ${actionsHtml}
            </div>
        `;
    }

    getOrderActionsHtml(order, status) {
        switch (status) {
            case 'confirmed':
                return `
                    <button class="btn btn-sm btn-success w-100" onclick="kitchenSystem.startPreparing(${order.id})">
                        <i class="fas fa-play me-1"></i>
                        بدء التحضير
                    </button>
                `;
            case 'preparing':
                return `
                    <button class="btn btn-sm btn-success w-100" onclick="kitchenSystem.markOrderReady(${order.id})">
                        <i class="fas fa-check-double me-1"></i>
                        تحديد الطلب كجاهز
                    </button>
                `;
            case 'ready':
                return `
                    <button class="btn btn-sm btn-outline-primary w-100" onclick="kitchenSystem.markOrderServed(${order.id})">
                        <i class="fas fa-truck me-1"></i>
                        تم التسليم
                    </button>
                `;
            default:
                return '';
        }
    }

    getTimeBadge(order, status) {
        const badgeClass = {
            'confirmed': 'danger',
            'preparing': 'info',
            'ready': 'success'
        }[status] || 'secondary';

        return `
            <span class="badge bg-${badgeClass}">
                ${order.waiting_minutes} د
            </span>
        `;
    }

    updateKPICounters(orders) {
        const counts = {
            confirmed: orders.filter(o => o.status === 'confirmed').length,
            preparing: orders.filter(o => o.status === 'preparing').length,
            ready: orders.filter(o => o.status === 'ready').length,
            total: orders.filter(o => ['confirmed', 'preparing', 'ready'].includes(o.status)).length
        };

        // Update KPI displays
        const kpiCards = document.querySelectorAll('.kpi-card');
        if (kpiCards[0]) kpiCards[0].querySelector('h4').textContent = counts.confirmed;
        if (kpiCards[1]) kpiCards[1].querySelector('h4').textContent = counts.preparing;
        if (kpiCards[2]) kpiCards[2].querySelector('h4').textContent = counts.ready;
        if (kpiCards[3]) kpiCards[3].querySelector('h4').textContent = counts.total;
    }

    updateLastUpdateTime(timestamp) {
        const lastUpdateElement = document.getElementById('lastUpdate');
        if (lastUpdateElement) {
            lastUpdateElement.textContent = this.formatTime(timestamp);
        }
    }

    // Kitchen Actions
    async startPreparing(orderId) {
        try {
            this.showLoadingState(true);
            
            const response = await fetch(`/kitchen/start-preparing/${orderId}`, {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': this.getCsrfToken()
                }
            });

            const data = await response.json();
            
            if (data.success) {
                this.showNotification('تم بدء تحضير الطلب', 'success');
                this.loadKitchenData();
            } else {
                this.showNotification(data.message || 'خطأ في بدء التحضير', 'error');
            }
        } catch (error) {
            console.error('Error starting preparation:', error);
            this.showNotification('خطأ في الاتصال', 'error');
        } finally {
            this.showLoadingState(false);
        }
    }

    async markItemReady(itemId) {
        try {
            const response = await fetch(`/kitchen/mark-item-ready/${itemId}`, {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': this.getCsrfToken()
                }
            });

            const data = await response.json();
            
            if (data.success) {
                this.showNotification('تم تحديد العنصر كجاهز', 'success');
                this.loadKitchenData();
            } else {
                this.showNotification(data.message || 'خطأ في تحديد العنصر', 'error');
            }
        } catch (error) {
            console.error('Error marking item ready:', error);
            this.showNotification('خطأ في الاتصال', 'error');
        }
    }

    async markOrderReady(orderId) {
        if (!confirm('هل أنت متأكد من أن الطلب جاهز؟')) return;

        try {
            this.showLoadingState(true);
            
            const response = await fetch(`/kitchen/mark-order-ready/${orderId}`, {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': this.getCsrfToken()
                }
            });

            const data = await response.json();
            
            if (data.success) {
                this.showNotification('تم تحديد الطلب كجاهز', 'success');
                this.loadKitchenData();
            } else {
                this.showNotification(data.message || 'خطأ في تحديد الطلب', 'error');
            }
        } catch (error) {
            console.error('Error marking order ready:', error);
            this.showNotification('خطأ في الاتصال', 'error');
        } finally {
            this.showLoadingState(false);
        }
    }

    async markOrderServed(orderId) {
        if (!confirm('هل تم تسليم الطلب؟')) return;

        try {
            this.showLoadingState(true);
            
            const response = await fetch(`/kitchen/mark-served/${orderId}`, {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': this.getCsrfToken()
                }
            });

            const data = await response.json();
            
            if (data.success) {
                this.showNotification('تم تحديد الطلب كمُسلم', 'success');
                this.loadKitchenData();
            } else {
                this.showNotification(data.message || 'خطأ في تحديد الطلب', 'error');
            }
        } catch (error) {
            console.error('Error marking order served:', error);
            this.showNotification('خطأ في الاتصال', 'error');
        } finally {
            this.showLoadingState(false);
        }
    }

    showItemNotes(itemId) {
        this.currentItemId = itemId;
        const modal = new bootstrap.Modal(document.getElementById('itemNotesModal'));
        modal.show();
    }

    async saveItemNotes() {
        const notes = document.getElementById('kitchenNotes')?.value;
        if (!notes || !this.currentItemId) return;

        try {
            const response = await fetch(`/kitchen/add-item-notes/${this.currentItemId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.getCsrfToken()
                },
                body: JSON.stringify({ kitchen_notes: notes })
            });

            const data = await response.json();
            
            if (data.success) {
                this.showNotification('تم حفظ الملاحظة', 'success');
                this.loadKitchenData();
                
                const modal = bootstrap.Modal.getInstance(document.getElementById('itemNotesModal'));
                modal.hide();
                document.getElementById('kitchenNotes').value = '';
            } else {
                this.showNotification(data.message || 'خطأ في حفظ الملاحظة', 'error');
            }
        } catch (error) {
            console.error('Error saving item notes:', error);
            this.showNotification('خطأ في الاتصال', 'error');
        }
    }

    showIssueModal(itemId) {
        this.currentItemId = itemId;
        const modal = new bootstrap.Modal(document.getElementById('issueModal'));
        modal.show();
    }

    async reportIssue() {
        const issueType = document.getElementById('issueType')?.value;
        const description = document.getElementById('issueDescription')?.value;
        
        if (!issueType || !description || !this.currentItemId) {
            this.showNotification('يرجى ملء جميع الحقول', 'warning');
            return;
        }

        try {
            const response = await fetch(`/kitchen/report-issue/${this.currentItemId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.getCsrfToken()
                },
                body: JSON.stringify({
                    issue_type: issueType,
                    description: description
                })
            });

            const data = await response.json();
            
            if (data.success) {
                this.showNotification('تم الإبلاغ عن المشكلة', 'success');
                this.loadKitchenData();
                
                const modal = bootstrap.Modal.getInstance(document.getElementById('issueModal'));
                modal.hide();
                document.getElementById('issueDescription').value = '';
            } else {
                this.showNotification(data.message || 'خطأ في الإبلاغ', 'error');
            }
        } catch (error) {
            console.error('Error reporting issue:', error);
            this.showNotification('خطأ في الاتصال', 'error');
        }
    }

    async showMetrics() {
        try {
            const response = await fetch('/kitchen/metrics');
            const data = await response.json();
            
            if (data.success) {
                this.displayMetrics(data.metrics);
                const modal = new bootstrap.Modal(document.getElementById('metricsModal'));
                modal.show();
            }
        } catch (error) {
            console.error('Error loading metrics:', error);
            this.showNotification('خطأ في تحميل الإحصائيات', 'error');
        }
    }

    displayMetrics(metrics) {
        const container = document.getElementById('metricsContent');
        if (!container) return;
        
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

    toggleFullscreen() {
        if (!document.fullscreenElement) {
            document.documentElement.requestFullscreen().catch(err => {
                console.warn('Error entering fullscreen:', err);
            });
        } else {
            document.exitFullscreen();
        }
    }

    toggleAutoRefresh() {
        this.autoRefreshEnabled = !this.autoRefreshEnabled;
        const btn = document.getElementById('refreshBtn');
        
        if (this.autoRefreshEnabled) {
            this.startAutoRefresh();
            btn.classList.add('active');
            btn.innerHTML = '<i class="fas fa-pause"></i> إيقاف التحديث';
            this.showNotification('تم تشغيل التحديث التلقائي', 'info');
        } else {
            this.stopAutoRefresh();
            btn.classList.remove('active');
            btn.innerHTML = '<i class="fas fa-sync-alt"></i> تحديث تلقائي';
            this.showNotification('تم إيقاف التحديث التلقائي', 'info');
        }
    }

    toggleSound() {
        this.soundEnabled = !this.soundEnabled;
        this.showNotification(`الأصوات ${this.soundEnabled ? 'مفعلة' : 'معطلة'}`, 'info');
    }

    startAutoRefresh() {
        if (!this.autoRefreshEnabled) return;
        
        this.refreshTimer = setInterval(() => {
            this.loadKitchenData();
        }, this.refreshInterval);
    }

    stopAutoRefresh() {
        if (this.refreshTimer) {
            clearInterval(this.refreshTimer);
            this.refreshTimer = null;
        }
    }

    showNewOrderNotification(count) {
        this.showNotification(`وصل ${count} طلب جديد`, 'success');
    }

    playNotificationSound() {
        if (!this.soundEnabled || !this.audioContext) return;
        
        try {
            const oscillator = this.audioContext.createOscillator();
            const gainNode = this.audioContext.createGain();
            
            oscillator.connect(gainNode);
            gainNode.connect(this.audioContext.destination);
            
            oscillator.frequency.value = 800;
            oscillator.type = 'sine';
            
            gainNode.gain.setValueAtTime(0.3, this.audioContext.currentTime);
            gainNode.gain.exponentialRampToValueAtTime(0.01, this.audioContext.currentTime + 0.5);
            
            oscillator.start(this.audioContext.currentTime);
            oscillator.stop(this.audioContext.currentTime + 0.5);
        } catch (error) {
            console.warn('Error playing notification sound:', error);
        }
    }

    showLoadingState(loading) {
        const container = document.querySelector('.kitchen-container');
        if (container) {
            if (loading) {
                container.classList.add('loading');
            } else {
                container.classList.remove('loading');
            }
        }
    }

    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `alert alert-${this.getAlertClass(type)} alert-dismissible fade show position-fixed`;
        notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px; max-width: 400px;';
        notification.innerHTML = `
            <div class="d-flex align-items-center">
                <i class="fas ${this.getIconClass(type)} me-2"></i>
                <span>${message}</span>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 3000);
    }

    getAlertClass(type) {
        const classes = {
            'success': 'success',
            'error': 'danger',
            'warning': 'warning',
            'info': 'info'
        };
        return classes[type] || 'info';
    }

    getIconClass(type) {
        const icons = {
            'success': 'fa-check-circle',
            'error': 'fa-exclamation-circle',
            'warning': 'fa-exclamation-triangle',
            'info': 'fa-info-circle'
        };
        return icons[type] || 'fa-info-circle';
    }

    getCsrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.content || '';
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    formatTime(timestamp) {
        return new Date(timestamp).toLocaleTimeString('ar-SA', {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        });
    }

    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    async searchProducts(query) {
        if (query.length < 2) return;
        
        try {
            // Implement product search for kitchen
            // This would connect to your product search endpoint
            console.log('Searching for:', query);
        } catch (error) {
            console.error('Search error:', error);
        }
    }
}

// Initialize Kitchen system when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    window.kitchenSystem = new KitchenSystem();
});

// Global functions for HTML onclick events
function startPreparing(orderId) {
    window.kitchenSystem.startPreparing(orderId);
}

function markItemReady(itemId) {
    window.kitchenSystem.markItemReady(itemId);
}

function markOrderReady(orderId) {
    window.kitchenSystem.markOrderReady(orderId);
}

function markOrderServed(orderId) {
    window.kitchenSystem.markOrderServed(orderId);
}

function showItemNotes(itemId) {
    window.kitchenSystem.showItemNotes(itemId);
}

function saveItemNotes() {
    window.kitchenSystem.saveItemNotes();
}

function showIssueModal(itemId) {
    window.kitchenSystem.showIssueModal(itemId);
}

function reportIssue() {
    window.kitchenSystem.reportIssue();
}

function toggleAutoRefresh() {
    window.kitchenSystem.toggleAutoRefresh();
}

function toggleFullscreen() {
    window.kitchenSystem.toggleFullscreen();
}

function showMetrics() {
    window.kitchenSystem.showMetrics();
}

// Page visibility change handling
document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
        window.kitchenSystem.stopAutoRefresh();
    } else {
        window.kitchenSystem.startAutoRefresh();
        window.kitchenSystem.loadKitchenData();
    }
});

// Before page unload
window.addEventListener('beforeunload', function() {
    window.kitchenSystem.stopAutoRefresh();
});