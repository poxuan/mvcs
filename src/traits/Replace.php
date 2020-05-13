<?php

namespace Callmecsx\Mvcs\Traits;

trait Replace 
{
    /**
     * 获取模板替换字段
     *
     * @author chentengfei <tengfei.chen@atommatrix.com>
     * @date   2018-08-13 18:14:56
     * @return array
     */
    function getStubParams()
    {
        $stubVar = [
            'table_name' => $this->table
        ];
        $this->tableColumns = $this->getTableColumns();
        $stubs = array_keys($this->config('common') + $this->config('' . $this->style));
        foreach ($stubs as $d) {
            $name = $this->stubConfig($d, 'name');
            $stubVar[$name . '_name'] = $this->getClassName($d);
            $stubVar[$name . '_ns'] = $this->getNameSpace($d); // 后缀不能有包含关系，故不使用 _namespace 后缀
            $stubVar[$name . '_use'] = $this->getBaseUse($d);
            $stubVar[$name . '_extends'] = $this->getExtends($d);
            $stubVar[$name . '_anno'] = stripos('_' . $this->only, $d) ? '' : '// '; //是否注释掉
            $extra = $this->stubConfig($d, 'replace', []);
            foreach ($extra as $key => $func) {
                $stubVar[$name . '_' . $key] = \is_callable($func) ? $func($this->model, $this->tableColumns, $this) : $func;
            }
        }
        $globalReplace = $this->config('global', []);
        foreach($globalReplace as $key => $val) {
            if ($val instanceof \Closure) {
                $stubVar[$key] = $val($this->model, $this->tableColumns);
            } elseif(is_string($val)) {
                $stubVar[$key] = $val;
            } else {
                $stubVar[$key] = strval($val);
            }
        }
        foreach ($this->traits as $trait) {
            $item = $this->config('tags.'.$trait);
            if ($rep = $item['replace'] ?? '') {
                foreach($rep as $key => $val) {
                    if ($val instanceof \Closure) {
                        $stubVar[$key] = $val($this->model, $this->tableColumns);
                    } elseif(is_string($val)) {
                        $stubVar[$key] = $val;
                    } else {
                        $stubVar[$key] = strval($val);
                    }
                }
            }
        }
        // 根据数据库字段生成一些模板数据。
        $stubVar2 = $this->getBuiltInData($this->tableColumns);
        return array_merge($stubVar2, $stubVar);
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
    function getBuiltInData($tableColumns)
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
                $columns[] = $this->surround($column->Field);
                $validators[] = $this->getColumnInfo($column, $relaies);
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

    public function getColumnInfo($column, & $relaies = null)
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
            $enum = str_replace(['enum', '(', ')', ' ', "'"], '', $column->Type);
            $enum = explode(',', $enum);
            $enum = array_map(function($item) {
                return "'$item' => '$item'";
            },$enum);
            $info['enum'] = "[ ".implode(',', $enum)." ]";
            $info['rule'][] = 'in:' . $info['enum'];
            $info['example'] = date('Y-m-d');
        }
        /**
         * 如果字段以 _id 结尾，认为是外键
         */
        if ($this->endsWith($column->Field, '_id')) {
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
     * 替换参数，生成目标文件内容
     *
     * @author chentengfei <tengfei.chen@atommatrix.com>
     * @date   2018-08-13 18:13:56
     * @param $stubData
     * @param $stub
     * @return mixed
     */
    function replaceStubParams($params, $stub)
    {
        // 先处理标签
        $stub = $this->replaceTags($stub, $this->config('tags'));
        $this->tagFix = $this->config('tags_fix', '{ }');
        foreach ($params as $search => $replace) {
            // 替换参数
            $stub = str_replace('$' . $search, $replace, $stub);
        }
        return $stub;
    }

    /**
     * 根据类型获取代码内容
     *
     * @param char $d
     * @return void
     * @author chentengfei
     * @since
     */
    function getTraitContent($d) {
        $typeName = $this->stubConfig($d, 'name', '');
        $traitContent = [];
        if ($this->traits) {
            foreach ($this->traits as $trait) {
                $traitPath = $this->projectPath('stubs/traits', 'resource') . DIRECTORY_SEPARATOR . $trait . DIRECTORY_SEPARATOR . $typeName . '.stub';
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
        return $traitContent;
    }

    function startsWith(string $haystack, string $needle, bool $sensitive = true)
    {
        $substr = substr($haystack,0, strlen($needle));
        if ($sensitive) 
            return $substr == $needle;
        return strtolower($substr) == strtolower($needle); 
    }

    function endsWith(string $haystack, string $needle, bool $sensitive = true)
    {
        $substr = substr($haystack, -1 * strlen($needle));
        if ($sensitive) 
            return $substr == $needle;
        return strtolower($substr) == strtolower($needle); 
    }
    
}