<?php
namespace Callmecsx\Mvcs\Interfaces;

use Callmecsx\Mvcs\Service\MvcsService;

interface Replace {
    /**
     * 获取替换数据数组
     */
    public function getReplaceData(Array $tableColumns, MvcsService $service) : array;
}