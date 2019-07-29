<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * model 基类示例
 *
 * @author chentengfei
 * @since  1.1.20190729
 */
class BaseModel extends Model
{
    /**
     * 自定义查询规则
     *
     * @param [type] $query
     * @return void
     * @author chentengfei
     * @since  1.1.20190729
     */
    public function scopeDefault($query)
    {
        return $query;
    }

    public function scopeMyOrder($query, $order) 
    {
        switch($order) {
            case 'hot':
                return $query->hot();
            case 'recent':
                return $query->orderBy('created_at', 'desc');
            case 'origin':
                return $query->orderBy('id');
            case 'update':
                return $query->orderBy('updated_at', 'desc');
        }
        return $query->orderBy('created_at', 'desc');
    }

    public function scopeHot($query)
    {
        return $query->orderBy('hot', 'desc')->orderBy('created_at', 'desc');
    }

    public function scopeOnline($query)
    {
        return $query->where('is_online', 1);
    }

    public function scopeOffline($query)
    {
        return $query->where('is_online', 0);
    }

}