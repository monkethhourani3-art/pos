<?php
/**
 * Language Middleware
 * Restaurant POS System
 */

namespace App\Http\Middleware;

use App\Http\Request;
use App\Support\Facades\Session;

class LanguageMiddleware
{
    /**
     * Handle the request
     */
    public function handle(Request $request)
    {
        // Get locale from session or cookie
        $locale = Session::get('locale') ?: $_COOKIE['locale'] ?? 'ar';
        
        // Validate locale
        $supportedLocales = ['ar', 'en'];
        if (!in_array($locale, $supportedLocales)) {
            $locale = 'ar';
        }
        
        // Set locale in session
        Session::set('locale', $locale);
        
        // Set PHP locale if available
        if ($locale === 'ar') {
            setlocale(LC_TIME, 'ar_IQ.UTF-8', 'ar_IQ', 'ar', 'ar_SA.UTF-8');
        } else {
            setlocale(LC_TIME, 'en_US.UTF-8', 'en_US', 'en');
        }
        
        // Set locale for the application
        $GLOBALS['app_locale'] = $locale;
        
        return true;
    }

    /**
     * Get current locale
     */
    public static function getLocale()
    {
        return $GLOBALS['app_locale'] ?? Session::get('locale', 'ar');
    }

    /**
     * Check if current locale is RTL
     */
    public static function isRtl()
    {
        $locale = self::getLocale();
        $rtlLocales = ['ar', 'he', 'fa', 'ur'];
        return in_array($locale, $rtlLocales);
    }

    /**
     * Get direction for current locale
     */
    public static function getDirection()
    {
        return self::isRtl() ? 'rtl' : 'ltr';
    }

    /**
     * Get text direction attribute
     */
    public static function getTextDirection()
    {
        return self::isRtl() ? 'dir="rtl"' : 'dir="ltr"';
    }
}