# Introduction

> a project to simple create file from stubs relay on laravel;

## Using Steps

1. Add params provider as such in config/app.php.
   * Callmecsx\Mvcs\MvcsServiceProvider::class
2. Publish it to create default config and stubs.
   * php artisan vendor:publish
   * choose the number of Callmecsx\Mvcs
3. Edit config/mvcs.php to fit your program.

## The Command make:mvcs

It work to create a file with clear template.

> Use it just like this: \
> php artisan make:mvcs {model} {--force=} {--only=} {--connect=}

```TEXT
model:
    It should be a camelCase word e.g. userAccount or UserAccount.
    And it will relate to your table user_account.
--force:
    This option means that you want to cover the old files.
    It default to be null, you can ues --force=all to cover all or --force=MC just cover M and C
--only:
    This option means which file(s) you want to create.
    It can be define in config files, can ues --only=MC just create M and C file.
--connect:
    As you can have many databases, this param mean which database this model use.
    Just use it as  --connect=mysql2
```

> Before you use this command, you should edit the stubs and config to fit your project. \
> Btw if you're using Mysql, you should create your tables in your datebase, It will reduce your workloads.

As you having exce this command: php artisan make:mvcs account.

```TEXT
such files will be created:

> app/Http/Controller/AccountController
> app/Models/Account
> app/Validators/AccountValidator
> app/services/AccountService

and it will also create routes:

> Route::post('account/up','AccountController@up');
> Route::post('account/down','AccountController@down');
> Route::get('account/template','AccountController@template');
> Route::post('account/import','AccountController@import');
> Route::Resource('account','AccountController']);
```

## The Command import:mvcs_db

This command is aimed to create you tables from a excel.

> Just using it as this: \
> php artisan import:mvcs_db {file} {--type=}

```TEXT
file:
    the excel or csv file that you want to import.
    you should upload it to your mechine rather than remote address.
    now it support xlsx xls csv files.
    And you can use mutil sheet to create mutil tables.
--type:
    this option means which type you want to do:
    1. import struct only
    2. import data only
    3. import data and struct
```

As you used this command, there will be some migrate files created and you can find your result in your database.

Your excel file should like this.

user | user_table | -
-|-|-
nickname|sex|brith
string@unique|tinyinteger|date
jack|1|1880-12-21

In cell A1 It's table name. \
In cell A2 It's table comment. \
In Row 2 It's your table columns \
In Row 3 It's the column type (type[_len1][_len2][@index]) \
After Row 3 It't your data to import into table.



## License

Callmecsx mvcs is open-sourced software licensed under the [MIT license](http://opensource.org/licenses/MIT).