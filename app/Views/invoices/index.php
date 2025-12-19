<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الفواتير - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?= ASSETS_URL ?>/css/admin.css" rel="stylesheet">
</head>
<body>
    <div class="admin-container">
        <!-- Header -->
        <div class="admin-header">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h3 class="mb-0">
                        <i class="fas fa-file-invoice me-2"></i>
                        إدارة الفواتير
                    </h3>
                    <small class="text-muted">
                        إجمالي الفواتير: <?= $total_invoices ?>
                    </small>
                </div>
                <div class="col-md-6 text-end">
                    <div class="btn-group">
                        <button type="button" class="btn btn-primary" onclick="showCreateInvoiceModal()">
                            <i class="fas fa-plus me-2"></i> فاتورة جديدة
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="exportInvoices()">
                            <i class="fas fa-download me-2"></i> تصدير
                        </button>
                        <button type="button" class="btn btn-outline-info" onclick="showStatistics()">
                            <i class="fas fa-chart-bar me-2"></i> الإحصائيات
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3" id="filtersForm">
                    <div class="col-md-2">
                        <label class="form-label">الحالة</label>
                        <select name="status" class="form-select">
                            <option value="">جميع الحالات</option>
                            <option value="pending" <?= $filters['status'] === 'pending' ? 'selected' : '' ?>>في الانتظار</option>
                            <option value="paid" <?= $filters['status'] === 'paid' ? 'selected' : '' ?>>مدفوعة</option>
                            <option value="partial" <?= $filters['status'] === 'partial' ? 'selected' : '' ?>>مدفوعة جزئياً</option>
                            <option value="cancelled" <?= $filters['status'] === 'cancelled' ? 'selected' : '' ?>>ملغية</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">من تاريخ</label>
                        <input type="date" name="date_from" class="form-control" value="<?= $filters['date_from'] ?? '' ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">إلى تاريخ</label>
                        <input type="date" name="date_to" class="form-control" value="<?= $filters['date_to'] ?? '' ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">اسم العميل</label>
                        <input type="text" name="customer_name" class="form-control" 
                               value="<?= $filters['customer_name'] ?? '' ?>" placeholder="ابحث عن عميل">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">رقم الفاتورة</label>
                        <input type="text" name="invoice_number" class="form-control" 
                               value="<?= $filters['invoice_number'] ?? '' ?>" placeholder="INV-...">
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Invoices List -->
        <div class="card">
            <div class="card-header">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h5 class="mb-0">قائمة الفواتير</h5>
                    </div>
                    <div class="col-md-6 text-end">
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-secondary" onclick="selectAll()">
                                <i class="fas fa-check-square me-1"></i> تحديد الكل
                            </button>
                            <button type="button" class="btn btn-outline-warning" onclick="bulkCancel()" id="bulkCancelBtn" disabled>
                                <i class="fas fa-times me-1"></i> إلغاء المحدد
                            </button>
                            <button type="button" class="btn btn-outline-success" onclick="bulkPrint()" id="bulkPrintBtn" disabled>
                                <i class="fas fa-print me-1"></i> طباعة المحدد
                            </button>
                            <button type="button" class="btn btn-outline-primary" onclick="bulkEmail()" id="bulkEmailBtn" disabled>
                                <i class="fas fa-envelope me-1"></i> إرسال المحدد
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (empty($invoices)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <p class="text-muted">لا توجد فواتير</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th width="30">
                                        <input type="checkbox" class="form-check-input" id="selectAllCheckbox" onchange="toggleSelectAll()">
                                    </th>
                                    <th>رقم الفاتورة</th>
                                    <th>رقم الطلب</th>
                                    <th>العميل</th>
                                    <th>المبلغ</th>
                                    <th>الحالة</th>
                                    <th>التاريخ</th>
                                    <th width="120">الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($invoices as $invoice): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" class="form-check-input invoice-checkbox" 
                                                   value="<?= $invoice->id ?>" onchange="updateBulkButtons()">
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($invoice->invoice_number) ?></strong>
                                            <?php if ($invoice->discount_amount > 0): ?>
                                                <br><small class="text-success">
                                                    <i class="fas fa-tag me-1"></i>
                                                    خصم: <?= number_format($invoice->discount_amount, 2) ?> ريال
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            #<?= $invoice->order_id ?>
                                            <?php if ($invoice->table_number): ?>
                                                <br><small class="text-muted">
                                                    <i class="fas fa-table me-1"></i>
                                                    طاولة <?= htmlspecialchars($invoice->table_number) ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($invoice->customer_name): ?>
                                                <?= htmlspecialchars($invoice->customer_name) ?>
                                                <?php if ($invoice->customer_phone): ?>
                                                    <br><small class="text-muted"><?= htmlspecialchars($invoice->customer_phone) ?></small>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted">عميل عام</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?= number_format($invoice->total_amount, 2) ?> ريال</strong>
                                            <?php if ($invoice->discount_amount > 0): ?>
                                                <br><small class="text-muted">
                                                    قبل الخصم: <?= number_format($invoice->subtotal + $invoice->discount_amount, 2) ?> ريال
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?= getInvoiceStatusBadge($invoice->status) ?>
                                            <?php if ($invoice->status === 'paid'): ?>
                                                <br><small class="text-success">
                                                    <i class="fas fa-check me-1"></i>
                                                    مدفوعة
                                                </small>
                                            <?php elseif ($invoice->status === 'partial'): ?>
                                                <?php
                                                $paidAmount = getPaidAmount($invoice->id);
                                                $remaining = $invoice->total_amount - $paidAmount;
                                                ?>
                                                <br><small class="text-warning">
                                                    متبقي: <?= number_format($remaining, 2) ?> ريال
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small>
                                                <?= date('Y-m-d H:i', strtotime($invoice->created_at)) ?>
                                                <br>
                                                <span class="text-muted"><?= timeAgo($invoice->created_at) ?></span>
                                            </small>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="/invoices/<?= $invoice->id ?>" class="btn btn-outline-primary" title="عرض التفاصيل">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <button type="button" class="btn btn-outline-success" onclick="printInvoice(<?= $invoice->id ?>)" title="طباعة">
                                                    <i class="fas fa-print"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-info" onclick="emailInvoice(<?= $invoice->id ?>)" title="إرسال إيميل">
                                                    <i class="fas fa-envelope"></i>
                                                </button>
                                                <div class="btn-group" role="group">
                                                    <button type="button" class="btn btn-outline-secondary dropdown-toggle" 
                                                            data-bs-toggle="dropdown" title="المزيد">
                                                        <i class="fas fa-ellipsis-v"></i>
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <li><a class="dropdown-item" href="/invoices/<?= $invoice->id ?>/edit">
                                                            <i class="fas fa-edit me-2"></i>تعديل
                                                        </a></li>
                                                        <li><a class="dropdown-item" href="/invoices/<?= $invoice->id ?>/duplicate">
                                                            <i class="fas fa-copy me-2"></i>نسخ
                                                        </a></li>
                                                        <?php if ($invoice->status !== 'paid' && $invoice->status !== 'cancelled'): ?>
                                                            <li><hr class="dropdown-divider"></li>
                                                            <li><a class="dropdown-item text-warning" href="#" onclick="applyDiscount(<?= $invoice->id ?>)">
                                                                <i class="fas fa-percent me-2"></i>تطبيق خصم
                                                            </a></li>
                                                            <li><a class="dropdown-item text-danger" href="#" onclick="cancelInvoice(<?= $invoice->id ?>)">
                                                                <i class="fas fa-times me-2"></i>إلغاء الفاتورة
                                                            </a></li>
                                                        <?php endif; ?>
                                                        <?php if ($invoice->status === 'paid'): ?>
                                                            <li><hr class="dropdown-divider"></li>
                                                            <li><a class="dropdown-item text-info" href="#" onclick="generatePdf(<?= $invoice->id ?>)">
                                                                <i class="fas fa-file-pdf me-2"></i>إنشاء PDF
                                                            </a></li>
                                                        <?php endif; ?>
                                                    </ul>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <nav aria-label="تنقل الصفحات" class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php if ($current_page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $current_page - 1 ?>&<?= http_build_query(array_filter($filters)) ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++): ?>
                        <li class="page-item <?= $i === $current_page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>&<?= http_build_query(array_filter($filters)) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($current_page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $current_page + 1 ?>&<?= http_build_query(array_filter($filters)) ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>

    <!-- Create Invoice Modal -->
    <div class="modal fade" id="createInvoiceModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">إنشاء فاتورة جديدة</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="createInvoiceForm">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">رقم الطلب <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" name="order_id" required>
                                    <small class="form-text text-muted">أدخل رقم الطلب المراد إنشاء فاتورة له</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">اسم العميل</label>
                                    <input type="text" class="form-control" name="customer_name" maxlength="100">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">رقم الهاتف</label>
                                    <input type="text" class="form-control" name="customer_phone" maxlength="20">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">العنوان</label>
                                    <input type="text" class="form-control" name="customer_address" maxlength="500">
                                </div>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <h6>الخصم (اختياري)</h6>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">نوع الخصم</label>
                                    <select class="form-select" name="discount_type">
                                        <option value="">بدون خصم</option>
                                        <option value="percentage">نسبة مئوية</option>
                                        <option value="fixed">مبلغ ثابت</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">قيمة الخصم</label>
                                    <input type="number" class="form-control" name="discount_value" min="0" step="0.01">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">سبب الخصم</label>
                                    <input type="text" class="form-control" name="discount_reason" maxlength="200">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-primary">إنشاء الفاتورة</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Apply Discount Modal -->
    <div class="modal fade" id="discountModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">تطبيق خصم على الفاتورة</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="discountForm">
                    <input type="hidden" id="discountInvoiceId" name="invoice_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">نوع الخصم</label>
                            <select class="form-select" name="discount_type" required>
                                <option value="percentage">نسبة مئوية</option>
                                <option value="fixed">مبلغ ثابت</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">قيمة الخصم</label>
                            <input type="number" class="form-control" name="discount_value" required min="0" step="0.01">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">سبب الخصم</label>
                            <textarea class="form-control" name="discount_reason" rows="2" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-warning">تطبيق الخصم</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Cancel Invoice Modal -->
    <div class="modal fade" id="cancelModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">إلغاء الفاتورة</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="cancelForm">
                    <input type="hidden" id="cancelInvoiceId" name="invoice_id">
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            هل أنت متأكد من إلغاء هذه الفاتورة؟
                        </div>
                        <div class="mb-3">
                            <label class="form-label">سبب الإلغاء <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="reason" rows="3" required 
                                      placeholder="اكتب سبب إلغاء الفاتورة..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">تراجع</button>
                        <button type="submit" class="btn btn-danger">إلغاء الفاتورة</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Email Invoice Modal -->
    <div class="modal fade" id="emailModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">إرسال الفاتورة بالبريد الإلكتروني</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="emailForm">
                    <input type="hidden" id="emailInvoiceId" name="invoice_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">البريد الإلكتروني <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" name="email" required 
                                   placeholder="customer@example.com">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">الموضوع</label>
                            <input type="text" class="form-control" name="subject" 
                                   placeholder="فاتورة رقم ...">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">الرسالة</label>
                            <textarea class="form-control" name="message" rows="3" 
                                      placeholder="رسالة إضافية (اختياري)"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-primary">إرسال</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Statistics Modal -->
    <div class="modal fade" id="statisticsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">إحصائيات الفواتير</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="statisticsContent">
                        <!-- Statistics will be loaded here -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= ASSETS_URL ?>/js/invoices.js"></script>
    
    <script>
        // Initialize Invoices Management
        document.addEventListener('DOMContentLoaded', function() {
            setupEventListeners();
        });

        function setupEventListeners() {
            // Forms
            document.getElementById('createInvoiceForm').addEventListener('submit', handleCreateInvoice);
            document.getElementById('discountForm').addEventListener('submit', handleApplyDiscount);
            document.getElementById('cancelForm').addEventListener('submit', handleCancelInvoice);
            document.getElementById('emailForm').addEventListener('submit', handleEmailInvoice);

            // Filters form
            document.getElementById('filtersForm').addEventListener('submit', function(e) {
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                submitBtn.disabled = true;
                
                setTimeout(() => {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }, 3000);
            });
        }

        function showCreateInvoiceModal() {
            const modal = new bootstrap.Modal(document.getElementById('createInvoiceModal'));
            modal.show();
        }

        async function handleCreateInvoice(e) {
            e.preventDefault();
            
            try {
                const formData = new FormData(e.target);
                
                const response = await fetch('/invoices/create', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-Token': getCsrfToken()
                    },
                    body: formData
                });

                const data = await response.json();
                
                if (data.success) {
                    showNotification('تم إنشاء الفاتورة بنجاح', 'success');
                    setTimeout(() => {
                        window.location.href = data.redirect_url;
                    }, 1500);
                } else {
                    showNotification(data.message || 'خطأ في إنشاء الفاتورة', 'error');
                }
            } catch (error) {
                showNotification('خطأ في الاتصال', 'error');
            }
        }

        function applyDiscount(invoiceId) {
            document.getElementById('discountInvoiceId').value = invoiceId;
            const modal = new bootstrap.Modal(document.getElementById('discountModal'));
            modal.show();
        }

        async function handleApplyDiscount(e) {
            e.preventDefault();
            
            const invoiceId = document.getElementById('discountInvoiceId').value;
            const formData = new FormData(e.target);
            
            try {
                const response = await fetch(`/invoices/${invoiceId}/discount`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-Token': getCsrfToken()
                    },
                    body: formData
                });

                const data = await response.json();
                
                if (data.success) {
                    showNotification('تم تطبيق الخصم بنجاح', 'success');
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showNotification(data.message || 'خطأ في تطبيق الخصم', 'error');
                }
            } catch (error) {
                showNotification('خطأ في الاتصال', 'error');
            }
        }

        function cancelInvoice(invoiceId) {
            document.getElementById('cancelInvoiceId').value = invoiceId;
            const modal = new bootstrap.Modal(document.getElementById('cancelModal'));
            modal.show();
        }

        async function handleCancelInvoice(e) {
            e.preventDefault();
            
            const invoiceId = document.getElementById('cancelInvoiceId').value;
            const formData = new FormData(e.target);
            
            try {
                const response = await fetch(`/invoices/${invoiceId}/cancel`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-Token': getCsrfToken()
                    },
                    body: formData
                });

                const data = await response.json();
                
                if (data.success) {
                    showNotification('تم إلغاء الفاتورة بنجاح', 'success');
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showNotification(data.message || 'خطأ في إلغاء الفاتورة', 'error');
                }
            } catch (error) {
                showNotification('خطأ في الاتصال', 'error');
            }
        }

        function emailInvoice(invoiceId) {
            document.getElementById('emailInvoiceId').value = invoiceId;
            const modal = new bootstrap.Modal(document.getElementById('emailModal'));
            modal.show();
        }

        async function handleEmailInvoice(e) {
            e.preventDefault();
            
            const invoiceId = document.getElementById('emailInvoiceId').value;
            const formData = new FormData(e.target);
            
            try {
                const response = await fetch(`/invoices/${invoiceId}/email`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-Token': getCsrfToken()
                    },
                    body: formData
                });

                const data = await response.json();
                
                if (data.success) {
                    showNotification('تم إرسال الفاتورة بنجاح', 'success');
                    const modal = bootstrap.Modal.getInstance(document.getElementById('emailModal'));
                    modal.hide();
                } else {
                    showNotification(data.message || 'خطأ في إرسال الفاتورة', 'error');
                }
            } catch (error) {
                showNotification('خطأ في الاتصال', 'error');
            }
        }

        function printInvoice(invoiceId) {
            window.open(`/invoices/${invoiceId}/print`, '_blank');
        }

        function generatePdf(invoiceId) {
            window.open(`/invoices/${invoiceId}/pdf`, '_blank');
        }

        // Bulk operations
        function toggleSelectAll() {
            const selectAllCheckbox = document.getElementById('selectAllCheckbox');
            const invoiceCheckboxes = document.querySelectorAll('.invoice-checkbox');
            
            invoiceCheckboxes.forEach(checkbox => {
                checkbox.checked = selectAllCheckbox.checked;
            });
            
            updateBulkButtons();
        }

        function selectAll() {
            document.getElementById('selectAllCheckbox').checked = true;
            toggleSelectAll();
        }

        function updateBulkButtons() {
            const selectedCheckboxes = document.querySelectorAll('.invoice-checkbox:checked');
            const bulkCancelBtn = document.getElementById('bulkCancelBtn');
            const bulkPrintBtn = document.getElementById('bulkPrintBtn');
            const bulkEmailBtn = document.getElementById('bulkEmailBtn');
            
            const hasSelection = selectedCheckboxes.length > 0;
            
            bulkCancelBtn.disabled = !hasSelection;
            bulkPrintBtn.disabled = !hasSelection;
            bulkEmailBtn.disabled = !hasSelection;
            
            // Update select all checkbox state
            const selectAllCheckbox = document.getElementById('selectAllCheckbox');
            const totalCheckboxes = document.querySelectorAll('.invoice-checkbox').length;
            selectAllCheckbox.checked = selectedCheckboxes.length === totalCheckboxes;
            selectAllCheckbox.indeterminate = selectedCheckboxes.length > 0 && selectedCheckboxes.length < totalCheckboxes;
        }

        async function bulkCancel() {
            const selectedCheckboxes = document.querySelectorAll('.invoice-checkbox:checked');
            const invoiceIds = Array.from(selectedCheckboxes).map(cb => cb.value);
            
            if (invoiceIds.length === 0) return;
            
            if (!confirm(`هل أنت متأكد من إلغاء ${invoiceIds.length} فاتورة؟`)) return;
            
            // Implementation for bulk cancel
            showNotification('جاري إلغاء الفواتير...', 'info');
        }

        function bulkPrint() {
            const selectedCheckboxes = document.querySelectorAll('.invoice-checkbox:checked');
            const invoiceIds = Array.from(selectedCheckboxes).map(cb => cb.value);
            
            if (invoiceIds.length === 0) return;
            
            invoiceIds.forEach(invoiceId => {
                window.open(`/invoices/${invoiceId}/print`, '_blank');
            });
        }

        function bulkEmail() {
            const selectedCheckboxes = document.querySelectorAll('.invoice-checkbox:checked');
            const invoiceIds = Array.from(selectedCheckboxes).map(cb => cb.value);
            
            if (invoiceIds.length === 0) return;
            
            showNotification(`تم تحديد ${invoiceIds.length} فاتورة للإرسال`, 'info');
        }

        async function exportInvoices() {
            const form = document.getElementById('filtersForm');
            const formData = new FormData(form);
            formData.append('export', 'excel');
            
            const params = new URLSearchParams(formData);
            window.open(`/invoices/export?${params.toString()}`, '_blank');
        }

        async function showStatistics() {
            try {
                const response = await fetch('/invoices/statistics', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-Token': getCsrfToken()
                    }
                });

                const data = await response.json();
                
                if (data.success) {
                    displayStatistics(data.statistics);
                    const modal = new bootstrap.Modal(document.getElementById('statisticsModal'));
                    modal.show();
                }
            } catch (error) {
                showNotification('خطأ في تحميل الإحصائيات', 'error');
            }
        }

        function displayStatistics(stats) {
            const container = document.getElementById('statisticsContent');
            container.innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <h6>ملخص الفواتير</h6>
                        <ul class="list-group">
                            <li class="list-group-item d-flex justify-content-between">
                                إجمالي الفواتير
                                <span class="badge bg-primary rounded-pill">${stats.total_invoices || 0}</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                الفواتير المدفوعة
                                <span class="badge bg-success rounded-pill">${stats.paid_invoices || 0}</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                الفواتير المعلقة
                                <span class="badge bg-warning rounded-pill">${stats.pending_invoices || 0}</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                الفواتير الملغية
                                <span class="badge bg-danger rounded-pill">${stats.cancelled_invoices || 0}</span>
                            </li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6>الإيرادات</h6>
                        <ul class="list-group">
                            <li class="list-group-item d-flex justify-content-between">
                                إجمالي الإيرادات
                                <span class="badge bg-success rounded-pill">${(stats.total_revenue || 0).toFixed(2)} ريال</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                متوسط قيمة الفاتورة
                                <span class="badge bg-info rounded-pill">${(stats.average_invoice_value || 0).toFixed(2)} ريال</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                إجمالي الخصومات
                                <span class="badge bg-warning rounded-pill">${(stats.total_discounts || 0).toFixed(2)} ريال</span>
                            </li>
                        </ul>
                    </div>
                </div>
            `;
        }

        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show position-fixed`;
            notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            notification.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 4000);
        }

        function getCsrfToken() {
            return document.querySelector('meta[name="csrf-token"]')?.content || '';
        }
    </script>
</body>
</html>

<?php
// Helper functions
function getInvoiceStatusBadge($status) {
    $badges = [
        'pending' => '<span class="badge bg-secondary">في الانتظار</span>',
        'paid' => '<span class="badge bg-success">مدفوعة</span>',
        'partial' => '<span class="badge bg-warning">مدفوعة جزئياً</span>',
        'cancelled' => '<span class="badge bg-danger">ملغية</span>'
    ];
    
    return $badges[$status] ?? '<span class="badge bg-light text-dark">غير محدد</span>';
}

function getPaidAmount($invoiceId) {
    // This would typically query the payment_transactions table
    // For now, returning a placeholder
    return 0;
}

function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'منذ لحظات';
    if ($time < 3600) return 'منذ ' . floor($time/60) . ' دقيقة';
    if ($time < 86400) return 'منذ ' . floor($time/3600) . ' ساعة';
    if ($time < 2592000) return 'منذ ' . floor($time/86400) . ' يوم';
    
    return date('Y-m-d', strtotime($datetime));
}
?>