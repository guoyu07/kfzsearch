<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

class Tool_Js
{
    /**
     * 打印js开头字符串
     *
     */
    public static function begin()
    {
        echo '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
                <html>
                <head>
                <meta http-equiv="Content-Type"     content="text/html; charset=utf-8">
                <meta http-equiv="Content-Language" content="utf-8">
                <title></title>
                </head>
                <body>
                <script type="text/javascript">';

    }

    /**
     * 打印js结束字符串
     *
     */
    public static function end()
    {
        echo '</script></body></html>';

    }

    /**
     * 调用js的alert方法
     *
     * @return:             string $str       要显示的字符串
     *
     */
    public static function alert($str)
    {
        self::begin();
        echo 'alert("' . $str . '");';
        self::end();

    }

    /**
     * 跳转到指定的页面
     *
     * @return:             string $str       要显示的字符串
     *
     */
    public static function doGoto($url, $target = 'self')
    {
        self::begin();
        if($url == "back"){
            echo 'history.back(-1)';
        }else{
            echo $target . '.location="' . $url . '"';
        }
        self::end();

    }

    /**
     * 打开指定的页面（在新窗口中）
     *
     * @return:             string $str       要显示的字符串
     *
     */
    public static function open($url)
    {
        self::begin();

        echo 'window.open(\'' . $url . '\');';

        self::end();

    }

    /**
     * 显示确认提示框，点确定跳转到url1，点取消跳转到url2，如果url2为空，则不作跳转。
     *
     * @return:             string $str       要显示的字符串
     *
     */
    public static function confirm($msg, $url1, $url2, $target = "self")
    {
        self::begin();
        echo <<<EOT
        if(confirm("$msg"))
        {
            if("$url1" == "back")
            {
                history.back(-1);
            }
            else
            {
                $target.location = "$url1";
            }
        }
        else
        {
            if("$url2" == "")
            {
                
            }
            else if("$url2" == "back")
            {
                history.back(-1);
            }
            else
            {
                $target.location = "$url2";
            }
        }
EOT;
        self::end();

    }

    /**
     * 根据php数组生成JS数组格式的字符串
     *
     * @param           array      $arr             要生成JS数组格式字符串的php数组
     * @param           string     $name            所生成JS数组格式字符串中JS数组的名称
     * @return          String     $jsArrayStr      所生成的JS数组格式字符串
     *
     */
    public static function buildJsArray($arr, $name)
    {
        $jsArrayStr = 'var ' . $name . ' = ';
        if(is_array($arr)){
            $jsArrayStr .= self::buildJsArrayItems($arr);
        }else{
            return false;
        }

        $jsArrayStr = substr_replace($jsArrayStr, '', strlen($jsArrayStr) - 1);
        $jsArrayStr .= ';';
        return $jsArrayStr;

    }

    /**
     * 递归函数，根据php数组生成JS数组格式的字符串，为buildJsArray函数所调用
     *
     * @param           array      $arr             要生成JS数组格式字符串的php数组
     * @return          String     $str             所生成的JS数组格式字符串
     *
     */
    public static function buildJsArrayItems($arr)
    {
        $str = "[";
        foreach($arr as $value){
            if(is_array($value)){
                $str .= self::buildJsArrayItems($value);
            }else{
                $str .= "'$value',";
            }
        }

        $str = substr_replace($str, '', strlen($str) - 1);

        $str .= "],";

        return $str;

    }

    /**
     * 关闭当前窗口
     *
     */
    public static function closeWindow()
    {
        self::begin();
        echo 'window.close();';
        self::end();

    }

    /**
     * 重新载入父窗口
     *
     */
    public static function parentReload()
    {
        self::begin();
        echo 'window.opener.parent.location.reload();';
        self::end();

    }

}