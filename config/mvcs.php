<?php


return [
    'default_stubs' => 'MVCS',
    'author' => 'chentengfei <tengfei.chen@atommatrix.com>', // 用户
    'stubs' => [
        'M' => [ // model 模板
            'name'      => 'model', // stabs文件名,及参数主名
            'post_fix'  => '',      //类名后缀
            'path'      => app_path().DIRECTORY_SEPARATOR.'Models', // 存储基础地址
            'namespace' => 'App\Models', // namespace
            'extands'   => ['namespace'=>'Illuminate\Database\Eloquent', 'name' => 'Model'], //继承基类。可以为空
            'extra'     => [
                'text' => function($model){ return $model;} // 额外的数据 在模板中对应 ${name}_{text}
            ],
        ],
        'V' => [ // 过滤器模板
            'name'      => 'validator',
            'post_fix'  => 'Validator',
            'path'      => app_path().DIRECTORY_SEPARATOR.'Validators',
            'namespave' => 'App\Validators',
            'extands'   => ['namespace'=>'App\Validators','name'=>'BaseValidator'],
        ],
        'C' => [ // 控制器模板
            'name'      => 'controller',
            'post_fix'  => 'Controller',
            'path'      => app_path().DIRECTORY_SEPARATOR.'Http'.DIRECTORY_SEPARATOR.'Controllers',
            'namespave' => 'App\Http\Controllers',
            'extands'   => ['namespace'=>'App\Http\Controllers','name'=>'Controller'],
        ],
        'S' => [ // 服务层模板
            'name'      => 'service',
            'post_fix'  => 'Service',
            'path'      => app_path().DIRECTORY_SEPARATOR.'Services',
            'namespave' => 'App\Services',
            'extands'   => [],
        ],
    ],
    // 表中不该用户填充的字段
    "ignore_columns" => ['id','created_at','updated_at','deleted_at','created_by','updated_by','deleted_by'],
    
    // 自动添加路由配置
    "add_route" => true,
    "route_type" => 'api',
    "routes" => [
        'post' => [ //method => route
            'import' => 'import'
        ],
        'get' => [
            'template' => 'template',
        ],
        'delete' => [],
        'put' => [
            'up' => 'up',
            'down' => 'down',
        ],
        'patch' => [],
        'apiResource' => true,
        'resource' => false,
        'middlewares' => [],
        'prefix' => '',
        'namespace' => '',
    ],

    //report  脚本相关
    "report" => [
        "extra_columns" => [
            //'$table->integer("org_id")->nullable()->comment("组织ID");',
            //'$table->string("report_id",100)->nullable()->comment("报表ID");',
            '$table->timestamps();',
        ],
        "table_prefix"  => "",
        "table_postfix" => "",
        "default_varchar_length" => 50,
    ],
];
