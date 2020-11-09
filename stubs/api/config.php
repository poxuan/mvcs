<?php

return [
    'desc'   => 'a default api template',
    'stubs'  => 'MVC', //默认模板
    'default_traits' => ['toggle',], //默认扩展
    'modules' => [
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
                        if (substr($column->Field, -3) == '_id') {
                            $field = substr($column->Field, 0, -3);
                        }
                        $lines[] = "'" . $field . "'  => \$this->" . $field;
                        
                    }
                    return implode(",\n            ", $lines);
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