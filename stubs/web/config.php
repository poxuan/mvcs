<?php

return [
    'desc'   => 'a default web template (not yet complate)',
    'stubs'  => 'MVCIF',
    'default_traits' => [],
    'modules' => [
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
            'hook_fix' => ['js' => '///', '*' => '<!-- -->'], // 分别定义js 和 html 里的扩展模式下的包围体，默认为【#】
            'path' => resource_path('views'),
            'replace' => [
                'from' => function ($model, $columns) {
                    // todo
                    return "";
                },
            ],
        ],
    ],
    'traits' => [
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
            'desc' => '回复',
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
    ]
];