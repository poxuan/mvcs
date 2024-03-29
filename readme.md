
# 介绍

本项目为基于 laravel 框架开发的快速代码生成器

[English Introduction](./README_EN.md)

## 使用步骤

第零步：安装包及依赖

> composer require callmecsx/mvcs

第一步: 在config/app.php 的 providers 添加 provider（5.5以上版本跳过此步）

> Callmecsx\Mvcs\MvcsServiceProvider::class

第二步: 发布 MVCS 模板和配置

> php artisan vendor:publish --provider="Callmecsx\Mvcs\MvcsServiceProvider"\
> 选择相应序号发布

第三步：修改config/mvcs.php 及 resource/stubs/

> 发布成功后，分别出现在config 和 resource 中，内有中文注解。

PS: 所有命令已限制在production环境下执行，

## mvcs:make 命令

该命令用来生成模板文件，默认提供了四个模板MVCS

> php artisan mvcs:make model {--force=} {--only=} {--connect=} {--style=} {--traits=}

```text
> model     驼峰式或骆驼式,如UserAccount 或 userAccount 对应表为 user_account,也可以加相对路径如 v1/UserAccount
> --force   表示强制覆盖文件,默认为空,可选值为:all 或 (M)(V)(C)(S) 如 --force=SVM 则将强制覆盖除C的三个文件
> --only    表示只生成一部分文件,默认为MVCS,可选值 (M)(V)(C)(S) 如--only=M 则将只生成model文件
> --connect 表示连接的数据库,默认为default数据库,若找不到,将跳过一些数据的生成.
> --style   表示模板的风格.
> --traits  表示额外使用的代码块

PS: 使用前应编辑stubs模板以适用自身项目
PS: 使用前应建表,包括表中备注,脚本会使用部分表格字段生成一些数据,可以省去不少操作,暂时只适配了mysql 和 mssql（未测）
```

通过该指令，默认将在app下自动生成 controller、validator、model、service 四个文件（或自己定义的任何文件）；

如：执行 php artisan mvcs:make account

将生成如下文件并构造好默认方法及数据

> app/Http/Controller/AccountController \
> app/Models/Account \
> app/Validators/AccountValidator \
> app/services/AccountService

并按配置生成好路由，若模板无问题，可直接进行调用。

## mvcs:append 命令

该命令用来给文件添加扩展代码

> php artisan mvcs:append model {--connect=} {--style=} {--traits=} {--only=}

```text
> model     驼峰式或骆驼式,如UserAccount 或 userAccount 对应表为 user_account,也可以加相对路径如 v1/UserAccount
> --only    表示只生成一部分文件,根据style 如--only=M 则将只生成model文件
> --connect 表示连接的数据库,默认为default数据库,若找不到,将跳过一些数据的生成.
> --style   表示模板的风格.
> --traits  表示额外使用的代码块

PS: 使用前应编辑stubs模板以适用自身项目
PS: 使用前应建表,包括表中备注,脚本会使用部分表格字段生成一些数据,可以省去不少操作,暂时只适配了mysql 和 mssql（未测）
```

## mvcs:make_all 命令

该命令用于将对于数据库中每张表和视图生成一次代码

> php artisan mvcs:make_all {--connect=} {--style=} {--y|yes}

```text
> --connect 表示连接的数据库,默认为default数据库,若找不到,将跳过一些数据的生成.
> --style   表示文件生成的风格.
> --y|yes   表示一键确认生成文件，否则脚本会在每个表相关文件生成前请求确认.
```

## mvcs:excel 命令

该命令用于将excel导入成数据库表,支持多sheet

> php artisan mvcs:excel {file} {--type=}

```text
> file 为需导入文件,请使用绝对路径
> --type 导入类型,1:结构,2:数据,3:数据和结构（默认）

excel 格式

> 第一行 表英文名、表中文解释
> 第二行 各列名 [* ]英文[#格式[#注释]] 开头* 表示必填项
> 例: *name#string_20#昵称, birth#date#生日
> 第三行 示例行，如果你不会写[#格式[#注释]，将只会识别为 数字、小数、字符串、文本四类格式。
> 第四行以后 待导入数据

PS: 第三行格式匹配失败时，当作字符尝试匹配字段类型 如 100 将匹配为 int
```

示例格式

user | 用户表 | -
:-:|:-:|:-:
*nickname#string_20#昵称|sex#enum_male_female#性别:男or女|brith#日期#生日
jack ma|1|1980-12-21

[示例文件](./example.xlsx)

## 模板的写法

一个基本模板文件是这样的：controller.stub 但并不限定为php文件，你同样可以写html或vue文件

```PHP
<?php
// 名字空间，由config.common.C.namespace 和 指令 model 决定
namespace $[controller_ns];

// 引用基类，没有基类返回空
$[controller_use]
// 如果构建包含包括 S（service）模板，则注入此块
@{S}use $[service_ns]\$[service_name];@{/S}
// 引用laravel类
use Illuminate\Http\Request;

$[controller_traits_head]

#{controller_hook_head} 头部扩展锚点

/**
 * $[controller_name]
 *
 * @author  $[author_info]
 * @version $[main_version]
 * @since   $[sub_version]
 */
class $controller_name $controller_extends
{
    // 根据 config.tags.foo 的返回值控制哪个块显示
    @{foo:a}
    protected $foo = 'fooA';
    @{foo}
    protected $foo = 'foo';
    @{!foo}
    protected $bar = 'bar';
    @{/foo}

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request, $[service_name] $service)
    {
        $result = $service->list($request->all());
        return response()->json($result);
    }

    // 根据config.traits 和指令行参数 加载额外代码块
    $[controller_traits_body]

#{controller_hook_body} 内部扩展锚点
}

```

## 扩展编写

扩展文件写法如下

```PHP
// 每套扩展可能有多个文件，以 reply/controller 文件为例

// 双@@ 在行首，表示锚点，替换内容为 $[controller_traits_head]
// 扩展模式下 mvcs:append 替换内容为 #{controller_hook_head}
@@head
use App\Base\Code;
@@body
    /**
     * teacher reply
     *
     * @param  Request $request
     * @param  int $id
     * @return void
     * @author $author_info
     * @since  $sub_version
     */
    public function reply(Request $request,$id) {
        $params = $request->input() ?: [];
        // 扩展文件同样可以使用预定的值和标签写法
        // 在扩展模式下，少部分原生内置的值不再提供，如：validator_rule、model_fillable 等
        $[validator_name]::reply($params);
        $info = $[model_name]::findOrfail($id);
        $info->reply = $params['reply'];
        $info->reply_teacher = $params['reply_teacher'];
        $res = $info->save();
        if (!$res) {
            return $this->error(Code::FATAL_ERROR);
        }
        return $this->success([]);
    }
```

> 编写新的模板文件后，须将其在 config 文件中定义

## 配置编写

```php
<?php

return [
    /* 使用前请务必阅读 readme 文件 */
    // 版本信息
    'version'  => '3.1',
    // 语言包，目前只有这一个包。
    'language' => 'zh-cn',
    /* 模板相关配置 */
    // 模板风格
    'style' => 'api', // 默认风格
    'table_style' => 'single', // 表名风格， 只支持plural 和 single
    // 模板全局替换参数
    'global_replace' => [
        'author_info'  => env('AUTHOR', 'foo <foo@example.com>'),
        'main_version' => '1.0', 
        'sub_version'  => '1.0.' . date('ymd'), 
        'create_date'  => date('Y-m-d H:i:s')
    ],
    // 替换类, 须实现替换类
    'replace_classes' => [
        Callmecsx\Mvcs\Impl\ExampleReplace::class,
    ],
    // 替换参数标识
    'replace_fix' => '$[ ]',
    // 扩展模式下扩展名的前后缀，可以没有后缀，可在style中自定义
    'hook_fix' => '#{ }',
    // 标签功能标志
    'tags_fix' => '@{ }',//单空格分割前后缀
    'tags' => [
        // 支持不同标签嵌套，同名嵌套会报错
        // @{foo} xxx @{!foo} yyy @{/foo} 返回为空 yyy保留 返回true xxx保留
        // @{style:api} xxx @{style:web} yyy @{/style} api xxx保留 返回web yyy保留 返回其他 全部块删除
        'authcheck' => false,
        'softdelete' => function ($columns) {
            foreach ($columns as $column) {
                if ($column->Field == 'deleted_at') {
                    return true;
                }
            }
            return false;
        },
        'base' => true,
    ],
    
    // 表中不该用户填充的字段
    "ignore_columns" => ['id', 'created_at', 'updated_at', 'deleted_at'],

    /* 自动添加路由配置 */
    // 是否自动添加路由
    "add_route" => true,
    // 路由类型,加到那个文件里
    "route_type" => 'api',
    // 添加路由数组
    "routes" => [
        // 方法 -> 路由
        'post' => [
            // 'foo' => '{id}/foo',
        ],
        // GET 路由等
        'get' => [
            'simple' => 'simple',
        ],
        // 是否添加 apiResource？
        'apiResource' => true,
        // 是否添加 resource？
        'resource' => false,
        // 公共中间件，非全局
        'middlewares' => [],
        // 公共路由前缀
        'prefix' => '',
        // 公共名字空间，如使用 mvcs:make test/miniProgram 还会添加额外的一级名字空间 Test
        'namespace' => '',
    ],

    /* mvcs:excel 脚本相关参数 */
    "excel" => [
        // 公共额外数据库字段，migrate 写法
        "extra_columns" => [
            //'$table->integer("org_id")->comment("组织ID");',
            '$table->timestamps();',
            '$table->timestamp("deleted_at")->nullable();',
        ],
        // 表名前缀
        "table_prefix" => "",
        // 表名后缀
        "table_postfix" => "",
        // 未定义varchar长度时的默认值。
        "default_varchar_length" => 50,
        // 未定义decimal 精度时的默认值。
        "default_decimal_pre" => 10,
        "default_decimal_post" => 2,
        // EXCEL第三行，类型映射
        "type_transfar" => [
            "短句" => "string",
            "字符串" => "string",
            "网址" => "string",
            "地址" => "string",
            "邮箱" => "string",
            "图片" => "string",
            "多媒体" => "string",
            "字符" => "string",
            "枚举" => "enum",
            "列举" => "enum",
            "数字" => "integer",
            "整数" => "integer",
            "小数" => "decimal",
            "文章" => "text",
            "内容" => "text",
            "时间" => "datetime",
            "日期" => "date",
        ]
    ],
];


```

## 更新

```text
3.1.0 配置文件向前不兼容
    - 将风格化的配置移动到 stubs 目录下
2.0 配置文件向前不兼容
    - 增加了一些自己使用到的内容
1.5 功能拓展及问题修复
    - 扩展模板增加了新的写法 ::body 用于指定插入位置
```

## License

Callmecsx mvcs is open-sourced software licensed under the [MIT license](http://opensource.org/licenses/MIT).
