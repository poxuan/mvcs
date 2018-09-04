<?php

namespace Poxuan\Mvcs\Console;

use Poxuan\Mvcs\Service\ExcelService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MakeReportConsole extends Command
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
    protected $signature = 'make:report {file} {--type=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'create report';

    // 当前表名
    protected $table;

    // 默认类型
    private $type = self::TYPE_STRUCTURE_DATA;

    // 额外字段,默认ID未主键
    private $extraColumns = [
        '$table->integer("org_id")->nullable()->comment("组织ID");',
        '$table->string("amazon_seller_id",50)->nullable()->comment("卖家ID");',
        '$table->string("report_id",100)->nullable()->comment("报表ID");',
    ];

    // 默认列宽
    private $defaultVarCharLen = 50;

    // 表前后缀
    private $table_prefix  = 'fba';
    private $table_postfix = 'test';

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
        foreach ($result as $data) {
            // 表名,取第一行第二列,行从1开始,列从0开始
            $report_name = $data[1][1]??"";
            if(!$report_name || !is_string($report_name) || !preg_match('/^[ a-zA-Z0-9\-_]*$/',$report_name)){
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
                $this->table = substr($this->table,0,64);
            }

            if (Schema::hasTable($this->table))
                continue;
            echo "Creating Table : ".$this->table."\n";
            Artisan::call('make:migration', ['name' => 'create_' . $this->table . '_table']);
            $this->replaceMigrationColumn($columns, $example);
            Artisan::call('migrate');
        }
    }

    /**
     * migrate
     *
     * @author ctf <tengfei.chen@atommatrix.com>
     * @param array $columns 列名
     * @param array $example 示例
     */
    private function replaceMigrationColumn($columns,$example)
    {
        $tableColumn = $this->extraColumns;
        foreach ($columns as $k=>$v)
        {
            if(ends_with($v,'date')) {//以date结尾的数据类型是datetime
                $tableColumn[] = '$table->datetime("'.$v.'")->nullable();';
            }elseif(isset($example[$k]) && is_numeric($example[$k])) {//数据格式
                if(strpos($example[$k],'.')){ // 小数用字符串记录
                    $tableColumn[] = '$table->string("'.$v.'",'.$this->defaultVarCharLen.')->nullable();';
                } elseif(strlen($example[$k]) < 10) { // 小于10位整数用int
                    $tableColumn[] = '$table->integer("'.$v.'")->nullable();';
                } else { //长整数用string
                    $tableColumn[] = '$table->string("'.$v.'",'.$this->defaultVarCharLen.')->nullable();';
                }
            } elseif(isset($example[$k]) && strlen($example[$k])>150){//超长字符串用text
                $tableColumn[] = '$table->text("'.$v.'")->nullable();';
            } elseif(isset($example[$k]) && strlen($example[$k])>$this->defaultVarCharLen - 10){ //长字符串用255存储
                $tableColumn[] = '$table->string("'.$v.'",255)->nullable();';
            } else { //普通用默认长度varchar存储
                $tableColumn[] = '$table->string("'.$v.'",'.$this->defaultVarCharLen.')->nullable();';
            }
        }
        $real_path = database_path('migrations');
        $files = scandir($real_path);
        // 最后一个文件是刚建的migration
        $file = $real_path.DIRECTORY_SEPARATOR.array_pop($files);
        $content = file_get_contents($file);
        // 替换默认时间戳字段为所需字段
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
}
