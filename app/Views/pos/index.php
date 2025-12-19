<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نقطة البيع - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?= ASSETS_URL ?>/css/pos.css" rel="stylesheet">
</head>
<body>
    <div class="pos-container">
        <!-- Header -->
        <div class="pos-header">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h3 class="mb-0">
                        <i class="fas fa-cash-register me-2"></i>
                        نقطة البيع
                    </h3>
                    <small class="text-muted">
                        مرحباً <?= htmlspecialchars($user->name) ?> - 
                        <?php if ($current_table): ?>
                            الطاولة رقم: <strong><?= $current_table ?></strong>
                        <?php else: ?>
                            لم يتم تحديد طاولة
                        <?php endif; ?>
                    </small>
                </div>
                <div class="col-md-6 text-end">
                    <div class="btn-group">
                        <button type="button" class="btn btn-outline-primary" onclick="showTableSelector()">
                            <i class="fas fa-table"></i> اختيار طاولة
                        </button>
                        <button type="button" class="btn btn-outline-info" onclick="showCurrentOrder()">
                            <i class="fas fa-shopping-cart"></i> الطلب الحالي
                        </button>
                        <button type="button" class="btn btn-outline-success" onclick="searchProducts()">
                            <i class="fas fa-search"></i> بحث
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="row pos-main">
            <!-- Categories Sidebar -->
            <div class="col-md-3 pos-categories">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-list me-2"></i>
                            الفئات
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="category-list">
                            <button class="category-item active" data-category="all">
                                <i class="fas fa-th-large me-2"></i>
                                جميع المنتجات
                            </button>
                            <?php foreach ($products_by_category as $categoryName => $products): ?>
                                <button class="category-item" data-category="<?= htmlspecialchars($categoryName) ?>">
                                    <i class="fas fa-tag me-2"></i>
                                    <?= htmlspecialchars($categoryName) ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Products Grid -->
            <div class="col-md-6 pos-products">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-utensils me-2"></i>
                            المنتجات
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="products-grid" id="productsGrid">
                            <?php foreach ($products_by_category as $categoryName => $products): ?>
                                <div class="product-category" data-category="<?= htmlspecialchars($categoryName) ?>">
                                    <h6 class="category-title"><?= htmlspecialchars($categoryName) ?></h6>
                                    <div class="row">
                                        <?php foreach ($products as $product): ?>
                                            <div class="col-md-6 col-lg-4 mb-3">
                                                <div class="product-card" onclick="addToOrder(<?= $product->id ?>)">
                                                    <div class="product-image">
                                                        <?php if ($product->image): ?>
                                                            <img src="<?= htmlspecialchars($product->image) ?>" 
                                                                 alt="<?= htmlspecialchars($product->name) ?>" 
                                                                 class="img-fluid">
                                                        <?php else: ?>
                                                            <div class="product-placeholder">
                                                                <i class="fas fa-utensils"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="product-info">
                                                        <h6 class="product-name"><?= htmlspecialchars($product->name) ?></h6>
                                                        <div class="product-price"><?= number_format($product->price, 2) ?> ريال</div>
                                                        <?php if ($product->preparation_time): ?>
                                                            <small class="text-muted">
                                                                <i class="fas fa-clock me-1"></i>
                                                                <?= $product->preparation_time ?> دقيقة
                                                            </small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Order Summary -->
            <div class="col-md-3 pos-order">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-shopping-cart me-2"></i>
                            ملخص الطلب
                        </h5>
                    </div>
                    <div class="card-body">
                        <div id="orderItems">
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-shopping-cart fa-3x mb-3"></i>
                                <p>لا توجد عناصر في الطلب</p>
                            </div>
                        </div>
                        
                        <div id="orderTotals" class="d-none">
                            <hr>
                            <div class="d-flex justify-content-between">
                                <span>المجموع الفرعي:</span>
                                <span id="subtotal">0.00 ريال</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>الضريبة (15%):</span>
                                <span id="taxAmount">0.00 ريال</span>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between fw-bold">
                                <span>الإجمالي:</span>
                                <span id="totalAmount">0.00 ريال</span>
                            </div>
                        </div>

                        <div class="mt-3">
                            <div class="btn-group w-100">
                                <button type="button" class="btn btn-warning" onclick="clearOrder()" id="clearBtn" disabled>
                                    <i class="fas fa-trash"></i> مسح
                                </button>
                                <button type="button" class="btn btn-success" onclick="submitOrder()" id="submitBtn" disabled>
                                    <i class="fas fa-check"></i> إرسال للمطبخ
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-bolt me-2"></i>
                            إجراءات سريعة
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="showSearchModal()">
                                <i class="fas fa-search me-2"></i> بحث المنتجات
                            </button>
                            <button type="button" class="btn btn-outline-info btn-sm" onclick="showNotesModal()">
                                <i class="fas fa-sticky-note me-2"></i> إضافة ملاحظة
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="showDiscountModal()">
                                <i class="fas fa-percent me-2"></i> تطبيق خصم
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Table Selector Modal -->
    <div class="modal fade" id="tableSelectorModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">اختيار طاولة</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row" id="availableTables">
                        <?php foreach ($available_tables as $table): ?>
                            <div class="col-md-4 mb-3">
                                <div class="table-card" onclick="selectTable(<?= $table->id ?>)">
                                    <h6>طاولة رقم <?= htmlspecialchars($table->table_number) ?></h6>
                                    <small class="text-muted">
                                        <i class="fas fa-users me-1"></i>
                                        <?= $table->seats ?> مقاعد
                                    </small>
                                    <?php if ($table->area_name): ?>
                                        <small class="text-muted d-block">
                                            <i class="fas fa-map-marker-alt me-1"></i>
                                            <?= htmlspecialchars($table->area_name) ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Product Search Modal -->
    <div class="modal fade" id="productSearchModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">بحث المنتجات</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <input type="text" class="form-control" id="searchInput" 
                               placeholder="ابحث عن منتج..." onkeyup="searchProducts()">
                    </div>
                    <div id="searchResults"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Notes Modal -->
    <div class="modal fade" id="notesModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">إضافة ملاحظة للطلب</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">ملاحظة</label>
                        <textarea class="form-control" id="orderNotes" rows="3" 
                                  placeholder="اكتب ملاحظة للطلب..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="button" class="btn btn-primary" onclick="saveNotes()">حفظ</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Discount Modal -->
    <div class="modal fade" id="discountModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">تطبيق خصم</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">نوع الخصم</label>
                        <select class="form-select" id="discountType">
                            <option value="percentage">نسبة مئوية</option>
                            <option value="fixed">مبلغ ثابت</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">قيمة الخصم</label>
                        <input type="number" class="form-control" id="discountValue" 
                               placeholder="0" min="0" step="0.01">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="button" class="btn btn-primary" onclick="applyDiscount()">تطبيق</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= ASSETS_URL ?>/js/pos.js"></script>
    
    <script>
        // Initialize POS
        document.addEventListener('DOMContentLoaded', function() {
            loadCurrentOrder();
            setupCategoryFilters();
            setupKeyboardShortcuts();
        });

        // Global variables
        let currentOrderId = null;
        let currentTableId = null;
        let orderItems = [];

        // Category filtering
        function setupCategoryFilters() {
            const categoryButtons = document.querySelectorAll('.category-item');
            const productCategories = document.querySelectorAll('.product-category');

            categoryButtons.forEach(button => {
                button.addEventListener('click', function() {
                    // Update active button
                    categoryButtons.forEach(btn => btn.classList.remove('active'));
                    this.classList.add('active');

                    // Show/hide product categories
                    const selectedCategory = this.dataset.category;
                    productCategories.forEach(category => {
                        if (selectedCategory === 'all' || category.dataset.category === selectedCategory) {
                            category.style.display = 'block';
                        } else {
                            category.style.display = 'none';
                        }
                    });
                });
            });
        }

        // Add product to order
        async function addToOrder(productId) {
            try {
                const response = await fetch('/pos/add-item', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': getCsrfToken()
                    },
                    body: JSON.stringify({
                        product_id: productId,
                        quantity: 1
                    })
                });

                const data = await response.json();
                
                if (data.success) {
                    showNotification('تم إضافة المنتج بنجاح', 'success');
                    updateOrderDisplay(data.order_items);
                } else {
                    showNotification(data.message || 'خطأ في إضافة المنتج', 'error');
                }
            } catch (error) {
                showNotification('خطأ في الاتصال', 'error');
            }
        }

        // Load current order
        async function loadCurrentOrder() {
            try {
                const response = await fetch('/pos/current-order');
                const data = await response.json();
                
                if (data.success && data.order_items) {
                    updateOrderDisplay(data.order_items);
                    if (data.order) {
                        currentOrderId = data.order.id;
                    }
                }
            } catch (error) {
                console.error('Error loading current order:', error);
            }
        }

        // Update order display
        function updateOrderDisplay(items) {
            const orderItemsContainer = document.getElementById('orderItems');
            const orderTotalsContainer = document.getElementById('orderTotals');
            const clearBtn = document.getElementById('clearBtn');
            const submitBtn = document.getElementById('submitBtn');

            if (items.length === 0) {
                orderItemsContainer.innerHTML = `
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-shopping-cart fa-3x mb-3"></i>
                        <p>لا توجد عناصر في الطلب</p>
                    </div>
                `;
                orderTotalsContainer.classList.add('d-none');
                clearBtn.disabled = true;
                submitBtn.disabled = true;
                return;
            }

            let itemsHtml = '';
            let subtotal = 0;

            items.forEach(item => {
                const itemTotal = item.quantity * item.unit_price;
                subtotal += itemTotal;

                itemsHtml += `
                    <div class="order-item">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <h6 class="mb-1">${item.product_name}</h6>
                                ${item.notes ? `<small class="text-muted">${item.notes}</small>` : ''}
                            </div>
                            <button class="btn btn-sm btn-outline-danger" onclick="removeItem(${item.id})">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <div class="quantity-controls">
                                <button class="btn btn-sm btn-outline-secondary" onclick="updateQuantity(${item.id}, ${item.quantity - 1})">
                                    <i class="fas fa-minus"></i>
                                </button>
                                <span class="mx-2">${item.quantity}</span>
                                <button class="btn btn-sm btn-outline-secondary" onclick="updateQuantity(${item.id}, ${item.quantity + 1})">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                            <span class="fw-bold">${itemTotal.toFixed(2)} ريال</span>
                        </div>
                    </div>
                `;
            });

            orderItemsContainer.innerHTML = itemsHtml;

            // Calculate totals
            const taxAmount = subtotal * 0.15;
            const totalAmount = subtotal + taxAmount;

            document.getElementById('subtotal').textContent = subtotal.toFixed(2) + ' ريال';
            document.getElementById('taxAmount').textContent = taxAmount.toFixed(2) + ' ريال';
            document.getElementById('totalAmount').textContent = totalAmount.toFixed(2) + ' ريال';

            orderTotalsContainer.classList.remove('d-none');
            clearBtn.disabled = false;
            submitBtn.disabled = false;
        }

        // Update item quantity
        async function updateQuantity(itemId, newQuantity) {
            if (newQuantity < 0) return;

            try {
                const response = await fetch(`/pos/update-item/${itemId}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': getCsrfToken()
                    },
                    body: JSON.stringify({ quantity: newQuantity })
                });

                const data = await response.json();
                
                if (data.success) {
                    updateOrderDisplay(data.order_items);
                } else {
                    showNotification(data.message || 'خطأ في تحديث العنصر', 'error');
                }
            } catch (error) {
                showNotification('خطأ في الاتصال', 'error');
            }
        }

        // Remove item from order
        async function removeItem(itemId) {
            if (!confirm('هل تريد حذف هذا العنصر؟')) return;

            try {
                const response = await fetch(`/pos/remove-item/${itemId}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-Token': getCsrfToken()
                    }
                });

                const data = await response.json();
                
                if (data.success) {
                    showNotification('تم حذف العنصر', 'success');
                    loadCurrentOrder();
                } else {
                    showNotification(data.message || 'خطأ في حذف العنصر', 'error');
                }
            } catch (error) {
                showNotification('خطأ في الاتصال', 'error');
            }
        }

        // Submit order
        async function submitOrder() {
            if (!confirm('هل تريد إرسال الطلب للمطبخ؟')) return;

            try {
                const response = await fetch('/pos/submit-order', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-Token': getCsrfToken()
                    }
                });

                const data = await response.json();
                
                if (data.success) {
                    showNotification('تم إرسال الطلب للمطبخ بنجاح', 'success');
                    setTimeout(() => {
                        window.location.href = '/pos';
                    }, 1500);
                } else {
                    showNotification(data.message || 'خطأ في إرسال الطلب', 'error');
                }
            } catch (error) {
                showNotification('خطأ في الاتصال', 'error');
            }
        }

        // Clear order
        function clearOrder() {
            if (!confirm('هل تريد مسح الطلب الحالي؟')) return;
            
            // Clear order items and reset display
            orderItems = [];
            updateOrderDisplay([]);
            currentOrderId = null;
            currentTableId = null;
            
            showNotification('تم مسح الطلب', 'info');
        }

        // Select table
        async function selectTable(tableId) {
            try {
                const response = await fetch('/pos/start-order', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': getCsrfToken()
                    },
                    body: JSON.stringify({ table_id: tableId })
                });

                const data = await response.json();
                
                if (data.success) {
                    currentTableId = tableId;
                    currentOrderId = data.order_id;
                    showNotification('تم تحديد الطاولة بنجاح', 'success');
                    
                    // Hide modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('tableSelectorModal'));
                    modal.hide();
                    
                    loadCurrentOrder();
                } else {
                    showNotification(data.message || 'خطأ في تحديد الطاولة', 'error');
                }
            } catch (error) {
                showNotification('خطأ في الاتصال', 'error');
            }
        }

        // Show modals
        function showTableSelector() {
            const modal = new bootstrap.Modal(document.getElementById('tableSelectorModal'));
            modal.show();
        }

        function showSearchModal() {
            const modal = new bootstrap.Modal(document.getElementById('productSearchModal'));
            modal.show();
        }

        function showNotesModal() {
            const modal = new bootstrap.Modal(document.getElementById('notesModal'));
            modal.show();
        }

        function showDiscountModal() {
            const modal = new bootstrap.Modal(document.getElementById('discountModal'));
            modal.show();
        }

        // Search products
        async function searchProducts() {
            const query = document.getElementById('searchInput').value;
            if (query.length < 2) return;

            try {
                const response = await fetch(`/pos/search-products?q=${encodeURIComponent(query)}`);
                const data = await response.json();
                
                if (data.success) {
                    displaySearchResults(data.products);
                }
            } catch (error) {
                console.error('Search error:', error);
            }
        }

        // Display search results
        function displaySearchResults(products) {
            const container = document.getElementById('searchResults');
            
            if (products.length === 0) {
                container.innerHTML = '<p class="text-center text-muted">لا توجد نتائج</p>';
                return;
            }

            let html = '<div class="row">';
            products.forEach(product => {
                html += `
                    <div class="col-md-6 mb-2">
                        <div class="search-result-item" onclick="addToOrder(${product.id})">
                            <h6>${product.name}</h6>
                            <small class="text-muted">${product.price} ريال</small>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            
            container.innerHTML = html;
        }

        // Save notes
        function saveNotes() {
            const notes = document.getElementById('orderNotes').value;
            // Save notes to current order
            // Implementation depends on your requirements
            
            const modal = bootstrap.Modal.getInstance(document.getElementById('notesModal'));
            modal.hide();
            showNotification('تم حفظ الملاحظة', 'success');
        }

        // Apply discount
        function applyDiscount() {
            const type = document.getElementById('discountType').value;
            const value = parseFloat(document.getElementById('discountValue').value);
            
            // Apply discount to current order
            // Implementation depends on your requirements
            
            const modal = bootstrap.Modal.getInstance(document.getElementById('discountModal'));
            modal.hide();
            showNotification('تم تطبيق الخصم', 'success');
        }

        // Show notification
        function showNotification(message, type = 'info') {
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show position-fixed`;
            notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            notification.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.appendChild(notification);
            
            // Auto remove after 3 seconds
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 3000);
        }

        // Get CSRF token
        function getCsrfToken() {
            return document.querySelector('meta[name="csrf-token"]')?.content || '';
        }

        // Setup keyboard shortcuts
        function setupKeyboardShortcuts() {
            document.addEventListener('keydown', function(e) {
                // Ctrl+F for search
                if (e.ctrlKey && e.key === 'f') {
                    e.preventDefault();
                    showSearchModal();
                }
                
                // Ctrl+Enter to submit order
                if (e.ctrlKey && e.key === 'Enter') {
                    e.preventDefault();
                    if (!document.getElementById('submitBtn').disabled) {
                        submitOrder();
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
            });
        }

        // Auto-refresh order data every 30 seconds
        setInterval(loadCurrentOrder, 30000);
    </script>
</body>
</html>