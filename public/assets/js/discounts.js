/**
 * Discounts.js - إدارة الخصومات
 * Restaurant POS System
 */

class DiscountsManager {
    constructor() {
        this.baseUrl = window.location.origin;
        this.currentFilters = {};
    }

    /**
     * تهيئة مدير الخصومات
     */
    init() {
        this.setupEventListeners();
        this.loadInitialData();
    }

    /**
     * تهيئة نموذج إنشاء الخصم
     */
    initCreateForm() {
        this.setupFormValidation();
        this.setupDateValidation();
        this.setupDiscountTypeValidation();
    }

    /**
     * إعداد مستمعي الأحداث
     */
    setupEventListeners() {
        // نموذج إنشاء الخصم
        const discountForm = document.getElementById('discountForm');
        if (discountForm) {
            discountForm.addEventListener('submit', (e) => this.handleFormSubmit(e));
        }

        // تحديث نوع الخصم
        const typeSelect = document.getElementById('type');
        if (typeSelect) {
            typeSelect.addEventListener('change', () => this.updateDiscountFields());
        }

        // تحديث المنتجات المطبقة
        const appliesToSelect = document.getElementById('applies_to');
        if (appliesToSelect) {
            appliesToSelect.addEventListener('change', () => this.updateSelectionSection());
        }

        // تحقق من تفرد الكود
        const codeInput = document.getElementById('code');
        if (codeInput) {
            codeInput.addEventListener('blur', () => this.validateCode());
        }

        // تحديث التواريخ
        this.setupDateValidation();
    }

    /**
     * إعداد تحقق صحة النموذج
     */
    setupFormValidation() {
        const form = document.getElementById('discountForm');
        if (!form) return;

        // تحقق فوري من الحقول
        const inputs = form.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            input.addEventListener('blur', () => this.validateField(input));
            input.addEventListener('input', () => this.clearFieldError(input));
        });
    }

    /**
     * تحقق من صحة حقل واحد
     */
    validateField(field) {
        const value = field.value.trim();
        const name = field.name;
        let isValid = true;
        let errorMessage = '';

        // تحقق من الحقول المطلوبة
        if (field.hasAttribute('required') && !value) {
            isValid = false;
            errorMessage = 'هذا الحقل مطلوب';
        }

        // تحقق خاص بكل نوع حقل
        switch (name) {
            case 'name':
                if (value && value.length < 3) {
                    isValid = false;
                    errorMessage = 'الاسم يجب أن يكون 3 أحرف على الأقل';
                }
                break;

            case 'code':
                if (value && !/^[A-Z0-9]+$/.test(value)) {
                    isValid = false;
                    errorMessage = 'الكود يجب أن يحتوي على أحرف وأرقام فقط';
                }
                break;

            case 'value':
                if (!value || parseFloat(value) < 0) {
                    isValid = false;
                    errorMessage = 'القيمة يجب أن تكون رقم موجب';
                } else {
                    const type = document.getElementById('type').value;
                    if (type === 'percentage' && parseFloat(value) > 100) {
                        isValid = false;
                        errorMessage = 'النسبة المئوية لا يمكن أن تتجاوز 100%';
                    }
                }
                break;

            case 'valid_from':
                const validFrom = new Date(value);
                const now = new Date();
                if (validFrom <= now) {
                    isValid = false;
                    errorMessage = 'تاريخ البداية يجب أن يكون في المستقبل';
                }
                break;

            case 'valid_until':
                const validFrom = document.getElementById('valid_from').value;
                if (validFrom) {
                    const validFromDate = new Date(validFrom);
                    const validUntil = new Date(value);
                    if (validUntil <= validFromDate) {
                        isValid = false;
                        errorMessage = 'تاريخ النهاية يجب أن يكون بعد تاريخ البداية';
                    }
                }
                break;
        }

        // عرض رسالة الخطأ
        this.showFieldError(field, isValid, errorMessage);
        return isValid;
    }

    /**
     * عرض رسالة خطأ للحقل
     */
    showFieldError(field, isValid, message) {
        const feedback = field.parentNode.querySelector('.invalid-feedback');
        
        if (isValid) {
            field.classList.remove('is-invalid');
            if (feedback) feedback.textContent = '';
        } else {
            field.classList.add('is-invalid');
            if (feedback) feedback.textContent = message;
        }
    }

    /**
     * مسح رسالة الخطأ من الحقل
     */
    clearFieldError(field) {
        field.classList.remove('is-invalid');
        const feedback = field.parentNode.querySelector('.invalid-feedback');
        if (feedback) feedback.textContent = '';
    }

    /**
     * تحقق من تاريخ البداية والنهاية
     */
    setupDateValidation() {
        const validFrom = document.getElementById('valid_from');
        const validUntil = document.getElementById('valid_until');

        if (validFrom && validUntil) {
            validFrom.addEventListener('change', () => {
                if (validUntil.value) {
                    this.validateField(validUntil);
                }
            });

            validUntil.addEventListener('change', () => {
                this.validateField(validUntil);
            });
        }
    }

    /**
     * تحديث حقول الخصم حسب النوع
     */
    updateDiscountFields() {
        const type = document.getElementById('type').value;
        const valueField = document.getElementById('value');
        const valueLabel = valueField.parentNode.querySelector('label');

        if (type === 'percentage') {
            valueLabel.textContent = 'نسبة الخصم (%) *';
            valueField.max = '100';
            valueField.placeholder = 'مثال: 15';
        } else if (type === 'fixed') {
            valueLabel.textContent = 'مبلغ الخصم (ر.س) *';
            valueField.max = '';
            valueField.placeholder = '0.00';
        } else if (type === 'buy_x_get_y') {
            valueLabel.textContent = 'تفاصيل العرض *';
            valueField.max = '';
            valueField.placeholder = 'سيتم تخصيصه لاحقاً';
        } else {
            valueLabel.textContent = 'قيمة الخصم *';
            valueField.max = '';
            valueField.placeholder = '0.00';
        }

        // تحديث التحقق
        this.validateField(valueField);
    }

    /**
     * تحديث قسم اختيار المنتجات
     */
    updateSelectionSection() {
        const appliesTo = document.getElementById('applies_to').value;
        const selectionSection = document.getElementById('selectionSection');
        const productsSelection = document.getElementById('productsSelection');
        const categoriesSelection = document.getElementById('categoriesSelection');

        if (appliesTo === 'products' || appliesTo === 'categories') {
            selectionSection.style.display = 'block';
            productsSelection.style.display = appliesTo === 'products' ? 'block' : 'none';
            categoriesSelection.style.display = appliesTo === 'categories' ? 'block' : 'none';
        } else {
            selectionSection.style.display = 'none';
            productsSelection.style.display = 'none';
            categoriesSelection.style.display = 'none';
        }
    }

    /**
     * تحقق من تفرد كود الخصم
     */
    async validateCode() {
        const codeInput = document.getElementById('code');
        if (!codeInput || !codeInput.value.trim()) return;

        try {
            const response = await fetch(`/discounts/search?q=${encodeURIComponent(codeInput.value)}`);
            const data = await response.json();

            if (data.success && data.results.length > 0) {
                this.showFieldError(codeInput, false, 'هذا الكود مستخدم بالفعل');
            } else {
                this.clearFieldError(codeInput);
            }
        } catch (error) {
            console.error('خطأ في التحقق من الكود:', error);
        }
    }

    /**
     * إرسال نموذج إنشاء الخصم
     */
    async handleFormSubmit(e) {
        e.preventDefault();

        const form = e.target;
        const submitButton = form.querySelector('button[type="submit"]');
        
        // تحقق من صحة جميع الحقول
        const inputs = form.querySelectorAll('input[required], select[required]');
        let isFormValid = true;

        inputs.forEach(input => {
            if (!this.validateField(input)) {
                isFormValid = false;
            }
        });

        if (!isFormValid) {
            this.showAlert('يرجى تصحيح الأخطاء في النموذج', 'error');
            return;
        }

        // إظهار حالة التحميل
        submitButton.classList.add('loading');
        submitButton.disabled = true;

        try {
            const formData = new FormData(form);
            const response = await fetch('/discounts', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                this.showAlert(data.message, 'success');
                setTimeout(() => {
                    window.location.href = data.redirect;
                }, 1500);
            } else {
                this.showAlert(data.message || 'فشل في إنشاء الخصم', 'error');
            }
        } catch (error) {
            console.error('خطأ في إنشاء الخصم:', error);
            this.showAlert('حدث خطأ أثناء إنشاء الخصم', 'error');
        } finally {
            submitButton.classList.remove('loading');
            submitButton.disabled = false;
        }
    }

    /**
     * تبديل حالة خصم
     */
    async toggleStatus(id, isActive) {
        try {
            const response = await fetch(`/discounts/${id}/toggle-status`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const data = await response.json();

            if (data.success) {
                this.showAlert(data.message, 'success');
                // تحديث واجهة المستخدم
                const switchElement = document.querySelector(`input[onchange="toggleDiscountStatus(${id}, ${isActive})"]`);
                if (switchElement) {
                    const label = switchElement.nextElementSibling;
                    label.textContent = isActive ? 'نشط' : 'معطل';
                }
            } else {
                this.showAlert(data.message || 'فشل في تغيير حالة الخصم', 'error');
                // إرجاع الحالة السابقة
                const switchElement = document.querySelector(`input[onchange="toggleDiscountStatus(${id}, ${isActive})"]`);
                if (switchElement) {
                    switchElement.checked = !isActive;
                }
            }
        } catch (error) {
            console.error('خطأ في تبديل حالة الخصم:', error);
            this.showAlert('حدث خطأ أثناء تغيير حالة الخصم', 'error');
            
            // إرجاع الحالة السابقة
            const switchElement = document.querySelector(`input[onchange="toggleDiscountStatus(${id}, ${isActive})"]`);
            if (switchElement) {
                switchElement.checked = !isActive;
            }
        }
    }

    /**
     * حذف خصم
     */
    async delete(id) {
        try {
            const response = await fetch(`/discounts/${id}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const data = await response.json();

            if (data.success) {
                this.showAlert(data.message, 'success');
                // إزالة الصف من الجدول
                setTimeout(() => {
                    location.reload();
                }, 1500);
            } else {
                this.showAlert(data.message || 'فشل في حذف الخصم', 'error');
            }
        } catch (error) {
            console.error('خطأ في حذف الخصم:', error);
            this.showAlert('حدث خطأ أثناء حذف الخصم', 'error');
        }
    }

    /**
     * تصدير الخصومات
     */
    async export() {
        try {
            const params = new URLSearchParams(this.currentFilters);
            const response = await fetch(`/discounts/export?${params}`);
            
            if (response.ok) {
                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `discounts_${new Date().toISOString().split('T')[0]}.xlsx`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);
                
                this.showAlert('تم تصدير الخصومات بنجاح', 'success');
            } else {
                throw new Error('فشل في تصدير الملف');
            }
        } catch (error) {
            console.error('خطأ في تصدير الخصومات:', error);
            this.showAlert('فشل في تصدير الخصومات', 'error');
        }
    }

    /**
     * تحميل البيانات الأولية
     */
    loadInitialData() {
        // تعيين التواريخ الافتراضية
        const now = new Date();
        const futureDate = new Date(now.getTime() + (30 * 24 * 60 * 60 * 1000)); // 30 يوم في المستقبل

        const validFrom = document.getElementById('valid_from');
        const validUntil = document.getElementById('valid_until');

        if (validFrom && !validFrom.value) {
            validFrom.value = now.toISOString().slice(0, 16);
        }

        if (validUntil && !validUntil.value) {
            validUntil.value = futureDate.toISOString().slice(0, 16);
        }
    }

    /**
     * عرض تنبيه
     */
    showAlert(message, type = 'info') {
        // إنشاء عنصر التنبيه
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show`;
        alertDiv.style.position = 'fixed';
        alertDiv.style.top = '20px';
        alertDiv.style.right = '20px';
        alertDiv.style.zIndex = '9999';
        alertDiv.style.minWidth = '300px';

        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;

        document.body.appendChild(alertDiv);

        // إزالة التنبيه تلقائياً
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove();
            }
        }, 5000);
    }

    /**
     * تطبيق مرشح البحث
     */
    applyFilter(filterType, value) {
        this.currentFilters[filterType] = value;
        
        // تحديث الرابط مع المرشحات الجديدة
        const url = new URL(window.location);
        Object.keys(this.currentFilters).forEach(key => {
            if (this.currentFilters[key]) {
                url.searchParams.set(key, this.currentFilters[key]);
            } else {
                url.searchParams.delete(key);
            }
        });
        
        window.location.href = url.toString();
    }

    /**
     * مسح جميع المرشحات
     */
    clearFilters() {
        this.currentFilters = {};
        window.location.href = window.location.pathname;
    }

    /**
     * البحث السريع
     */
    async quickSearch(query) {
        if (query.length < 2) return;

        try {
            const response = await fetch(`/discounts/search?q=${encodeURIComponent(query)}`);
            const data = await response.json();

            if (data.success) {
                this.displaySearchResults(data.results);
            }
        } catch (error) {
            console.error('خطأ في البحث:', error);
        }
    }

    /**
     * عرض نتائج البحث
     */
    displaySearchResults(results) {
        // يمكن تطبيق منطق عرض النتائج في قائمة منسدلة
        console.log('نتائج البحث:', results);
    }

    /**
     * التحقق من صحة البيانات قبل الحفظ
     */
    validateBeforeSave() {
        const form = document.getElementById('discountForm');
        if (!form) return false;

        const requiredFields = form.querySelectorAll('[required]');
        let isValid = true;

        requiredFields.forEach(field => {
            if (!this.validateField(field)) {
                isValid = false;
            }
        });

        // تحقق من التاريخ
        const validFrom = document.getElementById('valid_from');
        const validUntil = document.getElementById('valid_until');
        
        if (validFrom && validUntil) {
            const fromDate = new Date(validFrom.value);
            const untilDate = new Date(validUntil.value);
            
            if (untilDate <= fromDate) {
                this.showFieldError(validUntil, false, 'تاريخ النهاية يجب أن يكون بعد تاريخ البداية');
                isValid = false;
            }
        }

        return isValid;
    }

    /**
     * حفظ كمسودة
     */
    async saveAsDraft() {
        const form = document.getElementById('discountForm');
        if (!form) return;

        const formData = new FormData(form);
        formData.append('is_active', '0'); // حفظ كغير نشط

        try {
            const response = await fetch('/discounts', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                this.showAlert('تم حفظ الخصم كمسودة', 'success');
                setTimeout(() => {
                    window.location.href = data.redirect;
                }, 1500);
            } else {
                this.showAlert(data.message || 'فشل في حفظ المسودة', 'error');
            }
        } catch (error) {
            console.error('خطأ في حفظ المسودة:', error);
            this.showAlert('حدث خطأ أثناء حفظ المسودة', 'error');
        }
    }
}

// إنشاء مثيل عام من مدير الخصومات
window.DiscountsManager = DiscountsManager;

// دوال عامة للاستخدام في HTML
window.discountsManager = new DiscountsManager();

// دوال إضافية للاستخدام العام
window.generateCode = function() {
    if (window.discountsManager) {
        // توليد كود عشوائي
        const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        let result = 'DISC';
        for (let i = 0; i < 8; i++) {
            result += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        
        const codeInput = document.getElementById('code');
        if (codeInput) {
            codeInput.value = result;
        }
    }
};

window.updateDiscountFields = function() {
    if (window.discountsManager) {
        window.discountsManager.updateDiscountFields();
    }
};

window.updateSelectionSection = function() {
    if (window.discountsManager) {
        window.discountsManager.updateSelectionSection();
    }
};

// تصدير الكلاس للاستخدام في وحدات أخرى
if (typeof module !== 'undefined' && module.exports) {
    module.exports = DiscountsManager;
}