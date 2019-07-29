<?php

namespace App\Exceptions;

use App\Base\Code;
use Exception;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    /**
     * Report or log an exception.
     *
     * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
     *
     * @param  \Exception  $exception
     * @return void
     */
    public function report(Exception $exception)
    {
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Exception  $exception
     * @return \Illuminate\Http\Response
     */
    public function render($request, Exception $exception)
    {
        // 404 相关问题
        if ($exception instanceof NotFoundHttpException || $exception instanceof MethodNotAllowedHttpException) {
            return responseErr(Code::NOT_FOUND);
        }
        // 参数验证问题
        if($exception instanceof ValidationException) {
            $msgs = $exception->errors();
            $msg  = array_shift($msgs)[0] ?? $exception->getMessage();
            return responseErr(Code::BED_REQUEST,$msg);
        }
        // 错误信息
        $errcode = intval($exception->getCode()) ?: Code::FATAL_ERROR;
        $errmsg  = $exception->getMessage() ?: Code::defaultMsg($errcode);
        // 自定义异常
        if($exception instanceof CustomException || $exception instanceof RemoteInvokeException) {
            return responseErr($errcode, $errmsg,false,$request->all());
        }
        // 不明异常,记入日志
        logger()->error($exception->getMessage(),$request->all());
        
        return responseErr($errcode, $errmsg,false,$request->all());
    }
}
