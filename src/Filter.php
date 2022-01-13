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
        'include' => null,
        'roles' => [
            // 定义key应该被函数进行处理，你可通过设置key为null来移除。
            // 'key' => function(&$value) { $value = '******'; },
        ],
        'group' => [ // 特殊URI配置，多组配置，优先级高于include和roles
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
        $isMatch = false;
        if (!empty(static::$config['group'])) {
            foreach (static::$config['group'] as &$configUnit) {
                $returnData = static::apply($originData, $configUnit, $isMatch, $uri);
                if ($isMatch) {
                    return $returnData;
                }
            }
        }
        return static::apply($originData, static::$config, $isMatch, $uri);
    }

    /**
     * 过滤响应字段
     *
     * @param mixed $originData 原始数据
     * @param array $config 配置
     * @param bool $isMatch 是否匹配
     * @param string $uri 请求URI
     * @return mixed 过滤后的数据，如果原始数据有对象默认转换为数组
     */
    private static function apply(&$originData, &$config, bool &$isMatch, string $uri)
    {
        $isMatch = false;
        if (is_null($config['include'])) {
            return $originData;
        }
        if (is_array($config['include']) && isset($config['include']['match'])) {
            // 数组配置
            if (!preg_match($config['include']['match'], $uri)) {
                return $originData;
            }
        } elseif (!$config['include']($uri)) { // 匿名函数配置
            return $originData;
        }
        $isMatch = true;
        $returnData = json_decode(json_encode($originData), true);
        $originData = null;
        $handler = function (&$val, $keyOrigin, &$config, array $chains = []) {
            if (empty($config['dot'])) {
                $condition = isset($config['roles'][$key]) && !is_null($config['roles'][$key]);
                $key = $keyOrigin;
            } else { // 开启dot匹配
                $condition = false;
                $key = '';
                foreach ($config['roles'] as $k => &$v) {
                    $dotArray = explode('.', $k);
                    end($dotArray);
                    $dotArrayLength = key($dotArray) + 1;
                    if (!isset($chains[$dotArrayLength - 1])) {
                        continue;
                    }
                    if ($dotArray === array_slice($chains, -$dotArrayLength)) {
                        $condition = true;
                        $key = $k;
                        break;
                    }
                }
            }
            if ($condition) {
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
        };
        if (empty($config['dot'])) {
            array_walk_recursive($returnData, $handler, $config);
        } else {
            // 这里用的是深度优先搜索。因为一般响应有可能出现长度很长的数组，比如返回1000个订单等等。
            // 但是不太可能出现深度达到1000的数组或对象，所以如果这里用宽度优先搜索就可能需要创建一些
            // 长度很大的数组。
            if (!is_array($returnData)) {
                return $returnData;
            }
            $stack = [&$returnData]; // 里面存放的一定是数组，但是有可能为空
            $keyChain = [];
            while (!empty($stack)) {
                // 1. 从栈尾获取一个节点
                end($stack);
                $stackLastKey = key($stack);
                $node = &$stack[$stackLastKey];
                // 2. 展开节点
                $k = key($node);
                next($node);
                if (is_null($k)) { // 节点展开完成，无法进一步展开，那么此节点删除出栈
                    array_pop($stack);
                    array_pop($keyChain);
                } else { // 能进一步展开
                    $keyChain[] = $k;
                    if (is_array($node[$k])) { // 节点展开发现子节点，子节点入栈
                        $stack[] = &$node[$k];
                    } else { // 子节点不能展开的，那么不是数组，交给$handler
                        $handler($node[$k], $k, $config, $keyChain);
                        array_pop($keyChain);
                    }
                }
            }
        }
        return $returnData;
    }
}
