<?php

return [
    /* 使用前请务必阅读 readme 文件 */

    /* 模板相关配置 */
    // 模板风格
    'style' => 'api_default',
    // 语言包
    'language' => 'zh-cn',
    // 代码版本
    'version' => '1.0',
    // 默认生成模板
    'default_stubs' => [
        'api_default' => 'MVCS',
        'api_another' => 'MRQFC',
        'web_default' => 'MVCSIF',
    ],
    // 默认扩展；
    'default_traits' => [
        'excel','updown'
    ],
    // 用户描述,用于注释中
    'author' => 'chentengfei <tengfei.chen@atommatrix.com>',
    // 模板公共配置
    'common' => [
        // model 模板
        'M' => [
            // stabs文件名,及替换参数前缀名
            'name' => 'model',
            // 类名及文件名后缀
            'postfix' => '',
            // 文件基础地址
            'path' => app_path() . DIRECTORY_SEPARATOR . 'Models',
            // 基础名字空间
            'namespace' => 'App\Models',
            // 继承基类。可以为空
            'extands' => [
                'namespace' => 'Illuminate\Database\Eloquent', // 基类名字空间
                'name' => 'Model', // 基类类名
            ],
            // 模板中的替换字段
            // Model 中预定义 model_relay,model_fillable
            // Validator 中预定义 validator_rule、validator_column_rule、validator_column_default
            // PS：请不要使用 name、ns、use、extands、anno、traits 为键名，各模板均已预定义如下字段
            //     {name}_name 类名,{name}_ns 名字空间,{name}_use 基类use,{name}_extands 基类继承,
            //     {name}_anno 行注释，{name}_traits 扩展
            // PS2：请不要共用任何前缀，如定义 namespace 可能会被替换为 ${name}_name 的结果 + space
            'extra' => [
                // model_fillable 示例, 会覆盖预定义的值
                'fillable' => function ($model, $columns) {
                    $res = "";
                    foreach ($columns as $column) {
                        if (!in_array($column->Field, config('mvcs.ignore_columns'))) {
                            $v = [];
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
            'extands' => [
                'namespace' => 'App\Http\Controllers',
                'name' => 'Controller',
            ],
        ],
    ],

    // api_default 模板组配置
    'api_default' => [
        // 过滤器模板
        'V' => [
            'name' => 'validator',
            'postfix' => 'Validator',
            'path' => app_path() . DIRECTORY_SEPARATOR . 'Validators',
            'namespace' => 'App\Validators',
            'extands' => [],
        ],
        // 服务层模板
        'S' => [
            'name' => 'service',
            'postfix' => 'Service',
            'path' => app_path() . DIRECTORY_SEPARATOR . 'Services',
            'namespace' => 'App\Services',
            'extands' => [],
        ],
    ],
    // web_default 模板组配置
    'web_default' => [
        // 主视图模板
        'I' => [
            'name' => 'index',
            'postfix' => '/index.balde', // 最终生成文件 {path}/{Model}/index.balde.php
            'path' => resource_path('views'),
            'extra' => [
                'table' => function ($model, $columns) {
                    $arraylines = [];
                    foreach ($columns as $column) {
                        if (!in_array($column->Field, config('mvcs.ignore_columns'))) {
                            if (preg_match('/char/i', $column->Type, $match)) {
                                // todo 字符展示
                            } elseif (preg_match('/_id/i', $column->Field, $match)) {
                                // todo 外键展示
                            } elseif (preg_match('/int/i', $column->Type, $match) || preg_match('/decimal/i', $column->Type, $match)) {
                                // todo 数字展示
                            } elseif (preg_match('/date(time)*/', $column->Type, $match)) {
                                // todo 时间展示
                            }
                            // todo 其他展示
                        }
                    }
                    // 组成代码，添加tab
                    return implode($this->tabs(3, "\n"), $arraylines);
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
            'extra' => [
                'from' => function ($model, $columns) {
                    // todo
                    return "";
                },
            ],
        ],
    ],

    // 表中不该用户填充的字段
    "ignore_columns" => ['id', 'created_at', 'updated_at', 'deleted_at', 'created_by', 'updated_by', 'deleted_by'],

    /* 自动添加路由配置 */

    // 是否自动添加路由
    "add_route" => true,
    // 路由类型
    "route_type" => 'api',
    // 添加路由数组
    "routes" => [
        // post 路由 调用名 -> 方法名
        'post' => [
            'import' => 'import',
        ],
        // GET 路由
        'get' => [
            'template' => 'template',
        ],
        // DELETE 路由
        'delete' => [],
        // put 路由
        'put' => [
            '{id}/up' => 'up',
            '{id}/down' => 'down',
        ],
        // patch 路由
        'patch' => [],
        // 是否添加 apiResource？
        'apiResource' => true,
        // 是否添加 resource？
        'resource' => false,
        // 公共中间件
        'middlewares' => [],
        // 公共前缀
        'prefix' => '',
        // 公共名字空间，如使用 mvcs:make test/miniProgram 还会添加额外的一级名字空间 test
        'namespace' => '',
    ],

    /* report 脚本相关参数 */
    "report" => [
        // 公共额外数据库字段，migrate 写法
        "extra_columns" => [
            //'$table->integer("org_id")->nullable()->comment("组织ID");',
            //'$table->string("report_id",100)->nullable()->comment("报表ID");',
            '$table->integer("created_by")->nullable();',
            '$table->integer("updated_by")->nullable();',
            '$table->integer("deleted_by")->nullable();',
            '$table->timestamp("deleted_at")->nullable();',
            '$table->timestamps();',
        ],
        // 表名前缀
        "table_prefix" => "",
        // 表名后缀
        "table_postfix" => "",
        // 未定义varchar长度时的默认值。
        "default_varchar_length" => 50,
    ],
];
