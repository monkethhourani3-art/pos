/**
 * Invoice Management JavaScript
 * Restaurant POS System - Phase 4
 * Handles invoice management, filtering, and operations
 */

class InvoiceManager {
    constructor() {
        this.currentPage = 1;
        this.filters = {
            status: '',
            date_from: '',
            date_to: '',
            customer_name: '',
            search: ''
        };
        this.sortBy = 'created_at';
        this.sortOrder = 'desc';
        this.selectedInvoices = new Set();
        this.processing = false;

        this.init();
    }

    init() {
        this.bindEvents();
        this.setupFilters();
        this.loadInvoices();
        
        // Auto-refresh every 60 seconds
        setInterval(() => {
            this.refreshData();
        }, 60000);
    }

    bindEvents() {
        // Filter form
        document.addEventListener('submit', (e) => {
            if (e.target.matches('#invoiceFiltersForm')) {
                e.preventDefault();
                this.applyFilters(e.target);
            }
        });

        // Search input
        document.addEventListener('input', (e) => {
            if (e.target.matches('#searchInput')) {
                this.debouncedSearch(e.target.value);
            }
        });

        // Sort links
        document.addEventListener('click', (e) => {
            if (e.target.matches('.sort-link')) {
                e.preventDefault();
                this.handleSort(e.target.dataset.sort, e.target.dataset.order);
            }
        });

        // Pagination
        document.addEventListener('click', (e) => {
            if (e.target.matches('.pagination-link')) {
                e.preventDefault();
                this.changePage(parseInt(e.target.dataset.page));
            }
        });

        // Invoice actions
        document.addEventListener('click', (e) => {
            if (e.target.matches('.view-invoice-btn')) {
                this.viewInvoice(e.target.dataset.invoiceId);
            } else if (e.target.matches('.edit-invoice-btn')) {
                this.editInvoice(e.target.dataset.invoiceId);
            } else if (e.target.matches('.cancel-invoice-btn')) {
                this.cancelInvoice(e.target.dataset.invoiceId);
            } else if (e.target.matches('.print-invoice-btn')) {
                this.printInvoice(e.target.dataset.invoiceId);
            } else if (e.target.matches('.email-invoice-btn')) {
                this.emailInvoice(e.target.dataset.invoiceId);
            } else if (e.target.matches('.pdf-invoice-btn')) {
                this.generatePdf(e.target.dataset.invoiceId);
            } else if (e.target.matches('.duplicate-invoice-btn')) {
                this.duplicateInvoice(e.target.dataset.invoiceId);
            }
        });

        // Bulk actions
        document.addEventListener('click', (e) => {
            if (e.target.matches('.select-all-invoices')) {
                this.toggleSelectAll(e.target.checked);
            } else if (e.target.matches('.select-invoice')) {
                this.toggleInvoiceSelection(e.target.value, e.target.checked);
            } else if (e.target.matches('.bulk-print-btn')) {
                this.bulkPrint();
            } else if (e.target.matches('.bulk-email-btn')) {
                this.bulkEmail();
            } else if (e.target.matches('.bulk-export-btn')) {
                this.bulkExport();
            }
        });

        // Export
        document.addEventListener('click', (e) => {
            if (e.target.matches('.export-btn')) {
                this.exportInvoices(e.target.dataset.format);
            }
        });

        // Refresh button
        document.addEventListener('click', (e) => {
            if (e.target.matches('.refresh-invoices-btn')) {
                this.refreshData();
            }
        });
    }

    setupFilters() {
        // Initialize date inputs
        const today = new Date().toISOString().split('T')[0];
        const thirtyDaysAgo = new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
        
        const dateFromInput = document.querySelector('#dateFrom');
        const dateToInput = document.querySelector('#dateTo');
        
        if (dateFromInput && !dateFromInput.value) {
            dateFromInput.value = thirtyDaysAgo;
        }
        if (dateToInput && !dateToInput.value) {
            dateToInput.value = today;
        }
    }

    async loadInvoices(page = 1) {
        try {
            this.currentPage = page;
            const params = new URLSearchParams({
                ...this.filters,
                page: page,
                sort_by: this.sortBy,
                sort_order: this.sortOrder
            });

            const response = await fetch(`/invoices?${params}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) {
                throw new Error('Failed to load invoices');
            }

            const data = await response.json();
            this.renderInvoices(data.invoices);
            this.renderPagination(data.pagination);
            this.updateStats(data.stats);

        } catch (error) {
            console.error('Error loading invoices:', error);
            this.showError('فشل في تحميل الفواتير');
        }
    }

    renderInvoices(invoices) {
        const container = document.querySelector('.invoices-container');
        if (!container) return;

        if (invoices.length === 0) {
            container.innerHTML = `
                <div class="no-invoices">
                    <i class="fas fa-file-invoice"></i>
                    <h3>لا توجد فواتير</h3>
                    <p>لم يتم العثور على فواتير تطابق المعايير المحددة</p>
                </div>
            `;
            return;
        }

        const invoicesHtml = invoices.map(invoice => `
            <div class="invoice-card" data-invoice-id="${invoice.id}">
                <div class="invoice-header">
                    <div class="invoice-info">
                        <div class="invoice-number">
                            <strong>فاتورة #${invoice.invoice_number}</strong>
                        </div>
                        <div class="invoice-date">
                            ${new Date(invoice.created_at).toLocaleDateString('ar-SA')}
                        </div>
                    </div>
                    <div class="invoice-status">
                        <span class="status-badge status-${invoice.status}">
                            ${this.getStatusText(invoice.status)}
                        </span>
                    </div>
                </div>
                
                <div class="invoice-body">
                    <div class="invoice-customer">
                        <i class="fas fa-user"></i>
                        <span>${invoice.customer_name || 'غير محدد'}</span>
                    </div>
                    
                    <div class="invoice-amounts">
                        <div class="amount-row">
                            <span>المجموع الفرعي:</span>
                            <span>${invoice.subtotal} ر.س</span>
                        </div>
                        ${invoice.discount_amount > 0 ? `
                            <div class="amount-row discount">
                                <span>الخصم:</span>
                                <span>-${invoice.discount_amount} ر.س</span>
                            </div>
                        ` : ''}
                        <div class="amount-row">
                            <span>الضريبة:</span>
                            <span>${invoice.tax_amount} ر.س</span>
                        </div>
                        <div class="amount-row total">
                            <span>المجموع الكلي:</span>
                            <span>${invoice.total_amount} ر.س</span>
                        </div>
                    </div>
                    
                    ${invoice.due_date ? `
                        <div class="invoice-due-date">
                            <i class="fas fa-calendar"></i>
                            <span>تاريخ الاستحقاق: ${new Date(invoice.due_date).toLocaleDateString('ar-SA')}</span>
                        </div>
                    ` : ''}
                </div>
                
                <div class="invoice-footer">
                    <div class="invoice-actions">
                        <div class="checkbox-container">
                            <input type="checkbox" class="select-invoice" value="${invoice.id}" id="invoice-${invoice.id}">
                            <label for="invoice-${invoice.id}">تحديد</label>
                        </div>
                        
                        <div class="action-buttons">
                            <button class="btn btn-sm btn-outline view-invoice-btn" 
                                    data-invoice-id="${invoice.id}" title="عرض">
                                <i class="fas fa-eye"></i>
                            </button>
                            
                            <button class="btn btn-sm btn-outline print-invoice-btn" 
                                    data-invoice-id="${invoice.id}" title="طباعة">
                                <i class="fas fa-print"></i>
                            </button>
                            
                            <button class="btn btn-sm btn-outline email-invoice-btn" 
                                    data-invoice-id="${invoice.id}" title="إرسال">
                                <i class="fas fa-envelope"></i>
                            </button>
                            
                            ${invoice.status === 'draft' ? `
                                <button class="btn btn-sm btn-outline edit-invoice-btn" 
                                        data-invoice-id="${invoice.id}" title="تعديل">
                                    <i class="fas fa-edit"></i>
                                </button>
                            ` : ''}
                            
                            ${invoice.status === 'sent' ? `
                                <button class="btn btn-sm btn-outline cancel-invoice-btn" 
                                        data-invoice-id="${invoice.id}" title="إلغاء">
                                    <i class="fas fa-times"></i>
                                </button>
                            ` : ''}
                            
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline dropdown-toggle" 
                                        data-bs-toggle="dropdown">
                                    <i class="fas fa-ellipsis-h"></i>
                                </button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item pdf-invoice-btn" 
                                          data-invoice-id="${invoice.id}" href="#">
                                          <i class="fas fa-file-pdf"></i> تحميل PDF
                                      </a></li>
                                    <li><a class="dropdown-item duplicate-invoice-btn" 
                                          data-invoice-id="${invoice.id}" href="#">
                                          <i class="fas fa-copy"></i> نسخ
                                      </a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-danger" href="#">
                                          <i class="fas fa-trash"></i> حذف
                                      </a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `).join('');

        container.innerHTML = invoicesHtml;
    }

    renderPagination(pagination) {
        const container = document.querySelector('.pagination-container');
        if (!container || !pagination) return;

        if (pagination.total_pages <= 1) {
            container.innerHTML = '';
            return;
        }

        let paginationHtml = `
            <nav aria-label="تنقل بين الصفحات">
                <ul class="pagination justify-content-center">
        `;

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

        paginationHtml += '</ul></nav>';
        container.innerHTML = paginationHtml;
    }

    updateStats(stats) {
        const container = document.querySelector('.invoice-stats');
        if (!container || !stats) return;

        container.innerHTML = `
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-file-invoice"></i>
                </div>
                <div class="stat-info">
                    <h3>${stats.total}</h3>
                    <p>إجمالي الفواتير</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="stat-info">
                    <h3>${stats.total_amount} ر.س</h3>
                    <p>إجمالي المبلغ</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-info">
                    <h3>${stats.paid}</h3>
                    <p>فواتير مدفوعة</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-info">
                    <h3>${stats.pending}</h3>
                    <p>في الانتظار</p>
                </div>
            </div>
        `;
    }

    applyFilters(form) {
        const formData = new FormData(form);
        
        this.filters = {
            status: formData.get('status') || '',
            date_from: formData.get('date_from') || '',
            date_to: formData.get('date_to') || '',
            customer_name: formData.get('customer_name') || '',
            search: formData.get('search') || ''
        };

        this.loadInvoices(1);
    }

    debouncedSearch(query) {
        clearTimeout(this.searchTimeout);
        this.searchTimeout = setTimeout(() => {
            this.filters.search = query;
            this.loadInvoices(1);
        }, 500);
    }

    handleSort(field, order) {
        this.sortBy = field;
        this.sortOrder = order;
        this.loadInvoices(this.currentPage);
    }

    changePage(page) {
        this.loadInvoices(page);
    }

    toggleSelectAll(checked) {
        const checkboxes = document.querySelectorAll('.select-invoice');
        this.selectedInvoices.clear();
        
        checkboxes.forEach(checkbox => {
            checkbox.checked = checked;
            if (checked) {
                this.selectedInvoices.add(checkbox.value);
            }
        });

        this.updateBulkActionsVisibility();
    }

    toggleInvoiceSelection(invoiceId, checked) {
        if (checked) {
            this.selectedInvoices.add(invoiceId);
        } else {
            this.selectedInvoices.delete(invoiceId);
        }

        // Update select all checkbox
        const selectAllCheckbox = document.querySelector('.select-all-invoices');
        if (selectAllCheckbox) {
            const totalCheckboxes = document.querySelectorAll('.select-invoice').length;
            selectAllCheckbox.checked = this.selectedInvoices.size === totalCheckboxes;
            selectAllCheckbox.indeterminate = this.selectedInvoices.size > 0 && this.selectedInvoices.size < totalCheckboxes;
        }

        this.updateBulkActionsVisibility();
    }

    updateBulkActionsVisibility() {
        const bulkActionsContainer = document.querySelector('.bulk-actions');
        if (bulkActionsContainer) {
            bulkActionsContainer.style.display = this.selectedInvoices.size > 0 ? 'block' : 'none';
        }
    }

    async viewInvoice(invoiceId) {
        try {
            window.location.href = `/invoices/${invoiceId}`;
        } catch (error) {
            console.error('Error viewing invoice:', error);
            this.showError('فشل في عرض الفاتورة');
        }
    }

    async editInvoice(invoiceId) {
        try {
            window.location.href = `/invoices/${invoiceId}/edit`;
        } catch (error) {
            console.error('Error editing invoice:', error);
            this.showError('فشل في تعديل الفاتورة');
        }
    }

    async cancelInvoice(invoiceId) {
        if (!confirm('هل أنت متأكد من إلغاء هذه الفاتورة؟')) {
            return;
        }

        try {
            const response = await fetch(`/invoices/${invoiceId}/cancel`, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/json'
                }
            });

            if (!response.ok) {
                throw new Error('Failed to cancel invoice');
            }

            this.showSuccess('تم إلغاء الفاتورة بنجاح');
            this.loadInvoices(this.currentPage);

        } catch (error) {
            console.error('Error cancelling invoice:', error);
            this.showError('فشل في إلغاء الفاتورة');
        }
    }

    async printInvoice(invoiceId) {
        try {
            const response = await fetch(`/invoices/${invoiceId}/print`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/pdf'
                }
            });

            if (!response.ok) {
                throw new Error('Failed to generate invoice PDF');
            }

            const blob = await response.blob();
            const url = URL.createObjectURL(blob);
            
            const printWindow = window.open(url, '_blank');
            printWindow.onload = () => {
                printWindow.print();
            };

        } catch (error) {
            console.error('Error printing invoice:', error);
            this.showError('فشل في طباعة الفاتورة');
        }
    }

    async emailInvoice(invoiceId) {
        const email = prompt('يرجى إدخال عنوان البريد الإلكتروني:');
        if (!email) return;

        try {
            const response = await fetch(`/invoices/${invoiceId}/email`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ email })
            });

            if (!response.ok) {
                throw new Error('Failed to send email');
            }

            this.showSuccess('تم إرسال الفاتورة بنجاح');

        } catch (error) {
            console.error('Error emailing invoice:', error);
            this.showError('فشل في إرسال الفاتورة');
        }
    }

    async generatePdf(invoiceId) {
        try {
            const response = await fetch(`/invoices/${invoiceId}/pdf`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/pdf'
                }
            });

            if (!response.ok) {
                throw new Error('Failed to generate PDF');
            }

            const blob = await response.blob();
            const url = URL.createObjectURL(blob);
            
            const a = document.createElement('a');
            a.href = url;
            a.download = `invoice-${invoiceId}.pdf`;
            a.click();

            URL.revokeObjectURL(url);

        } catch (error) {
            console.error('Error generating PDF:', error);
            this.showError('فشل في إنشاء ملف PDF');
        }
    }

    async duplicateInvoice(invoiceId) {
        if (!confirm('هل تريد إنشاء نسخة من هذه الفاتورة؟')) {
            return;
        }

        try {
            const response = await fetch(`/invoices/${invoiceId}/duplicate`, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                throw new Error('Failed to duplicate invoice');
            }

            const result = await response.json();
            this.showSuccess('تم إنشاء نسخة من الفاتورة');
            
            // Redirect to edit the new invoice
            setTimeout(() => {
                window.location.href = `/invoices/${result.new_invoice_id}/edit`;
            }, 1500);

        } catch (error) {
            console.error('Error duplicating invoice:', error);
            this.showError('فشل في إنشاء نسخة من الفاتورة');
        }
    }

    async bulkPrint() {
        if (this.selectedInvoices.size === 0) {
            this.showError('يرجى تحديد فاتورة واحدة على الأقل');
            return;
        }

        try {
            const invoiceIds = Array.from(this.selectedInvoices);
            const response = await fetch('/invoices/bulk-print', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ invoice_ids: invoiceIds })
            });

            if (!response.ok) {
                throw new Error('Failed to generate bulk PDF');
            }

            const blob = await response.blob();
            const url = URL.createObjectURL(blob);
            
            const printWindow = window.open(url, '_blank');
            printWindow.onload = () => {
                printWindow.print();
            };

        } catch (error) {
            console.error('Error bulk printing:', error);
            this.showError('فشل في طباعة الفواتير المحددة');
        }
    }

    async bulkEmail() {
        if (this.selectedInvoices.size === 0) {
            this.showError('يرجى تحديد فاتورة واحدة على الأقل');
            return;
        }

        const email = prompt('يرجى إدخال عنوان البريد الإلكتروني:');
        if (!email) return;

        try {
            const invoiceIds = Array.from(this.selectedInvoices);
            const response = await fetch('/invoices/bulk-email', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ 
                    invoice_ids: invoiceIds,
                    email: email
                })
            });

            if (!response.ok) {
                throw new Error('Failed to send bulk emails');
            }

            this.showSuccess('تم إرسال الفواتير بنجاح');

        } catch (error) {
            console.error('Error bulk emailing:', error);
            this.showError('فشل في إرسال الفواتير');
        }
    }

    async bulkExport() {
        if (this.selectedInvoices.size === 0) {
            this.showError('يرجى تحديد فاتورة واحدة على الأقل');
            return;
        }

        try {
            const invoiceIds = Array.from(this.selectedInvoices);
            const response = await fetch('/invoices/bulk-export', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ invoice_ids: invoiceIds })
            });

            if (!response.ok) {
                throw new Error('Failed to export invoices');
            }

            const blob = await response.blob();
            const url = URL.createObjectURL(blob);
            
            const a = document.createElement('a');
            a.href = url;
            a.download = 'invoices-export.zip';
            a.click();

            URL.revokeObjectURL(url);

        } catch (error) {
            console.error('Error bulk exporting:', error);
            this.showError('فشل في تصدير الفواتير');
        }
    }

    async exportInvoices(format = 'csv') {
        try {
            const params = new URLSearchParams({
                ...this.filters,
                format: format
            });

            const response = await fetch(`/invoices/export?${params}`, {
                headers: {
                    'Accept': 'application/octet-stream'
                }
            });

            if (!response.ok) {
                throw new Error('Failed to export invoices');
            }

            const blob = await response.blob();
            const url = URL.createObjectURL(blob);
            
            const a = document.createElement('a');
            a.href = url;
            a.download = `invoices-export.${format}`;
            a.click();

            URL.revokeObjectURL(url);

        } catch (error) {
            console.error('Error exporting invoices:', error);
            this.showError('فشل في تصدير الفواتير');
        }
    }

    getStatusText(status) {
        const statusMap = {
            'draft': 'مسودة',
            'sent': 'مرسلة',
            'paid': 'مدفوعة',
            'overdue': 'متأخرة',
            'cancelled': 'ملغاة',
            'partially_paid': 'مدفوعة جزئياً'
        };
        return statusMap[status] || status;
    }

    refreshData() {
        this.loadInvoices(this.currentPage);
    }

    // Utility methods for notifications
    showSuccess(message) {
        console.log('Success:', message);
        this.showNotification(message, 'success');
    }

    showError(message) {
        console.error('Error:', message);
        this.showNotification(message, 'error');
    }

    showNotification(message, type = 'info') {
        // Simple notification implementation
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.textContent = message;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.remove();
        }, 5000);
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    if (document.querySelector('.invoices-page')) {
        window.invoiceManager = new InvoiceManager();
    }
});

// Export for module use
if (typeof module !== 'undefined' && module.exports) {
    module.exports = InvoiceManager;
}