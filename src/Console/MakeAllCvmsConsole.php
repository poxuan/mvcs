<?php

namespace Callmecsx\Mvcs\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class MakeCvmsAllConsole extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:all_mvcs {--connect=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'create templates of controller、validator、model、service';

    private $connect = null;

    /**
     * Create a new command instance.
     *
     * @param Filesystem $filesystem
     */
    public function __construct()
    {

    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $tables = $this->getTables();
        foreach ($tables as $table) {
            $tableName = array_values($table)[0];
            Artisan::call('make:mvcs', ['model' => $this->lineToHump($tableName)]);
        }
        $this->info("处理完成!");
    }

    /*
     * 下划线转驼峰
     */
    private function lineToHump($str)
    {
        $str = preg_replace_callback('/([-_]+([a-z]{1}))/i',function($matches){
            return strtoupper($matches[2]);
        },$str);
        return $str;
    }

    /**
     * 获取数据库表
     *
     * @author chentengfei <tengfei.chen@atommatrix.com>
     * @date   2018-08-13 18:16:11
     * @return array
     */
    public function getTables()
    {

        try {
            $this->connect = $this->option('connect')?:'';
            if ($this->connect) {
                DB::setDefaultConnection($this->connect);
            }
            return DB::select('show tables');
        } catch (\Exception $e) {
            $this->info('数据库配置不可用!');
            return [];
        }

    }

}
