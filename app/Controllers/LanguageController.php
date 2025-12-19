<?php
/**
 * Language Controller
 * Restaurant POS System
 */

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Support\Facades\Session;
use App\Support\Facades\Log;

class LanguageController
{
    /**
     * Switch language
     */
    public function switch(Request $request)
    {
        try {
            $locale = $request->post('locale', 'ar');
            
            // Validate locale
            $supportedLocales = ['ar', 'en'];
            if (!in_array($locale, $supportedLocales)) {
                return Response::json(['error' => 'لغة غير مدعومة'], 400);
            }
            
            // Store in session
            Session::set('locale', $locale);
            
            // Set cookie for persistence
            setcookie('locale', $locale, time() + (30 * 24 * 60 * 60), '/', '', false, true);
            
            // Log language switch
            Log::system('language_switched', [
                'locale' => $locale,
                'ip' => $request->ip()
            ]);
            
            // Return appropriate response
            if ($request->isAjax()) {
                return Response::json([
                    'success' => true,
                    'locale' => $locale,
                    'message' => $locale === 'ar' ? 'تم التبديل إلى العربية' : 'Switched to English'
                ]);
            } else {
                // Redirect back or to home
                $redirectUrl = $request->post('redirect', '/');
                return redirect($redirectUrl);
            }
            
        } catch (\Exception $e) {
            Log::error('Language switch error: ' . $e->getMessage());
            
            if ($request->isAjax()) {
                return Response::json(['error' => 'حدث خطأ'], 500);
            } else {
                return redirect('/');
            }
        }
    }

    /**
     * Get current language
     */
    public function current(Request $request)
    {
        $locale = Session::get('locale', 'ar');
        
        if ($request->isAjax()) {
            return Response::json([
                'locale' => $locale,
                'is_rtl' => $this->isRtl($locale)
            ]);
        }
        
        return $locale;
    }

    /**
     * Check if locale is RTL
     */
    protected function isRtl($locale)
    {
        $rtlLocales = ['ar', 'he', 'fa', 'ur'];
        return in_array($locale, $rtlLocales);
    }

    /**
     * Get language list
     */
    public function list(Request $request)
    {
        $languages = [
            'ar' => [
                'name' => 'العربية',
                'native_name' => 'العربية',
                'flag' => '🇮🇶',
                'direction' => 'rtl'
            ],
            'en' => [
                'name' => 'English',
                'native_name' => 'English',
                'flag' => '🇺🇸',
                'direction' => 'ltr'
            ]
        ];
        
        $currentLocale = Session::get('locale', 'ar');
        
        $result = [];
        foreach ($languages as $code => $lang) {
            $result[] = array_merge($lang, [
                'code' => $code,
                'is_current' => $code === $currentLocale
            ]);
        }
        
        if ($request->isAjax()) {
            return Response::json($result);
        }
        
        return $result;
    }

    /**
     * Set language for specific user session
     */
    public function setForUser(Request $request, $userId = null)
    {
        // This would be used for admin to set language for specific users
        // Implementation would depend on requirements
        
        return Response::json(['error' => 'Feature not implemented yet'], 501);
    }

    /**
     * Get translations for current language (AJAX)
     */
    public function translations(Request $request)
    {
        $locale = Session::get('locale', 'ar');
        
        // Basic translations for the interface
        $translations = [
            'ar' => [
                'login' => 'تسجيل الدخول',
                'logout' => 'تسجيل الخروج',
                'dashboard' => 'لوحة التحكم',
                'products' => 'المنتجات',
                'categories' => 'الفئات',
                'orders' => 'الطلبات',
                'reports' => 'التقارير',
                'settings' => 'الإعدادات',
                'save' => 'حفظ',
                'cancel' => 'إلغاء',
                'delete' => 'حذف',
                'edit' => 'تعديل',
                'add' => 'إضافة',
                'search' => 'بحث',
                'filter' => 'تصفية',
                'export' => 'تصدير',
                'print' => 'طباعة',
                'yes' => 'نعم',
                'no' => 'لا',
                'confirm' => 'تأكيد',
                'error' => 'خطأ',
                'success' => 'نجح',
                'warning' => 'تحذير',
                'info' => 'معلومات',
                'loading' => 'جاري التحميل...',
                'no_data' => 'لا توجد بيانات',
                'welcome' => 'مرحباً',
                'logout_confirm' => 'هل أنت متأكد من تسجيل الخروج؟',
                'delete_confirm' => 'هل أنت متأكد من الحذف؟',
                'action_not_permitted' => 'ليس لديك صلاحية لهذا الإجراء'
            ],
            'en' => [
                'login' => 'Login',
                'logout' => 'Logout',
                'dashboard' => 'Dashboard',
                'products' => 'Products',
                'categories' => 'Categories',
                'orders' => 'Orders',
                'reports' => 'Reports',
                'settings' => 'Settings',
                'save' => 'Save',
                'cancel' => 'Cancel',
                'delete' => 'Delete',
                'edit' => 'Edit',
                'add' => 'Add',
                'search' => 'Search',
                'filter' => 'Filter',
                'export' => 'Export',
                'print' => 'Print',
                'yes' => 'Yes',
                'no' => 'No',
                'confirm' => 'Confirm',
                'error' => 'Error',
                'success' => 'Success',
                'warning' => 'Warning',
                'info' => 'Info',
                'loading' => 'Loading...',
                'no_data' => 'No data available',
                'welcome' => 'Welcome',
                'logout_confirm' => 'Are you sure you want to logout?',
                'delete_confirm' => 'Are you sure you want to delete?',
                'action_not_permitted' => 'You do not have permission for this action'
            ]
        ];
        
        return Response::json($translations[$locale] ?? $translations['ar']);
    }
}