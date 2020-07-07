<?php

return [
    // 模板公共配置
    'common' => [
        // model 模板配置
        'M' => [
            // stabs文件名,及repalce参数前缀名
            'name' => 'model',
            // 类名及文件名后缀
            'postfix' => '',
            // 文件放置地址
            'path' => app_path() . DIRECTORY_SEPARATOR . 'Models',
            // 基础名字空间
            'namespace' => 'App\Models',
            // 继承基类。可以为空
            'extends' => [
                'namespace' => 'Illuminate\Database\Eloquent', // 基类名字空间
                'name' => 'Model', // 基类类名
            ],
            // 模板中的替换字段
            // PS：各模板均已预定义如下字段，部分模板还预定了其他一些字段
            //     {name}_name 类名,{name}_ns 名字空间,{name}_use 基类use,{name}_extends 基类继承,
            //     {name}_anno 行注释，{name}_traits 扩展
            // PS2：请不要共用任何前缀，如定义 namespace 可能会被替换为 ${name}_name 的结果 + space
            // PS3：{name}_hook_{position} 作为扩展模式使用, 默认前缀是#，可以在各风格中中自定义各position的前后缀
            'replace' => [
                // model_fillable 示例, 会覆盖预定义的值
                // 建议：模板中，替换内容使用下划线式写法，正式内容使用驼峰式写法
                'fillable' => function ($model, $columns) {
                    $res = "";
                    foreach ($columns as $column) {
                        if (!in_array($column->Field, config('mvcs.ignore_columns'))) {
                            $res .= "'" . $column->Field . "',";
                        }
                    }
                    return $res;
                },
            ],
        ],
        // 控制器模板
        'C' => [
            'name' => 'controller',
            'postfix' => 'Controller',
            'path' => app_path() . DIRECTORY_SEPARATOR . 'Http' . DIRECTORY_SEPARATOR . 'Controllers',
            'namespace' => 'App\Http\Controllers',
            'extends' => [
                'namespace' => 'App\Http\Controllers',
                'name' => 'Controller',
            ],
        ],
        // 过滤器模板
        'V' => [
            'name' => 'validator',
            'postfix' => 'Validator',
            'path' => app_path() . DIRECTORY_SEPARATOR . 'Validators',
            'namespace' => 'App\Validators',
            'extends' => [],
        ],
    ],
    // api 风格模板组配置
    'api' => [
        // 资源层模板
        'R' => [
            'name' => 'resource',
            'postfix' => 'Resource',
            'path' => app_path() . DIRECTORY_SEPARATOR . 'Resources',
            'namespace' => 'App\Resources',
            'extends' => [
                'namespace' => 'Illuminate\Http\Resources\Json',
                'name' => 'Resource',
            ],
            'replace' => [
                'array' => function ($model, $columns) {
                    // todo
                    $lines = [];
                    foreach ($columns as $column) {
                        $field = $column->Field;
                        if (ends_with($column->Field, '_id')) {
                            $field = substr($column->Field, 0, -3);
                        }
                        $lines[] = "'" . $field . "'  => \$this->" . $field;
                        
                    }
                    return implode(",\n            ", $lines);
                },
            ],
        ],
    ],
    // web 风格模板组配置
    'web' => [
        // 主视图模板
        'I' => [
            'name' => 'index',
            'postfix' => '/index.balde', // 最终生成文件 {path}/{Model}/index.balde.php
            'path' => resource_path('views'),
            'replace' => [
                'table' => function ($model, $columns) {
                    $arraylines = [];
                    foreach ($columns as $column) {
                        if (!in_array($column->Field, config('mvcs.ignore_columns'))) {
                            if (preg_match('/_id$/i', $column->Field, $match)) {
                                // todo 外键展示
                            } elseif (preg_match('/char/i', $column->Type, $match)) {
                                // todo 字符展示
                            } elseif (preg_match('/int/i', $column->Type, $match) || preg_match('/decimal/i', $column->Type, $match)) {
                                // todo 数字展示
                            } elseif (preg_match('/date(time)*/i', $column->Type, $match)) {
                                // todo 时间展示
                            }
                            // todo 其他展示
                        }
                    }
                    // 组成代码，添加tab
                    return implode("\n            ", $arraylines);
                },
                'from' => function ($model, $columns) {
                    // todo
                    return "";
                },
            ],
        ],
        // 详情/编辑视图模板
        'F' => [
            'name' => 'from',
            'postfix' => '/form.balde', // 最终生成文件 {path}/{Model}{postfix}{ext|.php}
            // 'ext' => '.vue', // 通过定义文件后缀生成非 php 文件
            'hook_fix' => ['js' => '//#', 'body' => '<!-- -->'], // 分别定义js 和 html 里的扩展模式下的包围体，默认为【#】
            'path' => resource_path('views'),
            'replace' => [
                'from' => function ($model, $columns) {
                    // todo
                    return "";
                },
            ],
        ],
    ],
];