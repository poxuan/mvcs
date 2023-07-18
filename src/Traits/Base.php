<?php

namespace Callmecsx\Mvcs\Traits;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * 尽量使迁移时，只改 此文件、ExcelData、Import、Route 文件 
 *
 * @author chentengfei
 * @since
 */
trait Base
{
    /**
     * 获取数据库字段
     *
     * @author chentengfei <tengfei.chen@atommatrix.com>
     * @date   2018-08-13 18:16:11
     * @return array 
     */
    public function getTableColumns()
    {
        try {
            $this->connect = $this->connect ?: DB::getDefaultConnection();
            $connect = $this->config('connections.' . $this->connect, '', 'database.');
            DB::setDefaultConnection($this->connect);
            switch ($connect['driver']) { //
                case 'mysql':
                    return DB::select('select COLUMN_NAME as Field,COLUMN_DEFAULT as \'Default\',
                       IS_NULLABLE as \'Nullable\',COLUMN_TYPE as \'Type\',COLUMN_COMMENT as \'Comment\'
                       from INFORMATION_SCHEMA.COLUMNS where table_name = :table and TABLE_SCHEMA = :schema',
                        [':table' => $this->tableF, ':schema' => $connect['database']]);
                case 'sqlsrv':
                    return DB::select("SELECT a.name as Field,b.name as 'Type',COLUMNPROPERTY(a.id,a.name,'PRECISION') as L,
                        isnull(COLUMNPROPERTY(a.id,a.name,'Scale'),0)  as L2,
                        (case when a.isnullable=1 then 'YES' else 'NO' end) as Nullable,
                        isnull(e.text,'') as Default,isnull(g.[value],'') as Comment
                        FROM   syscolumns   a
                        left   join   systypes   b   on   a.xusertype=b.xusertype
                        inner  join   sysobjects   d   on   a.id=d.id     and   d.xtype='U'   and     d.name<>'dtproperties'
                        left   join   syscomments   e   on   a.cdefault=e.id
                        left   join   sys.extended_properties   g   on   a.id=g.major_id   and   a.colid=g.minor_id
                        left   join   sys.extended_properties   f   on   d.id=f.major_id   and   f.minor_id=0
                        where   d.name= :table order by a.id,a.colorder",
                        [':table' => $this->tableF, ':schema' => $connect['database']]);
                default:
                    $this->myinfo('db_not_support', $connect['driver']);
                    return [];
            }

        } catch (\Exception $e) {
            $this->myinfo('db_disabled', $this->connect);
            $this->myinfo('message', $e->getMessage(), 'error');
            return [];
        }

    }

    /**
     * 获取含有某字段的表名
     *
     * @author chentengfei <tengfei.chen@atommatrix.com>
     * @date   2018-08-13 18:16:11
     * @return array
     */
    public function getTableByColumn($column, $connect = 'default')
    {
        try {
            $config = $this->config('connections.' . $connect, '', 'database.');
            DB::setDefaultConnection($connect);
            switch ($config['driver']) { //
                case 'mysql':
                    return DB::select('select table_name as TableName from INFORMATION_SCHEMA.COLUMNS where COLUMN_NAME = :column and TABLE_SCHEMA = :schema',
                        [':column' => $column, ':schema' => $config['database']]);
                default:
                    $this->myinfo('db_not_support', $config['driver']);
                    return [];
            }

        } catch (\Exception $e) {
            $this->myinfo('db_disabled', $connect);
            $this->myinfo('message', $e->getMessage(), 'error');
            return [];
        }

    }

    /**
     * 获取配置
     *
     * @author chentengfei <tengfei.chen@atommatrix.com>
     * @date   2018-08-13 18:13:56
     * @param string $key 配置项
     * @param  mixed $default 默认值
     * @return mixed
     */
    public function config(string $key, $default = '', $base = 'mvcs.')
    {
        return Config::get($base.$key, $default);
    }

    /**
     * 获取默认连接数据库配置名
     *
     * @return string
     * @author chentengfei
     * @since
     */
    public function getDefaultConnection() {
        return DB::getDefaultConnection();
    }

    /**
     * 获取项目目录
     *
     * @param [type] $filepath
     * @param string $base
     * @return string
     * @author chentengfei
     * @since
     */
    public function projectPath($filepath, $base = 'base')
    {
        $pathfunc = $base.'_path';

        return $pathfunc($filepath);
    }

    /**
     * 获取路由文件名
     *
     * @param string $type
     * @return string
     * @author chentengfei
     * @since
     */
    public function getRouteFilename($type) {
        return $this->projectPath("routes/$type.php");
    }

    /**
     * 获取扩展文件位置
     *
     * @return string
     * @author chentengfei
     * @since
     */
    public function getTraitPath($style = '') {
        $style = $style ?: $this->style;
        return $this->getStubPath($style . DIRECTORY_SEPARATOR . 'traits');
    }

    /**
     * 返回模板文件位置
     *
     * @return string
     * @author chentengfei
     * @since
     */
    public function getStubPath($path = '') {
        if ($path) {
            return $this->projectPath('stubs/'.$path, 'resource');
        } else {
            return $this->projectPath('stubs', 'resource');
        }
    }

    /**
     * 返回模板文件位置
     *
     * @return string
     * @author chentengfei
     * @since
     */
    public function getStoragePath($path = '') {
        return $this->projectPath($path, 'storage');
    }

    /**
     * 返回迁移文件位置
     *
     * @return string
     * @author chentengfei
     * @since
     */
    public function getMigrationPath() {
        return $this->projectPath('migrations', 'database');
    }

    /**
     * 获取数据库表前缀
     *
     * @return string
     * @author chentengfei
     * @since
     */
    public function getDatabasePrifix() {
        return $this->config("connections." . $this->connect . '.prefix', '', 'database.');
    }

    /**
     * 复数形式,依赖laravel助手函数
     *
     * @param string $name
     * @return string
     * @author chentengfei
     * @since
     */
    public function plural(string $name) {
        return Str::plural($name);
    }

    /**
     * 获取表名
     *
     * @param $model
     * @param $style
     * @return string
     * @author chentengfei
     * @since
     */
    public function getTableName($model, $style = 'single') {
        $name = $this->humpToLine($model);
        if ($style == 'plural') {
            $name = $this->plural($name);
        }
        return $name;
    }

    /**
     * 获取代码风格配置
     * 
     * @param $style 风格
     * @param $key   键
     * @param $dafault 默认值
     * @return mixed
     */
    public function styleConfig(string $style, String $key = '', $default = null) {
        $bathpath = $this->getStubPath();
        $baseConfig = require $bathpath.DIRECTORY_SEPARATOR.'config.php';
        $spConfig = require $bathpath.DIRECTORY_SEPARATOR.$style.DIRECTORY_SEPARATOR.'config.php';
        $spConfig['modules'] = array_merge($baseConfig['modules'], $spConfig['modules']);
        if ($key) {
            return $this->arrayGet($spConfig, $key, $default);
        }
        return $spConfig ?: $default;
    }

    /**
     * 获取代码风格配置
     * 
     * @param $style 风格
     * @param $key   键
     * @param $dafault 默认值
     * @return mixed
     */
    public function traitConfig(string $style, string $name, String $key = '', $default = null) {
        $bathpath = $this->getStubPath($style);
        $ds = DIRECTORY_SEPARATOR;
        $styleConfig = require $bathpath.$ds.'config.php';
        if(in_array($name, $styleConfig['traits'])) {
            $traitConfig = require $bathpath.$ds.'traits'.$ds.$name.$ds.'config.php';
            if ($key) {
                return $this->arrayGet($traitConfig, $key, $default);
            }
            return $traitConfig ?? $default;
        }
        
        return $default;
    }

    /**
     * 获取模板配置
     *
     * @author chentengfei <tengfei.chen@atommatrix.com>
     * @date   2018-08-13 18:13:56
     * @param string $slug 模板简称
     * @param string $key 配置项
     * @param  mixed $default 默认值
     * @return mixed
     */
    public function stubConfig($slug, $key, $default = '')
    {
        return $this->styleConfig($this->style, "modules.$slug.$key", $default);
    }

    /**
     * 获取所有定义模板简称
     *
     * @author chentengfei <tengfei.chen@atommatrix.com>
     * @date   2018-08-13 18:13:56
     * @param string $slug 模板简称
     * @param string $key 配置项
     * @param  mixed $default 默认值
     * @return mixed
     */
    function getSlugs($style){
        return array_keys($this->styleConfig($style, 'modules'));
    }
}