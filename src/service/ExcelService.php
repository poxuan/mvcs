<?php
/**
 * @var ${TYPE_HINT} ExcelService
 */

namespace Poxuan\Mvcs\Service;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;



class ExcelService
{
    use ExcelRules; //额外规则

    public $error_lines = [];  //错误行错误原因
    public $update_lines = []; //更新行数据

    private $offset  = 0;      //当前读取行数
    private $limit   = 1000;   //每次读取行数
    private $hasMore = true;   //还有更多数据
    private $type    = 'Xlsx'; //默认文档类型

    private $trans = []; // 转换字段


    /**
     * ExcelService constructor.
     *
     * @param int $limit 每次读取行数
     */
    public function __construct($type = 'Xlsx',$limit = 1000)
    {
        if (!in_array(ucfirst($type),['Xlsx','Xls'])) {
            throw new \Exception('文件类型不支持！');
        }
        $this->type = ucfirst($type);
        $this->limit = $limit;
    }

    /**
     * 从EXCEL文档中获取指定数据
     *
     * @author chentengfei <tengfei.chen@atommatrix.com>
     * @date   2018-08-06 19:31:08
     * @param string $file         文件,暂只支持xlsx
     * @param array  $keys         顺序读取的键,
     * @param int    $rowStart     开始读取行
     * @param int    $columnStart  开始读取列
     * @return array
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
     */
    private function getDataFromExcel($file,$keys,$rowStart = 2,$columnStart = 1,$sheet = 0)
    {
        $reader =  IOFactory::createReader($this->type);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($file); //载入excel表格
        if ($sheet) {
            $worksheet = $spreadsheet->getSheet($sheet);
        } else {
            $worksheet = $spreadsheet->getActiveSheet();
        }
        $highestRow = $worksheet->getHighestRow(); // 总行数
        //$highestColumn = $worksheet->getHighestColumn(); // 总列数

        // 剩余行数
        $rows = $highestRow - $rowStart - $this->offset;
        if ($rows < $this->limit){
            $this->hasMore = false;
        }
        // 开始读取行
        $start = $rowStart+$this->offset;
        // 下标增加
        $this->offset += $this->limit;
        // 结束行
        $end   = min($highestRow,$this->offset+$rowStart);
        $data = [];
        for ($row = $start; $row <= $end; $row++) {
            $l = array();
            foreach ($keys as $k => $v) {
                $value = $worksheet->getCellByColumnAndRow($k + $columnStart,$row)->getValue();
                $value = trim($value);
                if ($value !== null && $value !== ''){
                    $l[$v] = $value;
                }
            }
            if ($l) {
                $data[$row] = $l;
            } else {
                $this->error_lines[] = 'Line '.$row.' is empty.';
            }
        }
        return $data;
    }

    /**
     * 数据格式校验
     *
     * @author chentengfei <tengfei.chen@atommatrix.com>
     * @date   2018-08-06 19:31:34
     * @param $data
     * @param $validate_class
     * @param $validate_func
     */
    private function validate(& $data,$validate_class,$validate_func)
    {
        foreach ($data as $row => $item) {
            try {
                $validate_class::$validate_func($item);
            } catch (ValidationException $e){
                $message = array_values($e->errors())[0][0];
                $this->error_lines[] = 'Line '.$row.' is error:'.$message;
                unset($data[$row]);
            } catch (\Exception $e) {
                $this->error_lines[] = 'Line '.$row.' is error:'.$e->getMessage();
                unset($data[$row]);
            }
        }
    }

    /**
     * 数据按规则转化
     *
     * @author chentengfei <tengfei.chen@atommatrix.com>
     * @date   2018-08-06 19:32:17
     * @param $data
     * @param $columns
     */
    private function transData(& $data,$columns)
    {
        $trans = [];
        foreach ($data as $key => $item) {
            foreach ($item as $column => $value) {
                if (isset($columns[$column][2])) {
                    $regulation = $columns[$column][2];
                    if (is_array($regulation)) {
                        $item[$column] = array_search($value,$regulation);
                    } elseif (class_exists($regulation) && (new $regulation() instanceof BaseModel)) {
                        if(!in_array($column,$trans)) {

                            $this->trans[$column] = $this->getTransValues($data,$column,$regulation);
                            $trans[] = $column;
                        }
                        $this->replaceColumn($item,$column,$columns[$column]);
                    } else {
                        throw new \Exception('unknown rule:'.$regulation);
                    }
                }
            }
            $data[$key] = $item;
        }
    }

    /**
     *
     *
     * @author chentengfei <tengfei.chen@atommatrix.com>
     * @date   2018-08-11 17:55:58
     * @param $data
     * @param $column
     * @param $regulation
     * @return array
     */
    private function getTransValues($data,$column,$regulation)
    {

        $values = [];
        foreach ($data as $item){
            if ($v = $item[$column]??''){
                if(!in_array($v,$values)) {
                    $values[] = $v;
                }
            }
        }
        $model = new $regulation();
        $column = explode('-',$column)[0];
        $result = $model->default()->whereIn($column,$values)->select('id',$column)->get();

        $res = [];
        foreach ($result as $item){
            if(!isset($res[$item->$column])) {
                $res[$item->$column] = $item->id;
            }
        }
        return $res;
    }

    /**
     * 替换部分字段
     *
     * @author chentengfei <tengfei.chen@atommatrix.com>
     * @date   2018-08-06 19:40:05
     * @param $data
     * @param $column
     * @param $columnRule
     */
    private function replaceColumn(&$data, $column, $columnRule)
    {
        // 是否支持新建
        $creatable = $columnRule[3];
        // 替换字段
        $replaceColumn = $columnRule[4];
        // 依赖字段
        $relyColumn = $columnRule[5]??'';
        // 关联model
        $model = new $columnRule[2]();
        // 查找字段
        $searchColumn = explode('-',$column)[0];
        // 当前字段值
        $currentValue = $data[$column]??'';
        // 新建时的填充字段
        $fillColumn = [];
        if (isset($columnRule[6]) && is_array($columnRule[6])) {
            foreach ($columnRule[6] as $k=>$v){
                // % 包围的字段,使用数组内的值替换.
                if (preg_match('/%[_a-z0-9]+%/',$v,$matches)) {
                    foreach ($matches as $match){
                        $co = str_replace('%','',$match);
                        $v = str_replace($match,$data[$co]??'',$v);
                    }
                }
                // # 包围的字段,使用数组内转化后的值替换.
                if (preg_match('/#[_\-a-z0-9]+#/',$v,$matches)) {
                    foreach ($matches as $match){
                        $co = str_replace('#','',$match);
                        $v = str_replace($match,$this->trans[$relyColumn][$data[$co]??'0']??'',$v);
                    }
                }
                if ($v) {
                    $fillColumn[$k] = $v;
                }
            }
        }

        if ($relyColumn) { //如果存在依赖,即父子关系
            $relyColumnValue = $data[$relyColumn]??'';
            if(empty($relyColumnValue) || !isset($this->trans[$relyColumn][$relyColumnValue])){
                return ;
            }
        }
        //查看是否存在
        if (isset($this->trans[$column][$currentValue])) {
            $data[$replaceColumn] = $this->trans[$column][$currentValue];
        } elseif($creatable) { //如果可创建的话,就创建一个
            $info =  [
                'org_id' => defined('CURRENT_ORG_ID')?CURRENT_ORG_ID:0,
                $searchColumn => $currentValue,
            ];
            $info = array_merge($info,$fillColumn);
            $item = $model->create($info);
            if( $item ) {
                $this->trans[$column][$currentValue] = $item->id;
                $data[$replaceColumn] = $item->id;
            }
        } else { //不可用就将其置空
            $data[$replaceColumn] = '';
        }
    }

    /**
     * 获取导入数据
     *
     * @author chentengfei <tengfei.chen@atommatrix.com>
     * @date   2018-08-07 09:57:05
     * @param string $file_path  //路径
     * @param array  $columns    //列
     * @param string $validator_class //验证类
     * @param string $validator_func //验证类方法
     * @param int    $rowStart //数据开始行
     * @param int    $columnStart //数据结束行
     * @return array
     */
    public function importData($file_path,$columns,$validator_class = '',
                               $validator_func = 'excel',$rowStart = 2,$columnStart =1,$sheet = "")
    {
        if (is_file($file_path)) {
            try {
                $column_keys = array_keys($columns);
                $data = $this->getDataFromExcel($file_path, $column_keys, $rowStart, $columnStart,$sheet);

                if ($data) {
                    $this->transData($data, $columns);
                    if ($validator_class && $validator_func)
                        $this->validate($data, $validator_class, $validator_func);
                }
                return $data;
            } catch (\Exception $e) {
                $this->hasMore = false;
                $this->response['errors'] = $this->error_lines;
                return null;
            }
        }
        return null;
    }


    public function getAllData($file,$rowStart = 0,$columnStart = 1)
    {
        $reader =  IOFactory::createReader($this->type);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($file); //载入excel表格
        $worksheets = $spreadsheet->getAllSheets();
        $result = [];
        foreach ($worksheets as $worksheet) {
            $this->offset = 0;
            //$worksheet = $spreadsheet->getActiveSheet();

            $highestRow = $worksheet->getHighestRow(); // 总行数
            $highestColumn = $worksheet->getHighestColumn(); // 总列数
            $column = 0;
            for ($key = 0; $key < strlen($highestColumn); $key++) {
                $column += $column * 26 + ord($highestColumn[$key]) - ord('A') + 1;
            }
            $highestColumn = $column;
            // 剩余行数
            $rows = $highestRow - $rowStart - $this->offset;
            if ($rows < $this->limit) {
                $this->hasMore = false;
            }
            // 开始读取行
            $start = $rowStart + $this->offset;
            // 下标增加
            $this->offset += $this->limit;
            // 结束行
            $end = min($highestRow, $this->offset + $rowStart);
            $data = [];
            for ($row = $start; $row <= $end; $row++) {
                $l = array();
                for ($key = 0; $key < $highestColumn; $key++) {
                    $value = $worksheet->getCellByColumnAndRow($key + $columnStart, $row)->getValue();
                    $value = trim($value);
                    if ($value !== null && $value !== '') {
                        $l[$key] = $value;
                    }
                }
                if ($l) {
                    $data[$row] = $l;
                }
            }
            $result[]=$data;
        }
        return $result;
    }

    /**
     * 插入时的基础数据.可通过$unsetColumn删除
     *
     * @author chentengfei <tengfei.chen@atommatrix.com>
     * @date   2018-08-07 13:21:17
     * @return array
     */
    private function getBaseColumns()
    {
        return [
            'org_id'     => defined('CURRENT_ORG_ID')?CURRENT_ORG_ID:0,
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => defined('USER_ID')?USER_ID:0,
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => defined('USER_ID')?USER_ID:0,
        ];
    }

    /**
     * 插入数据到数据库
     *
     * @author chentengfei <tengfei.chen@atommatrix.com>
     * @date   2018-08-07 16:15:51
     * @param array  $data 待添加数据
     * @param array  $defaultColumn  可选字段默认值
     * @param string $table 表名
     * @param array  $unsetColumn 过滤字段
     * @return bool
     */
    public function insertToTable($data,$defaultColumn,$table,$unsetColumn = [])
    {
        if (!$data) {
            return false;
        }
        $baseColumns = $this->getBaseColumns();
        foreach ($data as & $item) {
            $item = array_merge($defaultColumn,$item,$baseColumns);
            $item = array_except($item,$unsetColumn);
        }
        return DB::table($table)->insert($data);
    }

    /**
     * 更新数据
     *
     * @author chentengfei <tengfei.chen@atommatrix.com>
     * @date   2018-08-15 18:18:00
     * @param \App\Models\BaseModel $model
     * @param array                 $unsetColumn  不可用键
     * @param string                $updateKey
     */
    public function updateByModel(Builder $model,$unsetColumn = [],$updateKey = 'id')
    {
        foreach ($this->update_lines as $key => $item){
            try {
                $model->where($updateKey, '=', $item[$updateKey])->update(array_except($item,$unsetColumn));
            } catch(\Exception $e) {
                $this->error_lines[] = 'Line '.$key.' update failed!';
            }
        }
        return true;
    }

    /**
     * 更新行指错
     *
     * @author chentengfei <tengfei.chen@atommatrix.com>
     * @date   2018-08-15 18:59:11
     */
    public function failUpdateLines()
    {
        foreach ($this->update_lines as $key => $item){
            $this->error_lines[] = 'Line '.$key.' is existed and not be covered!';
        }
    }


    /**
     * 是否还有更多数据
     *
     * @author chentengfei <tengfei.chen@atommatrix.com>
     * @date   2018-08-07 10:36:32
     * @return bool
     */
    public function hasMore()
    {
        return $this->hasMore;
    }

    /**
     * 生成模板
     *
     * @author chentengfei <tengfei.chen@atommatrix.com>
     * @date   2018-08-07 15:59:12
     * @param $name
     * @param $columns
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public function template(string $name, array $columns)
    {
        $spreadSheet = new Spreadsheet();
        $workSheet = $spreadSheet->getActiveSheet();
        $workSheet->setTitle($name);
        $i = 1;
        foreach ($columns as $column) {
            $workSheet->setCellValueByColumnAndRow($i, 1,$column[0]);
            //设置自动宽度
            $workSheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
            $workSheet->setCellValueByColumnAndRow($i, 2,$column[1]);
            if (isset($column[2]) && is_array($column[2])) { //设置可选项
                $objValidate = $workSheet->getCellByColumnAndRow($i,2)->getDataValidation();
                $objValidate->setType(DataValidation::TYPE_LIST)
                    ->setErrorStyle(DataValidation::STYLE_INFORMATION)
                    ->setShowInputMessage(true)
                    ->setShowErrorMessage(true)
                    ->setShowDropDown(true)
                    ->setErrorTitle('输入的值有误')
                    ->setError('您输入的值不在下拉框列表内.')
                    ->setPromptTitle($column[0])
                    ->setFormula1('"'.implode(',',$column[2]).'"');
            }
            $i ++ ;
        }
        $filename = $name.'.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="'.$filename.'"');
        header('Cache-Control: max-age=0');

        $writer = IOFactory::createWriter($spreadSheet, 'Xlsx');
        $writer->save('php://output');
    }

}
