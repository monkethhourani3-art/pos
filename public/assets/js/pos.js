/**
 * Restaurant POS System JavaScript
 * Handles POS interface interactions
 */

class POSSystem {
    constructor() {
        this.currentOrderId = null;
        this.currentTableId = null;
        this.orderItems = [];
        this.autoRefreshInterval = null;
        this.isLoading = false;
        
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.setupKeyboardShortcuts();
        this.loadCurrentOrder();
        this.startAutoRefresh();
    }

    setupEventListeners() {
        // Category filtering
        const categoryButtons = document.querySelectorAll('.category-item');
        categoryButtons.forEach(button => {
            button.addEventListener('click', (e) => {
                this.filterByCategory(e.target.closest('.category-item').dataset.category);
            });
        });

        // Form submissions
        document.getElementById('statusForm')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.updateOrderStatus();
        });

        document.getElementById('cancelForm')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.cancelOrderSubmit();
        });

        // Auto-refresh toggle
        document.getElementById('refreshBtn')?.addEventListener('click', () => {
            this.toggleAutoRefresh();
        });
    }

    setupKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Ctrl+F for search
            if (e.ctrlKey && e.key === 'f') {
                e.preventDefault();
                this.showSearchModal();
            }
            
            // Ctrl+Enter to submit order
            if (e.ctrlKey && e.key === 'Enter') {
                e.preventDefault();
                const submitBtn = document.getElementById('submitBtn');
                if (submitBtn && !submitBtn.disabled) {
                    this.submitOrder();
                }
            }
            
            // Escape to close modals
            if (e.key === 'Escape') {
                const openModal = document.querySelector('.modal.show');
                if (openModal) {
                    const modal = bootstrap.Modal.getInstance(openModal);
                    modal.hide();
                }
            }

            // Ctrl+R to refresh
            if (e.ctrlKey && e.key === 'r') {
                e.preventDefault();
                this.loadCurrentOrder();
            }
        });
    }

    async loadCurrentOrder() {
        if (this.isLoading) return;
        
        try {
            this.isLoading = true;
            const response = await fetch('/pos/current-order');
            const data = await response.json();
            
            if (data.success && data.order_items) {
                this.updateOrderDisplay(data.order_items);
                if (data.order) {
                    this.currentOrderId = data.order.id;
                }
            }
        } catch (error) {
            console.error('Error loading current order:', error);
            this.showNotification('خطأ في تحميل الطلب الحالي', 'error');
        } finally {
            this.isLoading = false;
        }
    }

    updateOrderDisplay(items) {
        const orderItemsContainer = document.getElementById('orderItems');
        const orderTotalsContainer = document.getElementById('orderTotals');
        const clearBtn = document.getElementById('clearBtn');
        const submitBtn = document.getElementById('submitBtn');

        if (items.length === 0) {
            orderItemsContainer.innerHTML = this.getEmptyOrderHTML();
            orderTotalsContainer.classList.add('d-none');
            this.updateButtonsState(true);
            return;
        }

        const itemsHtml = this.generateOrderItemsHTML(items);
        orderItemsContainer.innerHTML = itemsHtml;

        // Calculate and update totals
        const totals = this.calculateTotals(items);
        this.updateTotalsDisplay(totals);

        orderTotalsContainer.classList.remove('d-none');
        this.updateButtonsState(false);
    }

    getEmptyOrderHTML() {
        return `
            <div class="text-center text-muted py-4">
                <i class="fas fa-shopping-cart fa-3x mb-3"></i>
                <p>لا توجد عناصر في الطلب</p>
                <small>اختر طاولة وابدأ بإضافة المنتجات</small>
            </div>
        `;
    }

    generateOrderItemsHTML(items) {
        let html = '';
        
        items.forEach(item => {
            const itemTotal = item.quantity * item.unit_price;
            html += `
                <div class="order-item" data-item-id="${item.id}">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <h6 class="mb-1">${this.escapeHtml(item.product_name)}</h6>
                            ${item.notes ? `<small class="text-muted">${this.escapeHtml(item.notes)}</small>` : ''}
                            ${item.kitchen_notes ? `
                                <div class="kitchen-notes mt-1">
                                    <small class="text-info">
                                        <i class="fas fa-utensils me-1"></i>
                                        ${this.escapeHtml(item.kitchen_notes)}
                                    </small>
                                </div>
                            ` : ''}
                        </div>
                        <button class="btn btn-sm btn-outline-danger" onclick="posSystem.removeItem(${item.id})">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <div class="quantity-controls">
                            <button class="btn btn-sm btn-outline-secondary" onclick="posSystem.updateQuantity(${item.id}, ${item.quantity - 1})">
                                <i class="fas fa-minus"></i>
                            </button>
                            <span class="mx-2 fw-bold">${item.quantity}</span>
                            <button class="btn btn-sm btn-outline-secondary" onclick="posSystem.updateQuantity(${item.id}, ${item.quantity + 1})">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                        <span class="fw-bold text-success">${itemTotal.toFixed(2)} ريال</span>
                    </div>
                </div>
            `;
        });

        return html;
    }

    calculateTotals(items) {
        let subtotal = 0;
        items.forEach(item => {
            subtotal += item.quantity * item.unit_price;
        });

        const taxAmount = subtotal * 0.15; // 15% VAT
        const totalAmount = subtotal + taxAmount;

        return { subtotal, taxAmount, totalAmount };
    }

    updateTotalsDisplay(totals) {
        document.getElementById('subtotal').textContent = totals.subtotal.toFixed(2) + ' ريال';
        document.getElementById('taxAmount').textContent = totals.taxAmount.toFixed(2) + ' ريال';
        document.getElementById('totalAmount').textContent = totals.totalAmount.toFixed(2) + ' ريال';
    }

    updateButtonsState(disabled) {
        const clearBtn = document.getElementById('clearBtn');
        const submitBtn = document.getElementById('submitBtn');
        
        if (clearBtn) clearBtn.disabled = disabled;
        if (submitBtn) submitBtn.disabled = disabled;
    }

    filterByCategory(categoryName) {
        // Update active button
        document.querySelectorAll('.category-item').forEach(btn => {
            btn.classList.remove('active');
        });
        document.querySelector(`[data-category="${categoryName}"]`).classList.add('active');

        // Show/hide product categories
        document.querySelectorAll('.product-category').forEach(category => {
            if (categoryName === 'all' || category.dataset.category === categoryName) {
                category.style.display = 'block';
                category.style.animation = 'fadeIn 0.3s ease-in-out';
            } else {
                category.style.display = 'none';
            }
        });
    }

    async addToOrder(productId, quantity = 1, notes = '') {
        try {
            this.showLoadingState(true);
            
            const response = await fetch('/pos/add-item', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.getCsrfToken()
                },
                body: JSON.stringify({
                    product_id: productId,
                    quantity: quantity,
                    notes: notes
                })
            });

            const data = await response.json();
            
            if (data.success) {
                this.showNotification('تم إضافة المنتج بنجاح', 'success');
                this.updateOrderDisplay(data.order_items);
                this.animateProductAddition();
            } else {
                this.showNotification(data.message || 'خطأ في إضافة المنتج', 'error');
            }
        } catch (error) {
            console.error('Error adding item:', error);
            this.showNotification('خطأ في الاتصال', 'error');
        } finally {
            this.showLoadingState(false);
        }
    }

    async updateQuantity(itemId, newQuantity) {
        if (newQuantity < 0) return;

        try {
            const response = await fetch(`/pos/update-item/${itemId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.getCsrfToken()
                },
                body: JSON.stringify({ quantity: newQuantity })
            });

            const data = await response.json();
            
            if (data.success) {
                this.updateOrderDisplay(data.order_items);
            } else {
                this.showNotification(data.message || 'خطأ في تحديث العنصر', 'error');
            }
        } catch (error) {
            console.error('Error updating quantity:', error);
            this.showNotification('خطأ في الاتصال', 'error');
        }
    }

    async removeItem(itemId) {
        if (!confirm('هل تريد حذف هذا العنصر؟')) return;

        try {
            const response = await fetch(`/pos/remove-item/${itemId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-Token': this.getCsrfToken()
                }
            });

            const data = await response.json();
            
            if (data.success) {
                this.showNotification('تم حذف العنصر', 'success');
                this.loadCurrentOrder();
            } else {
                this.showNotification(data.message || 'خطأ في حذف العنصر', 'error');
            }
        } catch (error) {
            console.error('Error removing item:', error);
            this.showNotification('خطأ في الاتصال', 'error');
        }
    }

    async submitOrder() {
        if (!confirm('هل تريد إرسال الطلب للمطبخ؟')) return;

        try {
            this.showLoadingState(true);
            
            const response = await fetch('/pos/submit-order', {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': this.getCsrfToken()
                }
            });

            const data = await response.json();
            
            if (data.success) {
                this.showNotification('تم إرسال الطلب للمطبخ بنجاح', 'success');
                setTimeout(() => {
                    window.location.href = '/pos';
                }, 1500);
            } else {
                this.showNotification(data.message || 'خطأ في إرسال الطلب', 'error');
            }
        } catch (error) {
            console.error('Error submitting order:', error);
            this.showNotification('خطأ في الاتصال', 'error');
        } finally {
            this.showLoadingState(false);
        }
    }

    async selectTable(tableId) {
        try {
            this.showLoadingState(true);
            
            const response = await fetch('/pos/start-order', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.getCsrfToken()
                },
                body: JSON.stringify({ table_id: tableId })
            });

            const data = await response.json();
            
            if (data.success) {
                this.currentTableId = tableId;
                this.currentOrderId = data.order_id;
                this.showNotification('تم تحديد الطاولة بنجاح', 'success');
                
                // Hide modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('tableSelectorModal'));
                modal.hide();
                
                this.loadCurrentOrder();
            } else {
                this.showNotification(data.message || 'خطأ في تحديد الطاولة', 'error');
            }
        } catch (error) {
            console.error('Error selecting table:', error);
            this.showNotification('خطأ في الاتصال', 'error');
        } finally {
            this.showLoadingState(false);
        }
    }

    clearOrder() {
        if (!confirm('هل تريد مسح الطلب الحالي؟')) return;
        
        this.orderItems = [];
        this.updateOrderDisplay([]);
        this.currentOrderId = null;
        this.currentTableId = null;
        
        this.showNotification('تم مسح الطلب', 'info');
    }

    showTableSelector() {
        const modal = new bootstrap.Modal(document.getElementById('tableSelectorModal'));
        modal.show();
    }

    showSearchModal() {
        const modal = new bootstrap.Modal(document.getElementById('productSearchModal'));
        modal.show();
        
        // Focus on search input
        setTimeout(() => {
            document.getElementById('searchInput')?.focus();
        }, 300);
    }

    async searchProducts(query) {
        if (query.length < 2) return;

        try {
            const response = await fetch(`/pos/search-products?q=${encodeURIComponent(query)}`);
            const data = await response.json();
            
            if (data.success) {
                this.displaySearchResults(data.products);
            }
        } catch (error) {
            console.error('Search error:', error);
        }
    }

    displaySearchResults(products) {
        const container = document.getElementById('searchResults');
        
        if (products.length === 0) {
            container.innerHTML = '<p class="text-center text-muted">لا توجد نتائج</p>';
            return;
        }

        let html = '<div class="row">';
        products.forEach(product => {
            html += `
                <div class="col-md-6 mb-2">
                    <div class="search-result-item" onclick="posSystem.addToOrder(${product.id})">
                        <h6>${this.escapeHtml(product.name)}</h6>
                        <small class="text-muted">${product.price} ريال</small>
                    </div>
                </div>
            `;
        });
        html += '</div>';
        
        container.innerHTML = html;
    }

    showLoadingState(loading) {
        const container = document.querySelector('.pos-container');
        if (container) {
            if (loading) {
                container.classList.add('loading');
            } else {
                container.classList.remove('loading');
            }
        }
    }

    animateProductAddition() {
        // Add visual feedback for product addition
        const orderSummary = document.querySelector('.pos-order');
        if (orderSummary) {
            orderSummary.style.transform = 'scale(1.02)';
            orderSummary.style.transition = 'transform 0.3s ease';
            
            setTimeout(() => {
                orderSummary.style.transform = 'scale(1)';
            }, 300);
        }
    }

    startAutoRefresh() {
        this.autoRefreshInterval = setInterval(() => {
            this.loadCurrentOrder();
        }, 30000); // Refresh every 30 seconds
    }

    stopAutoRefresh() {
        if (this.autoRefreshInterval) {
            clearInterval(this.autoRefreshInterval);
            this.autoRefreshInterval = null;
        }
    }

    toggleAutoRefresh() {
        if (this.autoRefreshInterval) {
            this.stopAutoRefresh();
            this.showNotification('تم إيقاف التحديث التلقائي', 'info');
        } else {
            this.startAutoRefresh();
            this.showNotification('تم تشغيل التحديث التلقائي', 'info');
        }
    }

    showNotification(message, type = 'info') {
        // Create notification element
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
        
        // Auto remove after 3 seconds
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
}

// Initialize POS system when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    window.posSystem = new POSSystem();
});

// Global functions for HTML onclick events
function addToOrder(productId) {
    window.posSystem.addToOrder(productId);
}

function selectTable(tableId) {
    window.posSystem.selectTable(tableId);
}

function updateQuantity(itemId, newQuantity) {
    window.posSystem.updateQuantity(itemId, newQuantity);
}

function removeItem(itemId) {
    window.posSystem.removeItem(itemId);
}

function submitOrder() {
    window.posSystem.submitOrder();
}

function clearOrder() {
    window.posSystem.clearOrder();
}

function showTableSelector() {
    window.posSystem.showTableSelector();
}

function showSearchModal() {
    window.posSystem.showSearchModal();
}

function searchProducts() {
    const query = document.getElementById('searchInput')?.value || '';
    window.posSystem.searchProducts(query);
}

// Page visibility change handling
document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
        window.posSystem.stopAutoRefresh();
    } else {
        window.posSystem.startAutoRefresh();
        window.posSystem.loadCurrentOrder();
    }
});

// Before page unload
window.addEventListener('beforeunload', function() {
    window.posSystem.stopAutoRefresh();
});

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .fadeIn {
        animation: fadeIn 0.3s ease-in-out;
    }
    
    .loading {
        opacity: 0.7;
        pointer-events: none;
    }
    
    .loading::after {
        content: '';
        position: fixed;
        top: 50%;
        left: 50%;
        width: 40px;
        height: 40px;
        margin: -20px 0 0 -20px;
        border: 3px solid #f3f3f3;
        border-top: 3px solid var(--primary-color);
        border-radius: 50%;
        animation: spin 1s linear infinite;
        z-index: 10000;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
`;
document.head.appendChild(style);