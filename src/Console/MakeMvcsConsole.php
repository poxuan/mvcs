<?php

namespace Callmecsx\Mvcs\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
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
    private $model;

    // tab符
    private $spaces = '    ';

    // 表名
    private $table;
    private $tableF;
    private $tableColumns;

    private $language = 'zh-cn.php';

    // 文件组
    private $files;

    private $style = 'api_default';
    // 中间件
    private $middleware = [];

    // 额外名字空间和路径
    private $extraSpace = '';
    private $extraPath = '';

    //强制覆盖文件组
    private $force = '';

    //默认生成文件组
    private $only = 'MVCS';

    //数据库链接
    private $connect = null;

    //不该被用户填充的字段
    private $ignoreColumns = [];

    // 扩展
    private $traits = [];

    /**
     * Create a new command instance.
     *
     */
    public function __construct()
    {
        parent::__construct();
        $this->files = new Filesystem();
        $this->ignoreColumns = Config::get('mvcs.ignore_columns') ?: [];
        $this->style = Config::get('mvcs.style') ?: 'api_default';
        $this->only = Config::get('mvcs.default_stubs')[$this->style] ?? 'MVCS';
        $this->middleware = Config::get('mvcs.routes.middlewares');
        $this->language = Config::get('mvcs.language') ?: 'zh-cn';
        $this->traits = Config::get('mvcs.default_traits') ?: [];
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        if (Config::get('app.env') == 'production') {
            return $this->myinfo('deny', '', 'error');
        }
        $model = ucfirst($this->lineToHump($this->argument('model')));
        if (empty($model)) {
            return $this->myinfo('param_lack', 'model', 'error');
        }
        if (count($modelArray = explode('/', $model)) > 1) {
            $modelArray = array_map('ucfirst', $modelArray);
            $model = ucfirst(array_pop($modelArray));
            $this->extraSpace = '\\' . implode('\\', $modelArray);
            $this->extraPath = DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $modelArray);
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
        if ($style = $this->option('style')) {
            $this->style = $style;
        }
        if ($traits = $this->option('traits')) {
            $this->traits = array_merge($this->traits, \explode(',', $traits));
        }
        $this->model = $model;
        $this->tableF = Config::get('database.connections.' . $this->connect . '.prefix', '') . $this->humpToLine($model);
        $this->table = $this->humpToLine($model);
        // 生成MVCS文件
        $this->writeMVCS();

    }

    /*
     * 驼峰转下划线
     */
    private function humpToLine($str)
    {
        $str = preg_replace_callback('/([A-Z]{1})/', function ($matches) {
            return '_' . strtolower($matches[0]);
        }, $str);
        return $str[0] == '_' ? substr($str, 1) : $str;
    }

    /*
     * 下划线转驼峰
     */
    private function lineToHump($str)
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
        if (Config::get('mvcs.add_route')) {
            $routeStr = '';
            $group = false;
            $type = Config::get('mvcs.route_type') ?: 'api';
            $routes = Config::get('mvcs.routes');
            if ($this->middleware) {
                $routeStr .= 'Route::middleware(' . \json_encode($this->middleware) . ')';
                $group = true;
            }
            if ($prefix = Config::get('mvcs.routes.prefix')) {
                $routeStr .= ($routeStr ? "->prefix('$prefix')" : "Route::prefix('$prefix')");
                $group = true;
            }
            if ($namespace = Config::get('mvcs.routes.namespace')) {
                $routeStr .= ($routeStr ? "->namespace('$namespace')" : "Route::namespace('$namespace')");
                $group = true;
            }
            if ($group) {
                $routeStr .= "->group(function(){\n";
            }
            $method = ['get', 'post', 'put', 'delete', 'patch'];
            $controller = $this->getClassName('C');
            foreach ($method as $met) {
                $rs = Config::get('mvcs.routes.' . $met);
                foreach ($rs as $m => $r) {
                    $routeStr .= "    Route::$met('{$this->table}/$r','$controller@$m');\n";
                }
            }
            if (Config::get('mvcs.routes.apiResource')) {
                $routeStr .= "    Route::apiResource('{$this->table}','{$controller}');\n";
            } elseif (Config::get('mvcs.routes.resource')) {
                $routeStr .= "    Route::resource('{$this->table}','{$controller}');\n";
            }
            if ($group) {
                $routeStr .= "});\n\n";
            }
            $handle = fopen(base_path("routes/$type.php"), 'a+');
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
            $path = $this->getPath($d);
            $directory = dirname($path);
            //检查路径是否存在,不存在创建一个,并赋予775权限
            if (!$this->files->isDirectory($directory)) {
                $this->files->makeDirectory($directory, 0755, true);
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
            $path = $this->getPath($key);
            if (file_exists($path) && strpos($this->force, $key) === false && $this->force != 'all') {
                $this->myinfo('file_exist', $this->getClassName($key));
                continue;
            }
            $class = $this->files->put($this->getPath($key), $template);
        }
        return $class;
    }

    private function getPath($d)
    {
        return $this->getDirectory($d) . DIRECTORY_SEPARATOR . $this->getClassName($d) . '.php';
    }

    private function getDirectory($d)
    {
        $path = $this->stub_config($d, 'path');
        if (is_callable($path)) {
            return $path($this->model, $this->extraPath);
        }
        return $this->stub_config($d, 'path') . $this->extraPath;
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

            $connect = Config::get('database.connections.' . $this->connect);
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
        $configs = Config::get('mvcs');
        $stubs = [];
        for ($i = 0; $i < strlen($this->only); $i++) {
            $key = $this->only[$i];
            $filename = $configs[$this->style][$key]['name'] ?? ($configs['common'][$key]['name'] ?? '');
            if ($filename) {
                $filePath = resource_path('stubs') . DIRECTORY_SEPARATOR . $this->style . DIRECTORY_SEPARATOR . $filename . '.stub';
                if (file_exists($filePath)) {
                    $tempContent = $this->files->get($filePath);
                    $trait_content = "";
                    if ($this->traits) {
                        foreach ($this->traits as $trait) {
                            $traitPath = resource_path('stubs/traits') . DIRECTORY_SEPARATOR . $trait . DIRECTORY_SEPARATOR . $filename . '.stub';
                            if (file_exists($traitPath)) {
                                $trait_content .= $this->files->get($traitPath) . "\n";
                            }
                        }
                    }
                    $stubs[$key] = \str_replace('$'.$filename.'_traits', $trait_content, $tempContent);
                } else {
                    $this->myinfo('stub_not_found', $key, 'error');
                }
            } else {
                $this->myinfo('stub_not_found', $key, 'error');
            }
        }
        return $stubs;
    }

    public function getClassName($d)
    {
        return $this->model . $this->stub_config($d, 'postfix');
    }

    private function getNameSpace($d)
    {
        return $this->stub_config($d, 'namespace') . $this->extraSpace;
    }

    private function getBaseUse($d)
    {
        $ens = $this->stub_config($d, 'extends.namespace');
        $en = $this->stub_config($d, 'extends.name');
        if (empty($ens) || $ens == $this->getNameSpace($d)) {
            return null;
        }
        return 'use ' . $ens . '\\' . $en . ';';
    }

    private function getextends($d)
    {
        $en = $this->stub_config($d, 'extends.name');
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
        $create_date = date('Y-m-d H:i:s');
        $tableName = $this->table;
        $modularName = strtoupper($tableName);
        $this->tableColumns = $tableColumns = $this->getTableColumns();
        $templateVar = [
            'create_date' => $create_date,
            'table_name' => $tableName,
            'modular_name' => $modularName,
            'author_info' => Config::get('mvcs.author'),
            'main_version' => Config::get('mvcs.version'),
            'sub_version' => Config::get('mvcs.version') . '.' . date('ymdH'),
        ];
        $stubs = array_keys(Config::get('mvcs.common') + Config::get('mvcs.' . $this->style));
        foreach ($stubs as $d) {
            $name = $this->stub_config($d, 'name');
            $templateVar[$name . '_name'] = $this->getClassName($d);
            $templateVar[$name . '_ns'] = $this->getNameSpace($d); // 后缀不能有包含关系，故不使用 _namespace 后缀
            $templateVar[$name . '_use'] = $this->getBaseUse($d);
            $templateVar[$name . '_extends'] = $this->getextends($d);
            $templateVar[$name . '_anno'] = stripos('_' . $this->only, $d) ? '' : '// '; //是否注释掉
            $extra = $this->stub_config($d, 'replace', []);
            foreach ($extra as $key => $func) {
                $templateVar[$name . '_' . $key] = \is_callable($func) ? $func($this->model, $this->tableColumns, $this) : $func;
            }
        }
        $columns = [];
        // 根据数据库字段生成一些模板数据。
        $templateVar2 = $this->getDefaultData($tableColumns);
        return array_merge($templateVar2, $templateVar);
    }

    /**
     * 生成 Validator 配置
     *
     * @author chentengfei <tengfei.chen@atommatrix.com>
     * @date   2018-08-13 18:14:08
     * @param $tableColumns
     * @param $columns
     * @param $validatorRule
     * @param $validatorExcel
     * @param $validatorExcelDefault
     */
    private function getDefaultData($tableColumns)
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
        $excelColumn = [];
        $excelDefault = [];
        $relaies = [];
        if ($tableColumns) {
            foreach ($tableColumns as $column) {
                if (!in_array($column->Field, $this->ignoreColumns)) {
                    $v = [];
                    $v['column'] = $column->Field;
                    $columns[] = "'" . $column->Field . "'";
                    $v['comment'] = str_replace(['"', "'", "\n", "\t", "\r", "\\"], '', $column->Comment ?: $column->Field);
                    $v['example'] = '';
                    if ($column->Nullable == 'NO' && $column->Default === null) {
                        $v['rule'][] = 'required';
                        $v['messages'][$column->Field . '.required'] = $v['comment'] . ' 必填';
                        $v['nullable'] = false;
                    } else {
                        $v['rule'][] = 'sometimes';
                        $v['nullable'] = true;
                        $ed['column'] = $v['column'];
                        $ed['default'] = $column->Default ? "'{$column->Default}'" : (preg_match('/int/', $column->Type) ? 0 :
                            (starts_with($column->Type, 'date') ? "Db::raw('now()')" : "''"));
                        $excelDefault[] = $ed;
                    }
                    if (preg_match("/char\((\d+)\)/", $column->Type, $match)) {
                        $v['rule'][] = 'string';
                        $v['rule'][] = 'max:' . $match[1];// 可能需要添加扩展 mbmax
                        $v['messages'][$column->Field . '.max'] = $v['comment'] . ' 长度不得超过:' . $match[1];
                        $v['example'] = $column->Default ?: '';
                    } elseif (preg_match('/int/', $column->Type, $match)) {
                        $v['rule'][] = 'int';
                        $v['rule'][] = 'min:0';
                        $v['messages'][$column->Field . '.min'] = $v['comment'] . ' 不得小于:0';
                        $v['example'] = 1;
                    } elseif (preg_match('/decimal\((\d+),(\d+)\)/', $column->Type, $match)) {
                        //$v['rule'][] = 'int';
                        $v['rule'][] = 'decimal:' . $match[1] . ',' . $match[2];
                        $v['example'] = '1.00';
                    } elseif (preg_match('/date/', $column->Type, $match)) {
                        $v['rule'][] = 'date';
                        $v['example'] = date('Y-m-d');
                    } elseif (preg_match('/enum/', $column->Type, $match)) {
                        $enum = str_replace(['enum', '(', ')', ' ', "'"], '', $column->Type);
                        $v['enum'] = json_encode(explode(',', $enum), JSON_UNESCAPED_UNICODE);
                        $v['rule'][] = 'in:' . $v['enum'];
                        $v['example'] = date('Y-m-d');
                    }
                    if (ends_with($column->Field, '_id')) {
                        $otherTable = str_replace('_id', '', $column->Field);
                        $otherModel = $this->lineToHump($otherTable);
                        $v['relate'] = $this->getNameSpace('M') . '\\' . ucfirst($otherModel);
                        $v['rule'][] = 'exists:' . $otherTable . ',id';
                        $v['messages'][$column->Field . '.exists'] = $otherTable . ' 不存在';
                        $fullOtherModel = $this->getNameSpace('M') . '\\' . ucfirst($otherModel);
                        $relaies[] = "public function $otherModel() {" 
                            . $this->tabs(2, "\n") 
                            . 'return $this->belongsTo("' . $fullOtherModel . '");' 
                            . $this->tabs(1, "\n", "}\n");
                    }
                    $validators[] = $v;
                }
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
                "'c' => '{$arr['comment']}#{$nullable}'",
                "'e' => '{$arr['example']}'",
            ];
            if (isset($arr['enum'])) {
                $columns[] = $this->tabs(4, "\n") . "'l' => {$arr['enum']}";
            } elseif (isset($arr['relate'])) {
                $columns[] = $this->tabs(4, "\n") . "'l' => '{$arr['relate']}'";
                $columns[] = "'la' => false";
                $columns[] = "'lc' => 'name'";
            }
            return str_pad("'{$arr['column']}'", 25) . ' => [' . implode(', ', $columns) . ']';
        }, $validators));

        $validatorExcelDefault = implode($this->tabs(3, ",\n"), array_map(function ($arr) {
            return str_pad("'{$arr['column']}'", 25) . ' => ' . $arr['default'];
        }, $excelDefault));

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
        $tags = Config::get("mvcs.tags", []);
        foreach($tags as $tag => $value ) {
            if(is_callable($value)) {
                $value = $value($this->model, $this->tableColumns, $this);
            }
            $stub = $this->tagReplace($stub, $tag, $value);
        }
        return $stub;
    }

    function tagStacks($stub, $tag) {
        $tags_fix = Config::get("mvcs.tags_fix", "{ }");
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
                echo $value;
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
            $stub = substr($stub, 0, $stack_start) . $replace . substr($stub, $stack_end + 1);
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
     * @param  string $default 默认值
     * @return mixed
     */
    public function stub_config($d, $key, $default = '')
    {
        return Config::get("mvcs.{$this->style}.$d.$key", Config::get("mvcs.common.$d.$key", $default));
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

    public function tabs($count = 1, $pre = '', $post = '')
    {
        while ($count > 0) {
            $pre .= $this->spaces;
            $count--;
        }
        return $pre . $post;
    }

    /**
     * 添加空格对齐
     *
     * @param string $str 原词
     * @param integer $length 总长度
     * @param boolean $right 补在右边
     * @return void
     * @author chentengfei
     * @since
     */
    public function align(string $str, int $length = 15, $right = true)
    {
        $spaces = "";
        $len = strlen($str);
        while ($len < $length) {
            $spaces .= " ";
            $len++;
        }
        if ($right) {
            return $str . $spaces;
        }
        return $spaces . $str;
    }
}
