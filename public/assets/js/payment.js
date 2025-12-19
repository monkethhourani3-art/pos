/**
 * Payment Management JavaScript
 * Restaurant POS System - Phase 4
 * Handles payment processing, refunds, and split payments
 */

class PaymentManager {
    constructor() {
        this.orderId = null;
        this.order = null;
        this.selectedItems = [];
        this.paymentMethods = [];
        this.processing = false;
        
        this.init();
    }

    init() {
        this.bindEvents();
        this.loadPaymentMethods();
        this.setupFormValidation();
        
        // Auto-refresh every 30 seconds for real-time updates
        setInterval(() => {
            this.refreshData();
        }, 30000);
    }

    bindEvents() {
        // Payment method selection
        document.addEventListener('change', (e) => {
            if (e.target.matches('.payment-method-select')) {
                this.handlePaymentMethodChange(e.target);
            }
        });

        // Amount input changes
        document.addEventListener('input', (e) => {
            if (e.target.matches('.payment-amount')) {
                this.handleAmountChange(e.target);
            }
        });

        // Form submissions
        document.addEventListener('submit', (e) => {
            if (e.target.matches('#paymentForm')) {
                e.preventDefault();
                this.processPayment(e.target);
            } else if (e.target.matches('#splitPaymentForm')) {
                e.preventDefault();
                this.processSplitPayment(e.target);
            } else if (e.target.matches('#discountForm')) {
                e.preventDefault();
                this.applyDiscount(e.target);
            }
        });

        // Refund buttons
        document.addEventListener('click', (e) => {
            if (e.target.matches('.refund-btn')) {
                this.showRefundModal(e.target.dataset.transactionId);
            } else if (e.target.matches('.split-payment-btn')) {
                this.showSplitPaymentModal();
            } else if (e.target.matches('.apply-discount-btn')) {
                this.showDiscountModal();
            }
        });

        // Modal events
        document.addEventListener('click', (e) => {
            if (e.target.matches('.modal-close') || e.target.matches('.modal-backdrop')) {
                this.closeModal();
            }
        });

        // Print receipt
        document.addEventListener('click', (e) => {
            if (e.target.matches('.print-receipt-btn')) {
                this.printReceipt(e.target.dataset.transactionId);
            }
        });
    }

    async loadOrderData(orderId) {
        try {
            const response = await fetch(`/api/orders/${orderId}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) {
                throw new Error('Failed to load order data');
            }

            const data = await response.json();
            this.order = data.order;
            this.orderId = orderId;
            
            this.updateOrderSummary();
            this.loadPaymentHistory();
            
        } catch (error) {
            console.error('Error loading order data:', error);
            this.showError('فشل في تحميل بيانات الطلب');
        }
    }

    async loadPaymentMethods() {
        try {
            const response = await fetch('/api/payment-methods', {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) {
                throw new Error('Failed to load payment methods');
            }

            this.paymentMethods = await response.json();
            this.renderPaymentMethods();
            
        } catch (error) {
            console.error('Error loading payment methods:', error);
        }
    }

    renderPaymentMethods() {
        const container = document.querySelector('.payment-methods-container');
        if (!container) return;

        const methodsHtml = this.paymentMethods.map(method => `
            <div class="payment-method-card" data-method-id="${method.id}">
                <div class="method-header">
                    <i class="${method.icon_class}"></i>
                    <h4>${method.name}</h4>
                </div>
                <div class="method-details">
                    <p>${method.description}</p>
                    ${method.requires_reference ? '<span class="requires-ref">يتطلب مرجع</span>' : ''}
                </div>
            </div>
        `).join('');

        container.innerHTML = methodsHtml;
    }

    handlePaymentMethodChange(selectElement) {
        const methodId = selectElement.value;
        const method = this.paymentMethods.find(m => m.id == methodId);
        
        if (!method) return;

        // Show/hide reference input
        const referenceContainer = document.querySelector('.reference-container');
        if (method.requires_reference) {
            referenceContainer.style.display = 'block';
            referenceContainer.querySelector('input').setAttribute('required', 'required');
        } else {
            referenceContainer.style.display = 'none';
            referenceContainer.querySelector('input').removeAttribute('required');
        }

        // Update amount calculation
        this.calculateAmounts();
    }

    handleAmountChange(inputElement) {
        const amount = parseFloat(inputElement.value) || 0;
        this.calculateAmounts(amount);
    }

    calculateAmounts(customAmount = null) {
        const orderTotal = this.order?.total_amount || 0;
        const discountAmount = this.order?.discount_amount || 0;
        const taxAmount = this.order?.tax_amount || 0;
        
        const finalTotal = orderTotal + taxAmount - discountAmount;
        
        const amountInput = document.querySelector('.payment-amount');
        const changeInput = document.querySelector('.change-amount');
        
        let paymentAmount = customAmount || parseFloat(amountInput.value) || 0;
        
        if (paymentAmount >= finalTotal) {
            changeInput.value = (paymentAmount - finalTotal).toFixed(2);
            changeInput.parentElement.style.display = 'block';
        } else {
            changeInput.value = '0.00';
            changeInput.parentElement.style.display = 'none';
        }

        // Update remaining amount
        const remainingInput = document.querySelector('.remaining-amount');
        remainingInput.value = Math.max(0, finalTotal - paymentAmount).toFixed(2);
    }

    async processPayment(form) {
        if (this.processing) return;

        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());
        
        // Validation
        if (!this.validatePayment(data)) {
            return;
        }

        this.processing = true;
        this.showLoading('جاري معالجة الدفع...');

        try {
            const response = await fetch(`/payment/process/${this.orderId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (!response.ok) {
                throw new Error(result.message || 'Payment failed');
            }

            this.showSuccess('تم معالجة الدفع بنجاح');
            this.closeModal();
            
            // Refresh page data
            setTimeout(() => {
                window.location.reload();
            }, 1500);

        } catch (error) {
            console.error('Payment error:', error);
            this.showError(error.message || 'فشل في معالجة الدفع');
        } finally {
            this.processing = false;
            this.hideLoading();
        }
    }

    validatePayment(data) {
        const amount = parseFloat(data.amount);
        const methodId = data.payment_method_id;

        if (!methodId) {
            this.showError('يرجى اختيار طريقة دفع');
            return false;
        }

        if (amount <= 0) {
            this.showError('يرجى إدخال مبلغ صحيح');
            return false;
        }

        const method = this.paymentMethods.find(m => m.id == methodId);
        if (method?.requires_reference && !data.reference_number?.trim()) {
            this.showError('يرجى إدخال رقم المرجع');
            return false;
        }

        return true;
    }

    async processSplitPayment(form) {
        if (this.processing) return;

        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());

        this.processing = true;
        this.showLoading('جاري معالجة الدفع المقسم...');

        try {
            const response = await fetch(`/payment/split/${this.orderId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (!response.ok) {
                throw new Error(result.message || 'Split payment failed');
            }

            this.showSuccess('تم معالجة الدفع المقسم بنجاح');
            this.closeModal();
            
            setTimeout(() => {
                window.location.reload();
            }, 1500);

        } catch (error) {
            console.error('Split payment error:', error);
            this.showError(error.message || 'فشل في معالجة الدفع المقسم');
        } finally {
            this.processing = false;
            this.hideLoading();
        }
    }

    async applyDiscount(form) {
        if (this.processing) return;

        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());

        this.processing = true;
        this.showLoading('جاري تطبيق الخصم...');

        try {
            const response = await fetch(`/payment/discount/${this.orderId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (!response.ok) {
                throw new Error(result.message || 'Discount application failed');
            }

            this.showSuccess('تم تطبيق الخصم بنجاح');
            this.closeModal();
            
            // Update order data
            this.order = result.order;
            this.updateOrderSummary();

        } catch (error) {
            console.error('Discount error:', error);
            this.showError(error.message || 'فشل في تطبيق الخصم');
        } finally {
            this.processing = false;
            this.hideLoading();
        }
    }

    async showRefundModal(transactionId) {
        try {
            const response = await fetch(`/payment/transaction/${transactionId}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) {
                throw new Error('Failed to load transaction data');
            }

            const transaction = await response.json();
            this.renderRefundModal(transaction);
            
        } catch (error) {
            console.error('Error loading transaction:', error);
            this.showError('فشل في تحميل بيانات المعاملة');
        }
    }

    renderRefundModal(transaction) {
        const modal = document.getElementById('refundModal');
        const form = modal.querySelector('#refundForm');
        
        form.querySelector('[name="transaction_id"]').value = transaction.id;
        form.querySelector('.transaction-amount').textContent = `${transaction.amount} ر.س`;
        form.querySelector('.refund-amount').max = transaction.amount;
        form.querySelector('.refund-amount').value = transaction.amount;
        
        modal.style.display = 'flex';
    }

    async printReceipt(transactionId) {
        try {
            const response = await fetch(`/payment/print-receipt/${transactionId}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/pdf'
                }
            });

            if (!response.ok) {
                throw new Error('Failed to generate receipt');
            }

            const blob = await response.blob();
            const url = URL.createObjectURL(blob);
            
            const printWindow = window.open(url, '_blank');
            printWindow.onload = () => {
                printWindow.print();
            };

        } catch (error) {
            console.error('Print error:', error);
            this.showError('فشل في طباعة الإيصال');
        }
    }

    updateOrderSummary() {
        const summaryContainer = document.querySelector('.order-summary');
        if (!summaryContainer || !this.order) return;

        summaryContainer.innerHTML = `
            <div class="summary-row">
                <span>المجموع الفرعي:</span>
                <span>${this.order.subtotal} ر.س</span>
            </div>
            <div class="summary-row">
                <span>الضريبة:</span>
                <span>${this.order.tax_amount} ر.س</span>
            </div>
            ${this.order.discount_amount > 0 ? `
                <div class="summary-row discount">
                    <span>الخصم:</span>
                    <span>-${this.order.discount_amount} ر.س</span>
                </div>
            ` : ''}
            <div class="summary-row total">
                <span>المجموع الكلي:</span>
                <span>${this.order.total_amount} ر.س</span>
            </div>
        `;
    }

    async loadPaymentHistory() {
        try {
            const response = await fetch(`/payment/history/${this.orderId}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) {
                throw new Error('Failed to load payment history');
            }

            const payments = await response.json();
            this.renderPaymentHistory(payments);
            
        } catch (error) {
            console.error('Error loading payment history:', error);
        }
    }

    renderPaymentHistory(payments) {
        const container = document.querySelector('.payment-history');
        if (!container) return;

        if (payments.length === 0) {
            container.innerHTML = '<div class="no-payments">لا توجد معاملات دفع</div>';
            return;
        }

        const paymentsHtml = payments.map(payment => `
            <div class="payment-item">
                <div class="payment-info">
                    <span class="payment-method">${payment.method_name}</span>
                    <span class="payment-amount">${payment.amount} ر.س</span>
                    <span class="payment-date">${new Date(payment.created_at).toLocaleDateString('ar-SA')}</span>
                </div>
                <div class="payment-actions">
                    ${payment.status === 'completed' ? `
                        <button class="btn btn-sm btn-outline print-receipt-btn" 
                                data-transaction-id="${payment.id}">
                            طباعة
                        </button>
                        <button class="btn btn-sm btn-outline refund-btn" 
                                data-transaction-id="${payment.id}">
                            استرداد
                        </button>
                    ` : ''}
                </div>
            </div>
        `).join('');

        container.innerHTML = paymentsHtml;
    }

    showSplitPaymentModal() {
        const modal = document.getElementById('splitPaymentModal');
        if (modal) {
            modal.style.display = 'flex';
            this.setupSplitPaymentForm();
        }
    }

    showDiscountModal() {
        const modal = document.getElementById('discountModal');
        if (modal) {
            modal.style.display = 'flex';
        }
    }

    setupSplitPaymentForm() {
        const form = document.querySelector('#splitPaymentForm');
        const amountInput = form.querySelector('.split-amount');
        const methodSelect = form.querySelector('.split-method');

        // Add dynamic split payment rows
        const container = form.querySelector('.split-payments-container');
        container.innerHTML = `
            <div class="split-payment-row">
                <input type="number" class="form-control split-amount" 
                       name="split_amounts[]" placeholder="المبلغ" min="0.01" step="0.01" required>
                <select class="form-control split-method" name="payment_method_ids[]" required>
                    <option value="">طريقة الدفع</option>
                    ${this.paymentMethods.map(m => `<option value="${m.id}">${m.name}</option>`).join('')}
                </select>
                <button type="button" class="btn btn-sm btn-outline remove-split-row">×</button>
            </div>
        `;

        // Add event listeners for dynamic rows
        form.addEventListener('click', (e) => {
            if (e.target.matches('.remove-split-row')) {
                e.target.closest('.split-payment-row').remove();
            } else if (e.target.matches('.add-split-row')) {
                this.addSplitPaymentRow();
            }
        });
    }

    addSplitPaymentRow() {
        const container = document.querySelector('.split-payments-container');
        const row = document.createElement('div');
        row.className = 'split-payment-row';
        row.innerHTML = `
            <input type="number" class="form-control split-amount" 
                   name="split_amounts[]" placeholder="المبلغ" min="0.01" step="0.01" required>
            <select class="form-control split-method" name="payment_method_ids[]" required>
                <option value="">طريقة الدفع</option>
                ${this.paymentMethods.map(m => `<option value="${m.id}">${m.name}</option>`).join('')}
            </select>
            <button type="button" class="btn btn-sm btn-outline remove-split-row">×</button>
        `;
        container.appendChild(row);
    }

    closeModal() {
        document.querySelectorAll('.modal').forEach(modal => {
            modal.style.display = 'none';
        });
    }

    setupFormValidation() {
        // Add real-time validation for payment forms
        document.addEventListener('input', (e) => {
            if (e.target.matches('.payment-amount, .split-amount, .refund-amount')) {
                this.validateAmountField(e.target);
            }
        });
    }

    validateAmountField(input) {
        const value = parseFloat(input.value);
        const max = parseFloat(input.max);
        
        if (isNaN(value) || value <= 0) {
            input.classList.add('is-invalid');
            return false;
        } else if (max && value > max) {
            input.classList.add('is-invalid');
            return false;
        } else {
            input.classList.remove('is-invalid');
            return true;
        }
    }

    refreshData() {
        if (this.orderId) {
            this.loadOrderData(this.orderId);
        }
    }

    // Utility methods for notifications
    showLoading(message = 'جاري التحميل...') {
        // Implementation for loading indicator
        console.log('Loading:', message);
    }

    hideLoading() {
        // Implementation for hiding loading indicator
        console.log('Loading hidden');
    }

    showSuccess(message) {
        // Implementation for success notification
        console.log('Success:', message);
        this.showNotification(message, 'success');
    }

    showError(message) {
        // Implementation for error notification
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
    if (document.querySelector('.payment-interface')) {
        window.paymentManager = new PaymentManager();
        
        // Load order data if orderId is available
        const orderId = document.querySelector('[data-order-id]')?.dataset.orderId;
        if (orderId) {
            window.paymentManager.loadOrderData(orderId);
        }
    }
});

// Export for module use
if (typeof module !== 'undefined' && module.exports) {
    module.exports = PaymentManager;
}