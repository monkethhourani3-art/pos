<?php
/**
 * Forbidden Exception Class
 * Restaurant POS System
 */

namespace App\Exceptions;

class ForbiddenException extends HttpException
{
    public function __construct($message = null, $code = 0, $previous = null)
    {
        parent::__construct(403, $message, $code, $previous);
    }
}