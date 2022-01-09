<?php
namespace Desensitization;

/**
 * 过滤数据
 *
 * 使用方法。先调用config进行配置，再调用别的方法。当然你也可以不配置直接调用。
 * 暂时只支持php-fpm。非fpm的因为没有$_SERVER['REQUEST_URI']，我会排除掉统统不脱敏。
 *
 */
class Filter
{
    /**
     * 默认配置项
     *
     * @var array
     */
    private static $config = [
        // include配置，什么样的URI需要脱敏处理，你可以设置为null来移除。如果不配置默认不进行脱敏
        //'include' => function(string $uri) { return false; },
        'roles' => [
            // 定义key应该被函数进行处理，你可通过设置key为null来移除。
            // 'key' => function(&$value) { $value = '******'; },
        ],
    ];

    /**
     * 配置过滤器的特性
     *
     * @param array $config 配置
     * @return void
     */
    public static function config(array $config = [])
    {
        static::$config = array_replace_recursive(static::$config, $config);
    }

    /**
     * 获取配置
     *
     * @return array 配置
     */
    public static function getConfig(): array
    {
        return static::$config;
    }

    /**
     * 过滤响应字段
     *
     * @param mixed $originData 原始数据
     * @return mixed 过滤后的数据，如果原始数据有对象默认转换为数组
     */
    public static function response($originData)
    {
        if (!is_array($originData) && !is_object($originData)) {
            return $originData;
        }
        // 目前支持php-fpm
        if (!isset(static::$config['include']) || is_null(static::$config['include']) ||!isset($_SERVER['REQUEST_URI'])) {
            return $originData;
        }
        if (is_array(static::$config['include']) && isset(static::$config['include']['match'])) {
            // 数组配置
            if (!preg_match(static::$config['include']['match'], $_SERVER['REQUEST_URI'])) {
                return $originData;
            }
        } elseif (!static::$config['include']($_SERVER['REQUEST_URI'])) { // 匿名函数配置
            return $originData;
        }
        $returnData = json_decode(json_encode($originData), true);
        $originData = null;
        array_walk_recursive($returnData, function (&$val, $key, &$config) {
            if (isset($config['roles'][$key]) && !is_null($config['roles'][$key])) {
                $conf = &$config['roles'][$key];
                if (is_array($conf) && isset($conf['mask'])) { // 数组配置
                    if (!is_string($val)) return; // 默认只是处理字符串而已
                    $valueOf = function($pathConfig, $len) { // 根据配置和长度返回要掩盖的长度
                        if (empty($pathConfig)) return 0;
                        if (is_array($pathConfig)) return call_user_func($pathConfig[1], $pathConfig[0] * $len);
                        return round($pathConfig * $len);
                    };
                    $len = mb_strlen($val);
                    $leftLen = $valueOf($conf['mask']['left'] ?? null, $len);
                    $rightLen = $valueOf($conf['mask']['right'] ?? null, $len);
                    $symbol = $conf['mask']['symbol'] ?? '*'; // 符号默认 *
                    if (empty($conf['mask']['reverse'])) {
                        $val = str_repeat($symbol, $leftLen) . mb_substr($val, $leftLen, $len - $leftLen - $rightLen) . str_repeat($symbol, $rightLen);
                    } else {
                        $val = mb_substr($val, 0, $leftLen) . str_repeat($symbol, $len - $leftLen - $rightLen) . mb_substr($val, -$rightLen);
                    }
                } else { // 匿名函数
                    $conf($val);
                }
            }
        }, static::$config);
        return $returnData;
    }
}
