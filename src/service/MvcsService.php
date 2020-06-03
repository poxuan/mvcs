<?php

namespace Callmecsx\Mvcs\Service;

use Callmecsx\Mvcs\Traits\Base;
use Callmecsx\Mvcs\Traits\Helper;
use Callmecsx\Mvcs\Traits\Replace;
use Callmecsx\Mvcs\Traits\Route;
use Callmecsx\Mvcs\Traits\Tag;

/**
 * 按模板生成文件脚本
 *
 * @author chentengfei <tengfei.chen@atommatrix.com>
 * @since  1970-01-01 08:00:00
 */
class MvcsService
{

    use Base,Helper,Tag,Replace,Route;

    // 模型
    public $model;

    // 表名
    public $table;
    // 完整表名
    public $tableF;
    // 表字段
    public $tableColumns;
    // 语言
    public $language = 'zh-cn.php';

    // 风格
    public $style = 'api';
    // 中间件
    public $middleware = [];

    // 额外名字空间和路径
    public $extraSpace = '';
    public $extraPath = '';

    // 强制覆盖文件
    public $force = '';

    // 默认生成文件
    public $only = '';

    // 数据库配置名
    public $connect = null;

    // 不该被用户填充的字段
    public $ignoreColumns = [];

    // 选装扩展
    public $traits = [];

    /**
     * Create a new command instance.
     *
     */
    public function __construct()
    {
        $this->ignoreColumns = $this->config('ignore_columns') ?: [];
        $this->style         = $this->config('style', 'api');
        $this->only          = $this->config('style_config.'.$this->style.'.stubs', 'MVCS');
        $this->traits        = $this->config('style_config.'.$this->style.'.traits', []);
        $this->middleware    = $this->config('routes.middlewares');
        $this->language      = $this->config('language', 'zh-cn');
    }

    /**
     * 创建文件组
     *
     * @return mixed
     */
    public function create($model, $configs = [])
    {
        // 可能由多段组成  a/abc
        if (count($modelArray = explode('/', $model)) > 1) {
            $modelArray = array_map('ucfirst', $modelArray);
            // 转成骆驼式
            $model = ucfirst(array_pop($modelArray));
            // 其余部分组成额外的名字空间后缀和文件夹
            $this->extraSpace = '\\' . implode('\\', $modelArray);
            $this->extraPath = DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $modelArray);
        }
        if (!preg_match('/^[a-z][a-z0-9]*$/i',$model)) { // 只识别数字和字母
            return $this->myinfo('invalid_model', $model, 'error');
        }
        if ($style = $configs['style'] ?? '') { // 选用非默认文件风格
            $this->style = $style;
            $this->only = $this->config('style_config.'.$this->style.'.stubs', 'MVCS');
            $this->traits = $this->config('style_config.'.$this->style.'.traits', []);
        }
        if ($force = $configs['force'] ?? '') { // 自定义强制覆盖组
            $this->force = strtoupper($force);
        }
        if (($only = $configs['only'] ?? '') && $only != 'all') { // 自定义生成文件组
            $this->only = strtoupper($only);
        }
        if ($connect = $configs['connect'] ?? '') { // 自定义数据库选择
            $this->connect = $connect;
        } else { // 选择默认数据库
            $this->connect = $this->getDefaultConnection();
        }
        if ($middleware = $configs['middleware'] ?? '') { // 自定义中间件
            $this->middleware += explode(',', $middleware);
        }
        
        if ($traits = $configs['traits'] ?? '') { // 选装扩展
            $this->traits = array_unique(array_merge($this->traits, \explode(',', $traits)));
        }
        $this->model = $model;
        // 完整表名
        $this->tableF = $this->config("connections." . $this->connect . '.prefix', '', 'database.') . $this->humpToLine($model);
        // 表名
        $this->table = $this->humpToLine($model);
        // 生成文件组
        $this->writeMVCS();

    }

    /**
     * 扩展文件组
     *
     * @return mixed
     */
    public function append($model, $configs = [])
    {
        $model = ucfirst($this->lineToHump($model));
        if (empty($model)) {
            return $this->myinfo('param_lack', 'model', 'error');
        }
        if (!preg_match('/^[a-z][a-z0-9]*$/i',$model)) {
            return $this->myinfo('invalid_model', $model, 'error');
        }
        if (count($modelArray = explode('/', $model)) > 1) {
            $modelArray = array_map('ucfirst', $modelArray);
            $model = ucfirst(array_pop($modelArray));
            $this->extraSpace = '\\' . implode('\\', $modelArray);
            $this->extraPath = DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $modelArray);
        }
        if ($style = $configs['style'] ?? '') {
            $this->style = $style;
            $this->only = $this->config('style_config.'.$this->style.'.stubs', 'MVCS');
        }
        if (($only = $configs['only'] ?? '') && $only != 'all') {
            $this->only = strtoupper($only);
        }
        if ($connect = $configs['connect'] ?? '') {
            $this->connect = $connect;
        } else {
            $this->connect = $this->getDefaultConnection();
        }
        $this->traits = [];
        if ($traits = $configs['traits'] ?? '') {
            $this->traits = array_merge($this->traits, \explode(',', $traits));
        } else {
            return $this->myinfo('nothing_append', '', 'error');
        }
        $this->model  = $model;
        $this->tableF = $this->config("connections." . $this->connect . '.prefix', '', 'database.') . $this->humpToLine($model);
        $this->table  = $this->humpToLine($model);
        // 生成MVCS文件
        $this->appendMVCS();

    }

    /**
     * 生成mvcs文件
     *
     * @author chentengfei <tengfei.chen@atommatrix.com>
     * @date   2018-08-13 18:17:55
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    private function writeMVCS()
    {
        $this->createDirectory();
        if ($this->saveFiles()) {
            //若生成成功,则输出信息
            $this->myinfo('success', $this->only);
            $this->addRoutes();
        } else {
            $this->myinfo('fail');
        }
    }

    /**
     * 扩展mvcs文件
     *
     * @author chentengfei <tengfei.chen@atommatrix.com>
     * @date   2018-08-13 18:17:55
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    private function appendMVCS()
    {
        if ($this->appendFiles()) {
            // 若生成成功,则输出信息
            $this->myinfo('success', $this->only);
            // todo 扩展路由，未完成
            // $this->appendRoutes();
        } else {
            $this->myinfo('fail');
        }
    }

    /**
     * 扩展目标文件
     *
     * @author chentengfei <tengfei.chen@atommatrix.com>
     * @date   2018-08-13 18:16:56
     * @return int|null
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function appendFiles()
    {
        //渲染模板文件,替换模板文件中变量值
        $params = $this->getStubParams();
        $res = false;
        $len = strlen($this->only);
        for($i =0 ; $i < $len; $i ++) {
            $slug  = $this->only[$i];
            // 文件放置位置
            $path = $this->getSaveFile($slug);
            if (!file_exists($path)) {
                $this->myinfo('file_not_exist', $this->getClassName($slug));
                continue;
            }
            $content  = \file_get_contents($path);
            $traitContent = $this->getTraitContent($slug);
            foreach($traitContent as $point => $hookBody) {
                $hookBody = $this->replaceStubParams($params, $hookBody);
                $hookName = $this->getHookName($slug, $point);
                $content = \str_replace($hookName, $hookName."\n\n".$hookBody, $content);
            }
            $res = file_put_contents($this->getSaveFile($slug), $content);
        }
        return $res;
    }

    /**
     * 创建目标文件
     *
     * @author chentengfei <tengfei.chen@atommatrix.com>
     * @date   2018-08-13 18:16:56
     * @return int|null
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    private function saveFiles()
    {
        //渲染模板文件,替换模板文件中变量值
        $stubs = $this->stubRender();
        $res = false;
        foreach ($stubs as $slug => $content) {
            // 文件放置位置
            $path = $this->getSaveFile($slug);
            if (file_exists($path) && strpos($this->force, $slug) === false && $this->force != 'all') {
                $this->myinfo('file_exist', $this->getClassName($slug));
                continue;
            }
            $res = file_put_contents($this->getSaveFile($slug), $content);
        }
        return $res;
    }


    /**
     * 模板渲染
     *
     * @author chentengfei <tengfei.chen@atommatrix.com>
     * @date   2018-08-13 18:15:37
     * @return array
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    private function stubRender()
    {
        // 获取模板文件内容
        $stubs = $this->getStubContents();
        // 获取需要替换的模板文件中变量
        $stubParams = $this->getStubParams();
        $renderStubs = [];
        foreach ($stubs as $slug => $content) {
            // 进行模板渲染，替换字段
            $renderStubs[$slug] = $this->replaceStubParams($stubParams, $content);
        }
        return $renderStubs;
    }

    /**
     * 获取模板内容
     *
     * @author chentengfei <tengfei.chen@atommatrix.com>
     * @date   2018-08-13 18:15:13
     * @return array
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    private function getStubContents()
    {
        $stubs = [];
        for ($i = 0; $i < strlen($this->only); $i++) {
            $slug = $this->only[$i];
            $name = $this->stubConfig($slug, 'name', '');
            if (!$name) {
                $this->myinfo('stub_not_found', $slug, 'error');
                continue;
            }
            $filePath = $this->getStubPath() . DIRECTORY_SEPARATOR . $this->style . DIRECTORY_SEPARATOR . $name . '.stub';
            if (!file_exists($filePath)) {
                $this->myinfo('stub_not_found', $slug, 'error');
                continue;
            }
            $traitContent = $this->getTraitContent($name);
            $tempContent = file_get_contents($filePath);
            foreach($traitContent as $point => $content) {
                $tempContent = \str_replace($this->getReplaceName($name.'_traits_' . $point), ltrim($content), $tempContent);
            }
            // 把没用到的traits都干掉
            $stubs[$slug] = \preg_replace('/' . $this->getReplaceRegex($name.'_traits_[a-z0-9_]*').'/i', '', $tempContent);
        }
        return $stubs;
    }
}
