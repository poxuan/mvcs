<?php

namespace Callmecsx\Mvcs\Traits;

/**
 * 非 lavavel 可能需重写此文件
 *
 * @author chentengfei
 * @since
 */
trait Route 
{
    /**
     * 添加路由
     *
     * @author chentengfei <tengfei.chen@atommatrix.com>
     * @date   2018-08-13 18:17:55
     */
    public function addRoutes()
    {
        if ($this->config('add_route')) {
            $routeStr = '';
            $group = false;
            $type = $this->config('route_type', 'api');
            if ($this->middleware) {
                $routeStr .= 'Route::middleware(' . \json_encode($this->middleware) . ')';
                $group = true;
            }
            if ($prefix = $this->config('routes.prefix')) {
                $routeStr .= ($routeStr ? "->prefix('$prefix')" : "Route::prefix('$prefix')");
                $group = true;
            }
            if ($namespace = $this->config('routes.namespace')) {
                $routeStr .= ($routeStr ? "->namespace('$namespace')" : "Route::namespace('$namespace')");
                $group = true;
            }
            if ($group) {
                $routeStr .= "->group(function(){\n";
            }
            $method = ['get', 'post', 'put', 'delete', 'patch'];
            $controller = $this->getClassName('C');
            $routefile = @file_get_contents($this->getRouteFilename($type));
            foreach ($method as $met) {
                $rs = $this->config('routes.' . $met, []);
                foreach ($rs as $m => $r) {
                    $alias = ($prefix ? $prefix.'.' : '') . $this->table.'.'.$m;
                    if (!strpos($routefile, $alias)) {
                        $routeStr .= "    Route::$met('{$this->table}/$r','$controller@$m')->name('$alias');\n";
                    }
                }
            }
            // 添加trait对应的路由
            foreach ($this->traits as $trait) {
                $routes = $this->config('traits.' . $trait.'.routes');
                if ($routes) {
                    foreach ($method as $met) {
                        $rs = $routes[$met] ?? [];
                        foreach ($rs as $m => $r) {
                            $alias = ($prefix ? $prefix.'.' : '') . $this->table.$m;
                            if (!strpos($routefile, $alias)) {
                                $routeStr .= "    Route::$met('{$this->table}/$r','$controller@$m')->name('$alias');\n";
                            }
                        }
                    }
                }
            }
            if (!strpos($routefile, "source('{$this->table}'")) {
                if ($this->config('routes.apiResource')) {
                    $routeStr .= "    Route::apiResource('{$this->table}','{$controller}');\n";
                } elseif ($this->config('routes.resource')) {
                    $routeStr .= "    Route::resource('{$this->table}','{$controller}');\n";
                }
            }
            if ($group) {
                $routeStr .= "});\n\n";
            }
            $handle = fopen($this->getRouteFilename($type), 'a+');
            fwrite($handle, $routeStr);
            fclose($handle);
        }
    }
}