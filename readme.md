## Introduction

### 使用步骤
第一步: 在config/app.php 的 providers 添加 provider
> Poxuan\Mvcs\MvcsServiceProvider::class

第二步: 发布 MVCS

> php artisan vendor:publish \
> 选择相应序号发布

### 增加的artisan make:mvcs命令

该命令用来生成MVCS 四个模板文件

> php artisan make:mvcs {model} {--force=} {--only=} {--connect=} \
> \
> model 为驼峰式或骆驼式,如UserAccount 或 userAccount 对应表为 user_account \
> --force   表示强制覆盖文件,默认为空,可选值为:all 或 (M)(V)(C)(S) 如 --force=SVM 则将强制覆盖除C的三个文件 \
> --only    表示只生成一部分文件,默认为MVCS,可选值 (M)(V)(C)(S) 如--only=M 则将只生成model文件 \
> --connect 表示连接的数据库,默认为default数据库,若找不到,将跳过一些数据的生成.

通过该指令，将在app下自动生成 controller、validator、model、service 四个文件；

如：执行 php artisan make:mvcs account //model 为驼峰式或骆驼式,如UserAccount 或 userAccount

将生成如下文件并构造好默认方法及数据

> app/Http/Controller/AccountController \
> app/Models/Account \
> app/Validators/AccountValidator \
> app/services/AccountService 
 
 添加好路由后可直接调用
 
 >  Route::post('account/up','AccountController@up'); \
 >  Route::post('account/down','AccountController@down'); \
 >  Route::get('account/template','AccountController@template');//非必要 \
 >  Route::post('account/import','AccountController@import');//非必要 \
 >  Route::Resource('account','AccountController', ['only' => ['index', 'show', 'store', 'update', 'destroy']]); 

### 增加的artisan make:report命令

该命令用于将excel导入成数据库表

> php artisan make:report {file} {--type=} \
> \
> file 为需导入文件,请使用绝对路径 \
> --type    表示强制导入类型,1:结构,2:数据,3:数据和结构 \
> excel 格式 \
> 第一行 \
> 表中文名  表英文名 \
> 第二行 \
> 列名(date结尾存储为datetime )... \
> 第三行 \
> 字段值(此行作为示例,尽可能完整,作为类型判断简单依据,不导入数据库) \
> 其余行 \
> 字段值(待导入数据)


## License

Poxuan mvcs is open-sourced software licensed under the [MIT license](http://opensource.org/licenses/MIT).
