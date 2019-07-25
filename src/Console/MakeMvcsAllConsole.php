<?php

namespace Callmecsx\Mvcs\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class MakeMvcsAllConsole extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mvcs:make_all {--connect=}  {--style=} {--y|yes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '根据数据库中的表生成所有的文件';

    private $connect = null;

    private $language = 'zh-cn';

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
     * @return void
     */
    public function handle()
    {
        if (config('app.env') == 'production') {
            die("禁止在线上环境运行!");
        }
        $tables = $this->getTables();
        $params = [];
        if ($connect = $this->option('connect')) {
            $params['--connect'] = $connect;
        } else {
            $connect = DB::getDefaultConnection();
        }
        if ($style = $this->option('style')) {
            $params['--style'] = $style;
        }
        $check = false;
        if (!$this->option('yes')) {
            $res = $this->ask("创建文件前是否询问？[Y/n]", 'y');
            if (strtolower(trim($res))[0] == 'n') {
                $check = true;
            }
        }
        foreach ($tables as $table) {
            $tableName = array_values((array) $table)[0];
            if ($check) {
                $res = $this->ask("是否生成表 [$tableName] 相关文件[Y/n]", 'y');
                if (strtolower(trim($res))[0] == 'n') {
                    continue;
                }
            }
            $prefix = Config::get('database.connections.' . $connect . '.prefix', '');
            $params['model'] = $this->lineToHump(str_replace($prefix, '', $tableName));
            Artisan::call('mvcs:make', $params);
            $this->info("[$tableName] 相关文件已生成！");
        }
        $this->info("处理完成!");
    }

    /*
     * 下划线转驼峰
     */
    private function lineToHump($str)
    {
        $str = preg_replace_callback('/([-_]+([a-z]{1}))/i', function ($matches) {
            return strtoupper($matches[2]);
        }, $str);
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
            $this->connect = $this->option('connect') ?: '';
            if ($this->connect) {
                DB::setDefaultConnection($this->connect);
            }
            return DB::select('show table status where `Engine` is not null');
        } catch (\Exception $e) {
            $this->info('数据库配置不可用!');
            return [];
        }

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
