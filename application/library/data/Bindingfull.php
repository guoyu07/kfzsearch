<?php
/**
 * @Description    会员星级与竞拍保证金对应关系
 * @author wangkongming <komiles@163.com> 
 * @date 2014-7-28  15:25:08
 * @copyright Copyright (C) 2002-2014 孔夫子旧书网
 */
class Data_Bindingfull extends Data_Abstract
{
    public static $data  = 
        array(
            // 老数据
                '1' => '平装',
                //'2' => '软精装（覆膜版）',
                //'3' => '硬精装（硬纸版）',
                '2' => '精装',
                '4' => '线装',
                '5' => '毛边本',
                '9' => '其它',

            // 新数据 : binding_1.php
                '11' => '线装',
                '19' => '其它',

            // 新数据 : binding_2.php
                '21' => '平装',
                '22' => '硬精装',
                '23' => '软精装',
                '29' => '其它',

            // 新数据 : binding_4.php
                '46' => '软片',
                '47' => '托片',
                '45' => '镜心',
                '48' => '镜框',
                '41' => '立轴',
                '410' => '横幅',
                '42' => '扇面',
                '43' => '册页',
                '44' => '手卷',
                '49' => '其他',

            // 新数据 : binding_5.php
                '51'  => '未装裱',
                '52'  => '绷框',
                '53'  => '装框',
                '59'  => '其他'
        );
}