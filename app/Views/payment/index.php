<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>معالجة الدفع - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?= ASSETS_URL ?>/css/payment.css" rel="stylesheet">
</head>
<body>
    <div class="payment-container">
        <!-- Header -->
        <div class="payment-header">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h3 class="mb-0">
                        <i class="fas fa-credit-card me-2"></i>
                        معالجة الدفع
                    </h3>
                    <small class="text-muted">
                        الفاتورة رقم: <strong><?= htmlspecialchars($invoice->invoice_number) ?></strong>
                    </small>
                </div>
                <div class="col-md-6 text-end">
                    <div class="btn-group">
                        <a href="/orders/<?= $order->id ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-right me-2"></i> العودة للطلب
                        </a>
                        <button type="button" class="btn btn-outline-info" onclick="showPaymentHistory()">
                            <i class="fas fa-history me-2"></i> سجل المدفوعات
                        </button>
                        <button type="button" class="btn btn-outline-primary" onclick="printInvoice()">
                            <i class="fas fa-print me-2"></i> طباعة الفاتورة
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="row payment-main">
            <!-- Order Summary -->
            <div class="col-md-4 order-summary">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-receipt me-2"></i>
                            ملخص الطلب
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="order-info">
                            <div class="info-item">
                                <span class="label">رقم الطلب:</span>
                                <span class="value">#<?= $order->id ?></span>
                            </div>
                            <div class="info-item">
                                <span class="label">الطاولة:</span>
                                <span class="value">
                                    <?php if ($order->table_number): ?>
                                        طاولة <?= htmlspecialchars($order->table_number) ?>
                                    <?php else: ?>
                                        غير محدد
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="info-item">
                                <span class="label">التاريخ:</span>
                                <span class="value"><?= date('Y-m-d H:i', strtotime($order->created_at)) ?></span>
                            </div>
                        </div>

                        <hr>

                        <div class="amount-details">
                            <div class="amount-item">
                                <span>المجموع الفرعي:</span>
                                <span><?= number_format($invoice->subtotal, 2) ?> ريال</span>
                            </div>
                            
                            <?php if ($invoice->discount_amount > 0): ?>
                                <div class="amount-item discount">
                                    <span>الخصم:</span>
                                    <span>-<?= number_format($invoice->discount_amount, 2) ?> ريال</span>
                                    <?php if ($invoice->discount_reason): ?>
                                        <small class="d-block text-muted"><?= htmlspecialchars($invoice->discount_reason) ?></small>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="amount-item">
                                <span>الضريبة (15%):</span>
                                <span><?= number_format($invoice->tax_amount, 2) ?> ريال</span>
                            </div>
                            
                            <hr>
                            
                            <div class="amount-item total">
                                <span>الإجمالي:</span>
                                <span class="fw-bold"><?= number_format($invoice->total_amount, 2) ?> ريال</span>
                            </div>
                        </div>

                        <?php if (!empty($transactions)): ?>
                            <hr>
                            <div class="paid-amount">
                                <div class="amount-item">
                                    <span>المدفوع:</span>
                                    <span class="text-success">
                                        <?= number_format(array_sum(array_column($transactions, 'amount')), 2) ?> ريال
                                    </span>
                                </div>
                                <?php if ($remaining_amount > 0): ?>
                                    <div class="amount-item remaining">
                                        <span>المتبقي:</span>
                                        <span class="text-warning fw-bold">
                                            <?= number_format($remaining_amount, 2) ?> ريال
                                        </span>
                                    </div>
                                <?php else: ?>
                                    <div class="amount-item paid-full">
                                        <span>الحالة:</span>
                                        <span class="badge bg-success">
                                            <i class="fas fa-check me-1"></i>
                                            مدفوع بالكامل
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Payment Interface -->
            <div class="col-md-8 payment-interface">
                <?php if ($remaining_amount <= 0): ?>
                    <div class="card paid-notice">
                        <div class="card-body text-center">
                            <i class="fas fa-check-circle text-success fa-4x mb-3"></i>
                            <h4 class="text-success">تم دفع الفاتورة بالكامل!</h4>
                            <p class="text-muted">لا توجد مدفوعات متبقية لهذه الفاتورة</p>
                            <div class="mt-3">
                                <a href="/invoices/<?= $invoice->id ?>" class="btn btn-primary">
                                    <i class="fas fa-eye me-2"></i> عرض تفاصيل الفاتورة
                                </a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-money-bill-wave me-2"></i>
                                معالجة الدفعة
                            </h5>
                        </div>
                        <div class="card-body">
                            <form id="paymentForm">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">طريقة الدفع</label>
                                            <select class="form-select" id="paymentMethod" required>
                                                <option value="">اختر طريقة الدفع</option>
                                                <?php foreach ($payment_methods as $method): ?>
                                                    <option value="<?= $method->id ?>" 
                                                            data-type="<?= $method->type ?>">
                                                        <i class="<?= getMethodIcon($method->type) ?>"></i>
                                                        <?= htmlspecialchars($method->name) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">المبلغ</label>
                                            <div class="input-group">
                                                <input type="number" class="form-control" id="paymentAmount" 
                                                       min="0.01" max="<?= $remaining_amount ?>" 
                                                       step="0.01" value="<?= $remaining_amount ?>" required>
                                                <span class="input-group-text">ريال</span>
                                            </div>
                                            <small class="form-text text-muted">
                                                المتبقي: <?= number_format($remaining_amount, 2) ?> ريال
                                            </small>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">رقم المرجع (اختياري)</label>
                                            <input type="text" class="form-control" id="referenceNumber" 
                                                   placeholder="رقم مرجع المعاملة">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">ملاحظات (اختياري)</label>
                                            <input type="text" class="form-control" id="paymentNotes" 
                                                   placeholder="ملاحظات إضافية">
                                        </div>
                                    </div>
                                </div>

                                <!-- Quick Amount Buttons -->
                                <div class="mb-3">
                                    <label class="form-label">مبالغ سريعة</label>
                                    <div class="quick-amounts">
                                        <?php
                                        $quickAmounts = [
                                            round($remaining_amount / 4, 2),
                                            round($remaining_amount / 2, 2),
                                            round($remaining_amount * 0.75, 2),
                                            $remaining_amount
                                        ];
                                        foreach ($quickAmounts as $amount):
                                        ?>
                                            <button type="button" class="btn btn-outline-primary btn-sm" 
                                                    onclick="setQuickAmount(<?= $amount ?>)">
                                                <?= number_format($amount, 2) ?>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between">
                                    <div>
                                        <button type="button" class="btn btn-success" onclick="processPayment()">
                                            <i class="fas fa-check me-2"></i>
                                            تسجيل الدفعة
                                        </button>
                                        <button type="button" class="btn btn-info" onclick="showSplitPayment()">
                                            <i class="fas fa-split me-2"></i>
                                            تقسيم الدفعة
                                        </button>
                                    </div>
                                    <button type="button" class="btn btn-warning" onclick="applyDiscount()">
                                        <i class="fas fa-percent me-2"></i>
                                        تطبيق خصم
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Payment History -->
                <?php if (!empty($transactions)): ?>
                    <div class="card mt-3">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-history me-2"></i>
                                سجل المدفوعات
                            </h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>التاريخ</th>
                                            <th>طريقة الدفع</th>
                                            <th>المبلغ</th>
                                            <th>المرجع</th>
                                            <th>الحالة</th>
                                            <th>بواسطة</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($transactions as $transaction): ?>
                                            <tr>
                                                <td>
                                                    <small><?= date('Y-m-d H:i', strtotime($transaction->processed_at)) ?></small>
                                                </td>
                                                <td>
                                                    <i class="<?= getMethodIcon($transaction->payment_method_type) ?> me-1"></i>
                                                    <?= htmlspecialchars($transaction->payment_method_name) ?>
                                                </td>
                                                <td class="text-<?= $transaction->amount > 0 ? 'success' : 'danger' ?>">
                                                    <?= number_format($transaction->amount, 2) ?> ريال
                                                </td>
                                                <td>
                                                    <small><?= htmlspecialchars($transaction->reference_number ?? '-') ?></small>
                                                </td>
                                                <td>
                                                    <?= getTransactionStatusBadge($transaction->status) ?>
                                                </td>
                                                <td>
                                                    <small><?= htmlspecialchars($transaction->processed_by_name ?? 'غير محدد') ?></small>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Split Payment Modal -->
    <div class="modal fade" id="splitPaymentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">تقسيم الدفعة</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="splitPaymentForm">
                        <p class="text-muted">المبلغ المتبقي: <strong><?= number_format($remaining_amount, 2) ?> ريال</strong></p>
                        <div class="payment-methods-list">
                            <?php foreach ($payment_methods as $method): ?>
                                <div class="payment-method-item">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div class="method-info">
                                            <i class="<?= getMethodIcon($method->type) ?> me-2"></i>
                                            <span><?= htmlspecialchars($method->name) ?></span>
                                        </div>
                                        <div class="method-amount">
                                            <input type="number" class="form-control form-control-sm" 
                                                   id="splitAmount_<?= $method->id ?>" 
                                                   placeholder="0.00" min="0" step="0.01">
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="mt-3">
                            <div class="d-flex justify-content-between">
                                <span>المجموع:</span>
                                <span id="splitTotal">0.00 ريال</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="button" class="btn btn-primary" onclick="processSplitPayment()">تأكيد التقسيم</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Apply Discount Modal -->
    <div class="modal fade" id="discountModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">تطبيق خصم</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="discountForm">
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
                            <small class="form-text text-muted" id="discountHint">%</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">سبب الخصم</label>
                            <textarea class="form-control" id="discountReason" rows="2" 
                                      placeholder="اكتب سبب الخصم..."></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="button" class="btn btn-warning" onclick="applyDiscountSubmit()">تطبيق الخصم</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= ASSETS_URL ?>/js/payment.js"></script>
    
    <script>
        // Initialize Payment System
        document.addEventListener('DOMContentLoaded', function() {
            setupPaymentInterface();
        });

        const paymentData = {
            orderId: <?= $order->id ?>,
            invoiceId: <?= $invoice->id ?>,
            remainingAmount: <?= $remaining_amount ?>,
            paymentMethods: <?= json_encode(array_map(function($m) { return ['id' => $m->id, 'name' => $m->name, 'type' => $m->type]; }, $payment_methods)) ?>
        };

        function setupPaymentInterface() {
            // Update discount type hint
            document.getElementById('discountType').addEventListener('change', function() {
                const hint = document.getElementById('discountHint');
                hint.textContent = this.value === 'percentage' ? '%' : 'ريال';
            });

            // Update split payment total
            document.querySelectorAll('[id^="splitAmount_"]').forEach(input => {
                input.addEventListener('input', updateSplitTotal);
            });
        }

        function setQuickAmount(amount) {
            document.getElementById('paymentAmount').value = amount.toFixed(2);
        }

        async function processPayment() {
            const paymentMethod = document.getElementById('paymentMethod').value;
            const amount = parseFloat(document.getElementById('paymentAmount').value);
            const referenceNumber = document.getElementById('referenceNumber').value;
            const notes = document.getElementById('paymentNotes').value;

            if (!paymentMethod) {
                showNotification('يرجى اختيار طريقة الدفع', 'warning');
                return;
            }

            if (!amount || amount <= 0) {
                showNotification('يرجى إدخال مبلغ صحيح', 'warning');
                return;
            }

            if (amount > paymentData.remainingAmount) {
                showNotification('المبلغ المدفوع أكبر من المبلغ المطلوب', 'error');
                return;
            }

            try {
                const response = await fetch(`/payment/process/${paymentData.orderId}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': getCsrfToken()
                    },
                    body: JSON.stringify({
                        payment_method_id: paymentMethod,
                        amount: amount,
                        reference_number: referenceNumber,
                        notes: notes
                    })
                });

                const data = await response.json();
                
                if (data.success) {
                    showNotification('تم تسجيل الدفعة بنجاح', 'success');
                    
                    if (data.payment_complete) {
                        setTimeout(() => {
                            window.location.href = `/invoices/${paymentData.invoiceId}`;
                        }, 1500);
                    } else {
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    }
                } else {
                    showNotification(data.message || 'خطأ في معالجة الدفعة', 'error');
                }
            } catch (error) {
                showNotification('خطأ في الاتصال', 'error');
            }
        }

        function showSplitPayment() {
            if (paymentData.remainingAmount <= 0) {
                showNotification('لا توجد مبالغ متبقية للتقسيم', 'info');
                return;
            }

            // Reset form
            document.querySelectorAll('[id^="splitAmount_"]').forEach(input => {
                input.value = '';
            });
            updateSplitTotal();

            const modal = new bootstrap.Modal(document.getElementById('splitPaymentModal'));
            modal.show();
        }

        function updateSplitTotal() {
            let total = 0;
            document.querySelectorAll('[id^="splitAmount_"]').forEach(input => {
                const value = parseFloat(input.value) || 0;
                total += value;
            });

            document.getElementById('splitTotal').textContent = total.toFixed(2) + ' ريال';

            // Visual feedback
            const totalElement = document.getElementById('splitTotal');
            if (Math.abs(total - paymentData.remainingAmount) < 0.01) {
                totalElement.className = 'text-success';
            } else if (total > paymentData.remainingAmount) {
                totalElement.className = 'text-danger';
            } else {
                totalElement.className = 'text-muted';
            }
        }

        async function processSplitPayment() {
            const payments = [];
            
            document.querySelectorAll('[id^="splitAmount_"]').forEach(input => {
                const value = parseFloat(input.value) || 0;
                if (value > 0) {
                    const methodId = input.id.replace('splitAmount_', '');
                    payments.push({
                        payment_method_id: parseInt(methodId),
                        amount: value
                    });
                }
            });

            if (payments.length === 0) {
                showNotification('يرجى إدخال مبالغ للدفع', 'warning');
                return;
            }

            const totalAmount = payments.reduce((sum, payment) => sum + payment.amount, 0);
            
            if (Math.abs(totalAmount - paymentData.remainingAmount) > 0.01) {
                showNotification('مجموع المبالغ لا يساوي المبلغ المطلوب', 'error');
                return;
            }

            try {
                const response = await fetch(`/payment/split/${paymentData.orderId}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': getCsrfToken()
                    },
                    body: JSON.stringify({ payments: payments })
                });

                const data = await response.json();
                
                if (data.success) {
                    showNotification('تم تقسيم الدفعة بنجاح', 'success');
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showNotification(data.message || 'خطأ في تقسيم الدفعة', 'error');
                }
            } catch (error) {
                showNotification('خطأ في الاتصال', 'error');
            }
        }

        function applyDiscount() {
            const modal = new bootstrap.Modal(document.getElementById('discountModal'));
            modal.show();
        }

        async function applyDiscountSubmit() {
            const discountType = document.getElementById('discountType').value;
            const discountValue = parseFloat(document.getElementById('discountValue').value);
            const reason = document.getElementById('discountReason').value;

            if (!discountValue || discountValue <= 0) {
                showNotification('يرجى إدخال قيمة خصم صحيحة', 'warning');
                return;
            }

            if (!reason.trim()) {
                showNotification('يرجى إدخال سبب الخصم', 'warning');
                return;
            }

            try {
                const response = await fetch(`/payment/discount/${paymentData.orderId}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': getCsrfToken()
                    },
                    body: JSON.stringify({
                        discount_type: discountType,
                        discount_value: discountValue,
                        reason: reason
                    })
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

        function showPaymentHistory() {
            // This would show a detailed payment history modal
            window.location.href = `/invoices/${paymentData.invoiceId}`;
        }

        function printInvoice() {
            window.open(`/invoices/${paymentData.invoiceId}/print`, '_blank');
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
function getMethodIcon($type) {
    $icons = [
        'cash' => 'fas fa-money-bill-wave',
        'card' => 'fas fa-credit-card',
        'digital' => 'fas fa-mobile-alt',
        'bank_transfer' => 'fas fa-university',
        'check' => 'fas fa-file-invoice-dollar'
    ];
    return $icons[$type] ?? 'fas fa-question-circle';
}

function getTransactionStatusBadge($status) {
    $badges = [
        'completed' => '<span class="badge bg-success">مكتمل</span>',
        'pending' => '<span class="badge bg-warning">في الانتظار</span>',
        'failed' => '<span class="badge bg-danger">فشل</span>',
        'refunded' => '<span class="badge bg-info">مسترد</span>'
    ];
    return $badges[$status] ?? '<span class="badge bg-secondary">غير محدد</span>';
}
?>