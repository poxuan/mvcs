<?php

return [
    'desc'   => 'a default api template',
    'stubs'  => 'MVC', //默认模板
    'traits' => ['toggle',], //默认扩展
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
                        if (ends_with($column->Field, '_id')) {
                            $field = substr($column->Field, 0, -3);
                        }
                        $lines[] = "'" . $field . "'  => \$this->" . $field;
                        
                    }
                    return implode(",\n            ", $lines);
                },
            ],
        ],
    ]
];