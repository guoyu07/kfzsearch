<?php

/**
 * 邮件
 * 
 * @author xinde <zxdxinde@gmail.com>
 * @date   2015年1月4日14:02:08
*/ 
class Tool_Mail
{
    //邮件通知模板
    static $TEMPLATE_NOTICE = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>孔夫子旧书网-审核系统</title>
</head>
<body style="background:#f6f6f6;">
    <table width="650" border="0" align="center" cellspacing="0" cellpadding="0" style="border:1px solid #ececec; background:#fff; color:#333;">
        <tr>
            <td style="padding:20px 10px 20px 28px; border-bottom:1px solid #ececec;">
                <a href="{{site.verify}}" target="_blank"><img src="{{site.verify}}images/logo.jpg" width="117" height="43"  alt="孔夫子旧书网-审核系统" style="border:none;"/></a>
            </td>
        </tr>
        <tr>
            <td style="padding:28px 30px; font-size:12px; line-height:20px;">
                
                <div style="margin:15px auto;font-size:18px;color:blue;font-weight:bold;">监控提醒</div>
                <div style="margin:15px auto;">当前时间:{{date}}</div>
                <div style="margin:15px auto;">监控功能:{{name}}</div>
                <div style="margin:15px auto;">监控结果:{{content}}</div>
                
                <div style="margin-top:20px;">谢谢</div>
                <div>孔夫子旧书网</div>
                <div style="border-top:1px dotted #ccc; margin-top:20px;"></div>
                <div style="color:#999999; margin-top:20px;">本邮件由系统自动发出，请勿直接回复！</div>
            </td>
        </tr>
    </table>
    <table width="650" border="0" align="center" cellpadding="0" cellspacing="0" style="line-height:22px; color:#999; font-size:12px; margin-top:20px;" >
        <tr><td align="center">Copyright © 2002-{{year}} 孔夫子旧书网 <a href="{{site.www}}" style="color:#0066cc;" target="_blank">www.kongfz.com</a> 版权所有</td></tr>
    </table>
</body>
</html>';
    
    /**
     * 渲染视图
     * @param string $template
     * @param array $data
     * @return string
     */
    private static function render($template, $data)
    {
        $map = self::getReplaceMap($data);
        return str_replace(array_keys($map), array_values($map), $template);
    }

    /**
     * 获取替换映射
     * @param array $data
     * @param string $prefix
     * @return array
     */
    private static function getReplaceMap($data, $prefix = '')
    {
        $map = array();
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $map += self::getReplaceMap($value, $prefix . $key . '.');
            } else {
                $map['{{' . $prefix . $key . '}}'] = $value;
            }
        }
        return $map;
    }
    
    /**
     * 通知方法,处理发邮件等
     * 
     * @param array  $receiversInfo 接收人信息
     * @param string $actionName 功能名
     * @param string $actionContent 详情
     * @param string $title      邮件标题
     */
    public static function notice($receiversInfo, $actionName, $actionContent, $title = '')
    {
        $title = $title ? $title : '【kfz审核系统通知】';
        $datetime = date('Y-m-d H:i:s', time());

        $data = array();
        $site = Yaf\Application::app()->getConfig()->site->toArray();
        $data['site'] = array('verify' => $site['verify'], 'www' => $site['www']);
        $data['title'] = $title;
        $data['name'] = $actionName;
        $data['content'] = $actionContent;
        $data['year'] = date('Y');
        $data['date'] = $datetime;
        $content = self::render(self::$TEMPLATE_NOTICE, $data);

        foreach ($receiversInfo as $receiver) {
            Kfz_Lib_Mail::sendMailBySmtp($receiver['email'], $receiver['name'], $title, $content);
        }
    }
}