<?php

return [
    // 模板公共配置
    'modules' => [
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
];