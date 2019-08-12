<?php

namespace App\Base\Traits;

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
                if (count($rules) == 2) {
                    $this->$func($model, $rules[1], $column, $value);
                } elseif (is_callable($this, $func)) {
                    $this->$func($model, $column, $value);
                } else {
                    $model->where($column, $rule, $value);
                }
            }
        }
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
    protected function filterScope($model, string $scope, string $column , string $value) 
    {
        $model->$scope($value);
    }

    protected function filterLike($model,string $column,string $value) 
    {
        $model->where($column, 'like', '%'.$value.'%');
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
     * @param string $through
     * @param string $column
     * @param string|array $value
     * @return void
     * @author chentengfei
     * @since
     */
    protected function  filterThrough($model, $through, $column, $value) 
    {
        $throughs   = explode(',', $through);
        $table      = $throughs[0];
        $ownerKey   = $throughs[1];
        $compare    = $throughs[2] ?: '=';
        $foreignKey = $throughs[3] ?: 'id';
        if ($compare == 'like') {
            $value = "%{$value}%";
        }
        $ids = Db::table($table)->where($column, $compare, $value)->pluck($foreignKey);
        if ($ids) {
            $model->whereIn($ownerKey, $ids);
        }
    }
}