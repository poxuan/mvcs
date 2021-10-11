<?php

namespace Callmecsx\Mvcs\Traits;

trait Replace 
{
    /**
     * 获取模板替换字段
     *
     * @author chentengfei <tengfei.chen@atommatrix.com>
     * @date   2018-08-13 18:14:56
     * @return array
     */
    function getStubParams()
    {
        $stubVar = [
            'table_name' => $this->table
        ];
        $this->tableColumns = $this->getTableColumns();
        $slugs = $this->getSlugs($this->style);
        // 各模板替换数组
        foreach ($slugs as $slug) {
            $name = $this->stubConfig($slug, 'name');
            $stubVar[$name . '_name'] = $this->getClassName($slug);
            $stubVar[$name . '_namespace'] = $this->getNameSpace($slug); // 后缀不能有包含关系，故不使用 _namespace 后缀
            $stubVar[$name . '_use'] = $this->getBaseUse($slug);
            $stubVar[$name . '_extends'] = $this->getExtends($slug);
            $stubVar[$name . '_anno'] = stripos('_' . $this->only, $slug) ? '' : '// '; //是否注释掉
            $extra = $this->stubConfig($slug, 'replace', []);
            foreach ($extra as $key => $func) {
                $stubVar[$name . '_' . $key] = \is_callable($func) ? $func($this->model, $this->tableColumns, $this) : $func;
            }
        }
        // 全局替换数组
        $globalReplace = $this->config('global_replace', []);
        foreach($globalReplace as $key => $val) {
            $stubVar[$key] = $this->getReplaceVal($val);
        }
        // 标签替换数组
        foreach ($this->traits as $trait) {
            $item = $this->config('tags.'.$trait);
            if ($rep = $item['replace'] ?? '') {
                foreach($rep as $key => $val) {
                    $stubVar[$key] = $this->getReplaceVal($val);
                }
            }
        }
        // 预定类替换数组
        $stubVar2 = $this->getBuiltInData($this->tableColumns);
        return array_merge($stubVar2, $stubVar);
    }

    public function getReplaceVal($val) {
        if ($val instanceof \Closure) {
            return $val($this->tableColumns, $this);
        } elseif(is_string($val)) {
            return $val;
        } else {
            return strval($val);
        }
    }

    /**
     * 替换类替换词
     *
     * @author chentengfei <tengfei.chen@atommatrix.com>
     * @date   2018-08-13 18:14:08
     * @param $tableColumns
     * @param $columns
     * @param $validatorRule
     * @param $validatorExcel
     * @param $validatorExcelDefault
     */
    function getBuiltInData($tableColumns)
    {
        $result = [];
        $replaceClasses = $this->config('replace_classes', []);
        foreach($replaceClasses as $clazz) {
            $replaceData = (new $clazz())->getReplaceArr($tableColumns, $this);
            $result = array_merge($result, $replaceData);
        }
        return $result;
    }

    /**
     * 替换参数，生成目标文件内容
     *
     * @author chentengfei <tengfei.chen@atommatrix.com>
     * @date   2018-08-13 18:13:56
     * @param $stubData
     * @param $stub
     * @return mixed
     */
    function replaceStubParams($params, $stub)
    {
        // 先处理标签
        $tags = $this->config('tags');
        $slugs = $this->getSlugs($this->style);
        foreach ($slugs as $slug) {
            $tags[$slug] = stripos('_' . $this->only, $slug) ? true : false;
        }
        $stub = $this->replaceTags($stub, $tags);
        
        foreach ($params as $search => $replace) {
            $stub = str_replace($this->getReplaceName($search), $replace, $stub);
        }
        return $stub;
    }

    /**
     * 根据模板缩写获取扩展代码
     *
     * @param char $slug
     * @return array
     * @author chentengfei
     * @since
     */
    function getTraitContent($slug) {
        $typeName = $this->stubConfig($slug, 'name', '');
        $traitContent = [];
        if ($this->traits) {
            foreach ($this->traits as $trait) {
                $traitPath = $this->getTraitPath() . DIRECTORY_SEPARATOR . $trait . DIRECTORY_SEPARATOR . $typeName . '.stub';
                $handle = @fopen($traitPath, 'r+');
                $point  = 'body';
                if ($handle) {
                    while(!feof($handle)) {
                        $line = fgets($handle);
                        if (substr($line,0,2) == '@@') { // 双@开头作为位置标记
                            $point = trim(substr($line,2));
                        } elseif(isset($traitContent[$point])) {
                            $traitContent[$point] .= $line;
                        } else {
                            $traitContent[$point] = $line;
                        }
                    }
                    fclose($handle);
                }
            }
        }
        return $traitContent;
    }

    
}