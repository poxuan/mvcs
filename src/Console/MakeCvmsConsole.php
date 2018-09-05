<?php

namespace Callmecsx\Mvcs\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class MakeCvmsConsole extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:mvcs {model} {--force=} {--only=} {--connect=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'create templates of controller、validator、model、service';


    private $service;

    private $model;

    private $validator;

    private $controller;

    private $table;

    private $files;

    private $extraSpace = "";
    private $extraPath  = "";

    private $force = '';

    private $only = 'MVCS';

    private $connect = null;

    // validator 中忽略的字段,指不该被用户填充的字段
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
        $this->ignoreColumns = config("mvcs.ignore_columns")?:[];
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $model     = ucfirst($this->argument('model'));

        if (empty($model)){
            die("you must input your model!");
        }
        if (count($modelArray = explode('/',$model)) > 1){
            $model = array_pop($modelArray);
            $this->extraSpace = '\\'.implode('\\',$modelArray);
            $this->extraPath = '/'.implode('/',$modelArray);
        }
        $force = $this->option('force');
        if ($force) {
            $this->force = $force;
        }
        $only = $this->option('only');
        if ($only) {
            $this->only = $only;
        }
        $connect = $this->option('connect');
        if ($connect) {
            $this->connect = $connect;
        }
        $this->controller = $model."Controller";
        $this->model      = $model;
        $this->table      = $this->humpToLine($model);
        $this->service    = $model."Service";
        $this->validator  = $model."Validator";
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
        return substr($str,1);
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
        }
    }

    /**
     * 创建 service 目录
     *
     * @author chentengfei <tengfei.chen@atommatrix.com>
     * @date   2018-08-13 18:17:37
     * @return bool
     */
    private function createDirectory()
    {

        $directory = $this->getServiceDirectory();
        //检查路径是否存在,不存在创建一个,并赋予775权限
        if(! $this->files->isDirectory($directory)){
            $this->files->makeDirectory($directory, 0755, true);
        }

        $directory = $this->getControllerDirectory();
        //检查路径是否存在,不存在创建一个,并赋予775权限
        if(! $this->files->isDirectory($directory)){
            $this->files->makeDirectory($directory, 0755, true);
        }
        $directory = $this->getValidatorDirectory();
        //检查路径是否存在,不存在创建一个,并赋予775权限
        if(! $this->files->isDirectory($directory)){
            $this->files->makeDirectory($directory, 0755, true);
        }
        $directory = $this->getModelDirectory();
        //检查路径是否存在,不存在创建一个,并赋予775权限
        if(! $this->files->isDirectory($directory)){
            $this->files->makeDirectory($directory, 0755, true);
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
                $this->info($key.' is already exist. Add --force=all || --force='.$key.' to convert it');
                continue ;
            }
            $class = $this->files->put($this->getPath($key), $template);
        }
        return $class;
    }

    private function getPath($class)
    {
        // 两个模板文件,对应的两个路径
        $path = null;
        switch($class){
            case 'S':
                $path = $this->getServiceDirectory().DIRECTORY_SEPARATOR.$this->getService().'.php';
                break;
            case 'V':
                $path = $this->getValidatorDirectory().DIRECTORY_SEPARATOR.$this->getValidator().'.php';
                break;
            case "C":
                $path = $this->getControllerDirectory().DIRECTORY_SEPARATOR.$this->getController().'.php';
                break;
            case 'M':
                $path = $this->getModelDirectory().DIRECTORY_SEPARATOR.$this->getModel().'.php';
                break;
        }

        return $path;
    }

    private function getServiceDirectory()
    {
        return Config::get('mvcs.service_path').$this->extraPath;
    }

    private function getModelDirectory()
    {
        return Config::get('mvcs.model_path').$this->extraPath;
    }

    private function getValidatorDirectory()
    {
        return Config::get('mvcs.validator_path').$this->extraPath;
    }

    private function getControllerDirectory()
    {
        return Config::get('mvcs.controller_path').$this->extraPath;
    }


    private function getServiceNameSpace()
    {
        return Config::get('mvcs.service_namespace').$this->extraSpace;
    }

    private function getModelNameSpace()
    {
        return Config::get('mvcs.model_namespace').$this->extraSpace;
    }

    private function getValidatorNameSpace()
    {
        return Config::get('mvcs.validator_namespace').$this->extraSpace;
    }

    private function getControllerNameSpace()
    {
        return Config::get('mvcs.controller_namespace').$this->extraSpace;
    }
    /**
     * @return mixed
     */
    public function getService()
    {
        return $this->service;
    }

    /**
     * @return mixed
     */
    public function getModel()
    {
        return $this->model;
    }

    /**
     * @return mixed
     */
    public function getTable()
    {
        return $this->table;
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
            $database = config('database.connections.'.($this->connect?:DB::getDefaultConnection()).'.database');
            if ($this->connect) {
                DB::setDefaultConnection($this->connect);
            }
            return DB::select('select COLUMN_NAME as Field,COLUMN_DEFAULT as \'Default\',
                       IS_NULLABLE as \'Null\',COLUMN_TYPE as \'Type\',COLUMN_COMMENT as \'Comment\'
                       from INFORMATION_SCHEMA.COLUMNS where table_name = :table and TABLE_SCHEMA = :schema',
                [
                    ':table'=>$this->table,
                    ':schema' => $database,
                ]);
        } catch (\Exception $e) {
            $this->info('数据库配置['.$this->connect.']不可用，有些操作将会被跳过。建议先建表，重新执行一次。');
            return [];
        }

    }


    /**
     * @return mixed
     */
    public function getValidator()
    {
        return $this->validator;
    }

    /**
     * @return mixed
     */
    public function getController()
    {
        return $this->controller;
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
        $stubs = [
            'S'     => $this->files->get(resource_path('stubs').DIRECTORY_SEPARATOR."service.stub"),
            'C'     => $this->files->get(resource_path('stubs').DIRECTORY_SEPARATOR."controller.stub"),
            'V'     => $this->files->get(resource_path('stubs').DIRECTORY_SEPARATOR."validator.stub"),
            'M'     => $this->files->get(resource_path('stubs').DIRECTORY_SEPARATOR."model.stub"),
        ];
        foreach ($stubs as $k=>$v) {
            if (strpos($this->only,$k) === false){
                unset($stubs[$k]);
            }
        }
        return $stubs;
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
        $modelName = $this->model;
        $validatorName = $this->getValidator();
        $serverName = $this->getService();
        $create_date = date("Y-m-d H:i:s");
        $controllerName = $this->getController();
        $tableName = $this->getTable();
        $modularName = strtoupper($tableName);


        $modelNameSpace = $this->getModelNameSpace();
        $validatorNameSpace = $this->getValidatorNameSpace();
        $serverNameSpace = $this->getServiceNameSpace();
        $controllerNameSpace = $this->getControllerNameSpace();


        $modelBn = config('mvcs.model_base.name','');
        $validatorBn = config('mvcs.validator_base.name','');
        $serviceBn = config('mvcs.service_base.name','');
        $controllerBn = config('mvcs.controller_base.name','');

        $modelBu = config('mvcs.model_base.namespace',$modelNameSpace) == $modelNameSpace
            ? ""
            : config('mvcs.model_base.namespace','');
        $modelBu = $modelBu?"use ".$modelBu.'\\'.$modelBn.';':"";
        $validatorBu = config('mvcs.validator_base.namespace',$validatorNameSpace) == $validatorNameSpace
            ? ""
            : config('mvcs.validator_base.namespace','');
        $validatorBu = $validatorBu?"use ".$validatorBu.'\\'.$validatorBn.';':"";
        $serviceBu = config('mvcs.service_base.namespace',$serverNameSpace) == $serverNameSpace
            ? ""
            : config('mvcs.service_base.namespace','');
        $serviceBu = $serviceBu?"use ".$serviceBu.'\\'.$serviceBn.';':"";
        $controllerBu = config('mvcs.controller_base.namespace',$controllerNameSpace) == $controllerNameSpace
            ? ""
            : config('mvcs.controller_base.namespace','');
        $controllerBu = $controllerBu?"use ".$controllerBu.'\\'.$controllerBn.';':"";

        $validatorBn = $validatorBn?" extends ".$validatorBn:"";
        $modelBn = $modelBn?" extends ".$modelBn:"";
        $serviceBn = $serviceBn?" extends ".$serviceBn:"";
        $controllerBn = $controllerBn?" extends ".$controllerBn:"";
        $tableColumns = $this->getTableColumns();

        $columns = [];


        $this->getValidatorData($tableColumns,$columns,$validatorRule,$validatorExcel,$validatorExcelDefault,$modelRelaies);

        $fillableColumn = implode(',',$columns);

        $templateVar = [
            'validator_name'   => $validatorName,
            'service_name'     => $serverName,
            'create_date'      => $create_date,
            'model_name'       => $modelName,
            'controller_name'  => $controllerName,
            'validator_bu'     => $validatorBu,
            'service_bu'       => $serviceBu,
            'model_bu'         => $modelBu,
            'controller_bu'    => $controllerBu,
            'validator_bn'     => $validatorBn,
            'service_bn'       => $serviceBn,
            'model_bn'         => $modelBn,
            'controller_bn'    => $controllerBn,
            'validator_ns'     => $validatorNameSpace,
            'service_ns'       => $serverNameSpace,
            'model_ns'         => $modelNameSpace,
            'controller_ns'    => $controllerNameSpace,
            'table_name'       => $tableName,
            'modular_name'     => $modularName,
            'fillable_column'  => $fillableColumn,
            'validator_rule'   => trim($validatorRule),
            'excel_column_rule'=> trim($validatorExcel),
            'excel_column_default'=> trim($validatorExcelDefault),
            'model_relay'      => $modelRelaies,
        ];
        return $templateVar;
    }

    /**
     * 获取数据库字段,在validator中的展示
     *
     * @author chentengfei <tengfei.chen@atommatrix.com>
     * @date   2018-08-13 18:14:08
     * @param $tableColumns
     * @param $columns
     * @param $validatorRule
     * @param $validatorExcel
     * @param $validatorExcelDefault
     */
    private function getValidatorData($tableColumns,& $columns,& $validatorRule,& $validatorExcel, & $validatorExcelDefault,&$modelRelaies)
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
                    $e['comment'] = $v['comment'] = $column->Comment;
                    $e['comment'] = mb_substr($e['comment'],0,8);
                    if ($column->Null == 'NO' && $column->Default === null) {
                        $v['rule'][] = 'required';
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
                        $e['example'] = $column->Default?:'';
                    } elseif(preg_match('/\w*int\((\d+)\)/',$column->Type,$match)) {
                        $v['rule'][] = 'int';
                        $v['rule'][] = 'min:0';
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
                        $relaies[]   = "public function $otherModel() {\n".
                            '        return $this->belongsTo("'.$this->getModelNameSpace().'\\'.ucfirst($otherModel).'");'."\n".
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
     * 获取模板文件
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
}
