<?php

return [
    /* 使用前请务必阅读 readme 文件 */
    // 语言包
    'language' => 'zh-cn', // 目前只有这个。
    // 代码代码，不是此项目版本，应随 stubs 的更新而更新
    'version' => '1.0',
    /* 模板相关配置 */
    // 模板风格
    'style' => 'api_default', // 默认风格
    'style_desc' => [
        'api_default' => 'a default api template',
        'web_default' => 'a default web template (not yet finish)',
    ],

    // 默认生成模板
    'default_stubs' => [
        'api_default' => 'MVCR',
        'web_default' => 'MVCSIF',
    ],
    // 各风格的默认扩展；
    'default_traits' => [
        'api_default' => ['excel', 'updown'],
    ],
    // 扩展配置，
    'traits' => [// 目录 => 简介
        'updown' => [
            'desc' => '更新数据状态接口',
            'routes' => [ // 路由规则
                'put' => [
                    '{id}/up' => 'up',
                    '{id}/down' => 'down',
                ],
            ]
        ],
        'excel'  => [
            'desc' => '导入导出数据接口',
            'routes' => [
                'post' => [
                    'import' => 'import',
                ],
                // GET 路由
                'get' => [
                    'template' => 'template',
                ],
            ]
        ],
    ],
    // 标签功能配置
    'tags_fix' => '{ }',//单空格分割前后缀
    'tags' => [
        // 不支持嵌套
        // {foo} xxx {!foo} yyy {/foo} 返回false yyy保留 返回true xxx保留
        // {bar:a} xxx {bar:b} yyy {/bar} 返回a xxx保留 返回b yyy保留 返回其他 全部块删除
        'user' => function ($model, $columns) {
            foreach ($columns as $column) {
                if ($column->Field == 'user_id') {
                    return 'user';
                }
                if ($column->Field == 'author_id') {
                    return 'author';
                }
            }
            return false;
        },
        'status' => function ($model, $columns) {
            foreach ($columns as $column) {
                if ($column->Field == 'status') {
                    return true;
                }
            }
            return false;
        },
    ],
    
    // 用户描述,用于注释中
    'author' => env('AUTHOR', 'foo <foo@example.com>'),
    // 模板公共配置
    'common' => [
        // model 模板配置
        'M' => [
            // stabs文件名,及替换参数前缀名
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
            // PS3：{name}_append 作为扩展模式使用
            'replace' => [
                // model_fillable 示例, 会覆盖预定义的值
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
    // api_default 模板组配置
    'api_default' => [
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
    // web_default 模板组配置
    'web_default' => [
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
                            if (preg_match('/_id/i', $column->Field, $match)) {
                                // todo 外键展示
                            } elseif (preg_match('/char/i', $column->Type, $match)) {
                                // todo 字符展示
                            } elseif (preg_match('/int/i', $column->Type, $match) || preg_match('/decimal/i', $column->Type, $match)) {
                                // todo 数字展示
                            } elseif (preg_match('/date(time)*/', $column->Type, $match)) {
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
            'postfix' => '/form.balde', // 最终生成文件 {path}/{Model}/form.balde.php
            'path' => resource_path('views'),
            'replace' => [
                'from' => function ($model, $columns) {
                    // todo
                    return "";
                },
            ],
        ],
    ],

    // 表中不该用户填充的字段
    "ignore_columns" => ['id', 'created_at', 'updated_at', 'deleted_at'],

    /* 自动添加路由配置 */
    // 是否自动添加路由
    "add_route" => true,
    // 路由类型,加到那个文件里
    "route_type" => 'api',
    // 添加路由数组
    "routes" => [
        // 默认路由 -> 方法
        'post' => [
            // '{id}/foo' => 'foo',
        ],
        // GET 路由等
        'get' => [],
        // 是否添加 apiResource？
        'apiResource' => true,
        // 是否添加 resource？
        'resource' => false,
        // 公共中间件，非全局
        'middlewares' => [],
        // 公共路由前缀
        'prefix' => '',
        // 公共名字空间，如使用 mvcs:make test/miniProgram 还会添加额外的一级名字空间 test
        'namespace' => '',
    ],

    /* mvcs:excel 脚本相关参数 */
    "excel" => [
        // 公共额外数据库字段，migrate 写法
        "extra_columns" => [
            //'$table->integer("org_id")->comment("组织ID");',
            '$table->timestamps();',
            '$table->timestamp("deleted_at")->nullable();',
        ],
        // 表名前缀
        "table_prefix" => "",
        // 表名后缀
        "table_postfix" => "",
        // 未定义varchar长度时的默认值。
        "default_varchar_length" => 50,
    ],
];
