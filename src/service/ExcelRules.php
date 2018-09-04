<?php
/**
 * @var ${TYPE_HINT} ExcelRules
 */

namespace Poxuan\Mvcs\Service;


trait ExcelRules
{

    private $handled_unique = [];
    public function ruleUnique(& $data ,$column,$model = '')
    {
        $this->handled_unique[] = $column;
        $uniques = [];
        foreach ($data as $k=>$v) { //data中进行unique操作
            if (isset($uniques[$v[$column]]))
            {
                $this->error_lines[] = 'The line '.$k.' is repeated!';
                unset($data);
            }
            $uniques[$v[$column]] = $k;
        }
        if(class_exists($model)) { //与表中数据进行unique操作
            $model = new $model();
            $result = $model->default()->whereIn($column,array_keys($uniques))->select($column,'id')->get();
            foreach ($result as $item) {
                foreach ($uniques as $sku=>$k) {
                    if ($item->sku == $sku) {
                        $data[$k]['id'] = $item->id;
                        $this->update_lines[$k] = $data[$k];
                        unset($data[$k]);
                        continue;
                    }
                }
            }
        }
    }

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
