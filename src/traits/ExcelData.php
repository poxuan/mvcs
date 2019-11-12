<?php
/**
 * @var ${TYPE_HINT} ExcelData
 */

namespace Callmecsx\Mvcs\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

trait ExcelData
{
    protected $cacheData = []; // 临时缓存数据

    /**
     * 按规则转化从表单获取的数据
     *
     * @author chentengfei <tengfei.chen@atommatrix.com>
     * @date   2018-08-06 19:32:17
     * @param $data
     * @param $columns
     */
    public function transDataFromExcel(& $data, $columns)
    {
        foreach ($data as $k => $item) {
            foreach ($item as $key => $value) {
                if (strpos($key,'#')) {
                    unset($item[$key]);
                } elseif (isset($columns[$key][2])) {
                    $regulation = $columns[$key][2];
                    if (is_callable($regulation)) { // 闭包或函数参数定义 字段值，整条数据, 是否正向转换
                        $item[$key] = $regulation($item[$key], $item);
                    } elseif (is_array($regulation)) {
                        $item[$key] = array_search($value, $regulation);
                    } elseif (class_exists($regulation) && (new $regulation() instanceof Model)) {
                        $item[$key] = $this->transRelateColumnFromExcel($item[$key], $regulation, $columns[$key][3] ?? []);
                    } else {
                        throw new \Exception('unknown rule:' . $regulation);
                    }
                } 
            }
            $data[$k] = $item;
        }
    }

    /**
     * 
     *
     * @param mixed $value
     * @param string $modelClass
     * @param array $extra
     * @return void
     * @author chentengfei
     * @since
     */
    public function transRelateColumnFromExcel($currentValue,string $modelClass,array $extra = [])
    {
        // 关联model
        $model = new $modelClass();
        // 关联字段
        $realateColumn = $extra['column'] ?? 'name';
        // 关联查询条件
        $condition = $extra['condition'] ?? [];
        $condition[] = [$realateColumn => $currentValue];
        // 由于表可能很大，字段可能值很少，缓存数据
        $cacheName = md5($modelClass.'::'.$realateColumn.'@'.$currentValue);
        if (isset($this->cacheData[$cacheName])) {
            return $this->cacheData[$cacheName];
        } elseif ($item = $model->where($condition)->find()) {
            $this->cacheData[$cacheName] = $item[$model->getKeyName()];
            
        } elseif ($extra['create'] ?? false) { //如果可创建的话,就创建一个
            $info = [
                $realateColumn => $currentValue,
            ];
            // 关联额外插入值
            $fillColumn = $extra['fill'] ?? [];
            $info = array_merge($info, $fillColumn);
            $item = $model->create($info);
            if ($item) {
                $this->cacheData[$cacheName] = $item[$model->getKeyName()];
            }
        } else { 
            // 不可用就将其置空
            $this->cacheData[$cacheName] = '';
        }
        return $this->cacheData[$cacheName];
    }

    /**
     * 按规则转化从表单获取的数据
     *
     * @author chentengfei <tengfei.chen@atommatrix.com>
     * @date   2018-08-06 19:32:17
     * @param $data
     * @param $columns
     */
    public function transDataToExcel(& $data, $columns)
    {
        foreach ($data as $k => $item) {
            foreach ($columns as $column => $rule) {
                $column2 = explode('#', $column)[0]; // 数据原字段
                if (isset($rule[2])) {
                    $regulation = $rule[2];
                    if (is_callable($regulation)) {
                        $regulation = $rule[3] ?? '';
                        if (is_callable($regulation)) {
                            $item[$column] = $regulation($item[$column2], $item);
                        }
                    } elseif (is_array($regulation)) {
                        $item[$column] = $regulation[$column] ?? $item[$column2];
                    } elseif (class_exists($regulation) && (new $regulation() instanceof Model)) {
                        $item[$column] = $this->transRelateColumnToExcel($item[$column2], $regulation, $rule[3] ?? []);
                    }
                } 
            }
            $data[$k] = $item;
        }
    }

    /**
     * 将数据库字段转为EXCEL字段
     *
     * @param mixed $currentValue
     * @param string $modelClass
     * @param array $extra
     * @return void
     * @author chentengfei
     * @since
     */
    public function transRelateColumnToExcel($currentValue,string $modelClass,array $extra = [])
    {
        $model = new $modelClass();
        // 关联字段
        $realateColumn = $extra['column'] ?? 'name';
        // 关联字段
        $realatePk = $model->getKeyName();
        // 由于EXCEL表可能很大，但此字段可能值较少，内部缓存数据
        $cacheName = md5($modelClass.'::'.$realatePk.'@'.$currentValue);
        if (isset($this->cacheData[$cacheName])) {
            return $this->cacheData[$cacheName];
        } elseif ($item = $model->where($realatePk , $currentValue)->find()) {
            $this->cacheData[$cacheName] = $item[$realateColumn];
            
        } else { 
            // 不可用就将其置空
            $this->cacheData[$cacheName] = '';
        }
        return $this->cacheData[$cacheName];
    }

    /**
     * 插入数据到数据库
     *
     * @author chentengfei <tengfei.chen@atommatrix.com>
     * @date   2018-08-07 16:15:51
     * @param string $table 表名
     * @param array $data 待添加数据
     * @param array $defaultColumn 字段默认值，被data覆盖
     * @param array $unsetColumn 过滤字段
     * @param array $baseColumns 基础字段，覆盖data
     * @return bool
     */
    public function insertToTable($table, $data, $defaultColumn = [], $unsetColumn = [], $baseColumns = [])
    {
        if (!$data) {
            return false;
        }
        foreach ($data as & $item) {
            $item = array_merge($defaultColumn, $item, $baseColumns);
            $item = array_except($item, $unsetColumn);
        }
        return DB::table($table)->insert($data);
    }
}
