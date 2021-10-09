<?php
/**
 * @var ${TYPE_HINT} ExcelRules
 */

namespace Callmecsx\Mvcs\Traits;


trait ExcelRules
{

    private $handled_unique = [];

    /**
     * 从元数据中过滤重复数据
     */
    public function ruleUnique(& $data ,$column,$modelClass = '')
    {
        $this->handled_unique[] = $column;
        $uniques = [];
        foreach ($data as $k=>$v) { //data中进行unique操作
            if (isset($uniques[$v[$column]]))
            {
                $this->error_lines[] = '第 ' . $k . ' 行 重复';
                unset($data[$k]);
            } else {
                $uniques[$v[$column]] = $k;
            }
        }
        if(class_exists($modelClass)) { //与表中数据进行unique操作
            $model = new $modelClass();
            $result = $model->whereIn($column,array_keys($uniques))->select($column,'id')->get();
            foreach ($result as $item) {
                foreach ($uniques as $value => $key) {
                    if ($item[$column] == $value) {
                        $data[$key]['id'] = $item->id;
                        $this->update_lines[$key] = $data[$key];
                        unset($data[$key]);
                        continue;
                    }
                }
            }
        }
    }

    /**
     * 将元数据中的时间戳改为时间格式
     */
    public function ruleDatetime(& $data ,$columns)
    {
        foreach ($data as $k=>$v) { //data中进行unique操作
            foreach ($columns as $column){
                if (isset($data[$k][$column]))
                {
                    $data[$k][$column] = date('Y-m-d H:i:s',strtotime($data[$k][$column]));
                }
            }
        }
    }

    /**
     * 将元数据中的非数字转为数字
     */
    public function ruleNumber(& $data ,$columns)
    {
        foreach ($data as $k=>$v) { //data中进行unique操作
            foreach ($columns as $column){
                if (isset($data[$k][$column]))
                {
                    if(!is_numeric($data[$k][$column]))
                    $data[$k][$column] = intval($data[$k][$column]);
                }
            }
        }
    }

    /**
     * 过滤元数据中的项
     */
    public function ruleFilter(& $data ,$callable = null)
    {
        foreach ($data as $k=>$v) { //data中进行unique操作
            if($callable and is_callable($callable)) {
                $data[$k] = array_filter($v,$callable);
            } else {
                $data[$k] = array_filter($v);
            }
        }
    }
}
