<?php

namespace Callmecsx\Mvcs\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * 按模板生成文件脚本
 *
 * @author chentengfei <tengfei.chen@atommatrix.com>
 * @since  1970-01-01 08:00:00
 */
class MakeMvcsConsole extends Command
{
    // 脚本命令
    protected $signature = 'mvcs:make {model} {--force=} {--only=} {--connect=} {--middleware=} {--style=} {--traits=}';

    // 脚本描述
    protected $description = '根据预定的文件模板创建文件';

    // 模型
    public $model;

    // tab符
    public $spaces = '    ';

    // 表名
    public $table;
    public $tableF;
    public $tableColumns;

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
    public $only = 'MVCS';

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
        parent::__construct();
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
    public function handle()
    {
        if ($this->config('env', 'local', 'app.') == 'production') {
            return $this->myinfo('deny', '', 'error');
        }
        if ($this->config('version') < '2.0') {
            return $this->myinfo('version_deny', '2.0', 'error');
        }
        $model = ucfirst($this->lineToHump($this->argument('model')));
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
        if ($style = $this->option('style')) {
            $this->style = $style;
            $this->only = $this->config('style_config.'.$this->style.'.stubs', 'MVCS');
            $this->traits = $this->config('style_config.'.$this->style.'.traits', []);
        }
        if ($force = $this->option('force')) {
            $this->force = $force;
        }
        if (($only = $this->option('only')) && $only != 'all') {
            $this->only = strtoupper($only);
        }
        if ($connect = $this->option('connect')) {
            $this->connect = $connect;
        } else {
            $this->connect = DB::getDefaultConnection();
        }
        if ($middleware = $this->option('middleware', [])) {
            $this->middleware += explode(',', $middleware);
        }
        
        if ($traits = $this->option('traits')) {
            $this->traits = array_unique(array_merge($this->traits, \explode(',', $traits)));
        }
        $this->model = $model;
        $this->tableF = $this->config("connections." . $this->connect . '.prefix', '', 'database.') . $this->humpToLine($model);
        $this->table = $this->humpToLine($model);
        // 生成MVCS文件
        $this->writeMVCS();

    }

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
     * 添加路由
     *
     * @author chentengfei <tengfei.chen@atommatrix.com>
     * @date   2018-08-13 18:17:55
     */
    public function addRoutes()
    {
        if ($this->config('add_route')) {
            $routeStr = '';
            $group = false;
            $type = $this->config('route_type', 'api');
            if ($this->middleware) {
                $routeStr .= 'Route::middleware(' . \json_encode($this->middleware) . ')';
                $group = true;
            }
            if ($prefix = $this->config('routes.prefix')) {
                $routeStr .= ($routeStr ? "->prefix('$prefix')" : "Route::prefix('$prefix')");
                $group = true;
            }
            if ($namespace = $this->config('routes.namespace')) {
                $routeStr .= ($routeStr ? "->namespace('$namespace')" : "Route::namespace('$namespace')");
                $group = true;
            }
            if ($group) {
                $routeStr .= "->group(function(){\n";
            }
            $method = ['get', 'post', 'put', 'delete', 'patch'];
            $controller = $this->getClassName('C');
            $routefile = @file_get_contents($this->projectPath("routes/$type.php"));
            foreach ($method as $met) {
                $rs = $this->config('routes.' . $met, []);
                foreach ($rs as $m => $r) {
                    $alias = ($prefix ? $prefix.'.' : '') . $this->table.'.'.$m;
                    if (!strpos($routefile, $alias)) {
                        $routeStr .= "    Route::$met('{$this->table}/$r','$controller@$m')->name('$alias');\n";
                    }
                }
            }
            // 添加trait对应的路由
            foreach ($this->traits as $trait) {
                $routes = $this->config('traits.' . $trait.'.routes');
                if ($routes) {
                    foreach ($method as $met) {
                        $rs = $routes[$met] ?? [];
                        foreach ($rs as $m => $r) {
                            $alias = ($prefix ? $prefix.'.' : '') . $this->table.$m;
                            if (!strpos($routefile, $alias)) {
                                $routeStr .= "    Route::$met('{$this->table}/$r','$controller@$m')->name('$alias');\n";
                            }
                        }
                    }
                }
            }
            if ($this->config('routes.apiResource')) {
                $routeStr .= "    Route::apiResource('{$this->table}','{$controller}');\n";
            } elseif ($this->config('routes.resource')) {
                $routeStr .= "    Route::resource('{$this->table}','{$controller}');\n";
            }
            if ($group) {
                $routeStr .= "});\n\n";
            }
            $handle = fopen($this->projectPath("routes/$type.php"), 'a+');
            fwrite($handle, $routeStr);
            fclose($handle);
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
            $traitContent = [];
            if ($this->traits) {
                foreach ($this->traits as $trait) {
                    $traitPath = $this->projectPath('stubs/traits', 'resource') . DIRECTORY_SEPARATOR . $trait . DIRECTORY_SEPARATOR . $filename . '.stub';
                    $handle = @fopen($traitPath, 'r+');
                    $point  = 'body';
                    if ($handle) {
                        while(!feof($handle)) {
                            $line = fgets($handle);
                            if (substr($line,0,2) == '@@') { // 以双@开头
                                $point = trim(substr($line,2));
                            } elseif(isset($traitContent[$point])) {
                                $traitContent[$point] .= $line;
                            } else {
                                $traitContent[$point] = $line;
                            }
                        }
                        fclose($handle);
                    }
                }
            }
            foreach($traitContent as $point => $content) {
                $tempContent = \str_replace('$'.$filename.'_traits_' . $point, ltrim($content), $tempContent);
            }
            // 把没用到的traits消掉
            $stubs[$key] = \preg_replace('/\$'.$filename.'_traits_[a-z0-9]*/i', '', $tempContent);
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
     * 获取模板替换字段
     *
     * @author chentengfei <tengfei.chen@atommatrix.com>
     * @date   2018-08-13 18:14:56
     * @return array
     */
    private function getTemplateData()
    {
        $templateVar = [
            'table_name' => $this->table
        ];
        $this->tableColumns = $this->getTableColumns();
        $stubs = array_keys($this->config('common') + $this->config('' . $this->style));
        foreach ($stubs as $d) {
            $name = $this->stubConfig($d, 'name');
            $templateVar[$name . '_name'] = $this->getClassName($d);
            $templateVar[$name . '_ns'] = $this->getNameSpace($d); // 后缀不能有包含关系，故不使用 _namespace 后缀
            $templateVar[$name . '_use'] = $this->getBaseUse($d);
            $templateVar[$name . '_extends'] = $this->getExtends($d);
            $templateVar[$name . '_anno'] = stripos('_' . $this->only, $d) ? '' : '// '; //是否注释掉
            $extra = $this->stubConfig($d, 'replace', []);
            foreach ($extra as $key => $func) {
                $templateVar[$name . '_' . $key] = \is_callable($func) ? $func($this->model, $this->tableColumns, $this) : $func;
            }
        }
        $globalReplace = $this->config('global', []);
        foreach($globalReplace as $key => $val) {
            if ($val instanceof \Closure) {
                $templateVar[$key] = $val($this->model, $this->tableColumns);
            } elseif(is_string($val)) {
                $templateVar[$key] = $val;
            } else {
                $templateVar[$key] = strval($val);
            }
        }
        // 根据数据库字段生成一些模板数据。
        $templateVar2 = $this->getBuiltInData($this->tableColumns);
        return array_merge($templateVar2, $templateVar);
    }

    /**
     * 生成 内置配置
     *
     * @author chentengfei <tengfei.chen@atommatrix.com>
     * @date   2018-08-13 18:14:08
     * @param $tableColumns
     * @param $columns
     * @param $validatorRule
     * @param $validatorExcel
     * @param $validatorExcelDefault
     */
    private function getBuiltInData($tableColumns)
    {
        if (empty($tableColumns)) {
            return [
                'validator_rule' => '',
                'validator_excel_rule' => '',
                'validator_excel_default' => '',
                'validator_message' => '',
                'model_fillable' => '',
                'model_relation' => '',
            ];
        }
        $validators = [];
        $relaies = [];
        $columns = [];
        
        foreach ($tableColumns as $column) {
            if (!in_array($column->Field, $this->ignoreColumns)) {
                $validators[] = $this->getColumnInfo($column, $columns, $relaies);
            }
        }
        
        $validatorRule = implode($this->tabs(3, "\n"), array_map(function ($arr) {
            $rule = str_pad("'{$arr['column']}'", 25) . " => '" . implode('|', $arr['rule']) . "',";
            return str_pad($rule, 80) . '    //' . $arr['comment']; // 补充注释
        }, $validators));

        $validatorMessages = implode('', array_map(function ($arr) {
            $messages = '';
            if ($arr['messages'] ?? []) {
                foreach ($arr['messages'] as $key => $message) {
                    $messages .= str_pad($this->tabs(2) . "'$key'", 28) . " => '$message',\n";
                }
            }
            return $messages;
        }, $validators));

        $validatorExcel = implode($this->tabs(3, ",\n"), array_map(function ($arr) {
            $nullable = $arr['nullable'] ? '选填' : '必填';
            $columns = [
                "'{$arr['comment']}#{$nullable}'",
                "'{$arr['example']}'",
            ];
            if (isset($arr['enum'])) {
                $columns[] = "{$arr['enum']}";
            } elseif (isset($arr['relate'])) {
                $columns[] = "{$arr['relate']}";
                $columns[] = "['rc' => 'name']"; // 关联表名
            }
            return str_pad("'{$arr['column']}'", 25) . ' => [' . implode(', ', $columns) . ']';
        }, $validators));

        $validatorExcelDefault = implode($this->tabs(3, ",\n"), array_map(function ($arr) {
            return str_pad("'{$arr['column']}'", 25) . ' => ' . $arr['default'];
        }, array_filter($validators, function($item) { return is_null($item['default']);})));

        $result = [
            'validator_rule' => trim($validatorRule),
            'validator_excel_rule' => trim($validatorExcel),
            'validator_excel_default' => trim($validatorExcelDefault),
            'validator_message' => trim($validatorMessages),
            'model_fillable' => implode(',', $columns),
            'model_relation' => implode($this->tabs(1, "\n\n"), $relaies),
        ];
        return $result;
    }

    private function getColumnInfo($column, &$columns, & $relaies)
    {
        $info = [];
        $info['column'] = $column->Field;
        $columns[] = $this->surround($column->Field);
        $info['comment'] = str_replace(['"', "'", "\n", "\t", "\r", "\\"], '', $column->Comment ?: $column->Field);
        $info['example'] = '';
        if ($column->Nullable == 'NO' && $column->Default === null) {
            $info['rule'][] = 'required';
            $info['messages'][$column->Field . '.required'] = $info['comment'] . ' 必填';
            $info['nullable'] = false;
            $info['default'] = null;
        } else {
            $info['rule'][] = 'sometimes';
            $info['nullable'] = true;
            $info['default'] = "''";
            if ($column->Default) {
                if ($column->Default == 'CURRENT_TIMESTAMP') {
                    $info['default'] = "Db::raw('CURRENT_TIMESTAMP')";
                } else {
                    $info['default'] = $this->surround(str_replace("'","\\'", $column->Default));
                }
            } elseif (preg_match('/int/', $column->Type)) {
                $info['default'] = 0;
            } elseif ($this->starts_with($column->Type, 'date') || $this->starts_with($column->Type, 'time')) {
                $info['default'] = "Db::raw('CURRENT_TIMESTAMP')";
            }
        }
        if (preg_match("/char\((\d+)\)/", $column->Type, $match)) {
            $info['rule'][] = 'string';
            $info['rule'][] = 'max:' . $match[1];// 可能需要添加扩展 mbmax
            $info['messages'][$column->Field . '.max'] = $info['comment'] . ' 长度不得超过:' . $match[1];
            $info['example'] = $column->Default ?: '';
        } elseif (preg_match('/int/', $column->Type, $match)) {
            $info['rule'][] = 'int';
            $info['rule'][] = 'min:0';
            $info['messages'][$column->Field . '.min'] = $info['comment'] . ' 不得小于:0';
            $info['example'] = 1;
        } elseif (preg_match('/decimal\((\d+),(\d+)\)/', $column->Type, $match)) {
            //$info['rule'][] = 'int';
            $info['rule'][] = 'decimal:' . $match[1] . ',' . $match[2];
            $info['example'] = '1.00';
        } elseif (preg_match('/date/', $column->Type, $match)) {
            $info['rule'][] = 'date';
            $info['example'] = date('Y-m-d');
        } elseif (preg_match('/enum/', $column->Type, $match)) {
            $enum = str_replace(['enum', '(', ')', ' ', "'"], '', $column->Type);
            $enum = explode(',', $enum);
            $enum = array_map(function($item) {
                return "'$item' => '$item'";
            },$enum);
            $info['enum'] = "[ ".implode(',', $enum)." ]";
            $info['rule'][] = 'in:' . $info['enum'];
            $info['example'] = date('Y-m-d');
        }
        if ($this->ends_with($column->Field, '_id')) {
            $otherTable = str_replace('_id', '', $column->Field);
            $otherModel = $this->lineToHump($otherTable);
            $info['relate'] = '\\' . $this->getNameSpace('M') . '\\' . ucfirst($otherModel).'::class';
            $info['rule'][] = 'exists:' . $otherTable . ',id';
            $info['messages'][$column->Field . '.exists'] = $otherTable . ' 不存在';
            $fullOtherModel = $this->getNameSpace('M') . '\\' . ucfirst($otherModel);
            $relaies[] = "public function $otherModel() {\n" 
                . $this->tabs(2) . 'return $this->belongsTo("' . $fullOtherModel . '");' . "\n"
                . $this->tabs(1) . "}\n";
        }
        return $info;
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
        
        foreach ($templateData as $search => $replace) {
            // 先处理标签
            $stub = $this->solveTags($stub);
            // 替换参数
            $stub = str_replace('$' . $search, $replace, $stub);
        }
        return $stub;
    }

    private function solveTags($stub) 
    {
        $tags = $this->config('tags', []);
        foreach($tags as $tag => $value ) {
            if(is_callable($value)) {
                $value = $value($this->model, $this->tableColumns, $this);
            }
            $stub = $this->tagReplace($stub, $tag, $value);
        }
        return $stub;
    }

    function tagStacks($stub, $tag) {
        $tags_fix = $this->config('tags_fix', '{ }');
        list($tags_pre, $tags_post) = explode(' ', $tags_fix);
        $patton = '/'.$tags_pre.'((!|\/)?'.$tag.'(:[\w]*)?)'.$tags_post.'/i';
        $m = preg_match_all($patton, $stub, $matches);
        $stacks = [];
        if ($m) {
            $last_pos = 0;
            $last_stack = 0;
            foreach ($matches[0] as $key => $match) {
                $last_pos = strpos($stub, $match, $last_pos);
                if (!isset($stacks[$last_stack]['start'])) {
                    $stacks[$last_stack]['start'] = $last_pos;
                }
                $stacks[$last_stack]['items'][] = [
                    'start'  => $last_pos,
                    'length' => strlen($matches[0][$key]),
                    'match'  => $matches[1][$key],
                ];
                if ($matches[1][$key][0] == '/') {
                    $stacks[$last_stack]['end'] = $last_pos + strlen($matches[0][$key]);
                    $last_stack++;
                }
            }
        }
        $stacks = array_reverse($stacks);
        return $stacks;
    }
    
    function tagReplace($stub, $tag, $value) {
        $stacks = $this->tagStacks($stub, $tag);
        foreach($stacks as $stack) {
            $stack_start = $stack['start'];
            $stack_end   = $stack['end'] ?? die("标签没有闭合");
            $replace     = "";
            foreach ($stack['items'] as $key => $item) {
                $match = explode(':', $item['match']);
                if (isset($match[1])) {
                    if ($match[1] == $value) {
                        $start = $item['start'] + $item['length'];
                        $end   = $stack['items'][$key + 1]['start'];
                        $replace = substr($stub, $start, $end - $start);
                        break;
                    }
                } elseif ($item['match'][0] == '!' && ! $value) {
                    $start = $item['start'] + $item['length'];
                    $end   = $stack['items'][$key + 1]['start'];
                    $replace = substr($stub, $start, $end - $start);
                    break;
                } elseif ($value) {
                    $start = $item['start'] + $item['length'];
                    $end   = $stack['items'][$key + 1]['start'];
                    $replace = substr($stub, $start, $end - $start);
                    break;
                }
            }
            $stub = substr($stub, 0, $stack_start) . $replace . substr($stub, $stack_end);
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
        $this->$type($message);
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

    public function starts_with(string $haystack, string $needle, bool $sensitive = true)
    {
        $substr = substr($haystack,0, count($needle));
        if ($sensitive) 
            return $substr == $needle;
        return strtolower($substr) == strtolower($needle); 
    }

    public function ends_with(string $haystack, string $needle, bool $sensitive = true)
    {
        $substr = substr($haystack, -1 * count($needle));
        if ($sensitive) 
            return $substr == $needle;
        return strtolower($substr) == strtolower($needle); 
    }
}
