<?php

namespace App\Base;

class Code
{
    const OK = 0;
    const BED_REQUEST = 40000;
    const LACK_OF_PARAM = 40001;
    const INVALID_PARAM = 40002;
    const LACK_OF_QUERY = 40011;
    const INVALID_QUERY = 40012;
    const LACK_OF_FILE = 40021;
    const INVALID_FILE = 40022;
    const FILE_TOO_LARGE = 40099;
    const NO_ACCESS = 40100;
    const LACK_OF_GRANT = 40101;
    const INVALID_GRANT = 40102;
    const LACK_OF_SIGN = 40103;
    const INVALID_SIGN = 40104;
    const LACK_OF_APPKEY = 40105;
    const INVALID_APPKEY = 40106;
    const GRANT_OVERDUE = 40107;
    const INVALID_SESSION = 40108;
    const INVALID_REFERRER = 40110;
    const PERMISSION_DENY = 40300;
    const NOT_FOUND = 40400;
    const OBSOLETED_RESOURCE = 40401;
    const METHOD_NOT_ALLOWED = 40500;
    const TIME_OUT = 40800;
    const LACK_OF_TIMESTAMP = 40801;
    const INVALID_TIMESTAMP = 40802;
    const RESET_MAYBE = 42900;
    const SIGN_DUPLICATE = 42901;
    const FATAL_ERROR = 50000;
    const REMOTE_FAILED = 50031;


    public static function defaultMsg($code)
    {
        $msg = [
            Code::OK => 'ok',//成功
            Code::BED_REQUEST => 'data parsing error', // 请求异常
            Code::LACK_OF_PARAM => 'lack of parameter', // 缺少参数
            Code::INVALID_PARAM => 'invalid parameter', // 非法参数
            Code::LACK_OF_QUERY => 'lack of query', // 缺少查询条件
            Code::INVALID_QUERY => 'invalid query', // 非法查询条件
            Code::LACK_OF_FILE => 'lack of file', // 缺少上传文件
            Code::INVALID_FILE => 'invalid file', //非法文件
            Code::FILE_TOO_LARGE => 'payload too large',// 文件大小超出限制
            Code::NO_ACCESS => 'no access', //无法访问
            Code::LACK_OF_GRANT => 'lack of grant', //缺少授权信息
            Code::INVALID_GRANT => 'invalid grant', //非法授权信息
            Code::LACK_OF_SIGN => 'lack of signature', //非法请求签名
            Code::INVALID_SIGN => 'invalid signature', //非法请求签名
            Code::GRANT_OVERDUE => 'grant has over time',
            Code::LACK_OF_APPKEY => 'lack of app key', //缺少应用标识
            Code::INVALID_APPKEY => 'invalid app key', //非法应用标识
            Code::INVALID_SESSION => 'session expired',
            Code::INVALID_REFERRER => 'hotlinking or referrer not in whitelist', //非法请求来源
            Code::PERMISSION_DENY => 'permission required', //权限不足
            Code::NOT_FOUND => 'non-exists resourse', //不存在的资源
            Code::OBSOLETED_RESOURCE => 'obsoleted resourse', //已过期的资源
            Code::METHOD_NOT_ALLOWED => 'invalid method or no route matched',//路由错误
            Code::TIME_OUT => 'timeout or timestamp expired',//请求超时
            Code::LACK_OF_TIMESTAMP => 'lack of timestamp',//缺少时间戳
            Code::INVALID_TIMESTAMP => 'invalid timestamp',//非法时间戳
            Code::RESET_MAYBE => 'suspected replay attack',//疑似重放攻击
            Code::SIGN_DUPLICATE => 'duplicate signature',//请求签名冲突
            Code::FATAL_ERROR => 'fatal error',//致命错误
            Code::REMOTE_FAILED => 'remote invoking failed',//远程调用失败
        ];
        return $msg[$code] ?? 'system error';
    }
}