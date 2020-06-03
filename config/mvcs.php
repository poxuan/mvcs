<?php

return [
    /* 使用前请务必阅读 readme 文件 */
    // 版本信息
    'version'  => '2.2',
    // 语言包，目前只有这一个包。
    'language' => 'zh-cn',
    /* 模板相关配置 */
    // 模板风格
    'style' => 'api', // 默认风格
    'style_config' => [ // 风格默认配置
        'api' => [
            'desc'   => 'a default api template',
            'stubs'  => 'MVC', //默认模板
            'traits' => ['toggle',], //默认扩展
        ],
        'web' => [
            'desc'   => 'a default web template (not yet complate)',
            'stubs'  => 'MVCIF',
            'traits' => [],
        ]
    ],
    // 模板全局替换参数
    'global_replace' => [
        'author_info'  => env('AUTHOR', 'foo <foo@example.com>'),
        'main_version' => '1.0',
        'sub_version'  => '1.0.' . date('ymd'),
        'create_date'  => date('Y-m-d H:i:s')
    ],
    // 扩展配置
    'traits' => [// 目录 => 简介
        'updown' => [
            'desc' => '更新数据状态接口',
            'routes' => [ // 扩展路由规则
                'put' => [
                    'up' => '{id}/up',
                    'down' => '{id}/down',
                ],
            ]
        ],
        'toggle' => [
            'desc' => '状态更新接口',
            'routes' => [ // 扩展路由规则
                'put' => [
                    'toggle_something' => '{id}/toggle_something',
                ],
                'post' => [
                    'batch_something' => 'batch_something',
                ],
            ]
        ],
        'reply' => [
            'desc' => '老师回复',
            'routes' => [ // 扩展路由规则
                'post' => [
                    'reply' => '{id}/reply',
                ]
            ]
        ],
        'excel'  => [
            'desc' => '导入导出数据接口',
            'routes' => [
                'post' => [
                    'import' => 'import',
                ],
                'get' => [
                    'template' => 'template',
                    'export'   => 'export'
                ],
            ]
        ],
    ],
    // 替换参数前缀标识
    'replace_fix' => '$',
    // 扩展模式下扩展名的前后缀，可以没有后缀
    'hook_fix' => '#',
    // 标签功能配置
    'tags_fix' => '{ }',//单空格分割前后缀
    'tags' => [
        // 支持不同标签嵌套，同名嵌套会报错
        // {foo} xxx {!foo} yyy {/foo} 返回为空 yyy保留 返回true xxx保留
        // {style:api} xxx {style:web} yyy {/style} 返回api xxx保留 返回web yyy保留 返回其他 全部块删除
        'style' => function ($model, $columns, $obj) {
            return $obj->style;
        },
        'user' => false,
        'usercheck' => false,
        'status' => function ($model, $columns) {
            foreach ($columns as $column) {
                if ($column->Field == 'status') {
                    return true;
                }
            }
            return false;
        },
        'softdelete' => function ($model, $columns) {
            foreach ($columns as $column) {
                if ($column->Field == 'deleted_at') {
                    return true;
                }
            }
            return false;
        },
        'mytoarray' => true,
        'resource' => false,
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
        // 方法 -> 路由
        'post' => [
            // 'foo' => '{id}/foo',
        ],
        // GET 路由等
        'get' => [
            'simple' => 'simple',
        ],
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
