<?php
/**
 * CSRF Token Exception Class
 * Restaurant POS System
 */

namespace App\Exceptions;

class CsrfTokenException extends HttpException
{
    public function __construct($message = null, $code = 0, $previous = null)
    {
        parent::__construct(419, $message, $code, $previous);
    }
}