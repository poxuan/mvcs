<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Base\Traits\Scope;

/**
 * model 基类示例
 *
 * @author chentengfei
 * @since  1.1.20190729
 */
class BaseModel extends Model
{
    use Scope, SoftDeletes;
}