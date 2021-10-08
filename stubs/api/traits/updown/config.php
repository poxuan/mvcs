<?php

return [
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
];