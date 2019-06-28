<?php

namespace Callmecsx\Mvcs\Console;

use Callmecsx\Mvcs\Service\ExcelService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ImportMvcsDbConsole extends Command
{

    // 导入类型: 1 结构 2 数据 3结构和数据
    const TYPE_STRUCTURE_ONLY = 1;
    const TYPE_DATA_ONLY      = 2;
    const TYPE_STRUCTURE_DATA = 3;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:mvcs_db {file} {--type=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'create import db ';

    // 当前表名
    protected $table;

    // 默认类型
    private $type = self::TYPE_STRUCTURE_DATA;

    // 额外字段,默认ID未主键
    private $extraColumns = [];

    // 默认列宽
    private $defaultVarCharLen = 50;
    private $defaultDecimalL   = 8;
    private $defaultDecimalP   = 2;

    // 表前后缀
    private $table_prefix  = '';
    private $table_postfix = '';

    /**
     * Execute the console command.
     *
     */
    public function handle()
    {
        $file     = $this->argument('file');
        if (!is_file($file)){
            die("导入文件不存在!");
        }
        $type = $this->option('type');
        if ($type && in_array($type,[self::TYPE_STRUCTURE_ONLY,self::TYPE_DATA_ONLY,self::TYPE_STRUCTURE_DATA])) {
            $this->type = $type;
        }
        $this->extraColumns      = config('mvcs.report.extra_columns',[]);
        $this->defaultVarCharLen = config('mvcs.report.default_varchar_length',50);
        $this->table_prefix      = config('mvcs.report.table_prefix','');
        $this->table_postfix     = config('mvcs.report.table_postfix','');
        if(($this->type & 1) == 1) {
            $this->makeStruct($file);
        }
        if(($this->type & 2) == 2) {
            $this->makeData($file);
        }
    }

    /**
     * 构建表结构
     *
     * @author ctf <tengfei.chen@atommatrix.com>
     * @param $file
     */
    private function makeStruct($file)
    {
        $excelService = new ExcelService('Xlsx',3);
        // 获取所有sheet的数据
        $result = $excelService->getAllData($file);
        // 
        $report_names = [];
        foreach ($result as $data) {
            // 表名,取第一行第一列,行从1开始,列从0开始
            $report_name = $data[1][0]?:"";
            if(!$report_name || !is_string($report_name) || !preg_match('/^[ a-zA-Z0-9\-_]*$/',$report_name)){
                echo "Table name $report_name not a right case \n";
                continue;
            }
            // 列名
            $columns = array_map('trim',$data[2]);
            // 
            $rules = $data[3]?:[];
            $this->table = $this->table_prefix . strtolower($report_name) . $this->table_postfix;
            if (strlen($this->table) > 64) {
                $this->table = substr($this->table,0,64);
            }
            if (Schema::hasTable($this->table)) {
                echo "Table $report_name already existed \n";
                continue;
            }
            if (\in_array($report_name,$report_names)) {
                echo "Table $report_name defined in more than two sheet\n";
                continue;
            }
            $report_names[] = $report_name;
            echo "Creating Table : ".$this->table."\n";
            // 创建 migration 文件
            Artisan::call('make:migration', ['name' => 'create_' . $this->table . '_table']);
            // 修改 migration 文件 
            $this->replaceMigrationColumn($columns, $rules);
        }
        // 生成数据库
        Artisan::call('migrate');
    }

    /**
     * migrate
     *
     * @author ctf <tengfei.chen@atommatrix.com>
     * @param array $columns 列名
     * @param array $rules 示例
     */
    private function replaceMigrationColumn($columns,$rules)
    {
        // has primary?
        $has_primary = false;
        foreach ($columns as $key =>$column)
        {
            list($column_name,$column_comment) = array_map('trim',explode("@",$column));
            $column_name     = $this->humpToLine(trim($column_name));
            $column_comment  = addcslashes($column_comment ?: '');
            $rule            = $rules[$key] ?: '';
            list($colume_rule,$colume_index)    = explode('@',$rule);
            list($c_type,$c_l1,$c_l2) = explode('_',$colume_rule);
            $c_type          = strtolower($c_type);
            $index = '';
            if ($column_name = id);
            if ($is && \in_array($is,['index','unique'])) {
                $index = '->'.$is.'()';
            }
            $column_end = $index."->nullable()->comment(\"{$column_comment}\");";
            //int 转为 integer
            if (endsWith($c_type,'int')) {
                str_replace('int','integer',$c_type);
            }
            if (\in_array($c_type,['string','varchar'])) {//根据规则创建字段
                $l = $c_l1 ?: $this->defaultVarCharLen;
                $tableColumn[] = '$'."table->string('$column_name',$l){$column_end}";
            } elseif (\in_array($c,['decimal','double','float'])) {
                $l1 = $c_l1 ?: $this->defaultDecimalL;
                $l2 = $c_l1 ?: $this->defaultDecimalP;
                $tableColumn[] = '$'."table->decimal('$column_name',$l1,$l2){$column_end}";
            } elseif (\in_array($c,['bigincrements','biginteger','binary','boolean','char','date',
                'datetime','datetimetz','integer','json','jsonb','longtext','mediuminteger','mediumtext',
                'smallinteger','text','time','timestamp','tinyinteger','uuid' ])) {
                $tableColumn[] = '$'."table->$c('$column_name'){$column_end}";
            } elseif (ends_with($column_name,'date') || ends_with($column_name,'datetime')) {
                // name 以data 或 datetime 结尾，保存为datetime类型
                $tableColumn[] = '$table->datetime("'.$column_name.'")'.$column_end;
            } elseif (isset($example[$k]) && is_numeric($example[$k])) {
                // 数字处理方式
                if (strpos($example[$k],'.')){ 
                    // 小数用字符串记录
                    $tableColumn[] = '$table->string("'.$column_name.'",'.$this->defaultVarCharLen.')'.$column_end;
                } elseif (strlen($example[$k]) < 10) { 
                    // 小于10位整数用int
                    $tableColumn[] = '$table->integer("'.$column_name.'")'.$column_end;
                } else { 
                    // 长整数用string
                    $tableColumn[] = '$table->string("'.$column_name.'",'.$this->defaultVarCharLen.')'.$column_end;
                }
            } elseif(isset($example[$k]) && strlen($example[$k]) > 150){ 
                // 超长字符串用text
                $tableColumn[] = '$table->text("'.$column_name.'")'.$column_end;
            } elseif(isset($example[$k]) && strlen($example[$k]) > $this->defaultVarCharLen / 2){ 
                // 较长字符串用255存储
                $tableColumn[] = '$table->string("'.$column_name.'",255)'.$column_end;
            } else { 
                // 普通用默认长度varchar存储
                $tableColumn[] = '$table->string("'.$column_name.'",'.$this->defaultVarCharLen.')'.$column_end;
            }
        }
        // 默认字段放在结尾
        $tableColumn = array_merge($tableColumn, $this->extraColumns);
        $r_path  = database_path('migrations');
        $files   = scandir($r_path);
        // 默认情况下，最后一个文件是刚建的migration
        $file    = $real_path.DIRECTORY_SEPARATOR.array_pop($files);
        $content = file_get_contents($file);
        // 替换默认时间戳字段为所有字段
        $content = str_replace('$table->timestamps();',implode("\n            ",$tableColumn),$content);
        @file_put_contents($file,$content);
    }

    /**
     * 导入数据
     *
     * @author ctf <tengfei.chen@atommatrix.com>
     * @param $file
     */
    private function makeData($file)
    {
        $excelService = new ExcelService('Xlsx',3);
        $result = $excelService->getAllData($file);

        // 对每个sheet依次生成数据
        foreach ($result as $_key => $data) {
            // 表名,取第一行第二列,行从1开始,列从0开始
            $report_name = $data[1][1]??"";
            if (!$report_name || !is_string($report_name) || !preg_match('/^[ a-zA-Z0-9\-_]*$/',$report_name)) {
                continue;
            }
            // 列名
            $columns = array_map(function ($_item){
                return strtolower(str_replace([' ','-'],'_',$_item));
            },$data[2]);
            // 示例
            $example = $data[3]??[];

            $this->table = $this->table_prefix . strtolower($report_name) . $this->table_postfix;
            if (strlen($this->table) > 64) {
                $this->table = substr($this->table, 0, 64);
            }
            if(!Schema::hasTable($this->table))
                continue;
            if(DB::table($this->table)->count())
                continue;
            echo "Inserting Table : ".$this->table."\n";
            $defaultValues = $importColumns = $dataColumns = $numberColumns = [];
            foreach ($columns as $k => $v) {
                if (ends_with($v, 'date')) {
                    $dataColumns[] = $v;
                    $defaultValues[$v] = '1000-01-01 00:00:00';
                } elseif (isset($example[$k]) && is_numeric($example[$k])) {
                    $numberColumns[] = $v;
                    $defaultValues[$v] = 0;
                } else {
                    $defaultValues[$v] = '';
                }
                $importColumns[$v] = [$k, $k];
            }
            $excelService = new ExcelService('Xlsx',100);
            do {
                $data = $excelService->importData($file, $importColumns, '', '', 3,1,$_key);
                $excelService->ruleDatetime($data, $dataColumns);
                $excelService->ruleNumber($data, $numberColumns);
                $excelService->ruleFilter($data);
                $excelService->insertToTable($data, $defaultValues, $this->table, ['created_at', 'created_by', 'updated_at', 'updated_by']);
            } while ($excelService->hasMore());
        }
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
}
