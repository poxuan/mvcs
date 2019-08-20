
# 介绍

本项目基于laravel 框架开发,用于放便的生成基础代码

[English Introduction](./README_EN.md)

## 使用步骤

第一步: 在config/app.php 的 providers 添加 provider
> Callmecsx\Mvcs\MvcsServiceProvider::class

第二步: 发布 MVCS

> php artisan vendor:publish \
> 选择相应序号发布

第三步：修改config 及 stubs

> 发布成功后，分别出现在config 和 resource 中，内有中文注解。

## mvcs:make 命令

该命令用来生成模板文件，默认提供了四个模板MVCS

> php artisan mvcs:make {model} {--force=} {--only=} {--connect=} {--style=} {--traits=} \
> \
> model     驼峰式或骆驼式,如UserAccount 或 userAccount 对应表为 user_account,也可以加路径如 test/UserAccount \
> --force   表示强制覆盖文件,默认为空,可选值为:all 或 (M)(V)(C)(S) 如 --force=SVM 则将强制覆盖除C的三个文件 \
> --only    表示只生成一部分文件,默认为MVCS,可选值 (M)(V)(C)(S) 如--only=M 则将只生成model文件 \
> --connect 表示连接的数据库,默认为default数据库,若找不到,将跳过一些数据的生成. \
> --style   表示模板的风格. \
> --traits  表示额外使用的traits \
> \
> 使用前应编辑stubs模板以适用自身项目 \
> 使用前应建表,包括表中备注,脚本会使用部分表格字段生成一些数据,可以省去不少操作,暂时只适配了mysql 和 mssql（未测）

通过该指令，默认将在app下自动生成 controller、validator、model、service 四个文件（或自己定义的任何文件）；

如：执行 php artisan make:mvcs account

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

## mvcs:make_all 命令

该命令用于将对于数据库中每张表和视图生成一次代码

> php artisan mvcs:make_all {--connect=} {--style=} {--y|yes}\
> \
> --connect 表示连接的数据库,默认为default数据库,若找不到,将跳过一些数据的生成. \
> --style   表示文件生成的风格. \
> --y|yes   表示一键确认生成文件，否则脚本会在每个表相关文件生成前请求确认. \
> \
> 使用前应编辑stubs模板以适用自身项目 \
> 使用前应先建表,包括表中备注,脚本会使用部分表格字段生成一些数据,可以省去不少操作,暂时只适配了mysql 和 mssql（未测）

## mvcs:excel 命令

该命令用于将excel导入成数据库表,支持多sheet, 此命令只能在非线上环境执行

> php artisan mvcs:excel {file} {--type=} \
> \
> file 为需导入文件,请使用绝对路径 \
> --type 导入类型,1:结构,2:数据,3:数据和结构（默认） \
> excel 格式 \
> 第一行 表英文名、表中文解释 \
> 第二行 各列名 [* ]英文[#注释] 开头* 表示必填项 ... \
> 第三行 字段格式  type_length1_length2 [#index|unique|primary] \
> 例: int、char#index 、varchar_255#unique、decimal_8_2 \
> 格式匹配失败时，当作字符尝试匹配字段类型 如 100 将匹配为 int \
> 第四行以后 待导入数据

示例格式

user | 用户表 | -
:-:|:-:|:-:
*nickname#昵称|sex#性别1男2女|brith#生日
string_20#unique|tinyint|date
jack ma|1|1980-12-21

[示例文件](./example.xlsx)

## License

Callmecsx mvcs is open-sourced software licensed under the [MIT license](http://opensource.org/licenses/MIT).
