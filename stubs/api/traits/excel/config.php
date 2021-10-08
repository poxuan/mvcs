<?php

return [
    'desc' => '更新数据状态接口',
    'routes' => [ // 扩展路由规则
        'put' => [
            'up' => '{id}/up',
            'down' => '{id}/down',
        ],
    ]
];