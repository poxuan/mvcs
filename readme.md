
# Introduction

本项目基于laravel 框架开发,用于放便的生成基础代码

## 使用步骤

第一步: 在config/app.php 的 providers 添加 provider
> Callmecsx\Mvcs\MvcsServiceProvider::class

第二步: 发布 MVCS

> php artisan vendor:publish \
> 选择相应序号发布

## make:mvcs命令

该命令用来生成模板文件，默认提供了四个模板MVCS

> php artisan make:mvcs {model} {--force=} {--only=} {--connect=} \
> \
> model 为驼峰式或骆驼式,如UserAccount 或 userAccount 对应表为 user_account,也可以加路径如 test/UserAccount \
> --force   表示强制覆盖文件,默认为空,可选值为:all 或 (M)(V)(C)(S) 如 --force=SVM 则将强制覆盖除C的三个文件 \
> --only    表示只生成一部分文件,默认为MVCS,可选值 (M)(V)(C)(S) 如--only=M 则将只生成model文件 \
> --connect 表示连接的数据库,默认为default数据库,若找不到,将跳过一些数据的生成. \
> \
> 使用前可编辑stubs模板以适用自身项目 \
> 使用前应先建表,包括表中备注,脚本会使用部分表格字段生成一些数据,可以省去不少操作,暂时只适配了mysql

通过该指令，将在app下自动生成 controller、validator、model、service 四个文件（或自己定义的任何文件）；

如：执行 php artisan make:mvcs account //model 为驼峰式或骆驼式,如UserAccount 或 userAccount

将生成如下文件并构造好默认方法及数据

> app/Http/Controller/AccountController \
> app/Models/Account \
> app/Validators/AccountValidator \
> app/services/AccountService

并生成如下路由

> Route::post('account/up','AccountController@up'); \
> Route::post('account/down','AccountController@down'); \
> Route::get('account/template','AccountController@template'); \
> Route::post('account/import','AccountController@import'); \
> Route::Resource('account','AccountController']);

## import:mvcs_db命令

该命令用于将excel导入成数据库表,支持多sheet

> php artisan import:mvcs_db {file} {--type=} \
> \
> file 为需导入文件,请使用绝对路径 \
> --type 导入类型,1:结构,2:数据,3:数据和结构（默认） \
> excel 格式 \
> 第一行 表中文名、表英文名 \
> 第二行 各列名（英文）... \
> 第三行 字段格式...\
> 形如 type[_length1[_length2]][@index|unique]) \
> 例: int、char@index 、varchar_255@unique、decimal_8_2 \
> 匹配失败时，作为示例值匹配字段类型（如 100 将匹配为 int）。可能会导致后续数据导入失败。 \
> 其余行 \
> 待导入数据

## License

Callmecsx mvcs is open-sourced software licensed under the [MIT license](http://opensource.org/licenses/MIT).
