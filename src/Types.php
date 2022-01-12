<?php
namespace Desensitization;

/**
 * 内置的掩盖类型
 *
 * 例如，姓名、手机、地址、邮件等
 */
class Types
{
    /**
     * 普通证件号码
     *
     * 除了身份证号码，银行卡号之外的一般的证件号码（护照、军官证件等）处理规则
     *
     * @param string $val 待处理值
     * @param string $symbol 掩盖符号
     * @return void
     */
    public static function credential(string &$val, string $symbol = '*')
    {
        $len = mb_strlen($val);
        if ($len < 2) {
            return;
        }
        $val = mb_substr($val, 0, 1) . str_repeat($symbol, $len - 2) . mb_substr($val, -1, 1);
    }

    /**
     * 身份证号码
     *
     * @param string $val 待处理值
     * @param string $symbol 掩盖符号
     * @return void
     */
    public static function idcard(string &$val, string $symbol = '*')
    {
        $len = mb_strlen($val);
        if ($len < 4) {
            return;
        }
        $val = mb_substr($val, 0, 2) . str_repeat($symbol, $len - 4) . mb_substr($val, -2, 2);
    }

    /**
     * 银行卡号码
     *
     * @param string $val 待处理值
     * @param string $symbol 掩盖符号
     * @return void
     */
    public static function bank(string &$val, string $symbol = '*')
    {
        $len = mb_strlen($val);
        if ($len < 8) {
            return;
        }
        $val = mb_substr($val, 0, 4) . str_repeat($symbol, $len - 8) . mb_substr($val, -4, 4);
    }

    /**
     * 网络账号
     *
     * QQ、微博、微信（含微信小程序id、支付宝用户ID等）
     *
     * @param string $val 待处理值
     * @param string $symbol 掩盖符号
     * @return void
     */
    public static function netaccount(string &$val, string $symbol = '*')
    {
        $len = mb_strlen($val);
        if ($len < 2) {
            return;
        }
        $val = mb_substr($val, 0, 1) . str_repeat($symbol, $len - 2) . mb_substr($val, -1, 1);
    }

    /**
     * IP地址
     *
     * @param string $val 待处理值
     * @param string $symbol 掩盖符号
     * @return void
     */
    public static function ip(string &$val, string $symbol = '*')
    {
        $len = mb_strlen($val);
        if ($len < 6) {
            $val = str_repeat($symbol, $len);
            return;
        }
        $val = mb_substr($val, 0, $len - 6) . str_repeat($symbol, 6);
    }

    /**
     * 个人手机号码
     *
     * @param string $val 待处理值
     * @param string $symbol 掩盖符号
     * @return void
     */
    public static function mobile(string &$val, string $symbol = '*')
    {
        $len = mb_strlen($val);
        if ($len <= 4) {
            return;
        }
        if ($len > 4 && $len <= 8) {
            $val = mb_substr($val, 0, 4) . str_repeat($symbol, $len - 4);
            return;
        }
        $val = mb_substr($val, 0, 3) . str_repeat($symbol, 4) . mb_substr($val, 7);
    }

    /**
     * 座机号码
     *
     * 保留区号和后2位，其余掩盖
     *
     * @param string $val 待处理值
     * @param string $symbol 掩盖符号
     * @return void
     */
    public static function telephone(string &$val, string $symbol = '*')
    {
        $len = mb_strlen($val);
        if ($len <= 2) {
            return;
        }

        $funcParentheses = function ($l, $r, &$val, $symbol, $len) {
            $left = mb_strpos($val, $l);
            $right = mb_strpos($val, $r);
            $head = str_repeat($symbol, $left) . mb_substr($val, $left, $right - $left + 1);
            $lenHead = mb_strlen($head);
            if ($len - $lenHead <= 2) {
                $val = $head . mb_substr($val, -($len - $lenHead), $len - $lenHead);
                return;
            }
            $val = $head . str_repeat($symbol, $len - $lenHead - 2) . mb_substr($val, -2, 2);
        };

        $funcHorizontalLine = function($line, &$val, $symbol, $len) {
            $hori = mb_strpos($val, $line);
            $head = mb_substr($val, 0, $hori + mb_strlen($line));
            $lenHead = mb_strlen($head);
            if ($len - $lenHead <= 2) {
                $val = $head . mb_substr($val, -($len - $lenHead), $len - $lenHead);
                return;
            }
            $val = $head . str_repeat($symbol, $len - $lenHead - 2) . mb_substr($val, -2, 2);
        };

        $left = mb_strpos($val, '(');
        $right = mb_strpos($val, ')');
        if (false !== $left && false !== $right && $left < $right) {
            $funcParentheses('(', ')', $val, $symbol, $len);
            return;
        }
        $left = mb_strpos($val, '[');
        $right = mb_strpos($val, ']');
        if (false !== $left && false !== $right && $left < $right) {
            $funcParentheses('[', ']', $val, $symbol, $len);
            return;
        }
        if (false !== mb_strpos($val, '-')) {
            $funcHorizontalLine('-', $val, $symbol, $len);
            return;
        }
        if (false !== mb_strpos($val, '_')) {
            $funcHorizontalLine('_', $val, $symbol, $len);
            return;
        }

        if ($len <= 5) {
            return;
        }
        $head = mb_substr($val, 0, 3);
        $tail = mb_substr($val, -2);
        $val = $head . str_repeat($symbol, $len - 5) . $tail;
    }

    /**
     * 姓名
     *
     * @param string $val 待处理值
     * @param string $symbol 掩盖符号
     * @return void
     */
    public static function name(string &$val, string $symbol = '*')
    {
        $len = mb_strlen($val);
        switch ($len) {
        case 0:
            break;
        case 1:
            $val = $symbol;
            break;
        case 2:
        case 3:
            $val = $symbol . mb_substr($val, 1);
            break;
        default:
            $mark = floor(0.5 * $len);
            $val = str_repeat($symbol, $mark) . mb_substr($val, $mark);
        }
    }

    /**
     * 车牌号
     *
     * @param string $val 待处理值
     * @param string $symbol 掩盖符号
     * @return void
     */
    public static function plate(string &$val, string $symbol = '*')
    {
        $len = mb_strlen($val);
        if ($len <= 4) {
            $val = str_repeat($symbol, $len);
            return;
        }
        $head = mb_substr($val, 0, 2);
        $tail = mb_substr($val, -2);
        $val = $head . str_repeat($symbol, $len - 4) . $tail;
    }

    /**
     * E-mail
     *
     * @param string $val 待处理值
     * @param string $symbol 掩盖符号
     * @return void
     */
    public static function email(string &$val, string $symbol = '*')
    {
        $len = mb_strlen($val);
        $at = mb_strpos($val, '@');
        if (false === $at) {
            return;
        }
        $head = mb_substr($val, 0, $at);
        $tail = mb_substr($val, $at);
        $headShow = mb_substr($head, 0, 3);
        $val = $headShow . str_repeat($symbol, mb_strlen($head) - mb_strlen($headShow)) . $tail;
    }

    /**
     * 地址
     *
     * @param string $val 待处理值
     * @param string $symbol 掩盖符号
     * @return void
     */
    public static function address(string &$val, string $symbol = '*')
    {
        $len = mb_strlen($val);
        $keywords = ['区', '市', '省'];
        if ($len <= 1) {
            return;
        }
        foreach ($keywords as $key) {
            $start = mb_strpos($val, $key);
            if (false !== $start) {
                $head = mb_substr($val, 0, $start + 1);
                $val = $head . str_repeat($symbol, $len - mb_strlen($head));
                return;
            }
        }
        $val = str_repeat($symbol, $len);
    }
}
