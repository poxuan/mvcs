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
        $stubs = array_keys($this->styleConfig($this->style, 'modules'));
        foreach ($stubs as $slug) {
            $name = $this->stubConfig($slug, 'name');
            $stubVar[$name . '_name'] = $this->getClassName($slug);
            $stubVar[$name . '_ns'] = $this->getNameSpace($slug); // 后缀不能有包含关系，故不使用 _namespace 后缀
            $stubVar[$name . '_use'] = $this->getBaseUse($slug);
            $stubVar[$name . '_extends'] = $this->getExtends($slug);
            $stubVar[$name . '_anno'] = stripos('_' . $this->only, $slug) ? '' : '// '; //是否注释掉
            $extra = $this->stubConfig($slug, 'replace', []);
            foreach ($extra as $key => $func) {
                $stubVar[$name . '_' . $key] = \is_callable($func) ? $func($this->model, $this->tableColumns, $this) : $func;
            }
        }
        $globalReplace = $this->config('global_replace', []);
        foreach($globalReplace as $key => $val) {
            $stubVar[$key] = $this->getReplaceVal($val);
        }
        foreach ($this->traits as $trait) {
            $item = $this->config('tags.'.$trait);
            if ($rep = $item['replace'] ?? '') {
                foreach($rep as $key => $val) {
                    $stubVar[$key] = $this->getReplaceVal($val);
                }
            }
        }
        // 根据数据库字段生成一些模板数据。
        $stubVar2 = $this->getBuiltInData($this->tableColumns);
        return array_merge($stubVar2, $stubVar);
    }

    public function getReplaceVal($val) {
        if ($val instanceof \Closure) {
            return $val($this->model, $this->tableColumns, $this);
        } elseif(is_string($val)) {
            return $val;
        } else {
            return strval($val);
        }
    }

    /**
     * 生成 预置配置
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
            $replaceData = (new $clazz())->getReplaceData($tableColumns, $this);
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
        $stub = $this->replaceTags($stub, $this->config('tags'));
        
        foreach ($params as $search => $replace) {
            $stub = str_replace($this->getReplaceName($search), $replace, $stub);
        }
        return $stub;
    }

    /**
     * 根据类缩写获取代码内容
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