<?php

namespace Callmecsx\Mvcs\Traits;

trait Response 
{
    protected $response = [
        'errcode' => 200,
        'errmsg'  => 'success!',
        'ret'     => true,
        'data'    => []
    ];

    /**
     * 正确返回
     *
     * @param array $data
     * @return void
     * @author chentengfei
     * @since
     */
    protected function success($data = [], $extra = [])
    {
        $this->response['data'] = $data;
        foreach($extra as $key => $val) {
            $this->response[$key] = $val;
        }
        return response()->json($this->response);
    }
    
    /**
     * 错误返回
     *
     * @param integer $errcode
     * @param string $errmsg
     * @param boolean $ret
     * @param array $data
     * @return void
     * @author chentengfei
     * @since
     */
    protected function error($errcode , $errmsg = 'error', $ret = false, $data = [])
    {
        $responseCode = intval($errcode/100);
        $responseCode = in_array($responseCode, [400,401,403,404,500,502]) ? $responseCode : 400;
        http_response_code($responseCode);
        $this->response = compact('errcode', 'errmsg', 'ret', 'data');
        return response()->json($this->response);
    }
}