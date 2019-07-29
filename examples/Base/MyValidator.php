<?php
 
namespace App\Base;
 
use Illuminate\Validation\Validator;
 
/**
 * 
 * @desc 扩展验证类
 * @author helei
 */
class MyValidator extends Validator
{
    /**
     * 验证字符串后缀
     */
    public function validateEnds($attribute, $value , $rules)
    {
        return ends_with($value, $rules[0]);// 这里也可以直接将验证规则写在这里
    }

    /**
     * 验证字符串前缀
     */
    public function validateStarts($attribute, $value , $rules)
    {
        return starts_with($value, $rules[0]);// 这里也可以直接将验证规则写在这里
    }
 
}