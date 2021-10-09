<?php

namespace Callmecsx\Mvcs\Impl;

use Callmecsx\Mvcs\Interfaces\Replace;
use Callmecsx\Mvcs\Service\MvcsService;
use Callmecsx\Mvcs\Traits\Base;
use Callmecsx\Mvcs\Traits\Helper;

class ExampleReplace implements Replace {

    use Base,Helper;

    public $hasMany = <<<'EOF'

    public function %s() {
        return $this->hasMany('%s');
    }

EOF;

    public $belongsTo = <<<'EOF'

    public function %s() {
        return $this->belongsTo('%s');
    }

EOF;    

    /**
     * 获取替换数据数组
     */
    public function getReplaceArr(array $tableColumns, MvcsService $service) : array
    {
        $this->connect = $service->connect;
        if (empty($tableColumns)) {
            return [
                'validator_create_rule' => '',
                'validator_update_rule' => '',
                'validator_excel_rule' => '',
                'validator_excel_default' => '',
                'validator_message' => '',
                'model_fillable' => '',
                'model_relation' => '',
            ];
        }
        $validators = [];
        $relaies = $this->getTableRelaies($tableColumns, $service);
        $columns = [];
        
        foreach ($tableColumns as $column) {
            if (!in_array($column->Field, $service->ignoreColumns)) {
                $columns[] = $this->surround($column->Field);
                $validators[] = $this->getColumnInfo($column, $service);
            }
        }
        
        $validatorCreateRule = implode($this->tabs(3), array_map(function ($arr) {
            $rule = str_pad("'{$arr['column']}'", 25) . " => '" . implode('|', $arr['rule']) . "',";
            return str_pad($rule, 80) . '    //' . $arr['comment']."\n"; // 补充注释
        }, $validators));

        $validatorUpdateRule = str_replace("required", "sometimes", $validatorCreateRule);


        $validatorMessages = implode('', array_map(function ($arr) use ($service) {
            $messages = '';
            if ($arr['messages'] ?? []) {
                foreach ($arr['messages'] as $key => $message) {
                    $messages .= str_pad($this->tabs(2) . "'$key'", 28) . " => '$message',\n";
                }
            }
            return $messages;
        }, $validators));

        $validatorExcel = implode($this->tabs(3), array_map(function ($arr) {
            $nullable = $arr['nullable'] ? '选填' : '必填';
            $rules = [
                "'{$arr['comment']}#{$nullable}'",
                "'{$arr['example']}'",
            ];
            if (isset($arr['enum'])) {
                $rules[] = "{$arr['enum']}";
            } elseif (isset($arr['relate'])) {
                $rules[] = "{$arr['relate']}";
                $rules[] = "['rc' => 'name']"; // 关联表名
            }
            return str_pad("'{$arr['column']}'", 25) . ' => [' . implode(', ', $rules) . "],\n";
        }, $validators));

        $validatorExcelDefault = implode($this->tabs(3), array_map(function ($arr) {
            return str_pad("'{$arr['column']}'", 25) . ' => ' . $arr['default'] . ",\n";
        }, array_filter($validators, function($item) { return !is_null($item['default']);})));

        $result = [
            'validator_create_rule' => trim($validatorCreateRule),
            'validator_update_rule' => trim($validatorUpdateRule),
            'validator_excel_rule' => trim($validatorExcel),
            'validator_excel_default' => trim($validatorExcelDefault),
            'validator_message' => trim($validatorMessages),
            'model_fillable' => implode(',', $columns),
            'model_relation' => trim(implode("\n", $relaies)),
        ];
        return $result;
    }

    /**
     * 获取字段信息
     *
     * @author chentengfei <tengfei.chen@atommatrix.com>
     * @date   2018-08-13 18:14:08
     * @param $column 字段
     */
    public function getColumnInfo($column, $service)
    {
        $info = [];
        $info['column'] = $column->Field;
        // 字段注释，需要把可能导致代码异常的字符去掉
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
            } elseif ($this->startsWith($column->Type, 'date') || $this->startsWith($column->Type, 'time')) {
                $info['default'] = "date('Y-m-d H:i:s')";
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
            $enum = substr($column->Type, 5, -1);
            $str  = str_replace([' ','"',"'"], '', $enum);
            $enum = explode(',', $enum);
            $enum = array_map(function($item) {
                return "$item => $item";
            },$enum);
            $info['enum'] = "[ ".implode(',', $enum)." ]";
            $info['rule'][] = 'in:' . $str;
            $info['example'] = explode(",", $str)[0];
        }
        /**
         * 如果字段以 _id 结尾，认为是外键
         * if a column like xxx_id, regard as foreign key of xxx model
         */
        if ($this->endsWith($column->Field, '_id')) {
            $otherTable = str_replace('_id', '', $column->Field);
            $otherModel = $this->lineToHump($otherTable);
            $otherTable = $this->getTableName($otherTable, $this->config('table_style', 'single'));
            $info['relate'] = '\\' . $service->getNameSpace('M') . '\\' . ucfirst($otherModel).'::class';
            $info['rule'][] = 'exists:' . $otherTable . ',id';
            $info['messages'][$column->Field . '.exists'] = $otherTable . ' 不存在';
        }
        return $info;
    }

    public function getTableRelaies($columns, $service) 
    {
        $relaies = [];
        // 以键名查找外联表
        $foreignKey = $this->humpToLine($service->model)."_id";
        $foreignTables = $this->getTableByColumn($foreignKey, $service->connect);
        $prefix = $this->getDatabasePrifix();
        foreach($foreignTables as $foreignTable) {
            $tableName = $foreignTable->TableName;
            if ($prefix && $this->startsWith($tableName, $prefix)) {
                $tableName = substr($tableName, strlen($prefix));
            }
            $foreignModel = $this->lineToHump($tableName);
            $fullForeignModel = $service->getNameSpace('M') . '\\' . ucfirst($foreignModel);
            // 转换为复数形式
            $funcName = $this->plural($tableName); 
            $relaies[$funcName] = sprintf($this->hasMany, $funcName, $fullForeignModel);
        }
        // 
        foreach($columns as $column) {
            if ($this->endsWith($column->Field, '_id')) {
                $otherTable = str_replace('_id', '', $column->Field);
                $otherModel = $this->lineToHump($otherTable);
                $fullOtherModel = $service->getNameSpace('M') . '\\' . ucfirst($otherModel);
                $relaies[$otherModel] = sprintf($this->belongsTo, $otherTable, $fullOtherModel);
            }
        }
        return array_values($relaies);
    }
}

