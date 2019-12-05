<?php

namespace App\Base\Traits;



trait Response 
{
    protected $response = [
        'errcode' => 0,
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
    protected function success($data = [])
    {
        $this->response['data'] = $data;
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
        $this->response = compact('errcode', 'errmsg', 'ret', 'data');
        return response()->json($this->response);
    }
}