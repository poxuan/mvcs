<?php

namespace App\Base\Traits;

use DB;

trait Filter 
{
    // 通过定义参数  filterDefault filterRule 使用
    // 格式如下：
    // public $filterRule = [
    //     'title'      => 'like', //
    //     'nickname'   => 'through:user,user_id,like,id',
    //     'status'     => '=', // 直接用的where
    //     'created_at' => 'between', 
    //     'sort'       => 'scope:MySort',
    //     'foo'        => 'raw:find_in_set(?, foo)',
    //     'bar'        => [
    //         "-1" => 'rawCN:in (1,3,5)',
    //         "0"  => '',
    //         "*"  => '=' 
    //     ]
    // ];

    /**
     * 过滤方式
     *
     * @param Model $model
     * @param array $queries
     * @return Model
     * @author chentengfei
     * @since
     */
    public function scopeFilter($query,array $rules, array $queries, $default = []) 
    {
        $queries = array_merge($default, $queries);
        foreach($rules as $column => $rule) {
            if (isset($queries[$column])) {
                $value = $queries[$column];
                if (is_array($rule)) { // 规则是array，则按入参取
                    $rs = explode(':',$rule[$value] ?? ($rule['*'] ?? ''));
                } else {
                    $rs = explode(':',$rule);
                }
                if (empty($rs[0])) { // 规则为空。跳过
                    continue;
                }
                $func  = 'filter' . $rs[0];
                if (method_exists($this, $func)) {
                    $this->$func($query, $column, $value, $rs[1] ?? '');
                } else {
                    $query->where($column, $rs[0], $value);
                }
            }
        }
        return $query;
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
        if ($value) {
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

    protected function filterBool($model, string $column, int $value) 
    {
        switch ($value) {
            case 1:
                $model->where($column, '=', 1);
            break;
            case 2:
                $model->where($column, '=', 0);
            break;
        } 
    }
    
    /**
     * 两表查询
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
        $compare    = $throughs[2] ?? '=';  // 比较方式
        $foreignKey = $throughs[3] ?? 'id'; // 关联表字段
        if ($compare == 'like') {
            $value = "%{$value}%";
        }
        $model->whereRaw("$ownerKey in (select $foreignKey from $table where $column $compare ?)", [$value]);
    }

    /**
     * 原生sql，使用输入值做参数
     *
     * @param [type] $model
     * @param [type] $column
     * @param [type] $value
     * @param [type] $raw
     * @return void
     * @author chentengfei
     * @since
     */
    protected function filterRaw ($model, $column, $value, $raw)
    {
        $model->whereRaw($raw, is_array($value) ? $value : [$value]);
    } 

    /**
     * 原生sql，无参数
     *
     * @param [type] $model
     * @param [type] $column
     * @param [type] $value
     * @param [type] $raw
     * @return void
     * @author chentengfei
     * @since
     */
    protected function filterRawN ($model, $column, $value, $raw)
    {
        $model->whereRaw($raw);
    }

    /**
     * 原生sql，使用输入值做参数, 拼接column
     *
     * @param [type] $model
     * @param [type] $column
     * @param [type] $value
     * @param [type] $raw
     * @return void
     * @author chentengfei
     * @since
     */
    protected function filterRawC ($model, $column, $value, $raw)
    {
        $model->whereRaw($column . ' ' . $raw, is_array($value) ? $value : [$value]);
    }

    /**
     * 原生sql，无参数
     *
     * @param [type] $model
     * @param [type] $column
     * @param [type] $value
     * @param [type] $raw
     * @return void
     * @author chentengfei
     * @since
     */
    protected function filterRawCN ($model, $column, $value, $raw)
    {
        $model->whereRaw($column . ' ' . $raw);
    }
}