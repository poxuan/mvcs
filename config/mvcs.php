<?php

return [
    /* 使用前请务必阅读 readme 文件 */
    // 版本信息
    'version'  => '3.1',
    // 语言包，目前只有这一个包。
    'language' => 'zh-cn',
    /* 模板相关配置 */
    // 模板风格
    'style' => 'api', // 默认风格
    'table_style' => 'single', // 表名风格， 只支持plural 和 single
    // 模板全局替换参数
    'global_replace' => [
        'author_info'  => env('AUTHOR', 'foo <foo@example.com>'),
        'main_version' => '1.0', 
        'sub_version'  => '1.0.' . date('ymd'), 
        'create_date'  => date('Y-m-d H:i:s')
    ],
    // 额外的替换类, 根据表格内容进行精细替换，须实现替换类
    'replace_classes' => [
        Callmecsx\Mvcs\Impl\ExampleReplace::class,
    ],
    // 替换参数标识
    'replace_fix' => '$[ ]',
    // 扩展模式下扩展名的前后缀，可以没有后缀，可在style中自定义
    'hook_fix' => '#{ }',
    // 标签功能标志
    'tags_fix' => '@{ }',//单空格分割前后缀
    'tags' => [
        // 支持不同标签嵌套，同名嵌套会报错
        // {foo} xxx {!foo} yyy {/foo} 返回为空 yyy保留 返回true xxx保留
        // {style:api} xxx {style:web} yyy {/style} 返回api xxx保留 返回web yyy保留 返回其他 全部块删除
        //
        // 'authcheck' => function ($columns) { // 操作限制于自己的
        //     foreach ($columns as $column) {
        //         if ($column->Field == 'author_id') {
        //             return "author_id";
        //         } else if ($column->Field == 'user_id') {
        //             return "user_id";
        //         }
        //     }
        //     return false;
        // },
        'authcheck' => false,
        'softdelete' => function ($columns) {
            foreach ($columns as $column) {
                if ($column->Field == 'deleted_at') {
                    return true;
                }
            }
            return false;
        },
        'base' => true, // 默认把
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
        // 公共名字空间，如使用 mvcs:make test/miniProgram 还会添加额外的一级名字空间 Test
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
        // 未定义decimal 精度时的默认值。
        "default_decimal_pre" => 10,
        "default_decimal_post" => 2,
        // EXCEL第三行，类型映射
        "type_transfar" => [
            "短句" => "string",
            "字符串" => "string",
            "网址" => "string",
            "地址" => "string",
            "邮箱" => "string",
            "图片" => "string",
            "多媒体" => "string",
            "字符" => "string",
            "枚举" => "enum",
            "列举" => "enum",
            "数字" => "integer",
            "整数" => "integer",
            "小数" => "decimal",
            "文章" => "text",
            "内容" => "text",
            "时间" => "datetime",
            "日期" => "date",
        ]
    ],
];
