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
    "validator_base" => null,
    "controller_base" => ['namespace'=>'App\Http\Controllers','name'=>'Controller'],
    "model_base" => ['namespace'=>'App\Models','name'=>'BaseModel'],
];
