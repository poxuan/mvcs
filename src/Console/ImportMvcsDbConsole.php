<?php

namespace Callmecsx\Mvcs\Console;

use Callmecsx\Mvcs\Service\ExcelService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 通过Excel导入table
 *
 * @author chentengfei <tengfei.chen@atommatrix.com>
 * @since  1970-01-01 08:00:00
 */
class ImportMvcsDbConsole extends Command
{

    // 导入类型: 1 结构 2 数据 3结构和数据
    const TYPE_STRUCTURE_ONLY = 1;
    const TYPE_DATA_ONLY = 2;
    const TYPE_STRUCTURE_DATA = 3;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mvcs:excel {file} {--type=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'create db from excel';

    // 当前表名
    protected $table;

    // 默认类型
    private $type = self::TYPE_STRUCTURE_DATA;

    // 额外字段,默认ID未主键
    private $extraColumns = [];

    // 默认列宽
    private $defaultVarCharLen = 50;
    private $defaultDecimalL = 8;
    private $defaultDecimalP = 2;

    // 表前后缀
    private $table_prefix = '';
    private $table_postfix = '';

    private $language = 'zh-cn';

    /**
     * Create a new command instance.
     *
     */
    public function __construct()
    {
        parent::__construct();
        $this->language = Config::get('mvcs.language') ?: 'zh-cn';
    }

    /**
     * Execute the console command.
     *
     */
    public function handle()
    {
        if (config('app.env') == 'production') {
            return $this->myinfo('deny');
        }
        $file = $this->argument('file');
        if (!is_file($file)) {
            return $this->myinfo('param_invalid','file');
        }
        $type = $this->option('type');

        if ($type && in_array($type, [self::TYPE_STRUCTURE_ONLY, self::TYPE_DATA_ONLY, self::TYPE_STRUCTURE_DATA])) {
            $this->type = $type;
        }
        $this->extraColumns = config('mvcs.report.extra_columns', []);
        $this->defaultVarCharLen = config('mvcs.report.default_varchar_length', 50);
        $this->table_prefix = config('mvcs.report.table_prefix', '');
        $this->table_postfix = config('mvcs.report.table_postfix', '');
        if (($this->type & 1) == 1) {
            echo "Creating Table start";
            $this->myinfo('initing_structure');
            $this->makeStruct($file);
        }
        if (($this->type & 2) == 2) {
            echo "Inserting Data start";
            $this->myinfo('importing_data');
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
        $excelService = new ExcelService('Xlsx', 3);
        // 获取所有sheet的数据
        $result = $excelService->getAllData($file);
        //
        $report_names = [];
        foreach ($result as $data) {
            // 表名,取第一行第一列,行从1开始,列从0开始
            $report_name = $data[1][0] ?: "";
            if (!$report_name || !is_string($report_name) || !preg_match('/^[ a-zA-Z0-9\-_]*$/', $report_name)) {
                echo "Table name $report_name not a right case \n";
                $this->myinfo('excel_table_error', $report_name);
                continue;
            }
            // 列名
            $columns = array_map('trim', $data[2]);
            //
            $rules = $data[3] ?: [];
            $this->table = $this->table_prefix . strtolower($report_name) . $this->table_postfix;
            if (strlen($this->table) > 64) {
                $this->table = substr($this->table, 0, 64);
            }
            if (Schema::hasTable($this->table)) {
                $this->myinfo('excel_table_exist', $report_name);
                echo "Table $report_name already existed \n";
                continue;
            }
            if (\in_array($report_name, $report_names)) {
                $this->myinfo('excel_multiple', $report_name);
                echo "Table $report_name defined in more than two sheet\n";
                continue;
            }
            $report_names[] = $report_name;
            $this->myinfo('creating_table', $this->table);
            echo "Creating Table : " . $this->table . "\n";
            // 创建 migration 文件
            Artisan::call('make:migration', ['name' => 'create_' . $this->table . '_table']);
            // 修改 migration 文件
            $this->replaceMigrationColumn($columns, $rules);
        }
        
        echo "Creating Table finish\n";
        $this->myinfo('migrating_table');
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
    private function replaceMigrationColumn($columns, $rules)
    {
        // has primary?
        $has_primary = false;
        foreach ($columns as $key => $column) {
            list($c_name, $c_comment) = array_map('trim', explode("#", $column));
            $nullable = "->nullable()";
            if ($c_name[0] == '*') { // 必填
                $nullable = "";
                $c_name = str_replace('*', '', $c_name);
            }
            $c_name = $this->humpToLine(trim($c_name));

            $c_comment = addslashes($c_comment ?: '');

            $rule = $rules[$key] ?: '';
            list($c_rule, $c_index) = explode('#', $rule . "#");
            list($c_type, $c_l1, $c_l2) = explode('_', $c_rule . '__');
            $c_type = strtolower($c_type);
            $index = '';
            //int 转为 integer
            if (\substr($c_type, -3) == 'int') {
                $c_type = str_replace('int', 'integer', $c_type);
            }
            if ($c_name == 'id' && substr($c_type, -7) == 'integer') {
                $has_primary = true;
                $c_type = 'increments';
            } elseif ($c_index && \in_array($c_index, ['primary'])) {
                $has_primary = true;
                $index = '->' . $c_index . '()';
            } elseif ($c_index && \in_array($c_index, ['index', 'unique'])) {
                $index = '->' . $c_index . '()';
            }
            $column_end = $index . $nullable . "->comment(\"{$c_comment}\");";
            if (\in_array($c_type, ['string', 'varchar'])) { //根据规则创建字段
                $l = $c_l1 ?: $this->defaultVarCharLen;
                $tableColumn[] = '$' . "table->string('$c_name',$l){$column_end}";
            } elseif (\in_array($c_type, ['decimal', 'double', 'float'])) {
                $l1 = $c_l1 ?: $this->defaultDecimalL;
                $l2 = $c_l2 ?: $this->defaultDecimalP;
                $tableColumn[] = '$' . "table->decimal('$c_name',$l1,$l2){$column_end}";
            } elseif (\in_array($c_type, ['bigincrements', 'biginteger', 'binary', 
                'boolean','char', 'date', 'datetime', 'datetimetz', 'increments', 
                'integer', 'jsonb', 'longtext', 'mediuminteger', 'tinyinteger', 'uuid', 
                'mediumtext', 'smallinteger', 'text', 'time', 'timestamp', 'json', ]
            )) {
                $tableColumn[] = '$' . "table->$c_type('$c_name'){$column_end}";
            } elseif (ends_with($c_name, 'date') || ends_with($c_name, 'datetime')) {
                // name 以data 或 datetime 结尾，保存为datetime类型
                $tableColumn[] = '$table->datetime("' . $c_name . '")' . $column_end;
            } elseif (isset($example[$key]) && is_numeric($example[$key])) {
                // 数字处理方式
                if (strpos($example[$key], '.')) {
                    // 小数用字符串记录
                    $tableColumn[] = '$table->string("' . $c_name . '",' . $this->defaultVarCharLen . ')' . $column_end;
                } elseif (strlen($example[$key]) < 10) {
                    // 小于10位整数用int
                    $tableColumn[] = '$table->integer("' . $c_name . '")' . $column_end;
                } else {
                    // 长整数用string
                    $tableColumn[] = '$table->string("' . $c_name . '",' . $this->defaultVarCharLen . ')' . $column_end;
                }
            } elseif (isset($example[$key]) && strlen($example[$key]) > 150) {
                // 超长字符串用text
                $tableColumn[] = '$table->text("' . $c_name . '")' . $column_end;
            } elseif (isset($example[$key]) && strlen($example[$key]) > $this->defaultVarCharLen / 2) {
                // 较长字符串用255存储
                $tableColumn[] = '$table->string("' . $c_name . '",255)' . $column_end;
            } else {
                // 普通用默认长度varchar存储
                $tableColumn[] = '$table->string("' . $c_name . '",' . $this->defaultVarCharLen . ')' . $column_end;
            }
        }

        // 默认字段放在结尾
        $tableColumn = array_merge($tableColumn, $this->extraColumns);
        $r_path = database_path('migrations');
        $files = scandir($r_path);
        // 默认情况下，最后一个文件是刚建的migration
        $file = $r_path . DIRECTORY_SEPARATOR . array_pop($files);
        $content = file_get_contents($file);
        if ($has_primary) { //删除ID索引
            $content = str_replace('$table->increments(\'id\');', '', $content);
        }
        // 替换默认时间戳字段为所有字段
        $content = str_replace('$table->timestamps();', implode("\n            ", $tableColumn), $content);
        @file_put_contents($file, $content);
    }

    /**
     * 导入数据
     *
     * @author ctf <tengfei.chen@atommatrix.com>
     * @param $file
     */
    private function makeData($file)
    {
        $excelService = new ExcelService('Xlsx', 3);
        $result = $excelService->getAllData($file);

        // 对每个sheet依次生成数据
        foreach ($result as $_key => $data) {
            // 表名,取第一行第二列,行从1开始,列从0开始
            $report_name = $data[1][1] ?? "";
            if (!$report_name || !is_string($report_name) || !preg_match('/^[ a-zA-Z0-9\-_]*$/', $report_name)) {
                continue;
            }
            // 列名
            $columns = array_map(function ($_item) {
                return strtolower(str_replace([' ', '-'], '_', $_item));
            }, $data[2]);
            // 示例
            $example = $data[3] ?? [];

            $this->table = $this->table_prefix . strtolower($report_name) . $this->table_postfix;
            if (strlen($this->table) > 64) {
                $this->table = substr($this->table, 0, 64);
            }
            if (!Schema::hasTable($this->table)) {
                continue;
            }

            if (DB::table($this->table)->count()) {
                continue;
            }
            $this->myinfo('inserting_table', $this->table);
            echo "Inserting Table : " . $this->table . "\n";
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
            $excelService = new ExcelService('Xlsx', 100);
            do {
                $data = $excelService->importData($file, $importColumns, '', '', 3, 1, $_key);
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
    private function humpToLine($str)
    {
        $str = preg_replace_callback('/([A-Z]{1})/', function ($matches) {
            return '_' . strtolower($matches[0]);
        }, $str);
        return $str[0] == '_' ? substr($str, 1) : $str;
    }

    /**
     * 国际化的输出
     *
     * @param [type] $info
     * @param array $param
     * @param string $type
     * @return void
     * @author chentengfei
     * @since
     */
    public function myinfo($sign, $param = "", $type = 'info') 
    {
        $lang = require_once(__DIR__.'/../language/'.$this->language.'.php');
        $message = $lang[$sign] ?? $param;
        if ($param) {
            $message = sprintf($message, $param);
        }
        $this->$type($message);
    }
}
