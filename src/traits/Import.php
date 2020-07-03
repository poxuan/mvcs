<?php

namespace Callmecsx\Mvcs\Traits;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

use function PHPSTORM_META\type;

/**
 * 此内容依赖laravel框架
 *
 * @author chentengfei
 * @since
 */
trait Import
{
    public $importTables = [];
    /**
     * 构建表结构
     *
     * @author ctf <tengfei.chen@atommatrix.com>
     * @param $file
     */
    private function makeStruct($sheet)
    {
        // 表名,取第一行第一列,行从1开始,列从0开始
        $table_name = strtolower(trim($sheet[1][0] ?: ""));
        if (!$table_name || !is_string($table_name) || !preg_match('/^[ a-zA-Z0-9\-_]*$/', $table_name)) {
            $this->myinfo('excel_table_error', $table_name);
            return ;
        }
        $table_name = $this->config('excel.table_prefix', '') . $table_name . $this->config('excel.table_postfix', '');
        if (strlen($table_name) > 64) {
            $this->myinfo('message', "Table name $table_name is too long");
            return;
        }
        if (Schema::hasTable($table_name)) {
            $this->myinfo('excel_table_exist', $table_name);
            return;
        }
        if (\in_array($table_name, $this->importTables)) {
            $this->myinfo('excel_multiple', $table_name);
            return;
        }
        $this->importTables[] = $table_name;
        $this->myinfo('creating_table', $table_name);
        echo "Creating Table : " . $table_name . "\n";
        // 修改 migration 文件
        // 列
        $columns = array_map('trim', $sheet[2]);
        // 示例
        $example = array_map('trim', $sheet[3]);
        $this->makeMigrationFile($columns, $example, $table_name);
    }

    /**
     * 执行迁移文件
     *
     * @author ctf <tengfei.chen@atommatrix.com>
     * @param array $columns 列名
     * @param array $rules 示例
     */
    public function migrate() {
        echo "Creating Table finish\n";
        $this->myinfo('migrating_table');
        Artisan::call('migrate');
    }

    /**
     * 生成迁移文件
     *
     * @author ctf <tengfei.chen@atommatrix.com>
     * @param array $columns 列名
     * @param array $rules 示例
     */
    private function makeMigrationFile($columns, $example, $table)
    {
        $has_primary = false; // 已有主键?
        foreach ($columns as $key => $column) {
            $tableColumn[] = $this->migrationColumn($column, $example[$key] ?? "", $columnPostfix);
        }
        // 创建 migration 文件
        Artisan::call('make:migration', ['name' => 'create_' . $table . '_table']);
        // 默认字段放在结尾
        $tableColumn = array_merge($tableColumn, $this->config("excel.extra_columns", []));
        $r_path = $this->getMigrationPath();
        $files = scandir($r_path);
        // 注意！！！这里偷懒了，默认情况下，最后一个文件是刚建的migration
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
     * 生成迁移字段的内容
     *
     * @param string $column
     * @param string $rule
     * @param array $type
     * @return void
     * @author chentengfei
     * @since
     */
    protected function migrationColumnPostfix($column, $comment = "", $type, $extra = null) 
    {
        $extra    = ""; 
        $nullable = "->nullable(false)";
        if (strpos($column, "*") >= 0) { // 必填
            $nullable = "";
            $column = str_replace('*', '', $column);
        }
        $c_comment = addslashes($comment ?: '');
        $c_type = $type[0];
        $c_type = $this->config("excel.type_transfar", [])[$c_type] ?? $c_type;
        if (\substr($c_type, -3) == 'int') {
            $c_type = str_replace('int', 'integer', $c_type);
        }
        if ($c_type == 'bool') {
            $c_type = 'boolean';
        }
        if ($extra['default'] ?? "") {
            if (strtoupper($extra["default"]) == 'CURRENT_TIMESTAMP') {
                $extra .= "->default(\DB::raw('CURRENT_TIMESTAMP'))";
            } else {
                $extra .= "->default('".addslashes($extra['default'])."')";
            }
        }
        if ($extra['index'] ?? "") {
            if (strtoupper($extra["index"]) == 'unique') {
                $extra .= "->unique()";
            } else {
                $extra .= "->index()";
            }
        }
        return $extra. $nullable . "->comment(\"{$c_comment}\");";
    }

    protected function migrationParse($header, $example = "") 
    {
        $keys = array_map('trim', explode("#", $header));
        if (count($keys) >= 3) { // 新格式支持，不再支持设置索引，仅认可Id为主键
            $column = $keys[0];
            $type  = $keys[1];
            $comment = $keys[2];
        } else {
            $column = $keys[0];
            $comment = $keys[1] ?? "";
            $type  = explode('#', $example)[0];
        }
        return [$column, $comment, explode('_', $type)];
    }

    /**
     * 生成迁移字段的内容
     *
     * @param string $header 表头
     * @param string $example 示例
     * @param string $has_primary 是否有主键
     * @return void
     * @author chentengfei
     * @since
     */
    protected function migrationColumn($header, $example, & $has_primary) 
    {
        list($column, $comment, $type) = $this->migrationParse($header, $example);

        $columnPostfix = $this->migrationColumnPostfix($column, $comment, $type);
        $column = trim($column, "*");
        if ($column == 'id') {
            $has_primary = true;
            $type[0] = 'increments';
        }
        $defaultVarcharLength = $this->config('excel.default_varchar_length', 50);
        $c_type = $type[0];
        if (\in_array($c_type, ['string', 'varchar'])) { //根据规则创建字段
            $length = $type[1] ?? $defaultVarcharLength;
            return '$' . "table->string('$column',$length){$columnPostfix}";
        } elseif (\in_array($c_type, ['decimal', 'double', 'float'])) { // 小数一律存decimal
            $pre = $type[1] ?? $this->config('excel.default_decimal_pre', 10);
            $post = $type[2] ?? $this->config('excel.default_decimal_post', 2);
            return '$' . "table->{$c_type}('$column', $pre, $post){$columnPostfix}";
        } elseif (\in_array($c_type, ['enum'])) { // 枚举的处理
            array_shift($type);
            if ($type) {
                $enum = implode(",", array_map(function ($item) {return "'".addslashes($item)."'";}, $type));
                return '$' . "table->enum('$column',[{$enum}]){$columnPostfix}";
            }
        } elseif (\in_array($c_type, ['bigincrements', 'biginteger', 'binary', 
            'boolean','char', 'date', 'datetime', 'datetimetz', 'increments', 
            'integer', 'jsonb', 'longtext', 'mediuminteger', 'tinyinteger', 'uuid', 
            'mediumtext', 'smallinteger', 'text', 'time', 'timestamp', 'json', ]
        )) {
            return '$' . "table->$c_type('$column'){$columnPostfix}";
        } elseif (is_numeric($c_type)) {
            // 数字处理方式
            if (strpos($c_type, '.')) {
                // 小数用decimal记录
                $pre = $this->config('excel.default_decimal_pre', 10);
                $post =$this->config('excel.default_decimal_post', 2);
                return '$' . "table->decimal('$column', $pre, $post){$columnPostfix}";
            } elseif (strlen($c_type) <= 10) {
                // 小于10位整数用int
                return '$' . "table->integer('$column'){$columnPostfix}";
            } else {
                // 长整数用string
                return '$' . "table->string('$column',$defaultVarcharLength){$columnPostfix}";
            }
        } elseif (strlen($c_type) > $defaultVarcharLength * 2) {
            // 超长字符串用text
            return '$table->text("' . $column . '")' . $columnPostfix;
        } elseif (strlen($c_type) > $defaultVarcharLength) {
            // 较长字符串用255存储
            return '$' . "table->string('$column', 255){$columnPostfix}";
        } else {
            // 普通用默认长度varchar存储
            return '$' . "table->string('$column',$defaultVarcharLength){$columnPostfix}";
        }
        return "";
    }

    /**
     * 导入数据
     *
     * @author ctf <tengfei.chen@atommatrix.com>
     * @param $file
     */
    private function makeImportData($sheet, & $table_name)
    {
        $table_name = strtolower(trim($sheet[1][0] ?: ""));
        if (!$table_name || !is_string($table_name) || !preg_match('/^[ a-zA-Z0-9\-_]*$/', $table_name)) {
            $this->myinfo('excel_table_error', $table_name);
            return ;
        }
        $table_name = $this->config('excel.table_prefix', '') . $table_name . $this->config('excel.table_postfix', '');
        $columns = [];
        $header = array_map('trim', $sheet[2]);
        $example = array_map('trim', $sheet[3]);
        foreach ($header as $key => $column) {
            list($column) = $this->migrationParse($column, $example[$key] ?? "");
            $columns[$key] = trim($column, "*");

        }
        $result = [];
        for($i = 4; $i <= count($sheet); $i++) {
            foreach($columns as $key => $column) {
                $result[$i][$column] = $sheet[$i][$key] ?? null;
            }
        }
        return $result;
    }
}