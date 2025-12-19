<?php
/**
 * Unauthorized Exception Class
 * Restaurant POS System
 */

namespace App\Exceptions;

class UnauthorizedException extends HttpException
{
    public function __construct($message = null, $code = 0, $previous = null)
    {
        parent::__construct(401, $message, $code, $previous);
    }
}