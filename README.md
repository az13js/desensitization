# desensitization ，一个给 PHPer 用的数据脱敏包

## 安装

假设你正确安装了 Composer 和 JSON 、 mbstring 扩展。在 `composer.json` 中配置下面的属性：

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://gitee.com/az13js/desensitization.git"
        }
    ],
    "require": {
        "az13js/desensitization": "0.0.1-dev"
    }
}
```

然后执行 ```composer install``` ，这样就安装完了。对某些不支持 Composer 的项目，可能你需要通过包含 ```vendor/autoload.php``` 引入类的自动加载功能。

## 用法

首先需要在项目加载的时候配置，然后返回响应内容给前端之前用 ```Filter::response()``` 函数过滤。下面是一个简短的示例：

```php
require_once 'vendor/autoload.php';

// 项目加载时，配置
\Desensitization\Filter::config([
    // 对任意的URI访问include都返回true表示对所有URI请求的响应内容都进行脱敏
    'include' => function($uri) { return true; },
    'roles' => [
        // 对响应内容中名字为name的键都调用此处设置的匿名函数，这里是将它的值设置为**
        'name' => function(&$input) { $input = '**'; },
    ],
]);

// 在你的项目中返回响应内容给前端之前用 Filter::response() 处理：
return \Desensitization\Filter::response([
    'mobile' => '13699999999',
    'name' => '玉皇大帝',
]);
```

输出内容如下：

```json
{
    "mobile":"13699999999",
    "name":"**"
}
```

内部逻辑是： ```Filter``` 会在请求地址符合 ```include``` 配置的条件时，递归地检测 ```response``` 传入的内容，对内容中符合 ```roles``` 配置的键名调用对应的函数进行处理。

在这个示例中，响应内容包含 ```mobile``` 和 ```name``` 这两个键。 ```include``` 配置为对所有URI都返回 ```true``` ，并且 ```roles``` 配置为遇到 ```name``` 键的时候将其值改写为 ```**``` ，所以最终返回给前端的内容中 ```name``` 被 ```**``` 隐藏了。 ```mobile``` 在这里没有配置，所以原样返回。

## 特性

### 多层数组

遇到多层数组的时候， ```Filter``` 会递归子数组，遍历它们的键。例如：

```php
require_once 'vendor/autoload.php';

\Desensitization\Filter::config([
    'include' => function($uri) { return true; },
    'roles' => [
        'name' => function(&$input) { $input = '**'; },
    ],
]);

return \Desensitization\Filter::response([
    'mobile' => '13699999999',
    'name' => '玉皇大帝1',
    'sub' => [
        'mobile' => '13699999998',
        'name' => '玉皇大帝2',
    ],
]);
```

响应内容为：

```json
{
    "mobile":"13699999999",
    "name":"**",
    "sub":{
        "mobile":"13699999998",
        "name":"**"
    }
}
```

### 对象类型

确定需要进行脱敏处理的时候， ```Filter``` 会在实际遍历之前通过 ```json_encode``` 和 ```json_decode``` 对内容进行转换。这意味着在实际遍历响应内容时，所有对象都被转换掉了，如下：

```php
require_once 'vendor/autoload.php';

class Foo {
    public $name = '玉皇大帝';
    public $mobile = '13699999999';
    private $h = 'nothing';
}

\Desensitization\Filter::config([
    'include' => function($uri) { return true; },
    'roles' => [
        'name' => function(&$input) { $input = '**'; },
    ],
]);

var_dump(\Desensitization\Filter::response(new Foo()));
```

输出：

    array(2) {
      ["name"]=>
      string(3) "**"
      ["mobile"]=>
      string(11) "13699999999"
    }

### ```include``` 配置匿名函数

配置属性 ```include``` 的匿名函数接收的参数 ```$uri``` 是 ```$_SERVER['REQUEST_URI']``` 。在 ```$_SERVER['REQUEST_URI']``` 不存在的环境下， ```Filter::response()``` 永远不会处理传入的内容（当前不支持 Swoole ，也不支持在 cli 模式下脱敏）。

配置属性 ```include``` 的作用是，你可以判断符合某些条件的URI启用脱敏处理，另外的URI不进行处理。例如下面示例判断当前请求路径是否以 ```/user``` 开头，如果是那么启用脱敏处理，如果不是那么不处理原样返回。

```php
\Desensitization\Filter::config([
    'include' => function($uri) { return 0 === strpos($uri, '/user'); },
    'roles' => [
        'name' => function(&$input) { $input = '**'; },
    ],
]);
```

### 数组配置

当你的要求不是那么复杂的时候，可以用数组来配置，无需编写匿名函数。

#### ```include``` 数组配置

配置项 ```include``` 的目的无非是确定哪些URI是需要开启脱敏的，所以完全可以给一个正则表达式来达到相同目的。配置方式如下：

```php
\Desensitization\Filter::config([
    'include' => ['match' => '/^\/user/'],
    // ... 配置roles
]);
```

这里的正则表达式将匹配以 ```/user``` 开头的请求地址，如果匹配成功那么将会开启脱敏处理。

#### ```roles``` 数组配置

大部分脱敏处理可以简单地使用类似 ```*``` 这样的字符去掩盖一部分字符，让前端不显示完整的内容就可以了。 ```roles``` 数组配置能做到这一点，只要你配置字符串左侧和右侧多少占比的内容需要掩盖就可以了。

同时， ```Filter``` 内使用了 mbstring 扩展所提供的函数进行字符串操作，可以兼顾处理中文和英文字符的需要。

##### 基本配置

基本配置方式如下， ```left``` 和 ```right``` 是可选的，它们默认为 ```0``` ：

```php
require_once 'vendor/autoload.php';

\Desensitization\Filter::config([
    'include' => function($uri) { return true; },
    'roles' => [
        'example' => [
            'mask' => [
                'left' => 0.2,
                'right' => 0.2,
            ],
        ],
    ],
]);

return \Desensitization\Filter::response([
    'example' => '12345678901234567890',
]);
```

返回：

```json
{
    "example":"****567890123456****"
}
```

可以看到， ```example``` 总长度为20个字符，经过处理后左侧的 20% 和右侧的 20% （也就是各占比 0.2 ）的部分被符号 ```*``` 掩盖了。

##### 掩盖中间部分

默认配置是掩盖左侧和右侧的字符，如果你想要中间的部分被掩盖，那么可以设置 ```reverse``` 属性为 ```true``` 开启反方向掩盖。例如：

```php
require_once 'vendor/autoload.php';

\Desensitization\Filter::config([
    'include' => function($uri) { return true; },
    'roles' => [
        'example' => [
            'mask' => [
                'left' => 0.2,
                'right' => 0.2,
                'reverse' => true,
            ],
        ],
    ],
]);

return \Desensitization\Filter::response([
    'example' => '12345678901234567890',
]);
```

返回：

```json
{
    "example":"1234************7890"
}
```

##### 取整方式

在计算需要在左右掩盖多少个字符的时候，内部默认是采用四舍五入的方式进行取整，也就是调用函数 ```round``` 。这在一些特殊情况下可能不满足需要。 ```role``` 的数组配置 ```left``` 和 ```right``` 属性接受一个具有两个元素的数组，其中第一个元素还是作为掩盖的占比，第二个元素则作为一个回调函数用来取整。

例如如果你希望计算的时候向上取整，目的是尽可能多地掩盖左侧的内容时，可以这样：

```php
require_once 'vendor/autoload.php';

\Desensitization\Filter::config([
    'include' => function($uri) { return true; },
    'roles' => [
        'example' => [
            'mask' => [
                'left' => [0.5249, 'ceil'],
            ],
        ],
    ],
]);

return \Desensitization\Filter::response([
    'example' => '12345678901234567890',
]);
```

返回：

```json
{
    "example":"***********234567890"
}
```

此时， ```example``` 的 20 个字符，计算时按照 ```ceil(0.5249 * 20)``` 算出应该掩盖 11 个字符。

##### 设置掩盖符号

默认情况下， ```Filter``` 采用符号 ```*``` 来掩盖字符。你可以通过 ```symbol``` 属性来配置掩盖时采用的字符或者字符串。例如：

```php
require_once 'vendor/autoload.php';

\Desensitization\Filter::config([
    'include' => function($uri) { return true; },
    'roles' => [
        'example' => [
            'mask' => [
                'left' => 0.5,
                'symbol' => '?',
            ],
        ],
    ],
]);

return \Desensitization\Filter::response([
    'example' => '12345678901234567890',
]);
```

返回：

```json
{
    "example":"??????????1234567890"
}
```

##### 一些注意点

- 需要注意的是，数组配置方式只能处理字符串，如果不是字符串，例如 ```false``` 、 ```null``` 或者整数、浮点数等，配置将不会生效。因为 PHP 是动态语言，针对这些可能出现特殊值但是你又需要处理的键，建议使用匿名函数配置。
- 数组配置下，不检查你配置的数值是不是在合法范围，例如你可能不小心传了一个负数或者大于1的数到 ```left``` 或者 ```right``` 属性上，这种情况下不好保证能不能正常处理。你最好避免这种情况的发生。
