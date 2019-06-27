<?php


return [
    /* 使用前请务必阅读 readme 文件 */

    /* 模板相关配置 */
    // 默认生成模板
    'default_stubs' => 'MVCS',
    // 用户描述
    'author' => 'chentengfei <tengfei.chen@atommatrix.com>', 
    // 模板配置数组
    'stubs' => [
        // model 模板
        'M' => [
            // stabs文件名,及参数主名
            'name'      => 'model',
            // 类名后缀
            'postfix'   => '',
            // 文件基础地址
            'path'      => app_path().DIRECTORY_SEPARATOR.'Models',
            // 基础名字空间
            'namespace' => 'App\Models',
            // 继承基类。可以为空
            'extands'   => [
                'namespace' =>'Illuminate\Database\Eloquent', // 基类名字空间
                'name'      => 'Model' // 基类类名
            ], 
            // 模板中的替换字段
            // Model 中预定义 model_relay,model_fillable
            // Validator 中预定义 validator_rule、validator_column_rule、validator_column_default
            // PS：请不要使用 name、ns、use、extands 为键名，各模板均已预定义{name}_name,{name}_ns,{name}_use,{name}_extands
            // PS：请不要共用任何前缀，如定义 namespace 可能会替换为 ${name}_name 的结果 + space
            'extra'     => [

                // model_fillable 示例, 会覆盖预定义的值
                // 可以为方法或字符串（但字符串完全每必要吧）；
                // 为方法时，传入两个数据
                // $model   骆驼式，如使用 make:mvcs miniProgram 命令，此处将为 MiniProgram
                // $columns 可能从数据库中读取到的字段，object 或 空，具体结构请自行输出查看

                'fillable' => function($model, $columns) {
                    $res = "";
                    foreach ($columns as $column) {
                        if (!in_array($column->Field, config('mvcs.ignore_columns'))) {
                            $v = [];
                            $res .= "'".$column->Field."',";
                        }
                    }
                    return $res;
                }
            ],
        ],
        // 过滤器模板（不喜欢的话可以不用，自己重新编辑模板和配置）
        'V' => [
            'name'      => 'validator',
            'postfix'   => 'Validator',
            'path'      => app_path().DIRECTORY_SEPARATOR.'Validators',
            'namespave' => 'App\Validators',
            'extands'   => []
        ],
        // 控制器模板
        'C' => [ 
            'name'      => 'controller',
            'postfix'   => 'Controller',
            'path'      => app_path().DIRECTORY_SEPARATOR.'Http'.DIRECTORY_SEPARATOR.'Controllers',
            'namespave' => 'App\Http\Controllers',
            'extands'   => ['namespace'=>'App\Http\Controllers','name'=>'Controller'],
        ],
        // 服务层模板
        'S' => [
            'name'      => 'service',
            'postfix'   => 'Service',
            'path'      => app_path().DIRECTORY_SEPARATOR.'Services',
            'namespave' => 'App\Services',
            'extands'   => [],
        ],
    ],
    // 表中不该用户填充的字段
    "ignore_columns" => ['id','created_at','updated_at','deleted_at','created_by','updated_by','deleted_by'],
    
    /* 自动添加路由配置 */
    // 是否自动添加路由
    "add_route" => true,
    // 路由类型
    "route_type" => 'api', 
    // 路由数组
    "routes" => [ 
        // post 路由 调用名 -> 方法名
        'post' => [ 
            'import' => 'import'
        ],
        // GET 路由
        'get' => [ 
            'template' => 'template',
        ],
        // DELETE 路由
        'delete' => [],
        // put 路由
        'put' => [ 
            'up' => 'up',
            'down' => 'down',
        ],
        // patch 路由
        'patch' => [],
        // 是否添加 apiResource？
        'apiResource' => true,
        // 是否添加 resource？
        'resource'    => false,
        // 公共中间件
        'middlewares' => [],
        // 公共前缀
        'prefix'      => '',
        // 公共名字空间，如使用 make:mvcs text/miniProgram 还会添加额外的名字空间 text
        'namespace'   => '',     
    ],

    /* report 脚本相关参数 */
    "report" => [
        // 公共额外数据库字段，migrate 写法
        "extra_columns" => [
            //'$table->integer("org_id")->nullable()->comment("组织ID");',
            //'$table->string("report_id",100)->nullable()->comment("报表ID");',
            '$table->timestamps();',
        ],
        // 表名前缀
        "table_prefix"  => "", 
        // 表名后缀
        "table_postfix" => "", 
        // 未定义varchar长度时的默认值。
        "default_varchar_length" => 50, 
    ],
];
