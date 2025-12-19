<?php
/**
 * Application Routes
 * Restaurant POS System
 */

use App\Http\Request;
use App\Http\Response;

// Get router from application
$router = app()->getRouter();

// ================================================
// PUBLIC ROUTES (No Authentication Required)
// ================================================

// Homepage and Landing
$router->get('/', function() {
    if (auth()->check()) {
        return redirect('/dashboard');
    }
    return Response::view('auth.login');
});

$router->get('/login', function() {
    if (auth()->check()) {
        return redirect('/dashboard');
    }
    return Response::view('auth.login');
});

$router->post('/login', 'AuthController@login');
$router->post('/logout', 'AuthController@logout');

// Language switching
$router->post('/language', 'LanguageController@switch');

// ================================================
// AUTHENTICATED ROUTES
// ================================================

$router->group(['middleware' => ['auth', 'language']], function($router) {
    
    // Dashboard
    $router->get('/dashboard', 'DashboardController@index');
    
    // ================================================
    // USER MANAGEMENT
    // ================================================
    $router->group(['prefix' => 'users', 'middleware' => 'permissions:users.manage'], function($router) {
        $router->get('/', 'UserController@index');
        $router->get('/create', 'UserController@create');
        $router->post('/', 'UserController@store');
        $router->get('/{id}', 'UserController@show');
        $router->get('/{id}/edit', 'UserController@edit');
        $router->put('/{id}', 'UserController@update');
        $router->delete('/{id}', 'UserController@destroy');
    });
    
    // ================================================
    // PRODUCTS AND MENU MANAGEMENT
    // ================================================
    $router->group(['prefix' => 'products', 'middleware' => 'permissions:products.manage'], function($router) {
        $router->get('/', 'ProductController@index');
        $router->get('/create', 'ProductController@create');
        $router->post('/', 'ProductController@store');
        $router->get('/{id}', 'ProductController@show');
        $router->get('/{id}/edit', 'ProductController@edit');
        $router->put('/{id}', 'ProductController@update');
        $router->delete('/{id}', 'ProductController@destroy');
        $router->post('/{id}/toggle-availability', 'ProductController@toggleAvailability');
        $router->get('/api/search', 'ProductController@search');
    });
    
    // Categories
    $router->group(['prefix' => 'categories', 'middleware' => 'permissions:products.manage'], function($router) {
        $router->get('/', 'CategoryController@index');
        $router->post('/', 'CategoryController@store');
        $router->put('/{id}', 'CategoryController@update');
        $router->delete('/{id}', 'CategoryController@destroy');
        $router->post('/reorder', 'CategoryController@reorder');
    });
    
    // ================================================
    // RESTAURANT MANAGEMENT
    // ================================================
    $router->group(['prefix' => 'restaurant', 'middleware' => 'permissions:products.manage'], function($router) {
        
        // Tables
        $router->group(['prefix' => 'tables'], function($router) {
            $router->get('/', 'TableController@index');
            $router->post('/', 'TableController@store');
            $router->put('/{id}', 'TableController@update');
            $router->delete('/{id}', 'TableController@destroy');
            $router->post('/{id}/status', 'TableController@updateStatus');
        });
        
        // Areas
        $router->group(['prefix' => 'areas'], function($router) {
            $router->get('/', 'AreaController@index');
            $router->post('/', 'AreaController@store');
            $router->put('/{id}', 'AreaController@update');
            $router->delete('/{id}', 'AreaController@destroy');
        });
    });
    
    // ================================================
    // POINT OF SALE (POS)
    // ================================================
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
    
    // ================================================
    // KITCHEN DISPLAY SYSTEM (KDS)
    // ================================================
    $router->group(['prefix' => 'kitchen', 'middleware' => 'permissions:kitchen_access'], function($router) {
        $router->get('/', 'KitchenController@index');
        $router->get('/data', 'KitchenController@getKitchenData');
        $router->post('/start-preparing/{id}', 'KitchenController@startPreparing');
        $router->post('/mark-item-ready/{id}', 'KitchenController@markItemReady');
        $router->post('/mark-order-ready/{id}', 'KitchenController@markOrderReady');
        $router->post('/mark-served/{id}', 'KitchenController@markServed');
        $router->post('/add-item-notes/{id}', 'KitchenController@addItemNotes');
        $router->post('/report-issue/{id}', 'KitchenController@reportIssue');
        $router->get('/metrics', 'KitchenController@metrics');
        $router->get('/ready-orders', 'KitchenController@getReadyOrders');
        $router->get('/queue-status', 'KitchenController@getQueueStatus');
    });
    
    // ================================================
    // ORDERS MANAGEMENT
    // ================================================
    $router->group(['prefix' => 'orders', 'middleware' => 'permissions:orders_view'], function($router) {
        $router->get('/', 'OrderController@index');
        $router->get('/{id}', 'OrderController@show');
        
        // Order status management
        $router->post('/{id}/status', 'OrderController@updateStatus');
        $router->post('/{id}/cancel', 'OrderController@cancel');
        
        // Order operations
        $router->post('/merge', 'OrderController@merge');
        $router->post('/{id}/split', 'OrderController@split');
        $router->get('/{id}/print', 'OrderController@printReceipt');
        
        // Statistics
        $router->post('/statistics', 'OrderController@statistics');
        $router->get('/by-status', 'OrderController@getByStatus');
        
        // Bulk operations
        $router->post('/bulk-cancel', 'OrderController@bulkCancel');
        $router->post('/bulk-print', 'OrderController@bulkPrint');
    });
    
    // ================================================
    // SHIFTS MANAGEMENT
    // ================================================
    $router->group(['prefix' => 'shifts', 'middleware' => 'permissions:shifts.manage'], function($router) {
        $router->get('/', 'ShiftController@index');
        $router->get('/current', 'ShiftController@current');
        $router->post('/open', 'ShiftController@open');
        $router->post('/close', 'ShiftController@close');
        $router->get('/{id}', 'ShiftController@show');
        $router->post('/{id}/cash-movement', 'ShiftController@cashMovement');
    });

    // ================================================
    // INVOICES MANAGEMENT
    // ================================================
    $router->group(['prefix' => 'invoices', 'middleware' => 'permissions:invoices.view'], function($router) {
        $router->get('/', 'InvoiceController@index');
        $router->get('/create', 'InvoiceController@create');
        $router->post('/', 'InvoiceController@store');
        $router->get('/{id}', 'InvoiceController@show');
        $router->get('/{id}/edit', 'InvoiceController@edit');
        $router->put('/{id}', 'InvoiceController@update');
        $router->post('/{id}/cancel', 'InvoiceController@cancel');
        $router->get('/{id}/print', 'InvoiceController@print');
        $router->post('/{id}/email', 'InvoiceController@sendEmail');
        $router->get('/{id}/pdf', 'InvoiceController@generatePdf');
        $router->post('/export', 'InvoiceController@export');
        $router->get('/statistics', 'InvoiceController@statistics');
        $router->get('/overdue', 'InvoiceController@getOverdue');
        $router->get('/search', 'InvoiceController@search');
        $router->post('/{id}/duplicate', 'InvoiceController@duplicate');
        
        // Bulk operations
        $router->post('/bulk-print', 'InvoiceController@bulkPrint');
        $router->post('/bulk-email', 'InvoiceController@bulkEmail');
        $router->post('/bulk-export', 'InvoiceController@bulkExport');
    });

    // ================================================
    // PAYMENTS MANAGEMENT
    // ================================================
    $router->group(['prefix' => 'payment', 'middleware' => 'permissions:payments.process'], function($router) {
        // Payment processing
        $router->get('/{orderId}', 'PaymentController@index');
        $router->post('/process/{orderId}', 'PaymentController@processPayment');
        
        // Payment operations
        $router->post('/refund/{transactionId}', 'PaymentController@refund');
        $router->post('/split/{orderId}', 'PaymentController@splitPayment');
        $router->post('/discount/{orderId}', 'PaymentController@applyDiscount');
        
        // Payment history and receipts
        $router->get('/history/{orderId}', 'PaymentController@getPaymentHistory');
        $router->get('/print-receipt/{transactionId}', 'PaymentController@printReceipt');
        $router->get('/transaction/{transactionId}', 'PaymentController@getTransactionDetails');
        $router->get('/stats', 'PaymentController@getPaymentStats');
    });

    // ================================================
    // INVENTORY MANAGEMENT
    // ================================================
    $router->group(['prefix' => 'inventory', 'middleware' => 'permissions:inventory.view'], function($router) {
        // Inventory items
        $router->get('/', 'InventoryController@index');
        $router->get('/create', 'InventoryController@create');
        $router->post('/', 'InventoryController@store');
        $router->get('/{id}', 'InventoryController@show');
        $router->get('/{id}/edit', 'InventoryController@edit');
        $router->put('/{id}', 'InventoryController@update');
        $router->delete('/{id}', 'InventoryController@destroy');
        $router->post('/{id}/quantity', 'InventoryController@updateQuantity');
        
        // Inventory operations
        $router->get('/low-stock', 'InventoryController@lowStock');
        $router->get('/expiring', 'InventoryController@expiring');
        $router->get('/statistics', 'InventoryController@statistics');
        $router->get('/search', 'InventoryController@search');
        $router->post('/export', 'InventoryController@export');
        
        // Inventory movements
        $router->get('/{id}/movements', 'InventoryController@getMovements');
    });

    // ================================================
    // SUPPLIERS MANAGEMENT
    // ================================================
    $router->group(['prefix' => 'suppliers', 'middleware' => 'permissions:suppliers.view'], function($router) {
        $router->get('/', 'SupplierController@index');
        $router->get('/create', 'SupplierController@create');
        $router->post('/', 'SupplierController@store');
        $router->get('/{id}', 'SupplierController@show');
        $router->get('/{id}/edit', 'SupplierController@edit');
        $router->put('/{id}', 'SupplierController@update');
        $router->delete('/{id}', 'SupplierController@destroy');
        $router->post('/{id}/status', 'SupplierController@updateStatus');
        $router->get('/search', 'SupplierController@search');
        $router->get('/{id}/performance', 'SupplierController@performance');
        $router->get('/top-suppliers', 'SupplierController@topSuppliers');
        $router->post('/export', 'SupplierController@export');
    });

    // ================================================
    // PURCHASES MANAGEMENT
    // ================================================
    $router->group(['prefix' => 'purchases', 'middleware' => 'permissions:purchases.view'], function($router) {
        $router->get('/', 'PurchaseController@index');
        $router->get('/create', 'PurchaseController@create');
        $router->post('/', 'PurchaseController@store');
        $router->get('/{id}', 'PurchaseController@show');
        $router->get('/{id}/edit', 'PurchaseController@edit');
        $router->put('/{id}', 'PurchaseController@update');
        $router->delete('/{id}', 'PurchaseController@destroy');
        $router->post('/{id}/status', 'PurchaseController@updateStatus');
        $router->get('/statistics', 'PurchaseController@statistics');
        $router->get('/search', 'PurchaseController@search');
        $router->get('/supplier/{supplierId}', 'PurchaseController@bySupplier');
        $router->post('/export', 'PurchaseController@export');
    });

    // ================================================
    // UNITS MANAGEMENT
    // ================================================
    $router->group(['prefix' => 'units', 'middleware' => 'permissions:units.manage'], function($router) {
        $router->get('/', 'UnitController@index');
        $router->get('/create', 'UnitController@create');
        $router->post('/', 'UnitController@store');
        $router->get('/{id}', 'UnitController@show');
        $router->get('/{id}/edit', 'UnitController@edit');
        $router->put('/{id}', 'UnitController@update');
        $router->delete('/{id}', 'UnitController@destroy');
        $router->post('/{id}/status', 'UnitController@updateStatus');
        $router->get('/search', 'UnitController@search');
        $router->get('/common', 'UnitController@common');
        $router->get('/hierarchy', 'UnitController@hierarchy');
        $router->post('/convert', 'UnitController@convert');
        $router->post('/initialize-default', 'UnitController@initializeDefault');
        $router->post('/export', 'UnitController@export');
    });
    
    // ================================================
    // REPORTS
    // ================================================
    $router->group(['prefix' => 'reports', 'middleware' => 'permissions:reports.view'], function($router) {
        $router->get('/', 'ReportController@index');
        $router->get('/sales', 'ReportController@sales');
        $router->get('/products', 'ReportController@products');
        $router->get('/users', 'ReportController@users');
        $router->get('/shifts', 'ReportController@shifts');
        $router->get('/cash', 'ReportController@cash');
        $router->get('/tax', 'ReportController@tax');
        $router->post('/export', 'ReportController@export');
    });

    // ================================================
    // DISCOUNTS MANAGEMENT
    // ================================================
    $router->group(['prefix' => 'discounts', 'middleware' => 'permissions:discounts.manage'], function($router) {
        $router->get('/', 'DiscountController@index');
        $router->get('/create', 'DiscountController@create');
        $router->post('/', 'DiscountController@store');
        $router->get('/{id}', 'DiscountController@show');
        $router->get('/{id}/edit', 'DiscountController@edit');
        $router->put('/{id}', 'DiscountController@update');
        $router->delete('/{id}', 'DiscountController@destroy');
        $router->post('/{id}/toggle-status', 'DiscountController@toggleStatus');
        $router->post('/validate', 'DiscountController@validate');
        $router->get('/available', 'DiscountController@getAvailable');
        $router->post('/{id}/extend', 'DiscountController@extend');
        $router->post('/export', 'DiscountController@export');
        $router->get('/search', 'DiscountController@search');
    });

    // ================================================
    // PROMOTIONS MANAGEMENT
    // ================================================
    $router->group(['prefix' => 'promotions', 'middleware' => 'permissions:promotions.manage'], function($router) {
        $router->get('/', 'PromotionController@index');
        $router->get('/create', 'PromotionController@create');
        $router->post('/', 'PromotionController@store');
        $router->get('/{id}', 'PromotionController@show');
        $router->get('/{id}/edit', 'PromotionController@edit');
        $router->put('/{id}', 'PromotionController@update');
        $router->delete('/{id}', 'PromotionController@destroy');
        $router->post('/{id}/toggle-status', 'PromotionController@toggleStatus');
        $router->post('/{id}/priority', 'PromotionController@updatePriority');
        $router->post('/validate', 'PromotionController@validate');
        $router->get('/active', 'PromotionController@getActive');
        $router->post('/{id}/duplicate', 'PromotionController@duplicate');
        $router->post('/{id}/record-usage', 'PromotionController@recordUsage');
        $router->post('/export', 'PromotionController@export');
        $router->get('/search', 'PromotionController@search');
    });

    // ================================================
    // LOYALTY PROGRAM
    // ================================================
    $router->group(['prefix' => 'loyalty', 'middleware' => 'permissions:loyalty.manage'], function($router) {
        $router->get('/customers', 'LoyaltyController@customers');
        $router->get('/customers/{id}', 'LoyaltyController@customerDetails');
        $router->post('/customers', 'LoyaltyController@createCustomer');
        $router->post('/find-customer', 'LoyaltyController@findCustomer');
        $router->post('/add-points', 'LoyaltyController@addPoints');
        $router->post('/redeem-points', 'LoyaltyController@redeemPoints');
        $router->get('/rewards', 'LoyaltyController@rewards');
        $router->post('/redeem-reward', 'LoyaltyController@redeemReward');
        $router->post('/process-order', 'LoyaltyController@processOrder');
        $router->get('/statistics', 'LoyaltyController@statistics');
        $router->get('/top-customers', 'LoyaltyController@topCustomers');
        $router->get('/expiring-points', 'LoyaltyController@expiringPoints');
        $router->post('/extend-points', 'LoyaltyController@extendPoints');
        $router->post('/create-campaign', 'LoyaltyController@createCampaign');
        $router->post('/apply-campaign', 'LoyaltyController@applyCampaign');
        $router->post('/calculate-points', 'LoyaltyController@calculatePoints');
        $router->post('/export', 'LoyaltyController@export');
    });

    // ================================================
    // COUPONS MANAGEMENT
    // ================================================
    $router->group(['prefix' => 'coupons', 'middleware' => 'permissions:coupons.manage'], function($router) {
        $router->get('/', 'CouponController@index');
        $router->get('/create', 'CouponController@create');
        $router->post('/', 'CouponController@store');
        $router->get('/{id}', 'CouponController@show');
        $router->get('/{id}/edit', 'CouponController@edit');
        $router->put('/{id}', 'CouponController@update');
        $router->delete('/{id}', 'CouponController@destroy');
        $router->post('/{id}/toggle-status', 'CouponController@toggleStatus');
        $router->post('/validate', 'CouponController@validate');
        $router->get('/public', 'CouponController@getPublic');
        $router->post('/{id}/record-usage', 'CouponController@recordUsage');
        $router->post('/{id}/duplicate', 'CouponController@duplicate');
        $router->post('/{id}/extend', 'CouponController@extend');
        $router->get('/{id}/usage-history', 'CouponController@usageHistory');
        $router->get('/{id}/detailed-stats', 'CouponController@detailedStats');
        $router->post('/export', 'CouponController@export');
        $router->get('/search', 'CouponController@search');
    });
    
    // ================================================
    // SETTINGS
    // ================================================
    $router->group(['prefix' => 'settings', 'middleware' => 'permissions:settings.manage'], function($router) {
        $router->get('/', 'SettingController@index');
        $router->put('/', 'SettingController@update');
        $router->get('/general', 'SettingController@general');
        $router->put('/general', 'SettingController@updateGeneral');
        $router->get('/restaurant', 'SettingController@restaurant');
        $router->put('/restaurant', 'SettingController@updateRestaurant');
        $router->get('/printers', 'SettingController@printers');
        $router->put('/printers', 'SettingController@updatePrinters');
        $router->post('/backup', 'SettingController@backup');
        $router->get('/backup', 'SettingController@downloadBackup');
    });
    
    // ================================================
    // API ENDPOINTS
    // ================================================
    $router->group(['prefix' => 'api'], function($router) {
        
        // Orders API
        $router->group(['prefix' => 'orders'], function($router) {
            $router->get('/', 'ApiController@orders');
            $router->get('/active', 'ApiController@activeOrders');
            $router->get('/kitchen', 'ApiController@kitchenOrders');
            $router->get('/table/{tableId}', 'ApiController@tableOrders');
        });
        
        // Products API
        $router->group(['prefix' => 'products'], function($router) {
            $router->get('/', 'ApiController@products');
            $router->get('/categories', 'ApiController@categories');
            $router->get('/search', 'ApiController@searchProducts');
        });
        
        // Tables API
        $router->group(['prefix' => 'tables'], function($router) {
            $router->get('/', 'ApiController@tables');
            $router->get('/status', 'ApiController@tableStatus');
        });
        
        // Stats API
        $router->get('/stats/dashboard', 'ApiController@dashboardStats');
        $router->get('/stats/sales', 'ApiController@salesStats');
        
        // Invoices API
        $router->group(['prefix' => 'invoices'], function($router) {
            $router->get('/', 'ApiController@invoices');
            $router->get('/{id}', 'ApiController@invoiceDetails');
            $router->get('/statistics', 'ApiController@invoiceStats');
            $router->get('/overdue', 'ApiController@overdueInvoices');
        });
        
        // Payments API
        $router->group(['prefix' => 'payments'], function($router) {
            $router->get('/', 'ApiController@payments');
            $router->get('/methods', 'ApiController@paymentMethods');
            $router->get('/order/{orderId}', 'ApiController@orderPayments');
            $router->get('/statistics', 'ApiController@paymentStats');
            $router->get('/transactions', 'ApiController@paymentTransactions');
        });
        
        // Orders API (additional endpoints)
        $router->group(['prefix' => 'orders'], function($router) {
            $router->get('/{id}/invoice', 'ApiController@orderInvoice');
            $router->get('/{id}/payments', 'ApiController@orderPaymentHistory');
        });
        
        // Inventory API
        $router->group(['prefix' => 'inventory'], function($router) {
            $router->get('/', 'ApiController@inventory');
            $router->get('/{id}', 'ApiController@inventoryItem');
            $router->get('/low-stock', 'ApiController@lowStockItems');
            $router->get('/expiring', 'ApiController@expiringItems');
            $router->get('/statistics', 'ApiController@inventoryStats');
            $router->get('/valuation', 'ApiController@inventoryValuation');
            $router->get('/movements/{id}', 'ApiController@inventoryMovements');
        });
        
        // Suppliers API
        $router->group(['prefix' => 'suppliers'], function($router) {
            $router->get('/', 'ApiController@suppliers');
            $router->get('/{id}', 'ApiController@supplier');
            $router->get('/{id}/purchases', 'ApiController@supplierPurchases');
            $router->get('/{id}/performance', 'ApiController@supplierPerformance');
            $router->get('/top', 'ApiController@topSuppliers');
        });
        
        // Purchases API
        $router->group(['prefix' => 'purchases'], function($router) {
            $router->get('/', 'ApiController@purchases');
            $router->get('/{id}', 'ApiController@purchase');
            $router->get('/statistics', 'ApiController@purchaseStats');
            $router->get('/monthly', 'ApiController@monthlyPurchases');
        });
        
        // Units API
        $router->group(['prefix' => 'units'], function($router) {
            $router->get('/', 'ApiController@units');
            $router->get('/active', 'ApiController@activeUnits');
            $router->get('/common', 'ApiController@commonUnits');
            $router->get('/hierarchy', 'ApiController@unitHierarchy');
            $router->post('/convert', 'ApiController@convertUnit');
        });
        
        // Reports API
        $router->group(['prefix' => 'reports'], function($router) {
            $router->get('/overview', 'ApiController@reportsOverview');
            $router->get('/sales', 'ApiController@reportsSales');
            $router->get('/products', 'ApiController@reportsProducts');
            $router->get('/users', 'ApiController@reportsUsers');
            $router->get('/shifts', 'ApiController@reportsShifts');
            $router->get('/cash', 'ApiController@reportsCash');
            $router->get('/tax', 'ApiController@reportsTax');
            $router->post('/export', 'ApiController@exportReports');
            $router->post('/export/{type}', 'ApiController@exportReportData');
        });
    });
    
    // ================================================
    // UTILITY ROUTES
    // ================================================
    
    // Profile
    $router->get('/profile', 'ProfileController@show');
    $router->put('/profile', 'ProfileController@update');
    $router->post('/profile/password', 'ProfileController@changePassword');
    
    // Notifications
    $router->get('/notifications', 'NotificationController@index');
    $router->post('/notifications/{id}/read', 'NotificationController@markAsRead');
    
    // Search
    $router->get('/search', 'SearchController@index');
    
    // Help
    $router->get('/help', 'HelpController@index');
});

// ================================================
// ERROR HANDLING ROUTES
// ================================================

// 404 Not Found
$router->any('/{path}', function($path) {
    return Response::error('الصفحة غير موجودة: ' . $path, 404);
})->where('path', '.*');

// ================================================
// WEBHOOK AND EXTERNAL API ROUTES
// ================================================

// Payment webhooks
$router->post('/webhooks/payment', 'WebhookController@payment');

// System health check
$router->get('/health', 'HealthController@check');