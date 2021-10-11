<?php

return [
    'desc' => '导入导出接口',
    'routes' => [ // 扩展路由规则
        'get' => [
            'import' => 'import',   // 导入
            'template' => 'template', // 导入模板
            'export' => 'export', // 导出
        ],
    ]
];