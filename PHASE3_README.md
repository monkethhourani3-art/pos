# المرحلة الثالثة: نظام الطلبات الأساسي
## Restaurant POS System - Phase 3

### نظرة عامة
تم تطوير المرحلة الثالثة من نظام كاشير المطعم والتي تشمل نظام الطلبات الأساسي مع الواجهات التالية:
- واجهة نقطة البيع (POS Interface)
- لوحة تحكم المطبخ (Kitchen Display System - KDS)
- واجهة إدارة الطلبات (Orders Management)

### المكونات المطورة

#### 1. Controllers

##### PosController.php
**المسار:** `/app/Controllers/PosController.php`
**الوظائف:**
- `index()` - عرض واجهة نقطة البيع الرئيسية
- `startOrder()` - بدء طلب جديد لطاولة محددة
- `addItem()` - إضافة منتج للطلب الحالي
- `updateItem()` - تحديث كمية عنصر في الطلب
- `removeItem()` - حذف عنصر من الطلب
- `submitOrder()` - إرسال الطلب للمطبخ
- `getCurrentOrder()` - جلب تفاصيل الطلب الحالي
- `searchProducts()` - البحث في المنتجات

##### OrderController.php
**المسار:** `/app/Controllers/OrderController.php`
**الوظائف:**
- `index()` - عرض قائمة الطلبات مع الفلترة
- `show()` - عرض تفاصيل طلب محدد
- `updateStatus()` - تحديث حالة الطلب
- `cancel()` - إلغاء طلب
- `statistics()` - إحصائيات الطلبات
- `merge()` - دمج طلبات
- `split()` - تقسيم طلب
- `printReceipt()` - طباعة الفاتورة

##### KitchenController.php
**المسار:** `/app/Controllers/KitchenController.php`
**الوظائف:**
- `index()` - عرض لوحة المطبخ الرئيسية
- `getKitchenData()` - جلب بيانات المطبخ للتحديث المباشر
- `startPreparing()` - بدء تحضير طلب
- `markItemReady()` - تحديد عنصر كجاهز
- `markOrderReady()` - تحديد طلب كامل كجاهز
- `markServed()` - تحديد طلب كمُسلم
- `addItemNotes()` - إضافة ملاحظات للمطبخ
- `reportIssue()` - الإبلاغ عن مشكلة
- `metrics()` - إحصائيات الأداء

#### 2. Models

##### Order.php
**المسار:** `/app/Models/Order.php`
**الوظائف:**
- إنشاء، تحديث، حذف الطلبات
- جلب الطلبات مع التفاصيل
- فلترة الطلبات حسب الحالة والتاريخ
- إحصائيات الطلبات
- حساب الأداء للمطبخ

##### OrderItem.php
**المسار:** `/app/Models/OrderItem.php`
**الوظائف:**
- إدارة عناصر الطلبات
- حساب إجماليات الطلب
- دمج وتقسيم الطلبات
- المنتجات الأكثر مبيعاً
- تتبع وقت التحضير

##### Product.php
**المسار:** `/app/Models/Product.php`
**الوظائف:**
- إدارة المنتجات
- البحث والفلترة
- تجميع المنتجات بالفئات
- إحصائيات المنتجات
- المنتجات المميزة

##### Table.php
**المسار:** `/app/Models/Table.php`
**الوظائف:**
- إدارة الطاولات
- تتبع حالة الطاولات
- إحصائيات الإشغال
- كود QR للطاولات
- تقرير الاستخدام

#### 3. Views

##### واجهة نقطة البيع
**المسار:** `/app/Views/pos/index.php`
**المميزات:**
- تصميم responsive مع دعم العربية RTL
- فلترة المنتجات بالفئات
- بحث سريع في المنتجات
- إضافة وإزالة المنتجات
- حساب الإجماليات تلقائياً
- اختيار الطاولات
- إجراءات سريعة

##### لوحة المطبخ
**المسار:** `/app/Views/kitchen/index.php`
**المميزات:**
- تصميم مظلم مناسب للمطبخ
- عرض الطلبات مرتبة حسب الحالة
- إحصائيات مباشرة
- تحديث تلقائي كل 30 ثانية
- إضافة ملاحظات للمطبخ
- الإبلاغ عن المشاكل
- وضع ملء الشاشة
- أصوات الإشعارات

##### إدارة الطلبات
**المسار:** `/app/Views/orders/index.php`
**المميزات:**
- جدول شامل للطلبات
- فلترة متقدمة
- إجراءات متعددة (تغيير الحالة، الإلغاء)
- عمليات مجمعة
- تصدير البيانات
- إحصائيات مفصلة
- pagination

#### 4. CSS & JavaScript

##### CSS Files
- `/public/assets/css/pos.css` - تصميم واجهة POS
- `/public/assets/css/kitchen.css` - تصميم لوحة المطبخ
- `/public/assets/css/admin.css` - تصميم واجهة الإدارة

##### JavaScript Files
- `/public/assets/js/pos.js` - منطق واجهة POS
- `/public/assets/js/kitchen.js` - منطق لوحة المطبخ
- `/public/assets/js/orders.js` - منطق إدارة الطلبات

#### 5. Routes المحدثة

##### Routes المضافة/المحدثة في `/app/routes.php`:

```php
// POS Routes
$router->group(['prefix' => 'pos', 'middleware' => 'permissions:pos_access'], function($router) {
    $router->get('/', 'PosController@index');
    $router->post('/start-order', 'PosController@startOrder');
    $router->post('/add-item', 'PosController@addItem');
    $router->post('/update-item/{id}', 'PosController@updateItem');
    $router->delete('/remove-item/{id}', 'PosController@removeItem');
    $router->post('/submit-order', 'PosController@submitOrder');
    $router->get('/current-order', 'PosController@getCurrentOrder');
    $router->get('/search-products', 'PosController@searchProducts');
});

// Kitchen Routes
$router->group(['prefix' => 'kitchen', 'middleware' => 'permissions:kitchen_access'], function($router) {
    $router->get('/', 'KitchenController@index');
    $router->get('/data', 'KitchenController@getKitchenData');
    $router->post('/start-preparing/{id}', 'KitchenController@startPreparing');
    $router->post('/mark-item-ready/{id}', 'KitchenController@markItemReady');
    $router->post('/mark-order-ready/{id}', 'KitchenController@markOrderReady');
    $router->post('/mark-served/{id}', 'KitchenController@markServed');
    $router->get('/metrics', 'KitchenController@metrics');
});

// Orders Management Routes
$router->group(['prefix' => 'orders', 'middleware' => 'permissions:orders_view'], function($router) {
    $router->get('/', 'OrderController@index');
    $router->get('/{id}', 'OrderController@show');
    $router->post('/{id}/status', 'OrderController@updateStatus');
    $router->post('/{id}/cancel', 'OrderController@cancel');
    $router->post('/statistics', 'OrderController@statistics');
});
```

### المميزات التقنية

#### 1. دعم العربية RTL
- تصميم متجاوب مع دعم كامل للعربية
- اتجاه النص من اليمين إلى اليسار
- خطوط عربية محسنة

#### 2. Real-time Updates
- تحديث لوحة المطبخ كل 30 ثانية
- إشعارات صوتية للطلبات الجديدة
- حالة الاتصال التلقائي

#### 3. User Experience
- واجهات سهلة الاستخدام
- اختصارات لوحة المفاتيح
- تأكيدات للعمليات المهمة
- رسائل واضحة للمستخدم

#### 4. Performance
- تحسين استعلامات قاعدة البيانات
- تحميل البيانات بشكل تدريجي
- حفظ حالة الجلسة

#### 5. Security
- حماية CSRF
- التحقق من الصلاحيات
- تنظيف المدخلات

### استخدام النظام

#### 1. نقطة البيع (POS)
1. الدخول على `/pos`
2. اختيار طاولة
3. إضافة المنتجات بالنقر عليها
4. تعديل الكميات حسب الحاجة
5. إضافة ملاحظات للطلب
6. إرسال الطلب للمطبخ

#### 2. لوحة المطبخ (KDS)
1. الدخول على `/kitchen`
2. مراجعة الطلبات الواردة
3. بدء تحضير الطلبات
4. تحديد العناصر الجاهزة
5. إشعار النادل عند اكتمال الطلب

#### 3. إدارة الطلبات
1. الدخول على `/orders`
2. فلترة الطلبات حسب الحالة والتاريخ
3. تغيير حالة الطلبات
4. إلغاء الطلبات
5. طباعة الفواتير
6. عرض الإحصائيات

### المرحلة القادمة
- نظام الفواتير والدفع
- إدارة المخزون
- التقارير المتقدمة
- نظام الخصومات والعروض

### المتطلبات التقنية
- PHP 8.2+
- MySQL 8.0+
- Modern web browser
- Bootstrap 5.3+
- Font Awesome 6.4+

### التثبيت والإعداد
1. تأكد من وجود قاعدة البيانات والجداول المطلوبة
2. تحقق من وجود ملفات CSS و JS في المسارات المحددة
3. تأكد من صلاحيات المجلدات
4. قم بتحديث routes.php
5. اختبر الواجهات المختلفة

تم إكمال المرحلة الثالثة بنجاح مع جميع المكونات المطلوبة!