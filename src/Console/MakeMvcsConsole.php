<?php

namespace Callmecsx\Mvcs\Console;

use Callmecsx\Mvcs\Service\MvcsService;
use Callmecsx\Mvcs\Traits\Helper;
use Illuminate\Console\Command;

/**
 * 按模板生成文件脚本
 *
 * @author chentengfei <tengfei.chen@atommatrix.com>
 * @since  1970-01-01 08:00:00
 */
class MakeMvcsConsole extends Command
{
    use Helper;
    // 脚本命令
    protected $signature = 'mvcs:make {model} {--force=} {--only=} {--connect=} {--middleware=} {--style=} {--traits=}';

    // 脚本描述
    protected $description = '根据预定的文件模板创建文件';

    
    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        if (config('app.env', 'local') == 'production') {
            return $this->myinfo('deny', '', 'error');
        }
        if (config('mvcs.version', '0') < '3.0') {
            return $this->myinfo('version_deny', '3.0', 'error');
        }
        $model = ucfirst($this->lineToHump($this->argument('model')));
        if (empty($model)) {
            return $this->myinfo('param_lack', 'model', 'error');
        }
        if (!preg_match('/^[a-z][a-z0-9]*$/i',$model)) {
            return $this->myinfo('invalid_model', $model, 'error');
        }
        $service = new MvcsService();
        $options = array_filter($this->options());
        $service->create($model, $options);
    }

    /**
     * 国际化的输出
     *
     * @param [type] $info
     * @param array $param
     * @param string $type
     * @return void
     * @author chentengfei
     * @since
     */
    public function myinfo($sign, $param = "", $type = 'info') 
    {
        $lang = require_once(__DIR__.'/../language/'.$this->language.'.php');
        $message = $lang[$sign] ?? $param;
        if ($param) {
            $message = sprintf($message, $param);
        }
        $this->$type($message);
    }
}
