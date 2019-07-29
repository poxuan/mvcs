<?php

namespace App\Exceptions;

use Exception;

/**
 * 第三方请求异常
 * Class RemoteInvokeException
 * @package App\Exceptions
 */
class RemoteInvokeException extends Exception
{
    //
    protected $code = 50031;
    protected $message = "";
}
