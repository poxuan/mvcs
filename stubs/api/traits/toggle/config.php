<?php

return [
    'desc' => '状态切换接口',
    'routes' => [ // 扩展路由规则
        'put' => [
            'toggle' => '{id}/toggle',
        ],
        'post' => [
            'batch' => 'batch',
        ],
    ]
];