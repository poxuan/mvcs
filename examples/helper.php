<?php

if(!function_exists('responseOK')) {
    function responseOK($data, $ret = true, $msg = 'ok') {
        return response()->json([
            'errcode' => 0,
            'errmsg' => $msg,
            'ret' => $ret,
            'data' => $data
        ]);
    }
}

if(!function_exists('responseErr')) {
    function responseErr(int $code, $msg = '', $ret = false, $data = []) {
        if (!$msg) {
            $msg = \App\Base\Code::defaultMsg($code);
        }
        $httpCode = intval($code/100);
        return response()->json([
            'errcode' => $code,
            'errmsg' => $msg,
            'ret' => $ret,
            'data' => $data
        ],in_array($httpCode,[200,400,401,403,404,405,408,500]) ? $httpCode : 500);
    }
}

if(!function_exists('signatureCheck')) {
    // 签名验证，返回0表示成功，其余表示错误码
    function signatureCheck(array $queries, $secret = null) {
        // 没有时间戳字段
        if (!isset($queries['timestamp'])) {
            return \App\Base\Code::LACK_OF_TIMESTAMP;
        }
        // 没有签名字段
        if (!isset($queries['signature'])) {
            return \App\Base\Code::LACK_OF_SIGN;
        }
        $signature = $queries['signature'];
        unset($queries['signature']);
        $now = time();
        // 时间戳不是数字，或与当前时间相差大于300秒
        if (!is_numeric($queries['timestamp']) || $now - $queries['timestamp'] > 300 || $queries['timestamp'] - $now > 300) {
            return \App\Base\Code::INVALID_TIMESTAMP;
        }
        ksort($queries);
        $str = '';
        foreach ($queries as $k => $v) {
            // _开头或signature字段不计入计算
            if($k!='signature' && $k[0] == '_') {
                $str .= '&' . $k . '=' . $v;
            }
        }
        $str = ltrim($str, '&');
        if ($secret) {
            $sign = hash_hmac('sha1', $str, $secret);
        } else {
            $sign = sha1($str);
        }
        if ($signature != $sign) {
            return \App\Base\Code::INVALID_SIGN;
        }
        return 0;
    }
}

if(!function_exists('signatureGenerate')) {
    // 生成签名
    function signatureGenerate(array & $queries,$secret = null) {
        if (!isset($queries['nonce'])) {
            $queries['nonce'] = uniqid() . uniqid();
        }
        if (!isset($queries['timestamp'])) {
            $queries['timestamp'] = time();
        }
        ksort($queries);
        $str = '';
        foreach ($queries as $k => $v) {
            // _开头或signature字段不计入计算
            if($k != 'signature' && $k[0] != '_' && !empty($v)) {
                $str .= '&' . $k . '=' . $v;
            }
        }
        $str = ltrim($str, '&');
        if($secret) {
            $sign = hash_hmac('sha1', $str, $secret);
        } else {
            $sign = sha1($str);
        }
        $queries['signature'] = $sign;
        return $sign;
    }
}

if(!function_exists('isWeixin')) {
    // 判断请求是否来自微信浏览器
    function isWeixin() {
        if (strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false) {
            return true;
        } return false;
    }
}

if(!function_exists('generateSignedUrl')) {
    // 判断请求是否来自微信浏览器
    function generateSignedUrl(array $queries,string $url,string $secret) {
        signatureGenerate($queries,$secret);
        $str = '';
        foreach ($queries as $k => $v) {
            $str .= '&' . $k . '=' . $v;
        }
        $str = ltrim($str, '&');
        return $url."?".$str;
    }
}

if(!function_exists('curlGet')) {
    function curlGet($url,$headers = [],$timeout = 30) {
        return curlPost($url,[],$headers,0,$timeout);
    }
}

if(!function_exists('curlPost')) {
    function curlPost($url, $params = [], $headers = [], $isPost = 1, $timeout = 30)
    {
        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $url);
        if ($isPost == 1) {
            curl_setopt($curl, CURLOPT_POST, 1);
        }
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        if ($isPost == 1) {
            if (is_array($params)) {
                curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($params));
            } else {
                curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
            }
        }

        $data = curl_exec($curl);

        if ($error = curl_error($curl)) {
            logger()->error('remote invoking failed',['url'=>$url,'params'=>$params,'error'=>$error]);
            curl_close($curl);
            throw new \App\Exceptions\RemoteInvokeException();
        }
        curl_close($curl);
        $json = json_decode($data, true);
        return is_array($json) ? $json : $data;
    }
}

