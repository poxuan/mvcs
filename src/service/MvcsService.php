<?php

namespace Callmecsx\Mvcs\Service;

use Callmecsx\Mvcs\Traits\Helper;
use Callmecsx\Mvcs\Traits\Replace;
use Callmecsx\Mvcs\Traits\Route;
use Callmecsx\Mvcs\Traits\Tag;
use Illuminate\Support\Facades\DB;

/**
 * 按模板生成文件脚本
 *
 * @author chentengfei <tengfei.chen@atommatrix.com>
 * @since  1970-01-01 08:00:00
 */
class MvcsService
{

    use Helper,Tag,Replace,Route;

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

    // 文件组

    public $style = 'api';
    // 中间件
    public $middleware = [];

    // 额外名字空间和路径
    public $extraSpace = '';
    public $extraPath = '';

    //强制覆盖文件组
    public $force = '';

    //默认生成文件组
    public $only = '';

    //数据库链接
    public $connect = null;

    //不该被用户填充的字段
    public $ignoreColumns = [];

    // 扩展
    public $traits = [];

    /**
     * Create a new command instance.
     *
     */
    public function __construct()
    {
        $this->ignoreColumns = $this->config('ignore_columns') ?: [];
        $this->style = $this->config('style', 'api');
        $this->only = $this->config('style_config.'.$this->style.'.stubs', 'MVCS');
        $this->traits = $this->config('style_config.'.$this->style.'.traits', []);
        $this->middleware = $this->config('routes.middlewares');
        $this->language = $this->config('language', 'zh-cn');
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function create($model, $configs = [])
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
            $this->traits = $this->config('style_config.'.$this->style.'.traits', []);
        }
        if ($force = $configs['force'] ?? '') {
            $this->force = $force;
        }
        if (($only = $configs['only'] ?? '') && $only != 'all') {
            $this->only = strtoupper($only);
        }
        if ($connect = $configs['connect'] ?? '') {
            $this->connect = $connect;
        } else {
            $this->connect = DB::getDefaultConnection();
        }
        if ($middleware = $configs['middleware'] ?? '') {
            $this->middleware += explode(',', $middleware);
        }
        
        if ($traits = $configs['traits'] ?? '') {
            $this->traits = array_unique(array_merge($this->traits, \explode(',', $traits)));
        }
        $this->model = $model;
        $this->tableF = $this->config("connections." . $this->connect . '.prefix', '', 'database.') . $this->humpToLine($model);
        $this->table = $this->humpToLine($model);
        // 生成MVCS文件
        $this->writeMVCS();

    }

    /**
     * Execute the console command.
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
            $this->connect = DB::getDefaultConnection();
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
        if ($this->createClass()) {
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
        if ($this->appendClass()) {
            // 若生成成功,则输出信息
            $this->myinfo('success', $this->only);
            // $this->appendRoutes();
        } else {
            $this->myinfo('fail');
        }
    }

    /**
     * 创建目标文件
     *
     * @author chentengfei <tengfei.chen@atommatrix.com>
     * @date   2018-08-13 18:16:56
     * @return int|null
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function appendClass()
    {
        //渲染模板文件,替换模板文件中变量值
        $params = $this->getTemplateParams();
        $res = false;
        $len = count($this->only);
        for($i =0 ; $i < $this->only; $i ++) {
            $key  = $this->only[$i];
            // 文件放置位置
            $path = $this->getSavePath($key);
            if (!file_exists($path)) {
                $this->myinfo('file_not_exist', $this->getClassName($key));
                continue;
            }
            $content  = \file_get_contents($path);

            $filename = $this->stubConfig($key, 'name', '');
            $traitContent = $this->getTraitContent($filename);
            foreach($traitContent as $point => $hook) {
                $hook = $this->replaceStubParams($params, $hook);
                $content = \str_replace('#'.$filename.'_hook_' . $point, ltrim($hook), $content);
            }
            $res = $this->files->put($this->getSavePath($key), $content);
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
    private function templateRender()
    {
        // 获取模板文件内容
        $stubs = $this->getStubContents();
        // 获取需要替换的模板文件中变量
        $templateData = $this->getTemplateParams();
        $renderStubs = [];
        foreach ($stubs as $key => $stub) {
            // 进行模板渲染，替换字段
            $renderStubs[$key] = $this->replaceStubParams($templateData, $stub);
        }

        return $renderStubs;
    }

    /**
     * 获取模板地址
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
            $key = $this->only[$i];
            $filename = $this->stubConfig($key, 'name', '');
            if (!$filename) {
                $this->myinfo('stub_not_found', $key, 'error');
                continue;
            }
            $filePath = $this->projectPath('stubs', 'resource') . DIRECTORY_SEPARATOR . $this->style . DIRECTORY_SEPARATOR . $filename . '.stub';
            if (!file_exists($filePath)) {
                $this->myinfo('stub_not_found', $key, 'error');
                continue;
            }
            $traitContent = $this->getTraitContent($filename);
            $tempContent = file_get_contents($filePath);
            
            foreach($traitContent as $point => $content) {
                $tempContent = \str_replace('$'.$filename.'_traits_' . $point, ltrim($content), $tempContent);
            }
            // 把没用到的traits消掉
            $stubs[$key] = \preg_replace('/\$'.$filename.'_traits_[a-z0-9_]*/i', '', $tempContent);
        }
        return $stubs;
    }
}
