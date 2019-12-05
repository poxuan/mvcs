<?php

namespace App\Base\Traits;

/**
 * 自定义的scope
 *
 * @author chentengfei
 * @since
 */
trait Scope 
{
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
                return $query->orderBy('id', 'desc');
            case 'origin':
                return $query->orderBy('id');
            case 'update':
                return $query->orderBy('updated_at', 'desc');
        }
        return $query->orderByRaw($order);
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
