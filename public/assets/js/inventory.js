/**
 * Inventory Management JavaScript
 * Restaurant POS System - Phase 5
 * Handles inventory management, stock updates, and operations
 */

class InventoryManager {
    constructor() {
        this.currentPage = 1;
        this.filters = {
            search: '',
            category_id: '',
            supplier_id: '',
            low_stock: ''
        };
        this.selectedItems = new Set();
        this.processing = false;

        this.init();
    }

    init() {
        this.bindEvents();
        this.setupFilters();
        this.loadData();
        
        // Auto-refresh every 60 seconds
        setInterval(() => {
            this.refreshData();
        }, 60000);
    }

    bindEvents() {
        // Filter form
        document.addEventListener('submit', (e) => {
            if (e.target.matches('#inventoryFiltersForm')) {
                e.preventDefault();
                this.applyFilters(e.target);
            }
        });

        // Search input with debounce
        document.addEventListener('input', (e) => {
            if (e.target.matches('#searchInput')) {
                this.debouncedSearch(e.target.value);
            }
        });

        // Pagination
        document.addEventListener('click', (e) => {
            if (e.target.matches('.pagination-link')) {
                e.preventDefault();
                this.changePage(parseInt(e.target.dataset.page));
            }
        });

        // Item selection
        document.addEventListener('change', (e) => {
            if (e.target.matches('#selectAllItems')) {
                this.toggleSelectAll(e.target.checked);
            } else if (e.target.matches('.select-item')) {
                this.toggleItemSelection(e.target.value, e.target.checked);
            }
        });

        // Item actions
        document.addEventListener('click', (e) => {
            if (e.target.matches('.inventory-item-row')) {
                // Row click - maybe select
            }
        });

        // Modal events
        document.addEventListener('click', (e) => {
            if (e.target.matches('.modal-close') || e.target.matches('.modal-backdrop')) {
                this.closeModal();
            }
        });

        // Refresh button
        document.addEventListener('click', (e) => {
            if (e.target.matches('.refresh-inventory-btn')) {
                this.refreshData();
            }
        });
    }

    setupFilters() {
        // Initialize filter values
        const searchInput = document.querySelector('#searchInput');
        if (searchInput && searchInput.value) {
            this.filters.search = searchInput.value;
        }

        const categorySelect = document.querySelector('#categoryFilter');
        if (categorySelect && categorySelect.value) {
            this.filters.category_id = categorySelect.value;
        }

        const supplierSelect = document.querySelector('#supplierFilter');
        if (supplierSelect && supplierSelect.value) {
            this.filters.supplier_id = supplierSelect.value;
        }

        const lowStockSelect = document.querySelector('#lowStockFilter');
        if (lowStockSelect && lowStockSelect.value) {
            this.filters.low_stock = lowStockSelect.value;
        }
    }

    async loadData(page = 1) {
        try {
            this.currentPage = page;
            const params = new URLSearchParams({
                ...this.filters,
                page: page
            });

            const response = await fetch(`/inventory?${params}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) {
                throw new Error('Failed to load inventory data');
            }

            const data = await response.json();
            this.renderInventoryItems(data.inventory_items);
            this.renderPagination(data.pagination);
            this.updateStatistics(data.statistics);

        } catch (error) {
            console.error('Error loading inventory data:', error);
            this.showError('فشل في تحميل بيانات المخزون');
        }
    }

    renderInventoryItems(items) {
        const container = document.querySelector('tbody');
        if (!container) return;

        if (items.length === 0) {
            container.innerHTML = `
                <tr>
                    <td colspan="11" class="text-center py-4">
                        <div class="no-items">
                            <i class="fas fa-boxes text-muted mb-3" style="font-size: 3rem;"></i>
                            <p class="text-muted">لا توجد أصناف</p>
                        </div>
                    </td>
                </tr>
            `;
            return;
        }

        const itemsHtml = items.map(item => `
            <tr class="inventory-item-row" data-item-id="${item.id}">
                <td>
                    <input class="form-check-input select-item" type="checkbox" 
                           value="${item.id}">
                </td>
                <td>
                    <div class="item-info">
                        <div class="item-name">
                            <strong>${this.escapeHtml(item.name)}</strong>
                            ${item.barcode ? `<small class="text-muted">#${this.escapeHtml(item.barcode)}</small>` : ''}
                        </div>
                        ${item.location ? `
                            <small class="text-muted">
                                <i class="fas fa-map-marker-alt me-1"></i>
                                ${this.escapeHtml(item.location)}
                            </small>
                        ` : ''}
                    </div>
                </td>
                <td>
                    <span class="badge bg-secondary">
                        ${this.escapeHtml(item.category_name || 'غير محدد')}
                    </span>
                </td>
                <td>
                    ${this.escapeHtml(item.supplier_name || 'غير محدد')}
                </td>
                <td>
                    <span class="quantity-display" data-quantity="${item.quantity}">
                        ${this.formatNumber(item.quantity, 2)}
                    </span>
                </td>
                <td>
                    ${this.escapeHtml(item.unit_symbol || '')}
                </td>
                <td class="text-success">
                    ${this.formatNumber(item.unit_cost, 2)} ر.س
                </td>
                <td class="text-primary">
                    ${this.formatNumber(item.selling_price, 2)} ر.س
                </td>
                <td class="fw-bold">
                    ${this.formatNumber(item.total_value, 2)} ر.س
                </td>
                <td>
                    ${this.renderStockStatus(item.stock_status)}
                </td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary" 
                                onclick="viewItem(${item.id})" 
                                title="عرض">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn btn-outline-secondary" 
                                onclick="editItem(${item.id})" 
                                title="تعديل">
                            <i class="fas fa-edit"></i>
                        </button>
                        <div class="btn-group">
                            <button class="btn btn-outline-info dropdown-toggle" 
                                    data-bs-toggle="dropdown" title="المزيد">
                                <i class="fas fa-ellipsis-h"></i>
                            </button>
                            <ul class="dropdown-menu">
                                <li>
                                    <a class="dropdown-item" href="#" 
                                       onclick="updateQuantity(${item.id})">
                                        <i class="fas fa-plus-minus me-2"></i>تحديث الكمية
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="/inventory/${item.id}/movements">
                                        <i class="fas fa-history me-2"></i>سجل الحركات
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item text-danger" href="#" 
                                       onclick="deleteItem(${item.id})">
                                        <i class="fas fa-trash me-2"></i>حذف
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </td>
            </tr>
        `).join('');

        container.innerHTML = itemsHtml;
    }

    renderStockStatus(status) {
        const statusMap = {
            'low': {
                class: 'bg-danger',
                icon: 'fa-exclamation-triangle',
                text: 'منخفض'
            },
            'medium': {
                class: 'bg-warning',
                icon: 'fa-exclamation-circle',
                text: 'متوسط'
            },
            'normal': {
                class: 'bg-success',
                icon: 'fa-check-circle',
                text: 'كافي'
            }
        };

        const config = statusMap[status] || statusMap.normal;
        
        return `
            <span class="badge ${config.class}">
                <i class="fas ${config.icon} me-1"></i>
                ${config.text}
            </span>
        `;
    }

    renderPagination(pagination) {
        const container = document.querySelector('.pagination');
        if (!container || !pagination || pagination.total_pages <= 1) {
            return;
        }

        let paginationHtml = '';

        // Previous button
        if (pagination.current_page > 1) {
            paginationHtml += `
                <li class="page-item">
                    <a class="page-link pagination-link" href="#" data-page="${pagination.current_page - 1}">
                        <i class="fas fa-chevron-right"></i> السابق
                    </a>
                </li>
            `;
        }

        // Page numbers
        const startPage = Math.max(1, pagination.current_page - 2);
        const endPage = Math.min(pagination.total_pages, pagination.current_page + 2);

        if (startPage > 1) {
            paginationHtml += `
                <li class="page-item">
                    <a class="page-link pagination-link" href="#" data-page="1">1</a>
                </li>
            `;
            if (startPage > 2) {
                paginationHtml += '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
        }

        for (let i = startPage; i <= endPage; i++) {
            const activeClass = i === pagination.current_page ? 'active' : '';
            paginationHtml += `
                <li class="page-item ${activeClass}">
                    <a class="page-link pagination-link" href="#" data-page="${i}">${i}</a>
                </li>
            `;
        }

        if (endPage < pagination.total_pages) {
            if (endPage < pagination.total_pages - 1) {
                paginationHtml += '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
            paginationHtml += `
                <li class="page-item">
                    <a class="page-link pagination-link" href="#" data-page="${pagination.total_pages}">
                        ${pagination.total_pages}
                    </a>
                </li>
            `;
        }

        // Next button
        if (pagination.current_page < pagination.total_pages) {
            paginationHtml += `
                <li class="page-item">
                    <a class="page-link pagination-link" href="#" data-page="${pagination.current_page + 1}">
                        التالي <i class="fas fa-chevron-left"></i>
                    </a>
                </li>
            `;
        }

        container.innerHTML = paginationHtml;
    }

    updateStatistics(stats) {
        const containers = {
            total_items: document.querySelector('.stat-card h3'),
            low_stock_items: document.querySelectorAll('.stat-card h3')[1],
            total_value: document.querySelectorAll('.stat-card h3')[2],
            average_cost: document.querySelectorAll('.stat-card h3')[3]
        };

        if (containers.total_items) {
            containers.total_items.textContent = this.formatNumber(stats.total_items || 0);
        }
        if (containers.low_stock_items) {
            containers.low_stock_items.textContent = this.formatNumber(stats.low_stock_items || 0);
        }
        if (containers.total_value) {
            containers.total_value.textContent = this.formatNumber(stats.total_value || 0, 2) + ' ر.س';
        }
        if (containers.average_cost) {
            containers.average_cost.textContent = this.formatNumber(stats.average_cost || 0, 2) + ' ر.س';
        }
    }

    applyFilters(form) {
        const formData = new FormData(form);
        
        this.filters = {
            search: formData.get('search') || '',
            category_id: formData.get('category_id') || '',
            supplier_id: formData.get('supplier_id') || '',
            low_stock: formData.get('low_stock') || ''
        };

        this.loadData(1);
    }

    debouncedSearch(query) {
        clearTimeout(this.searchTimeout);
        this.searchTimeout = setTimeout(() => {
            this.filters.search = query;
            this.loadData(1);
        }, 500);
    }

    changePage(page) {
        this.loadData(page);
    }

    toggleSelectAll(checked) {
        const checkboxes = document.querySelectorAll('.select-item');
        this.selectedItems.clear();
        
        checkboxes.forEach(checkbox => {
            checkbox.checked = checked;
            if (checked) {
                this.selectedItems.add(checkbox.value);
            }
        });

        this.updateBulkActionsVisibility();
    }

    toggleItemSelection(itemId, checked) {
        if (checked) {
            this.selectedItems.add(itemId);
        } else {
            this.selectedItems.delete(itemId);
        }

        // Update select all checkbox
        const selectAllCheckbox = document.querySelector('#selectAllItems');
        if (selectAllCheckbox) {
            const totalCheckboxes = document.querySelectorAll('.select-item').length;
            selectAllCheckbox.checked = this.selectedItems.size === totalCheckboxes;
            selectAllCheckbox.indeterminate = this.selectedItems.size > 0 && this.selectedItems.size < totalCheckboxes;
        }

        this.updateBulkActionsVisibility();
    }

    updateBulkActionsVisibility() {
        const bulkActionsContainer = document.querySelector('.bulk-actions');
        const selectedCount = document.querySelector('.selected-count');
        
        if (bulkActionsContainer) {
            bulkActionsContainer.style.display = this.selectedItems.size > 0 ? 'block' : 'none';
        }
        
        if (selectedCount) {
            selectedCount.textContent = this.selectedItems.size;
        }
    }

    async updateItemQuantity(itemId, data) {
        try {
            const response = await fetch(`/inventory/${itemId}/quantity`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (!response.ok) {
                throw new Error(result.error || 'Failed to update quantity');
            }

            return result;

        } catch (error) {
            console.error('Update quantity error:', error);
            throw error;
        }
    }

    async deleteItem(itemId) {
        try {
            const response = await fetch(`/inventory/${itemId}`, {
                method: 'DELETE',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/json'
                }
            });

            const result = await response.json();

            if (!response.ok) {
                throw new Error(result.message || 'Failed to delete item');
            }

            return result;

        } catch (error) {
            console.error('Delete item error:', error);
            throw error;
        }
    }

    async searchItems(query) {
        try {
            const response = await fetch(`/inventory/search?q=${encodeURIComponent(query)}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) {
                throw new Error('Search failed');
            }

            const result = await response.json();
            return result.items || [];

        } catch (error) {
            console.error('Search items error:', error);
            throw error;
        }
    }

    async exportItems(format = 'csv') {
        try {
            const params = new URLSearchParams({
                ...this.filters,
                format: format
            });

            const response = await fetch(`/inventory/export?${params}`, {
                headers: {
                    'Accept': 'application/octet-stream'
                }
            });

            if (!response.ok) {
                throw new Error('Export failed');
            }

            const blob = await response.blob();
            const url = URL.createObjectURL(blob);
            
            const a = document.createElement('a');
            a.href = url;
            a.download = `inventory-export.${format}`;
            a.click();

            URL.revokeObjectURL(url);

        } catch (error) {
            console.error('Export error:', error);
            throw error;
        }
    }

    // Utility methods
    formatNumber(number, decimals = 0) {
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

    closeModal() {
        document.querySelectorAll('.modal').forEach(modal => {
            const modalInstance = bootstrap.Modal.getInstance(modal);
            if (modalInstance) {
                modalInstance.hide();
            }
        });
    }

    refreshData() {
        this.loadData(this.currentPage);
    }

    // Notification methods
    showSuccess(message) {
        console.log('Success:', message);
        this.showNotification(message, 'success');
    }

    showError(message) {
        console.error('Error:', message);
        this.showNotification(message, 'error');
    }

    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.textContent = message;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.remove();
        }, 5000);
    }

    // Bulk operations
    async bulkUpdateQuantity() {
        if (this.selectedItems.size === 0) {
            this.showError('يرجى تحديد صنف واحد على الأقل');
            return;
        }

        // Show bulk update modal
        const modal = new bootstrap.Modal(document.getElementById('bulkQuantityModal'));
        modal.show();
    }

    async bulkExport() {
        if (this.selectedItems.size === 0) {
            this.showError('يرجى تحديد صنف واحد على الأقل');
            return;
        }

        try {
            const format = prompt('تنسيق التصدير (csv, excel):', 'csv');
            if (!format) return;

            // This would require server-side support for bulk export
            await this.exportItems(format);

        } catch (error) {
            this.showError('فشل في تصدير البيانات المحددة');
        }
    }

    // Stock level alerts
    checkStockLevels() {
        const items = document.querySelectorAll('.inventory-item-row');
        items.forEach(item => {
            const quantityElement = item.querySelector('.quantity-display');
            const itemId = item.dataset.itemId;
            
            if (quantityElement) {
                const quantity = parseFloat(quantityElement.dataset.quantity);
                
                // Add visual alert for low stock
                if (quantity <= 10) { // Adjust threshold as needed
                    item.classList.add('low-stock-alert');
                }
            }
        });
    }

    // Real-time stock updates
    startRealtimeUpdates() {
        // This would connect to WebSocket or Server-Sent Events
        // for real-time stock level updates
        console.log('Starting real-time updates...');
    }
}

// Global utility functions
function viewItem(id) {
    window.location.href = `/inventory/${id}`;
}

function editItem(id) {
    window.location.href = `/inventory/${id}/edit`;
}

function updateQuantity(id) {
    const modal = new bootstrap.Modal(document.getElementById('quantityModal'));
    document.getElementById('quantityItemId').value = id;
    modal.show();
}

async function deleteItem(id) {
    if (!confirm('هل أنت متأكد من حذف هذا الصنف؟')) {
        return;
    }

    try {
        if (window.inventoryManager) {
            await window.inventoryManager.deleteItem(id);
            window.inventoryManager.showSuccess('تم حذف الصنف بنجاح');
            window.inventoryManager.refreshData();
        }
    } catch (error) {
        if (window.inventoryManager) {
            window.inventoryManager.showError(error.message || 'فشل في حذف الصنف');
        }
    }
}

function clearFilters() {
    const form = document.getElementById('inventoryFiltersForm');
    if (form) {
        form.reset();
        form.dispatchEvent(new Event('submit'));
    }
}

function refreshData() {
    if (window.inventoryManager) {
        window.inventoryManager.refreshData();
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    if (document.querySelector('.inventory-page')) {
        window.inventoryManager = new InventoryManager();
        
        // Check stock levels after loading
        setTimeout(() => {
            window.inventoryManager.checkStockLevels();
        }, 1000);
        
        // Start real-time updates
        window.inventoryManager.startRealtimeUpdates();
    }
});

// Export for module use
if (typeof module !== 'undefined' && module.exports) {
    module.exports = InventoryManager;
}