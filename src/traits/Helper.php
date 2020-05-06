<?php

namespace Callmecsx\Mvcs\Traits;

use Illuminate\Support\Facades\Config;

trait Helper 
{
    // tab符
    public $spaces = '    ';
    /*
     * 驼峰转下划线
     */
    public function humpToLine($str)
    {
        $str = preg_replace_callback('/([A-Z]{1})/', function ($matches) {
            return '_' . strtolower($matches[0]);
        }, $str);
        return $str[0] == '_' ? substr($str, 1) : $str;
    }

    /*
     * 下划线转驼峰
     */
    public function lineToHump($str)
    {
        $str = preg_replace_callback('/([-_]+([a-z]{1}))/i', function ($matches) {
            return strtoupper($matches[2]);
        }, $str);
        return $str;
    }

    public function starts_with(string $haystack, string $needle, bool $sensitive = true)
    {
        $substr = substr($haystack,0, strlen($needle));
        if ($sensitive) 
            return $substr == $needle;
        return strtolower($substr) == strtolower($needle); 
    }

    public function ends_with(string $haystack, string $needle, bool $sensitive = true)
    {
        $substr = substr($haystack, -1 * strlen($needle));
        if ($sensitive) 
            return $substr == $needle;
        return strtolower($substr) == strtolower($needle); 
    }
    
    /**
     * tab对齐
     *
     * @param integer $count
     * @param string $pre
     * @param string $post
     * @return void
     * @author chentengfei
     * @since
     */
    public function tabs($count = 1, $pre = '', $post = '')
    {
        while ($count > 0) {
            $pre .= $this->spaces;
            $count--;
        }
        return $pre . $post;
    }

    /**
     * 字符串加边界符
     *
     * @param integer $count
     * @param string $pre
     * @param string $post
     * @return void
     * @author chentengfei
     * @since
     */
    public function surround($str, $char = "'") 
    {
        return $char.$str.$char;
    }

    /**
     * 获取配置
     *
     * @author chentengfei <tengfei.chen@atommatrix.com>
     * @date   2018-08-13 18:13:56
     * @param string $d 模板简称
     * @param string $key 配置项
     * @param  mixed $default 默认值
     * @return mixed
     */
    public function config(string $key, $default = '', $base = 'mvcs.')
    {
        return Config::get($base.$key, $default);
    }

    /**
     * 国际化的提示信息
     *
     * @param [type] $info
     * @param array $param
     * @param string $type
     * @return void
     * @author chentengfei
     * @since
     */
    public function myinfo($sign, $param = '', $type = 'info')
    {
        $lang = require_once __DIR__ . '/../language/' . $this->language . '.php';
        $message = $lang[$sign] ?? $param;
        if ($param) {
            $message = sprintf($message, $param);
        }
        echo $type . " : " . $message;
    }
}
