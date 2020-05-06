<?php

namespace Callmecsx\Mvcs\Traits;


trait Tag 
{

    public $tagFix = '{ }';

    function solveTags($stub, $tags) 
    {
        // $tags = $this->config('tags', []);
        foreach($tags as $tag => $value ) {
            if(is_callable($value)) {
                $value = $value($this->model, $this->tableColumns, $this);
            }
            $stub = $this->tagReplace($stub, $tag, $value);
        }
        return $stub;
    }

    function tagStacks($stub, $tag) {
         // $tags_fix = $this->config('tags_fix', '{ }');
        list($tags_pre, $tags_post) = explode(' ', $this->tagFix);
        $patton = '/'.$tags_pre.'((!|\/)?'.$tag.'(:[\w]*)?)'.$tags_post.'/i';
        $m = preg_match_all($patton, $stub, $matches);
        $stacks = [];
        if ($m) {
            $last_pos = 0;
            $last_stack = 0;
            foreach ($matches[0] as $key => $match) {
                $last_pos = strpos($stub, $match, $last_pos);
                if (!isset($stacks[$last_stack]['start'])) {
                    $stacks[$last_stack]['start'] = $last_pos;
                }
                $stacks[$last_stack]['items'][] = [
                    'start'  => $last_pos,
                    'length' => strlen($matches[0][$key]),
                    'match'  => $matches[1][$key],
                ];
                if ($matches[1][$key][0] == '/') {
                    $stacks[$last_stack]['end'] = $last_pos + strlen($matches[0][$key]);
                    $last_stack++;
                }
            }
        }
        $stacks = array_reverse($stacks);
        return $stacks;
    }
    
    function tagReplace($stub, $tag, $value) {
        $stacks = $this->tagStacks($stub, $tag);
        foreach($stacks as $stack) {
            $stack_start = $stack['start'];
            $stack_end   = $stack['end'] ?? die("标签没有闭合");
            $replace     = "";
            foreach ($stack['items'] as $key => $item) {
                $match = explode(':', $item['match']);
                if (isset($match[1])) {
                    if ($match[1] == $value) {
                        $start = $item['start'] + $item['length'];
                        $end   = $stack['items'][$key + 1]['start'];
                        $replace = substr($stub, $start, $end - $start);
                        break;
                    }
                } elseif ($item['match'][0] == '!' && ! $value) {
                    $start = $item['start'] + $item['length'];
                    $end   = $stack['items'][$key + 1]['start'];
                    $replace = substr($stub, $start, $end - $start);
                    break;
                } elseif ($value) {
                    $start = $item['start'] + $item['length'];
                    $end   = $stack['items'][$key + 1]['start'];
                    $replace = substr($stub, $start, $end - $start);
                    break;
                }
            }
            $stub = substr($stub, 0, $stack_start) . $replace . substr($stub, $stack_end);
        }
        return $stub;
    }
}