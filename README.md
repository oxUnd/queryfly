# queryfly

The database extension of laravel, it a simple Restful Api database driver.

## how to install

It a component of composer.

Write bellow to

```json
{
    "require": {
        "epsilon/queryfly": "dev-master"
    }
}
```

your `composer.json`, then run `composer update`

## how to use

### client

create a Model, it extends `Epsilon\Queryfly\Eloquant\Model`

```php
<?php namespace App/Foo;

use Epsilon\Queryfly\Eloquant\Model;

class Foo extends Model
{
    protected $connect = 'remote_user';
}
```

it's `connect` config to `ROOT/config/database.php`

```php
<?php
...

'connections' => [
    // ...
    'remote_user' => [
        'dsn' => null,
        'prefix' => null,
        'driver' => 'queryfly',
        'host' => '127.0.0.1:8080',
        'database' => 'article',
        'protocol' => 'http'
    ]
    // ...
]
```

now you can use it.

```php
$all = Foo::where('name', 'like', 'foo')->get(['id', 'name']);
```

will request resource from [server](#server), request url

```
http://127.0.0.1:8080/api/article/foos/query?_field=id,name&name=like:foo
```

### server

```
http://127.0.0.1:8080/api/article/foos/query?_field=id,name&name=like:foo
|       host         |   |       |    |        query_string             |
                       |     |     |
                     prefix  |     $M 
                           system
```

- system sub system
- $M meybe a ORM model or data repository.
- query_string query paramaters

Queryfly support a method, easier to use.

```php
<?php

...
use Epsilon\Queryfly\Parser\Query;
...

class Controler extends BaseController
{
    public function query(Request $request)
    {
        $queryParser = new Query($request->all());

        $model = new Foooo(); // suppose model name is Foooo

        $all = $queryParser->bindToModel($model, function ($select, $model)
        {
            return $model->get($select);
        });

        $all->each(function ($one)
        {
            echo $one->name . PHP_EOL;
        });
    }
}
```

And route

```php
<?php

...

Route::get('/api/article/foos/query', 'Controller@query');

...
```

now only `bindToModel`.

## document
