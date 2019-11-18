<?php

namespace Callmecsx\Mvcs\Traits;

trait Template 
{
    // public $rplace_prefix  = "<{"
    // public $rplace_postfix = "}>";
    function temp_replace($string, $values = []) 
    {
        $posNow = 0;
        $posArr = [];
        $fixlen = strlen($this->rplace_prefix);
        do {
            $startPos = strpos($string, $this->rplace_prefix, $posNow);
            $afterPos = strpos($string, $this->rplace_postfix, $posNow);
            $posNow = $afterPos;
            // 记录匹配到的位置及长度
            if ($startPos !== false && $afterPos !== false)
                $posArr[] = [$startPos, $afterPos - $startPos + $fixlen];
        } while($startPos !== false);
        $posArr = array_reverse($posArr);

        foreach ($posArr as $pos) {
            $item = substr($string, $pos[0], $pos[1]);
            $contain = trim(substr($item, $fixlen, $pos[1] - 2 * $fixlen));
            if ($contain[0] == ':') { //方法
                $fc = explode(substr($contain, 1),'(');
                if (function_exists($fc[0])) {
                    $params = array_map('trim', explode(',', substr($fc[1], 0, strlen($fc[1]))));
                    foreach ($params as &$param) {
                        $param = $this->temp_var($param, $values, $param);
                    }
                    $replacement = call_user_func_array($fc[0],$params);
                }
            } elseif ($contain[0] == '@') { // 运算公式,请确保每一项都为数值；
                
            } else {
                $replacement = $this->temp_var($contain, $values, '');
            }
            $string = substr_replace($string, $replacement, $pos[0], $pos[1]);
        }
        return $string;
    }

    function temp_var($key, $values, $default = null) 
    {
        $kv = $this->temp_kv($values);
        return $kv[$key] ?? $default;
    }

    function temp_calc($equation, $values, $default = null) 
    {
        $kv = $this->temp_kv($values);
        krsort($kv); // 按键倒序，防前缀匹配
        foreach ($kv as $k => $v) {
            $equation = str_replace($k,$v,$equation);
        }
        $result = eval("return $equation;");
        return  is_null($result) ? $default : $result;
    }

    function temp_kv($values, $pre = '')
    {
        $return = [];
        foreach($values as $key => $value) {
            if (is_array($value)) {
                $return = array_merge($return, $this->temp_kv($value, $pre.$key.'.'));
            } else {
                $return[$pre.$key] = $value;
            }
        }
        return $return;
    }
}