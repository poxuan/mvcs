<?php

namespace Callmecsx\Mvcs\Traits;

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

    /**
     * 对齐
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
        $lang = require __DIR__ . '/../language/' . $this->language . '.php';
        $message = $lang[$sign] ?? $param;
        if ($param) {
            $message = sprintf($message, $param);
        }
        $color = 32;
        switch ($type) {
            case 'info':
                $color = 33;
            break;
            case 'error':
                $color = 31;
            break;
            case 'debug':
                $color = 34;
            break;
        }
        echo "\033[{$color}m " . $type . " : " . $message . " \033[0m\n";
    }

    /**
     * 文件保存地址
     *
     * @param [type] $slug
     * @return void
     * @author chentengfei
     * @since
     */
    public function getSaveFile($slug)
    {
        return $this->getSaveDirectory($slug) . DIRECTORY_SEPARATOR . $this->getClassName($slug) . $this->getClassExt($slug);
    }

    /**
     * 文件存储目录
     *
     * @param [type] $slug
     * @return void
     * @author chentengfei
     * @since
     */
    public function getSaveDirectory($slug)
    {
        $path = $this->stubConfig($slug, 'path');
        if (is_callable($path)) {
            return $path($this->model, $this->extraPath);
        }
        return $this->stubConfig($slug, 'path') . $this->extraPath;
    }

     /**
     * 获取类名
     *
     * @param [type] $slug
     * @return void
     * @author chentengfei
     * @since
     */
    public function getClassName($slug)
    {
        return $this->model . $this->stubConfig($slug, 'postfix');
    }

    /**
     * 获取类后缀
     *
     * @param [type] $slug
     * @return void
     * @author chentengfei
     * @since
     */
    public function getClassExt($slug)
    {
        return $this->stubConfig($slug, 'ext', '.php');
    }

    /**
     * 获取类名字空间
     *
     * @param [type] $slug
     * @return void
     * @author chentengfei
     * @since
     */
    public function getNameSpace($slug)
    {
        return $this->stubConfig($slug, 'namespace') . $this->extraSpace;
    }

    /**
     * 获取类的基类use
     *
     * @param [type] $slug
     * @return void
     * @author chentengfei
     * @since
     */
    public function getBaseUse($slug)
    {
        $ens = $this->stubConfig($slug, 'extends.namespace');
        $en  = $this->stubConfig($slug, 'extends.name');
        if (empty($ens) || $ens == $this->getNameSpace($slug)) {
            return null;
        }
        return 'use ' . $ens . '\\' . $en . ';';
    }

    /**
     * 获取 extends
     *
     * @param [type] $slug
     * @return void
     * @author chentengfei
     * @since
     */
    public function getExtends($slug)
    {
        $en = $this->stubConfig($slug, 'extends.name');
        if (empty($en)) {
            return null;
        }
        return ' extends ' . $en;
    }

    /**
     * 获取模板配置
     *
     * @author chentengfei <tengfei.chen@atommatrix.com>
     * @date   2018-08-13 18:13:56
     * @param string $slug 模板简称
     * @param string $key 配置项
     * @param  mixed $default 默认值
     * @return mixed
     */
    public function stubConfig($slug, $key, $default = '')
    {
        return $this->config("$slug.$key", $default, "stubs.{$this->style}.") 
                ?: $this->config("$slug.$key", $default, "stubs.common.");
    }

    /**
     * 创建文件保存目录
     *
     * @author chentengfei <tengfei.chen@atommatrix.com>
     * @date   2018-08-13 18:17:37
     * @return bool
     */
    public function createDirectory()
    {

        for ($i = 0; $i < strlen($this->only); $i++) {
            $slug = $this->only[$i];
            $path = $this->getSaveFile($slug);
            $directory = dirname($path);
            //检查路径是否存在,不存在创建一个,并赋予775权限
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }
        }
        return true;
    }

    /**
     * 获取扩展钩子名
     *
     * @param [type] $slug
     * @param [type] $point
     * @return void
     * @author chentengfei
     * @sinced
     */
    public function getHookName($slug, $point)
    {
        // 通过简名获取全名
        $name = $this->stubConfig($slug, 'name', '');
        $hookFix = $this->stubConfig($slug, 'hook_fix', '');
        if ($hookFix && is_array($hookFix)) {
            if (isset($hookFix[$point])) { // 设置模板专用包围物
                $hookFix = $hookFix[$point];
                $fixs = explode(' ', $hookFix);
                return $fixs[0].$name.'_hook_'.$point.($fixs[1] ?? '');
            } elseif (isset($hookFix['*'])) { // 设置模板通用包围物
                $hookFix = $hookFix['*'];
                $fixs = explode(' ', $hookFix);
                return $fixs[0].$name.'_hook_'.$point.($fixs[1] ?? '');
            }
        } elseif ($hookFix && is_string($hookFix)) { // 是非空字符串 
            $fixs = explode(' ', $hookFix);
            return $fixs[0].$name.'_hook_'.$point.($fixs[1] ?? '');
        }
        // 全局通用包围物
        $hookFix = $this->config('hook_fix', '#');
        $fixs = explode(' ', $hookFix);
        return $fixs[0].$name.'_hook_'.$point.($fixs[1] ?? '');
    }

    /**
     * 获取替换名
     *
     * @param [type] $name
     * @return void
     * @author chentengfei
     * @since
     */
    public function getReplaceName($name) {
        $replaceFix = $this->config('replace_fix', '$');
        $fixs = explode(' ', $replaceFix);
        return $fixs[0].$name.($fixs[1] ?? '');
    }

    /**
     * 获取替换规则
     *
     * @param [type] $name
     * @return void
     * @author chentengfei
     * @since
     */
    public function getReplaceRegex($preg) {
        $replaceFix = $this->config('replace_fix', '$');
        $fixs = explode(' ', $replaceFix);
        return preg_quote($fixs[0]) . $preg . preg_quote($fixs[1] ?? '');
    }

    public function startsWith(string $haystack, string $needle, bool $sensitive = true)
    {
        $substr = substr($haystack,0, strlen($needle));
        if ($sensitive) 
            return $substr == $needle;
        return strtolower($substr) == strtolower($needle); 
    }

    public function endsWith(string $haystack, string $needle, bool $sensitive = true)
    {
        $substr = substr($haystack, -1 * strlen($needle));
        if ($sensitive) 
            return $substr == $needle;
        return strtolower($substr) == strtolower($needle); 
    }
    
}
