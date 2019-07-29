<?php

namespace App\Exceptions;

use Exception;

/**
 * 自定义异常
 * Class CustomException
 * @package App\Exceptions
 */
class CustomException extends Exception
{
    // 默认错误号
    protected $code = 40000;
    protected $message = "";
}
