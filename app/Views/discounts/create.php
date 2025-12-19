<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إضافة خصم جديد - Restaurant POS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="/assets/css/discounts.css" rel="stylesheet">
</head>
<body>
    <div class="discount-form-container">
        <!-- Header -->
        <div class="discount-form-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="discount-form-title">
                        <i class="fas fa-plus me-2"></i>
                        إضافة خصم جديد
                    </h1>
                    <p class="discount-form-subtitle">إنشاء خصم جديد للمطعم</p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="/discounts" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>العودة للقائمة
                    </a>
                </div>
            </div>
        </div>

        <!-- Form -->
        <div class="discount-form-section">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-tags me-2"></i>
                        تفاصيل الخصم
                    </h5>
                </div>
                <div class="card-body">
                    <form id="discountForm" method="POST" action="/discounts">
                        <!-- Basic Information -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="section-title">
                                    <i class="fas fa-info-circle me-2"></i>
                                    المعلومات الأساسية
                                </h6>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="name" class="form-label">اسم الخصم *</label>
                                <input type="text" class="form-control" id="name" name="name" required
                                       placeholder="مثال: خصم summer 2024">
                                <div class="invalid-feedback"></div>
                            </div>
                            <div class="col-md-6">
                                <label for="code" class="form-label">كود الخصم</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="code" name="code" 
                                           placeholder="سيتم توليده تلقائياً">
                                    <button class="btn btn-outline-secondary" type="button" onclick="generateCode()">
                                        <i class="fas fa-random"></i>
                                    </button>
                                </div>
                                <div class="form-text">اتركه فارغاً لتوليد كود تلقائياً</div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-12">
                                <label for="description" class="form-label">وصف الخصم</label>
                                <textarea class="form-control" id="description" name="description" rows="3"
                                          placeholder="وصف مختصر للخصم..."></textarea>
                            </div>
                        </div>

                        <!-- Discount Details -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="section-title">
                                    <i class="fas fa-calculator me-2"></i>
                                    تفاصيل الخصم
                                </h6>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="type" class="form-label">نوع الخصم *</label>
                                <select class="form-select" id="type" name="type" required onchange="updateDiscountFields()">
                                    <option value="">اختر نوع الخصم</option>
                                    <option value="percentage">نسبة مئوية</option>
                                    <option value="fixed">مبلغ ثابت</option>
                                    <option value="buy_x_get_y">اشتري X واحصل على Y</option>
                                </select>
                                <div class="invalid-feedback"></div>
                            </div>
                            <div class="col-md-4">
                                <label for="value" class="form-label">قيمة الخصم *</label>
                                <input type="number" class="form-control" id="value" name="value" required
                                       min="0" step="0.01" placeholder="0.00">
                                <div class="invalid-feedback"></div>
                            </div>
                            <div class="col-md-4">
                                <label for="applies_to" class="form-label">ينطبق على</label>
                                <select class="form-select" id="applies_to" name="applies_to">
                                    <option value="all">جميع المنتجات</option>
                                    <option value="products">منتجات محددة</option>
                                    <option value="categories">فئات محددة</option>
                                </select>
                            </div>
                        </div>

                        <!-- Limits and Restrictions -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="section-title">
                                    <i class="fas fa-lock me-2"></i>
                                    الحدود والقيود
                                </h6>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="min_order_amount" class="form-label">الحد الأدنى للطلب</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="min_order_amount" 
                                           name="min_order_amount" min="0" step="0.01" value="0">
                                    <span class="input-group-text">ر.س</span>
                                </div>
                                <div class="form-text">اتركه 0 لعدم وجود حد أدنى</div>
                            </div>
                            <div class="col-md-4">
                                <label for="max_discount_amount" class="form-label">الحد الأقصى للخصم</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="max_discount_amount" 
                                           name="max_discount_amount" min="0" step="0.01">
                                    <span class="input-group-text">ر.س</span>
                                </div>
                                <div class="form-text">اتركه فارغاً لعدم وجود حد أقصى</div>
                            </div>
                            <div class="col-md-4">
                                <label for="usage_limit" class="form-label">حد الاستخدام</label>
                                <input type="number" class="form-control" id="usage_limit" 
                                       name="usage_limit" min="1" placeholder="∞">
                                <div class="form-text">اتركه فارغاً للاستخدام اللامحدود</div>
                            </div>
                        </div>

                        <!-- Validity Period -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="section-title">
                                    <i class="fas fa-calendar me-2"></i>
                                    فترة الصلاحية
                                </h6>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="valid_from" class="form-label">صالح من *</label>
                                <input type="datetime-local" class="form-control" id="valid_from" 
                                       name="valid_from" required>
                                <div class="invalid-feedback"></div>
                            </div>
                            <div class="col-md-6">
                                <label for="valid_until" class="form-label">صالح حتى *</label>
                                <input type="datetime-local" class="form-control" id="valid_until" 
                                       name="valid_until" required>
                                <div class="invalid-feedback"></div>
                            </div>
                        </div>

                        <!-- Product/Category Selection -->
                        <div id="selectionSection" class="row mb-4" style="display: none;">
                            <div class="col-12">
                                <h6 class="section-title">
                                    <i class="fas fa-list me-2"></i>
                                    اختيار المنتجات/الفئات
                                </h6>
                            </div>
                            <div class="col-12">
                                <div id="productsSelection" style="display: none;">
                                    <label class="form-label">اختر المنتجات</label>
                                    <div class="row">
                                        <?php foreach ($products as $product): ?>
                                            <div class="col-md-6 col-lg-4 mb-2">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" 
                                                           name="selected_products[]" value="<?= $product->id ?>" 
                                                           id="product_<?= $product->id ?>">
                                                    <label class="form-check-label" for="product_<?= $product->id ?>">
                                                        <?= htmlspecialchars($product->name_ar) ?>
                                                        <small class="text-muted">
                                                            (<?= number_format($product->base_price, 2) ?> ر.س)
                                                        </small>
                                                    </label>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div id="categoriesSelection" style="display: none;">
                                    <label class="form-label">اختر الفئات</label>
                                    <div class="row">
                                        <?php foreach ($categories as $category): ?>
                                            <div class="col-md-6 col-lg-4 mb-2">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" 
                                                           name="selected_categories[]" value="<?= $category->id ?>" 
                                                           id="category_<?= $category->id ?>">
                                                    <label class="form-check-label" for="category_<?= $category->id ?>">
                                                        <?= htmlspecialchars($category->name_ar) ?>
                                                    </label>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div class="row">
                            <div class="col-12">
                                <hr>
                                <div class="d-flex justify-content-end gap-2">
                                    <a href="/discounts" class="btn btn-outline-secondary">
                                        إلغاء
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>حفظ الخصم
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/assets/js/discounts.js"></script>
    <script>
        // Initialize form
        document.addEventListener('DOMContentLoaded', function() {
            DiscountsManager.initCreateForm();
        });

        // Generate random code
        function generateCode() {
            const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            let result = 'DISC';
            for (let i = 0; i < 8; i++) {
                result += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            document.getElementById('code').value = result;
        }

        // Update discount fields based on type
        function updateDiscountFields() {
            const type = document.getElementById('type').value;
            const valueField = document.getElementById('value');
            const valueLabel = valueField.previousElementSibling;

            if (type === 'percentage') {
                valueLabel.textContent = 'نسبة الخصم (%) *';
                valueField.max = '100';
                valueField.placeholder = 'مثال: 15';
            } else if (type === 'fixed') {
                valueLabel.textContent = 'مبلغ الخصم (ر.س) *';
                valueField.max = '';
                valueField.placeholder = '0.00';
            } else if (type === 'buy_x_get_y') {
                valueLabel.textContent = 'اشتري واحصل على *';
                valueField.max = '';
                valueField.placeholder = 'سيتم تخصيصه لاحقاً';
            } else {
                valueLabel.textContent = 'قيمة الخصم *';
                valueField.max = '';
                valueField.placeholder = '0.00';
            }
        }

        // Show/hide selection section
        document.getElementById('applies_to').addEventListener('change', function() {
            const appliesTo = this.value;
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
        });

        // Set default dates
        document.getElementById('valid_from').value = new Date().toISOString().slice(0, 16);
        document.getElementById('valid_until').value = new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString().slice(0, 16);
    </script>
</body>
</html>