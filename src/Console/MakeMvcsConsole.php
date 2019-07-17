<?php

namespace Callmecsx\Mvcs\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class MakeMvcsConsole extends Command
{
    // 脚本命令
    protected $signature = 'mvcs:make {model} {--force=} {--only=} {--connect=} {--middleware=} {--style=}';

    // 脚本描述
    protected $description = '根据预定的文件模板创建文件';

    //模型
    private $model;

    //表名
    private $table;

    //文件组
    private $files;

    private $style="api_default";
    //中间件
    private $middleware = [];

    //额外名字空间和路径
    private $extraSpace = "";
    private $extraPath  = "";

    //强制覆盖文件组
    private $force = '';

    //默认生成文件组
    private $only = 'MVCS';

    //数据库链接
    private $connect = null;

    //不该被用户填充的字段
    private $ignoreColumns = [];

    /**
     * Create a new command instance.
     *
     * @param Filesystem $filesystem
     */
    public function __construct()
    {
        parent::__construct();
        $this->files    = new Filesystem();
        $this->ignoreColumns = Config::get("mvcs.ignore_columns") ?: [];
        $this->style = Config::get("mvcs.style") ?: 'api_default';
        $this->only = Config::get("mvcs.default_stubs")[$this->style] ?? 'MVCS';
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        if (Config::get('app.env') == 'production') {
            die("禁止在线上环境运行!");
        }
        $model     = ucfirst($this->argument('model'));

        if (empty($model)){
            die("you must input your model!");
        }
        if (count($modelArray = explode('/',$model)) > 1){
            $modelArray = array_map('ucfirst',$modelArray);
            $model = ucfirst(array_pop($modelArray));
            $this->extraSpace = '\\'.implode('\\',$modelArray);
            $this->extraPath = DIRECTORY_SEPARATOR.implode(DIRECTORY_SEPARATOR,$modelArray);
        }
        $force = $this->option('force');
        if ($force) {
            $this->force = $force;
        }
        $only = $this->option('only');
        if ($only && $only != 'all') {
            $this->only = strtoupper($only);
        }
        $connect = $this->option('connect');
        if ($connect) {
            $this->connect = $connect;
        }
        $middleware = $this->option('middleware')?:[];
        if ($middleware) {
            $middleware = explode(',',$middleware);
        }
        $style = $this->option('style');
        if ($style) {
            $this->style = $style;
        }
        $this->middleware = Config::get('mvcs.routes.middlewares') + $middleware;
        $this->model      = $model;
        $this->table      = $this->humpToLine($model);
        // 自动生成MVCS文件
        $this->writeMVCS();

    }

    /*
     * 驼峰转下划线
     */
    private function humpToLine($str){
        $str = preg_replace_callback('/([A-Z]{1})/',function($matches){
            return '_'.strtolower($matches[0]);
        },$str);
        return $str[0] == '_' ? substr($str,1) : $str;
    }

    /*
     * 下划线转驼峰
     */
    private function lineToHump($str)
    {
        $str = preg_replace_callback('/([-_]+([a-z]{1}))/i',function($matches){
            return strtoupper($matches[2]);
        },$str);
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
        if($this->createClass()){
            //若生成成功,则输出信息
            $this->info('Success to make '.$this->only.' files !');
            $this->addRoutes();
        }
    }

    /**
     * 添加路由
     *
     * @author chentengfei <tengfei.chen@atommatrix.com>
     * @date   2018-08-13 18:17:55
     */
    function addRoutes() 
    {
        if (Config::get('mvcs.add_route')) {
            $routeStr = "";
            $group = false;
            $type = Config::get('mvcs.route_type')?:'api';
            $routes = Config::get('mvcs.routes');
            if ($this->middleware) {
                $routeStr .= "Route::middleware(".\json_encode($this->middleware).")";
                $group = true;
            }
            if ($prefix = Config::get('mvcs.routes.prefix')) {
                $routeStr .= ($routeStr?"->prefix('$prefix')":"Route::prefix('$prefix')");
                $group = true;
            }
            if ($namespace = Config::get('mvcs.routes.namespace')) {
                $routeStr .= ($routeStr?"->namespace('$namespace')":"Route::namespace('$namespace')");
                $group = true;
            }
            if ($group) {
                $routeStr .= "->group(function(){\n";
            }
            $method = ['get','post','put','delete','patch'];
            $controller = $this->getClassName('C');
            foreach ($method as $met) {
                $rs = Config::get('mvcs.routes.'.$met);
                foreach($rs as $m => $r) {
                    $routeStr .= "    Route::$met('{$this->table}/$r','$controller@$m');\n";
                }
            }
            if(Config::get('mvcs.routes.apiResource')) {
                $routeStr .= "    Route::apiResource('{$this->table}','{$controller}');\n";
            } elseif (Config::get('mvcs.routes.resource')) {
                $routeStr .= "    Route::resource('{$this->table}','{$controller}');\n";
            }
            if ($group) {
                $routeStr .= "});\n\n";
            }
            $handle = fopen(base_path("routes/$type.php"),'a+');
            fwrite($handle,$routeStr);
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

        for($i=0; $i< strlen($this->only); $i ++) {
            $d = $this->only[$i];
            $path = $this->getPath($d);
            $directory = dirname($path);
            //检查路径是否存在,不存在创建一个,并赋予775权限
            if(! $this->files->isDirectory($directory)){
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
        $templates = $this->templateStub();
        $class     = null;
        foreach ($templates as $key => $template) {
            //根据不同路径,渲染对应的模板文件
            $path = $this->getPath($key);
            if (file_exists($path) && strpos($this->force,$key) === false && $this->force != 'all') {
                $this->info($this->getClassName($key).' 已存在. 可添加 --force=all || --force='.$key.' 来覆盖旧文件');
                continue ;
            }
            $class = $this->files->put($this->getPath($key), $template);
        }
        return $class;
    }

    private function getPath($d)
    {
        return $this->getDirectory($d).DIRECTORY_SEPARATOR.$this->getClassName($d).'.php';
    }

    private function getDirectory($d)
    {
        $path = $this->stub_config($d,'path');
        if (is_callable($path)) {
            return $path($this->model,$this->extraPath);
        }
        return $this->stub_config($d,'path').$this->extraPath;
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

            $connect = Config::get('database.connections.'.$this->connect);
            DB::setDefaultConnection($this->connect);
            switch ($connect['driver']) {// 
                case 'mysql':
                    return DB::select('select COLUMN_NAME as Field,COLUMN_DEFAULT as \'Default\',
                       IS_NULLABLE as \'Nullable\',COLUMN_TYPE as \'Type\',COLUMN_COMMENT as \'Comment\'
                       from INFORMATION_SCHEMA.COLUMNS where table_name = :table and TABLE_SCHEMA = :schema',
                    [':table' => $this->table,':schema' => $connect['database'],]);
                case 'sqlsrv':
                    return DB::select("SELECT
                    a.name as Field
                    b.name as 'Type',
                    COLUMNPROPERTY(a.id,a.name,'PRECISION') as L,
                    isnull(COLUMNPROPERTY(a.id,a.name,'Scale'),0)  as L2,
                    (case when a.isnullable=1 then 'YES' else 'NO' end) as Nullable,
                    isnull(e.text,'') as Default,
                    isnull(g.[value],'') as Comment
                    FROM   syscolumns   a
                    left   join   systypes   b   on   a.xusertype=b.xusertype
                    inner   join   sysobjects   d   on   a.id=d.id     and   d.xtype='U'   and     d.name<>'dtproperties'
                    left   join   syscomments   e   on   a.cdefault=e.id
                    left   join   sys.extended_properties   g   on   a.id=g.major_id   and   a.colid=g.minor_id
                    left   join   sys.extended_properties   f   on   d.id=f.major_id   and   f.minor_id=0 
                    where   d.name= :table
                    order   by   a.id,a.colorder ",
                    [':table' => $this->table,':schema' => $connect['database'],]);
                default:
                    $this->info('数据库类型['.$connect['driver'].']暂不支持，字段操作将会被跳过。');
                    return [];
                
            }
            
        } catch (\Exception $e) {
            $this->info('数据库配置['.$this->connect.']不可用，有些操作将会被跳过。建议先建表，重新执行一次。');
            $this->info($e->getMessage());
            return [];
        }

    }

    /**
     * 获取渲染后的模板
     *
     * @author chentengfei <tengfei.chen@atommatrix.com>
     * @date   2018-08-13 18:15:37
     * @return array
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    private function templateStub()
    {
        // 获取两个模板文件
        $stubs        = $this->getStub();
        // 获取需要替换的模板文件中变量
        $templateData = $this->getTemplateData();
        $renderStubs  = [];
        foreach ($stubs as $key => $stub) {
            // 进行模板渲染
            $renderStubs[$key] = $this->getRenderStub($templateData, $stub);
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
        for($i = 0;$i < strlen($this->only) ;$i++) {
            $key = $this->only[$i];
            $filename = $configs[$this->style][$key]['name'] ?? ($configs['common'][$key]['name'] ?? '');
            if ($filename) {
                $filePath = resource_path('stubs').DIRECTORY_SEPARATOR.$this->style.DIRECTORY_SEPARATOR.$filename.'.stub';
                if (file_exists($filePath)) {
                    $stubs[$key] = $this->files->get($filePath);
                } else {
                    $this->error("[$key]模板未找到文件:".$filePath);
                }
            } else {
                $this->error("[$key]模板未定义。");
            }
        }
        return $stubs;
    }

    public function getClassName($d)
    {
        return $this->model.$this->stub_config($d,"postfix");
    }

    private function getNameSpace($d)
    {
        return $this->stub_config($d,"namespace").$this->extraSpace;
    }

    private function getBaseUse($d)
    {
        $ens = $this->stub_config($d,"extands.namespace");
        $en = $this->stub_config($d,"extands.name");
        if (empty($ens) || $ens == $this->getNameSpace($d)) {
            return null;
        }
        return "use ".$ens.'\\'.$en.';';
    }

    private function getExtands($d)
    {
        $en = $this->stub_config($d,"extands.name");
        if (empty($en)) {
            return null;
        }
        return " extends ".$en;
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
        $create_date  = date("Y-m-d H:i:s");
        $tableName    = $this->table;
        $modularName  = strtoupper($tableName);
        $tableColumns = $this->getTableColumns();
        $templateVar  = [
            'create_date'      => $create_date,
            'table_name'       => $tableName,
            'modular_name'     => $modularName,
            'author_info'      => Config::get("mvcs.author")
        ];
        $stubs = array_keys(Config::get('mvcs.common') + Config::get('mvcs.'.$this->style));
        foreach($stubs as $d) {
            $name = $this->stub_config($d,"name");
            $templateVar[$name.'_name'] = $this->getClassName($d);
            $templateVar[$name.'_ns']   = $this->getNameSpace($d); // 后缀不能有包含关系，故不使用 _namespace 后缀
            $templateVar[$name.'_use']   = $this->getBaseUse($d);
            $templateVar[$name.'_extands'] = $this->getExtands($d);
            $templateVar[$name.'_anno']   = stripos('_'.$this->only,$d) ? "" : "// "; //是否注释掉
            $extra = $this->stub_config($d,"extra",[]);
            foreach($extra as $key => $func) {
                $templateVar[$name.'_'.$key] = \is_callable($func) ? $func($this->model,$tableColumns) : $func;
            }
        }
        $columns = [];
        // 根据数据库字段生成一些模板数据。
        $this->getValidatorData($tableColumns,$columns,$validatorRule,$validatorExcel,$validatorExcelDefault,$modelRelaies,$validatorMessages);

        $fillableColumn = implode(',',$columns);

        $templateVar2 = [ // 默认构造的数据
            'validator_rule'   => trim($validatorRule),
            'validator_column_rule'=> trim($validatorExcel),
            'validator_column_default'=> trim($validatorExcelDefault),
            'validator_message' => trim($validatorMessages),
            'model_fillable'  => $fillableColumn,
            'model_relay'     => $modelRelaies,
        ];
        return array_merge($templateVar2,$templateVar);
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
    private function getValidatorData($tableColumns,& $columns,& $validatorRule,& $validatorExcel, & $validatorExcelDefault,&$modelRelaies,&$validatorMessages)
    {
        $validators = [];
        $excelColumn = [];
        $excelDefault = [];
        $relaies = [];
        if($tableColumns) {
            foreach ($tableColumns as $column) {
                if (!in_array($column->Field,$this->ignoreColumns)) {
                    $v = [];
                    $columns[] = $v['column'] = "'".$column->Field."'";
                    $e['column'] = $v['column'];
                    $e['comment'] = $v['comment'] = $column->Comment?:$column->Field;
                    $e['example'] = '';
                    if ($column->Nullable == 'NO' && $column->Default === null) {
                        $v['rule'][] = 'required';
                        $v['messages'][$column->Field.'.required'] = $v['comment'].' 必填';
                        $e['comment'].= '(必填)';
                    } else {
                        $v['rule'][] = 'sometimes';
                        $e['comment'].= '(选填)';
                        $ed['column'] = $e['column'];
                        $ed['default'] = $column->Default?:(starts_with($column->Type,'varchar')?'':
                            (starts_with($column->Type,'date')?date('Y-m-d'):0));
                        $excelDefault[] = $ed;
                    }
                    //var_dump($column->Type);die;
                    if(preg_match("/varchar\((\d+)\)/",$column->Type,$match)) {
                        $v['rule'][] = 'string';
                        $v['rule'][] = 'max:'.$match[1];
                        $v['messages'][$column->Field.'.max'] = $v['comment'].' 长度不得超过:' . $match[1];
                        $e['example'] = $column->Default?:'';
                    } elseif(preg_match('/\w*int\((\d+)\)/',$column->Type,$match)) {
                        $v['rule'][] = 'int';
                        $v['rule'][] = 'min:0';
                        $v['messages'][$column->Field.'.max'] = $v['comment'].' 不得小于:0';
                        $e['example'] = 10;
                    } elseif(preg_match('/decimal\((\d+),(\d+)\)/',$column->Type,$match)) {
                        //$v['rule'][] = 'int';
                        $v['rule'][] = 'decimal:'.$match[1].','.$match[2];
                        $e['example'] = '12.5';
                    } elseif(preg_match('/date(time)*/',$column->Type,$match)) {
                        $v['rule'][] = 'date';
                        $e['example'] = date('Y-m-d');
                    }
                    if (ends_with($column->Field,'_id')) {
                        $otherTable  = str_replace('_id','',$column->Field);
                        $otherModel  = $this->lineToHump($otherTable);
                        $v['rule'][] = 'exist:'.$otherTable.',id';
                        $v['messages'][$column->Field.'.exist'] = $otherTable.' 不存在';
                        $relaies[]   = "public function $otherModel() {\n".
                            '        return $this->belongsTo("'.$this->getNameSpace('M').'\\'.ucfirst($otherModel).'");'."\n".
                            "    }\n";
                    }
                    $validators[] = $v;
                    $excelColumn[] = $e;
                }
            }
        }
        $validatorRule = implode("\n",array_map(function($arr){
            $column = '            '.$arr['column'].' ';//前面加12个空格
            $len = strlen($column);
            for($i =$len;$i<35;$i++) {
                $column .= ' ';
            }
            $rule = $column.' => \''.implode('|',$arr['rule']).'\',';

            for($i =strlen($rule);$i<80;$i++) {
                $rule .= ' ';
            }
            return $rule.'    //'.$arr['comment'] ;
        },$validators));

        $validatorMessages = implode("",array_map(function($arr){
            $messages = '';
            if ($arr['messages'] ?? []) {
                foreach($arr['messages'] as $key => $message) {
                    $messages .= str_pad("        '$key'",28)." => '$message',\n";
                }
            }
            return $messages;
        },$validators));


        $validatorExcel = implode("\n",array_map(function($arr){
            $column = '            '.$arr['column'].' ';//前面加12个空格
            $len = strlen($column);
            for($i =$len;$i<35;$i++) {
                $column .= ' ';
            }
            $rule = $column.' => [\''.$arr['comment'].'\',\''.$arr['example'].'\'],';
            return $rule;
        },$excelColumn));

        $validatorExcelDefault = implode("\n",array_map(function($arr){
            $column = '            '.$arr['column'].' ';//前面加12个空格
            $len = strlen($column);
            for($i =$len;$i<35;$i++) {
                $column .= ' ';
            }
            $rule = $column.' => \''.$arr['default'].'\',';
            return $rule;
        },$excelDefault));

        $modelRelaies = implode("\n\n    ",$relaies);
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
    private function getRenderStub($templateData, $stub)
    {
        foreach ($templateData as $search => $replace) {
            $stub = str_replace('$'.$search, $replace, $stub);
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
}
