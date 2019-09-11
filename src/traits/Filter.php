<?php

namespace Callmecsx\Mvcs\Traits;

use DB;

trait Filter 
{
    // 通过定义参数  filterRule 使用
    // 格式如下：
    // protected $filterRule = [
    //     'title'      => 'like', //
    //     'nickname'   => 'through:user,user_id,like,id',
    //     'status'     => '=', // 直接用的where
    //     'created_at' => 'between', 
    //     'sort'       => 'scope:MySort',
    //     'foo'        => 'raw:find_in_set(?, foo_ids)'
    // ];
    /**
     * 过滤方式
     *
     * @param Model $model
     * @param array $queries
     * @return void
     * @author chentengfei
     * @since
     */
    protected function filter($model, array $queries) 
    {
        foreach($this->filterRule as $column => $rule) {
            if (isset($queries[$column])) {
                $value = $queries[$column];
                $rules = explode(':',$rule);
                $func  = 'filter' . $rules[0];
                if (is_callable($this, $func)) {
                    $this->$func($model, $column, $value, $rules[1] ?? '');
                } else {
                    $model->where($column, $rule, $value);
                }
            }
        }
        foreach($this->filterDefault as $column => $item) {
            if (!isset($queries[$column])) {
                $rules = explode(':',$item[0]);
                $func  = 'filter' . $rules[0];
                if (is_callable($this, $func)) {
                    $this->$func($model, $column, $item[1] ?? '', $rules[1] ?? '');
                } else {
                    $model->where($column, $item[0], $item[1]);
                }
            }
        }
        return $model;
    }

    /**
     * 使用model scope作用域
     *
     * @param Model $model
     * @param array $queries
     * @return void
     * @author chentengfei
     * @since
     */
    protected function filterScope($model, string $column , string $value, string $scope) 
    {
        $model->$scope($value);
    }

    protected function filterLike($model, string $column,string $value) 
    {
        $model->where($column, 'like', '%'.$value.'%');
    }

    protected function filterEq($model, string $column,string $value) 
    {
        $model->where($column, '=', $value);
    }

    protected function filterLt($model, string $column,string $value) 
    {
        $model->where($column, '<', $value);
    }

    protected function filterGt($model, string $column,string $value) 
    {
        $model->where($column, '>', $value);
    }

    protected function filterElt($model, string $column,string $value) 
    {
        $model->where($column, '<=', $value);
    }

    protected function filterEgt($model, string $column,string $value) 
    {
        $model->where($column, '>=', $value);
    }

    protected function filterNeq($model, string $column, string $value) 
    {
        $model->where($column, '<>', $value);
    }

    protected function filterNull($model, string $column, string $value) 
    {
        if ($value == 1) {
            $model->whereNull($column);
        } else {
            $model->whereNotNull($column);
        }
    }

    protected function filterBetween($model,string $column,array $value) 
    {
        if (is_array($value) && count($value) == 2) {
            $model->whereBetween($column, $value);
        }
    }

    protected function filterIn($model,string $column,array $value) 
    {
        $model->whereIn($column, $value);
    }

    // protected function filterDate($model, string $column, string $value) 
    // {
    //     // todo
    // }

    // protected function filterDatetime($model, string $column, string $value) 
    // {
    //     // todo
    // }

    // protected function filterTime($model, string $column, string $value) 
    // {
    //     // todo
    // }

    protected function filterStatus($model, string $column, int $value) 
    {
        if ($value >= 0) {
            $model->where($column, $value);
        }
    }
    
    /**
     * 联表查询，使用多次查询替代
     *
     * @param Model $model
     * @param string $column
     * @param string|array $value
     * @param string $through
     * @return void
     * @author chentengfei
     * @since
     */
    protected function filterThrough ($model, $column, $value, $through) 
    {
        $throughs   = explode(',', $through);
        $table      = $throughs[0]; // 表名
        $ownerKey   = $throughs[1]; // 对应本表关联字段
        $compare    = $throughs[2] ?: '=';  // 比较方式
        $foreignKey = $throughs[3] ?: 'id'; // 关联表字段
        if ($compare == 'like') {
            $value = "%{$value}%";
        }
        $ids = Db::table($table)->where($column, $compare, $value)->pluck($foreignKey);
        if ($ids) {
            $model->whereIn($ownerKey, $ids);
        } else {
            $model->whereIn($ownerKey, []);
        }
    }

    protected function filterRaw ($model, $column, $value, $raw)
    {
        $model->whereRaw($raw, is_array($value) ? $value : [$value]);
    } 
}