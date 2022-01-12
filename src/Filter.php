<?php
namespace Desensitization;

/**
 * 过滤数据
 *
 * 使用方法。先调用config进行配置，再调用别的方法。当然你也可以不配置直接调用。
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
     * @return array
     */
    public static function config(array $config = []): array
    {
        return static::$config = array_replace_recursive(static::$config, $config);
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
     * @param string|null $forceUri 强制指定URI
     * @return mixed 过滤后的数据，如果原始数据有对象默认转换为数组
     */
    public static function response($originData, $forceUri = null)
    {
        if (!is_array($originData) && !is_object($originData)) {
            return $originData;
        }
        if (is_null($forceUri)) {
            $uri = $_SERVER['REQUEST_URI'] ?? null;
            if (isset(static::$config['uri']) && !is_null(static::$config['uri'])) {
                $uri = static::$config['uri'];
            }
        } else {
            $uri = $forceUri;
        }
        if (is_null($uri)) {
            return $originData;
        }
        if (is_array(static::$config['include']) && isset(static::$config['include']['match'])) {
            // 数组配置
            if (!preg_match(static::$config['include']['match'], $uri)) {
                return $originData;
            }
        } elseif (!static::$config['include']($uri)) { // 匿名函数配置
            return $originData;
        }
        $returnData = json_decode(json_encode($originData), true);
        $originData = null;
        array_walk_recursive($returnData, function (&$val, $key, &$config) {
            if (isset($config['roles'][$key]) && !is_null($config['roles'][$key])) {
                $conf = &$config['roles'][$key];
                if (is_string($conf)) { Types::$conf($val); return; }
                if (is_array($conf) && isset($conf['mask'])) { // 数组配置
                    if (!is_string($val)) return; // 默认只是处理字符串而已
                    $symbol = $conf['mask']['symbol'] ?? '*'; // 掩盖符号默认 *
                    if (!empty($conf['mask']['type'])) { $type = $conf['mask']['type']; Types::$type($val, $symbol); return; } // 如果定义了类型
                    $valueOf = function($pathConfig, $len) { // 根据配置和长度返回要掩盖的长度
                        if (empty($pathConfig)) $l = 0;
                        elseif (is_array($pathConfig)) $l = call_user_func($pathConfig[1], $pathConfig[0] * $len);
                        else $l = is_int($pathConfig) ? $pathConfig : round($pathConfig * $len);
                        return $l > $len ? $len : $l;
                    };
                    $len = mb_strlen($val);
                    $leftLen = $valueOf($conf['mask']['left'] ?? null, $len);
                    $rightLen = $valueOf($conf['mask']['right'] ?? null, $len);
                    if ($leftLen + $rightLen > $len) {$leftLen = $len; $rightLen = 0;}
                    if (empty($conf['mask']['reverse'])) {
                        $val = str_repeat($symbol, $leftLen) . mb_substr($val, $leftLen, $len - $leftLen - $rightLen) . str_repeat($symbol, $rightLen);
                    } else {
                        $val = mb_substr($val, 0, $leftLen) . str_repeat($symbol, $len - $leftLen - $rightLen) . ($rightLen > 0 ? mb_substr($val, -$rightLen) : '');
                    }
                } else { // 匿名函数
                    $conf($val);
                }
            }
        }, static::$config);
        return $returnData;
    }
}
