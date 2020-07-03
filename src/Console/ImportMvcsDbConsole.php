<?php

namespace Callmecsx\Mvcs\Console;

use Callmecsx\Mvcs\Service\MvcsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

/**
 * 通过Excel导入table
 *
 * @author chentengfei <tengfei.chen@atommatrix.com>
 * @since  1970-01-01 08:00:00
 */
class ImportMvcsDbConsole extends Command
{

    // 导入类型: 1 结构 2 数据 3结构和数据
    const TYPE_STRUCTURE_ONLY = 1;
    const TYPE_DATA_ONLY = 2;
    const TYPE_STRUCTURE_DATA = 3;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mvcs:excel {file} {--type=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'create db from excel';

    // 当前表名
    protected $table;

    /**
     * Create a new command instance.
     *
     */
    public function __construct()
    {
        parent::__construct();
        $this->language = Config::get('mvcs.language') ?: 'zh-cn';
    }

    /**
     * Execute the console command.
     *
     */
    public function handle()
    {
        if (config('app.env') == 'production') {
            return $this->myinfo('deny');
        }
        $file = $this->argument('file');
        if (!is_file($file)) {
            return $this->myinfo('param_invalid','file');
        }
        $type = $this->option('type');
        $service = new MvcsService();
        $service->import($file, $type);
        
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
