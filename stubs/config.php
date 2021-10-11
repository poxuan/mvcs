<?php

return [
    // 模板公共配置
    'modules' => [
        // model 模板配置
        'M' => [
            // 模板文件名,同时也是替换参数前缀
            'name' => 'model',
            // 类名及文件名后部
            'postfix' => '',
            // 文件格式，默认是.php，可省略
            'ext' => '.php',
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
            //     {name}_name 类名,{name}_namespace 名字空间,{name}_use 基类use,{name}_extends 基类继承,
            //     {name}_anno 行注释，{name}_traits 扩展
            // PS2：{name}_hook_{position} 作为扩展模式使用, 默认由#[ ]包裹，可通过 hook_fix 配置调整
            'replace' => [
                // model_fillable 示例, 会覆盖预定义的值
                // 建议：模板中，替换内容使用下划线式写法，正式内容使用驼峰式写法
                'fillable' => function ($columns) {
                    $res = "";
                    foreach ($columns as $column) {
                        // column expample:
                        // Field => id
                        // Type => int(11)
                        // Default => null
                        // Nullable => NO
                        // Comment => 主键ID
                        if (!in_array($column->Field, config('mvcs.ignore_columns'))) {
                            $res .= "'" . $column->Field . "',";
                        }
                    }
                    return $res;
                },
                'properties' => function ($columns) {
                    $properties = [];
                    foreach ($columns as $column) {
                        if (!in_array($column->Field, config('mvcs.ignore_columns'))) {
                            $type = strpos($column->Type, 'int') !== false ? 'int' : 'string';
                            $properties[] .= " * @property $type $" . $column->Field . "";
                        }
                    }
                    return implode("\n", $properties);
                }
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
        // 请求层模板
        'Q' => [
            'name' => 'request',
            'postfix' => 'Request',
            'path' => app_path() . DIRECTORY_SEPARATOR . 'Http' . DIRECTORY_SEPARATOR . 'Requests',
            'namespace' => 'App\Http\Requests',
            'extends' => [],
        ],
        // 详情/编辑视图模板
        // 'F' => [
        //     'name' => 'from',
        //     'postfix' => '/form.balde', // 最终生成文件名 {path}/{Model}{postfix}{ext|.php}
        //     // 'ext' => '.vue', // 通过定义文件后缀生成非 php 文件
        //     'hook_fix' => ['js' => '//{ }', '*' => '<!--{ }-->'], // 分别定义js 和 html 里的扩展模式下的包围体，默认为【#】
        //     'path' => resource_path('views'),
        //     'replace' => [
        //         'from' => function ($columns) {
        //             // todo
        //             return "";
        //         },
        //     ],
        // ],
    ],
];