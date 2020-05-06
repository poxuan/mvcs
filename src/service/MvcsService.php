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
    public function handle($model, $configs = [])
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
     * 创建目录
     *
     * @author chentengfei <tengfei.chen@atommatrix.com>
     * @date   2018-08-13 18:17:37
     * @return bool
     */
    private function createDirectory()
    {

        for ($i = 0; $i < strlen($this->only); $i++) {
            $d = $this->only[$i];
            $path = $this->getSavePath($d);
            $directory = dirname($path);
            //检查路径是否存在,不存在创建一个,并赋予775权限
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }
        }
        return true;
    }

    /**
     * 创建目标文件
     *
     * @author chentengfei <tengfei.chen@atommatrix.com>
     * @date   2018-08-13 18:16:56
     * @return int|null
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    private function createClass()
    {
        //渲染模板文件,替换模板文件中变量值
        $templates = $this->templateRender();
        $class = null;
        foreach ($templates as $key => $template) {
            // 文件放置位置
            $path = $this->getSavePath($key);
            if (file_exists($path) && strpos($this->force, $key) === false && $this->force != 'all') {
                $this->myinfo('file_exist', $this->getClassName($key));
                continue;
            }
            $class = file_put_contents($this->getSavePath($key), $template);
        }
        return $class;
    }

    /**
     * 文件保存名
     *
     * @param [type] $d
     * @return void
     * @author chentengfei
     * @since
     */
    private function getSavePath($d)
    {
        return $this->getDirectory($d) . DIRECTORY_SEPARATOR . $this->getClassName($d) . $this->getClassExt($d);
    }

    /**
     * 文件存储路径
     *
     * @param [type] $d
     * @return void
     * @author chentengfei
     * @since
     */
    private function getDirectory($d)
    {
        $path = $this->stubConfig($d, 'path');
        if (is_callable($path)) {
            return $path($this->model, $this->extraPath);
        }
        return $this->stubConfig($d, 'path') . $this->extraPath;
    }

    /**
     * 获取数据库字段
     *
     * @author chentengfei <tengfei.chen@atommatrix.com>
     * @date   2018-08-13 18:16:11
     * @return array
     */
    public function getTableColumns()
    {

        try {
            $this->connect = $this->connect ?: DB::getDefaultConnection();

            $connect = $this->config('connections.' . $this->connect, '', 'database.');
            DB::setDefaultConnection($this->connect);
            switch ($connect['driver']) { //
                case 'mysql':
                    return DB::select('select COLUMN_NAME as Field,COLUMN_DEFAULT as \'Default\',
                       IS_NULLABLE as \'Nullable\',COLUMN_TYPE as \'Type\',COLUMN_COMMENT as \'Comment\'
                       from INFORMATION_SCHEMA.COLUMNS where table_name = :table and TABLE_SCHEMA = :schema',
                        [':table' => $this->tableF, ':schema' => $connect['database']]);
                case 'sqlsrv':
                    return DB::select("SELECT a.name as Field,b.name as 'Type',COLUMNPROPERTY(a.id,a.name,'PRECISION') as L,
                        isnull(COLUMNPROPERTY(a.id,a.name,'Scale'),0)  as L2,
                        (case when a.isnullable=1 then 'YES' else 'NO' end) as Nullable,
                        isnull(e.text,'') as Default,isnull(g.[value],'') as Comment
                        FROM   syscolumns   a
                        left   join   systypes   b   on   a.xusertype=b.xusertype
                        inner  join   sysobjects   d   on   a.id=d.id     and   d.xtype='U'   and     d.name<>'dtproperties'
                        left   join   syscomments   e   on   a.cdefault=e.id
                        left   join   sys.extended_properties   g   on   a.id=g.major_id   and   a.colid=g.minor_id
                        left   join   sys.extended_properties   f   on   d.id=f.major_id   and   f.minor_id=0
                        where   d.name= :table order by a.id,a.colorder",
                        [':table' => $this->tableF, ':schema' => $connect['database']]);
                default:
                    $this->myinfo('db_not_support', $connect['driver']);
                    return [];
            }

        } catch (\Exception $e) {
            $this->myinfo('db_disabled', $this->connect);
            $this->myinfo('message', $e->getMessage(), 'error');
            return [];
        }

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
        // 获取两个模板文件
        $stubs = $this->getStub();
        // 获取需要替换的模板文件中变量
        $templateData = $this->getTemplateData();
        $renderStubs = [];
        foreach ($stubs as $key => $stub) {
            // 进行模板渲染
            $renderStubs[$key] = $this->replaceStub($templateData, $stub);
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
    private function getStub()
    {
        $stubs = [];
        $traitContent = $this->getTraitContent();
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
            $tempContent = file_get_contents($filePath);
            
            foreach($traitContent as $point => $content) {
                $tempContent = \str_replace('$'.$filename.'_traits_' . $point, ltrim($content), $tempContent);
            }
            // 把没用到的traits消掉
            $stubs[$key] = \preg_replace('/\$'.$filename.'_traits_[a-z0-9_]*/i', '', $tempContent);
        }
        return $stubs;
    }

    
    /**
     * 获取类名
     *
     * @param [type] $d
     * @return void
     * @author chentengfei
     * @since
     */
    public function getClassName($d)
    {
        return $this->model . $this->stubConfig($d, 'postfix');
    }

    /**
     * 获取类后缀
     *
     * @param [type] $d
     * @return void
     * @author chentengfei
     * @since
     */
    public function getClassExt($d)
    {
        return $this->stubConfig($d, 'ext', '.php');
    }

    /**
     * 获取类名字空间
     *
     * @param [type] $d
     * @return void
     * @author chentengfei
     * @since
     */
    private function getNameSpace($d)
    {
        return $this->stubConfig($d, 'namespace') . $this->extraSpace;
    }

    /**
     * 获取类的基类use
     *
     * @param [type] $d
     * @return void
     * @author chentengfei
     * @since
     */
    private function getBaseUse($d)
    {
        $ens = $this->stubConfig($d, 'extends.namespace');
        $en = $this->stubConfig($d, 'extends.name');
        if (empty($ens) || $ens == $this->getNameSpace($d)) {
            return null;
        }
        return 'use ' . $ens . '\\' . $en . ';';
    }

    /**
     * 获取 extends
     *
     * @param [type] $d
     * @return void
     * @author chentengfei
     * @since
     */
    private function getExtends($d)
    {
        $en = $this->stubConfig($d, 'extends.name');
        if (empty($en)) {
            return null;
        }
        return ' extends ' . $en;
    }

    
    /**
     * 替换参数，生成目标文件
     *
     * @author chentengfei <tengfei.chen@atommatrix.com>
     * @date   2018-08-13 18:13:56
     * @param $templateData
     * @param $stub
     * @return mixed
     */
    private function replaceStub($templateData, $stub)
    {
        
        $this->tagFix = $this->config('tags_fix', '{ }');
        foreach ($templateData as $search => $replace) {
            // 先处理标签
            $stub = $this->solveTags($stub, $this->config('tags'));
            // 替换参数
            $stub = str_replace('$' . $search, $replace, $stub);
        }
        return $stub;
    }

    /**
     * 获取模板配置
     *
     * @author chentengfei <tengfei.chen@atommatrix.com>
     * @date   2018-08-13 18:13:56
     * @param string $d 模板简称
     * @param string $key 配置项
     * @param  mixed $default 默认值
     * @return mixed
     */
    public function stubConfig($d, $key, $default = '')
    {
        return $this->config("$d.$key", $default, "mvcs.{$this->style}.") 
                ?: $this->config("$d.$key", $default, "mvcs.common.");
    }

    /**
     * 获取项目目录
     *
     * @param [type] $filepath
     * @param string $base
     * @return void
     * @author chentengfei
     * @since
     */
    public function projectPath($filepath, $base = 'base')
    {
        $pathfunc = $base.'_path';

        return $pathfunc($filepath);
    }

}
