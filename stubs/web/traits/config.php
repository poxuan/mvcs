<?php

return [
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
];