<?php


return [

    "service_path" => app_path().DIRECTORY_SEPARATOR.'Services',
    "validator_path" => app_path().DIRECTORY_SEPARATOR.'Validators',
    "controller_path" => app_path().DIRECTORY_SEPARATOR.'Http'.DIRECTORY_SEPARATOR.'Controllers',
    "model_path" => app_path().DIRECTORY_SEPARATOR.'Models',

    "service_namespace" => 'App\Services',
    "validator_namespace" => 'App\Validators',
    "controller_namespace" => 'App\Http\Controllers',
    "model_namespace" => 'App\Models',

    "service_base" => ['namespace'=>'App\Services','name'=>'BaseService'],
    "validator_base" => ['namespace'=>'App\Validators','name'=>'BaseValidator'],
    "controller_base" => ['namespace'=>'App\Http\Controllers','name'=>'Controller'],
    "model_base" => ['namespace'=>'App\Models','name'=>'BaseModel'],

    // 表中不该用户填充的字段
    "ignore_columns" => ['id','org_id','created_at','updated_at','deleted_at',
        'created_by','updated_by','deleted_by'],
    
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
