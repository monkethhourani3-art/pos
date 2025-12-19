<?php
/**
 * HTTP Exception Class
 * Restaurant POS System
 */

namespace App\Exceptions;

class HttpException extends \Exception
{
    protected $statusCode;

    public function __construct($statusCode = 500, $message = null, $code = 0, $previous = null)
    {
        $this->statusCode = $statusCode;
        
        if ($message === null) {
            $message = $this->getDefaultMessage($statusCode);
        }
        
        parent::__construct($message, $code, $previous);
    }

    protected function getDefaultMessage($statusCode)
    {
        $messages = [
            400 => 'طلب غير صحيح',
            401 => 'غير مصرح',
            403 => 'ممنوع',
            404 => 'غير موجود',
            405 => 'طريقة غير مدعومة',
            422 => 'بيانات غير صالحة',
            500 => 'خطأ في الخادم',
            502 => 'خطأ في البوابة',
            503 => 'الخدمة غير متاحة'
        ];
        
        return $messages[$statusCode] ?? 'خطأ غير معروف';
    }

    public function getStatusCode()
    {
        return $this->statusCode;
    }
}