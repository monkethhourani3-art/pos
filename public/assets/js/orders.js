/**
 * Orders Management System JavaScript
 * Handles admin interface for order management
 */

class OrdersManagement {
    constructor() {
        this.selectedOrders = new Set();
        this.currentFilters = {};
        this.isLoading = false;
        
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.setupFiltersForm();
        this.updateBulkButtons();
    }

    setupEventListeners() {
        // Status form submission
        document.getElementById('statusForm')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.updateOrderStatus();
        });

        // Cancel form submission
        document.getElementById('cancelForm')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.cancelOrderSubmit();
        });

        // Filters form
        document.getElementById('filtersForm')?.addEventListener('submit', (e) => {
            this.handleFiltersSubmit(e);
        });

        // Search input
        document.getElementById('searchInput')?.addEventListener('input', (e) => {
            this.debounce(() => this.searchOrders(e.target.value), 300)();
        });
    }

    setupFiltersForm() {
        const filtersForm = document.getElementById('filtersForm');
        if (filtersForm) {
            // Auto-submit on filter change (debounced)
            const inputs = filtersForm.querySelectorAll('select, input[type="date"]');
            inputs.forEach(input => {
                input.addEventListener('change', () => {
                    this.debounce(() => filtersForm.submit(), 500)();
                });
            });
        }
    }

    async handleFiltersSubmit(e) {
        const submitBtn = e.target.querySelector('button[type="submit"]');
        if (submitBtn) {
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> جاري البحث...';
            submitBtn.disabled = true;
            
            // Reset after a delay (in case of errors)
            setTimeout(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 3000);
        }
    }

    // Order Status Management
    changeStatus(orderId) {
        document.getElementById('orderId').value = orderId;
        const modal = new bootstrap.Modal(document.getElementById('statusModal'));
        modal.show();
    }

    async updateOrderStatus() {
        const form = document.getElementById('statusForm');
        const formData = new FormData(form);
        const orderId = formData.get('order_id');
        
        try {
            this.showLoadingState(true);
            
            const response = await fetch(`/orders/${orderId}/status`, {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': this.getCsrfToken()
                },
                body: formData
            });

            const data = await response.json();
            
            if (data.success) {
                this.showNotification('تم تحديث حالة الطلب بنجاح', 'success');
                this.reloadPage();
            } else {
                this.showNotification(data.message || 'خطأ في تحديث حالة الطلب', 'error');
            }
        } catch (error) {
            console.error('Error updating order status:', error);
            this.showNotification('خطأ في الاتصال', 'error');
        } finally {
            this.showLoadingState(false);
        }
    }

    // Order Cancellation
    cancelOrder(orderId) {
        document.getElementById('cancelOrderId').value = orderId;
        const modal = new bootstrap.Modal(document.getElementById('cancelModal'));
        modal.show();
    }

    async cancelOrderSubmit() {
        const form = document.getElementById('cancelForm');
        const formData = new FormData(form);
        const orderId = formData.get('order_id');
        
        try {
            this.showLoadingState(true);
            
            const response = await fetch(`/orders/${orderId}/cancel`, {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': this.getCsrfToken()
                },
                body: formData
            });

            const data = await response.json();
            
            if (data.success) {
                this.showNotification('تم إلغاء الطلب بنجاح', 'success');
                this.reloadPage();
            } else {
                this.showNotification(data.message || 'خطأ في إلغاء الطلب', 'error');
            }
        } catch (error) {
            console.error('Error cancelling order:', error);
            this.showNotification('خطأ في الاتصال', 'error');
        } finally {
            this.showLoadingState(false);
        }
    }

    // Order Operations
    printReceipt(orderId) {
        window.open(`/orders/${orderId}/print`, '_blank');
    }

    // Bulk Operations
    toggleSelectAll() {
        const selectAllCheckbox = document.getElementById('selectAllCheckbox');
        const orderCheckboxes = document.querySelectorAll('.order-checkbox');
        
        orderCheckboxes.forEach(checkbox => {
            checkbox.checked = selectAllCheckbox.checked;
            this.updateSelectedOrders(checkbox.value, checkbox.checked);
        });
        
        this.updateBulkButtons();
    }

    selectAll() {
        document.getElementById('selectAllCheckbox').checked = true;
        this.toggleSelectAll();
    }

    updateSelectedOrders(orderId, selected) {
        if (selected) {
            this.selectedOrders.add(orderId);
        } else {
            this.selectedOrders.delete(orderId);
        }
    }

    updateBulkButtons() {
        const selectedCheckboxes = document.querySelectorAll('.order-checkbox:checked');
        const bulkCancelBtn = document.getElementById('bulkCancelBtn');
        const bulkPrintBtn = document.getElementById('bulkPrintBtn');
        
        const hasSelection = selectedCheckboxes.length > 0;
        
        if (bulkCancelBtn) bulkCancelBtn.disabled = !hasSelection;
        if (bulkPrintBtn) bulkPrintBtn.disabled = !hasSelection;
        
        // Update select all checkbox state
        this.updateSelectAllCheckbox();
    }

    updateSelectAllCheckbox() {
        const selectAllCheckbox = document.getElementById('selectAllCheckbox');
        const orderCheckboxes = document.querySelectorAll('.order-checkbox');
        const selectedCheckboxes = document.querySelectorAll('.order-checkbox:checked');
        
        if (selectAllCheckbox) {
            const totalCheckboxes = orderCheckboxes.length;
            const selectedCount = selectedCheckboxes.length;
            
            if (selectedCount === 0) {
                selectAllCheckbox.checked = false;
                selectAllCheckbox.indeterminate = false;
            } else if (selectedCount === totalCheckboxes) {
                selectAllCheckbox.checked = true;
                selectAllCheckbox.indeterminate = false;
            } else {
                selectAllCheckbox.checked = false;
                selectAllCheckbox.indeterminate = true;
            }
        }
    }

    async bulkCancel() {
        const selectedOrders = Array.from(this.selectedOrders);
        
        if (selectedOrders.length === 0) {
            this.showNotification('يرجى تحديد طلبات للإلغاء', 'warning');
            return;
        }
        
        if (!confirm(`هل أنت متأكد من إلغاء ${selectedOrders.length} طلب؟`)) return;
        
        try {
            this.showLoadingState(true);
            
            const response = await fetch('/orders/bulk-cancel', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.getCsrfToken()
                },
                body: JSON.stringify({ order_ids: selectedOrders })
            });

            const data = await response.json();
            
            if (data.success) {
                this.showNotification(`تم إلغاء ${selectedOrders.length} طلب بنجاح`, 'success');
                this.reloadPage();
            } else {
                this.showNotification(data.message || 'خطأ في إلغاء الطلبات', 'error');
            }
        } catch (error) {
            console.error('Error bulk cancelling orders:', error);
            this.showNotification('خطأ في الاتصال', 'error');
        } finally {
            this.showLoadingState(false);
        }
    }

    bulkPrint() {
        const selectedOrders = Array.from(this.selectedOrders);
        
        if (selectedOrders.length === 0) {
            this.showNotification('يرجى تحديد طلبات للطباعة', 'warning');
            return;
        }
        
        // Open print windows for selected orders
        selectedOrders.forEach(orderId => {
            this.printReceipt(orderId);
        });
    }

    async exportOrders() {
        const form = document.getElementById('filtersForm');
        const formData = new FormData(form);
        
        // Add export parameter
        formData.append('export', 'excel');
        
        try {
            this.showLoadingState(true);
            
            // Create download link
            const params = new URLSearchParams(formData);
            const response = await fetch(`/orders/export?${params.toString()}`);
            
            if (response.ok) {
                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.style.display = 'none';
                a.href = url;
                a.download = `orders_export_${new Date().toISOString().split('T')[0]}.xlsx`;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                
                this.showNotification('تم تصدير الطلبات بنجاح', 'success');
            } else {
                throw new Error('Export failed');
            }
        } catch (error) {
            console.error('Error exporting orders:', error);
            this.showNotification('خطأ في تصدير الطلبات', 'error');
        } finally {
            this.showLoadingState(false);
        }
    }

    // Statistics
    async showStatistics() {
        try {
            const form = document.getElementById('filtersForm');
            const formData = new FormData(form);
            
            this.showLoadingState(true);
            
            const response = await fetch('/orders/statistics', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.getCsrfToken()
                },
                body: JSON.stringify(Object.fromEntries(formData))
            });

            const data = await response.json();
            
            if (data.success) {
                this.displayStatistics(data.statistics);
                const modal = new bootstrap.Modal(document.getElementById('statisticsModal'));
                modal.show();
            } else {
                this.showNotification(data.message || 'خطأ في تحميل الإحصائيات', 'error');
            }
        } catch (error) {
            console.error('Error loading statistics:', error);
            this.showNotification('خطأ في تحميل الإحصائيات', 'error');
        } finally {
            this.showLoadingState(false);
        }
    }

    displayStatistics(stats) {
        const container = document.getElementById('statisticsContent');
        if (!container) return;
        
        container.innerHTML = `
            <div class="row">
                <div class="col-md-6">
                    <h6>ملخص الطلبات</h6>
                    <div class="stats-card">
                        <div class="stats-icon">
                            <i class="fas fa-shopping-cart text-primary"></i>
                        </div>
                        <div class="stats-number">${stats.total_orders || 0}</div>
                        <div class="stats-label">إجمالي الطلبات</div>
                    </div>
                    <div class="stats-card mt-3">
                        <div class="stats-icon">
                            <i class="fas fa-check-circle text-success"></i>
                        </div>
                        <div class="stats-number">${stats.completed_orders || 0}</div>
                        <div class="stats-label">الطلبات المكتملة</div>
                    </div>
                    <div class="stats-card mt-3">
                        <div class="stats-icon">
                            <i class="fas fa-times-circle text-danger"></i>
                        </div>
                        <div class="stats-number">${stats.cancelled_orders || 0}</div>
                        <div class="stats-label">الطلبات الملغية</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <h6>الإيرادات</h6>
                    <div class="stats-card">
                        <div class="stats-icon">
                            <i class="fas fa-money-bill-wave text-success"></i>
                        </div>
                        <div class="stats-number">${(stats.total_revenue || 0).toFixed(2)}</div>
                        <div class="stats-label">إجمالي الإيرادات (ريال)</div>
                    </div>
                    <div class="stats-card mt-3">
                        <div class="stats-icon">
                            <i class="fas fa-chart-line text-info"></i>
                        </div>
                        <div class="stats-number">${(stats.average_order_value || 0).toFixed(2)}</div>
                        <div class="stats-label">متوسط قيمة الطلب (ريال)</div>
                    </div>
                </div>
            </div>
        `;
    }

    // Search
    async searchOrders(query) {
        if (query.length < 2) return;
        
        try {
            const response = await fetch(`/orders/search?q=${encodeURIComponent(query)}`);
            const data = await response.json();
            
            if (data.success) {
                this.displaySearchResults(data.orders);
            }
        } catch (error) {
            console.error('Search error:', error);
        }
    }

    displaySearchResults(orders) {
        // This would update the orders table with search results
        // Implementation depends on your search endpoint structure
        console.log('Search results:', orders);
    }

    // Utility Functions
    showLoadingState(loading) {
        const container = document.querySelector('.admin-container');
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
        }, 4000);
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

    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func.apply(this, args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    reloadPage() {
        setTimeout(() => {
            window.location.reload();
        }, 1500);
    }
}

// Initialize Orders Management when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    window.ordersManagement = new OrdersManagement();
    
    // Add event listeners for checkbox changes
    document.querySelectorAll('.order-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', (e) => {
            window.ordersManagement.updateSelectedOrders(e.target.value, e.target.checked);
            window.ordersManagement.updateBulkButtons();
        });
    });
});

// Global functions for HTML onclick events
function changeStatus(orderId) {
    window.ordersManagement.changeStatus(orderId);
}

function cancelOrder(orderId) {
    window.ordersManagement.cancelOrder(orderId);
}

function printReceipt(orderId) {
    window.ordersManagement.printReceipt(orderId);
}

function toggleSelectAll() {
    window.ordersManagement.toggleSelectAll();
}

function selectAll() {
    window.ordersManagement.selectAll();
}

function updateBulkButtons() {
    window.ordersManagement.updateBulkButtons();
}

function bulkCancel() {
    window.ordersManagement.bulkCancel();
}

function bulkPrint() {
    window.ordersManagement.bulkPrint();
}

function exportOrders() {
    window.ordersManagement.exportOrders();
}

function showStatistics() {
    window.ordersManagement.showStatistics();
}

function updateOrderStatus() {
    window.ordersManagement.updateOrderStatus();
}

function cancelOrderSubmit() {
    window.ordersManagement.cancelOrderSubmit();
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl+A to select all
    if (e.ctrlKey && e.key === 'a') {
        e.preventDefault();
        selectAll();
    }
    
    // Ctrl+P to bulk print
    if (e.ctrlKey && e.key === 'p') {
        e.preventDefault();
        bulkPrint();
    }
    
    // Ctrl+E to export
    if (e.ctrlKey && e.key === 'e') {
        e.preventDefault();
        exportOrders();
    }
    
    // Ctrl+S to show statistics
    if (e.ctrlKey && e.key === 's') {
        e.preventDefault();
        showStatistics();
    }
    
    // Escape to clear selections
    if (e.key === 'Escape') {
        document.querySelectorAll('.order-checkbox').forEach(checkbox => {
            checkbox.checked = false;
        });
        window.ordersManagement.selectedOrders.clear();
        window.ordersManagement.updateBulkButtons();
    }
});

// Table row click for quick actions
document.addEventListener('click', function(e) {
    const row = e.target.closest('tr');
    if (row && !e.target.closest('.btn, .dropdown, .form-check-input')) {
        // Double-click to view order details
        if (e.detail === 2) {
            const orderId = row.querySelector('.order-checkbox')?.value;
            if (orderId) {
                window.open(`/orders/${orderId}`, '_blank');
            }
        }
    }
});

// Auto-refresh functionality
let autoRefreshInterval;

function startAutoRefresh() {
    autoRefreshInterval = setInterval(() => {
        // Check if user is actively using the page
        if (document.hidden) return;
        
        // Refresh orders if no modals are open
        const openModal = document.querySelector('.modal.show');
        if (!openModal) {
            // You can implement AJAX refresh here instead of full page reload
            // For now, we'll just show a subtle notification
            console.log('Auto-refresh check...');
        }
    }, 60000); // Refresh every minute
}

function stopAutoRefresh() {
    if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
        autoRefreshInterval = null;
    }
}

// Start auto-refresh
startAutoRefresh();

// Stop auto-refresh when page is hidden
document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
        stopAutoRefresh();
    } else {
        startAutoRefresh();
    }
});

// Before page unload
window.addEventListener('beforeunload', function() {
    stopAutoRefresh();
});

// Print styles
window.addEventListener('beforeprint', function() {
    // Hide bulk action buttons when printing
    document.querySelectorAll('.bulk-actions, .btn-group').forEach(el => {
        el.style.display = 'none';
    });
});

window.addEventListener('afterprint', function() {
    // Restore bulk action buttons after printing
    document.querySelectorAll('.bulk-actions, .btn-group').forEach(el => {
        el.style.display = '';
    });
});