<?php

namespace Callmecsx\Mvcs\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Callmecsx\Mvcs\Service\MvcsService;
use Callmecsx\Mvcs\Traits\Helper;

/**
 * 按模板生成文件脚本
 *
 * @author chentengfei <tengfei.chen@atommatrix.com>
 * @since  1970-01-01 08:00:00
 */
class AppendMvcsConsole extends Command
{
    use Helper;
    // 脚本命令
    protected $signature = 'mvcs:append {model} {--only=} {--style=} {--traits=} {--connect=}';

    // 脚本描述
    protected $description = '扩展模板代码';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        if (Config::get('app.env') == 'production') {
            return $this->myinfo('deny', '', 'error');
        }
        $model = ucfirst($this->lineToHump($this->argument('model')));
        if (empty($model)) {
            return $this->myinfo('param_lack', 'model', 'error');
        }
        $service = new MvcsService();
        $options = array_filter($this->options());
        $service->append($model, $options);
    }
}
