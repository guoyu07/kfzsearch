<?php
/**
 * 新搜索分页类文件
 * 版权所有
 * @author      xinde <zxdxinde@gmail.com>
 * @version     $Id: SearchPagination.class.php 4432 2013-10-17 17:00Z zhangxinde $
*/

class Tool_SearchPagination
{
    public $totalNum;  //总数
    public $pageNum;   //当前页数
    public $perNum;    //每页显示数量
    public $customVarStr; //用户自定义变量字符串(除分页)
    public $pageVar;   //用户自定义变量(分页)
    public $endNum;    //尾页页数
    public $pageStr = '';   //分页字符串
    public $totalStr = '';  //总记录字符串
    public $upTotal = '';     //总记录(上)
    public $downTotal = '';   //总记录(下)
    public $previousStr = ''; //上一页字符串
    public $nextStr = '';   //下一页字符串
    public $centerStr = ''; //中间页码字符串
    public $goStr = '';     //跳转字符串
    public $moreStr = '';    //查看更多的结果提示
    public $fixStr = '';   //分页链接调整串
    
    public function __construct($pageNum, $perNum, $totalNum, $customVarStr, $pageVar)
    {
        $this->pageNum = intval($pageNum) ? intval($pageNum) : 1;
        $this->perNum = intval($perNum);
        $this->totalNum = intval($totalNum);
        $this->customVarStr = $customVarStr;
        $this->pageVar = $pageVar;
        $this->endNum = ceil($this->totalNum / $this->perNum) ? ceil($this->totalNum / $this->perNum) : 1;
        $this->fixStr = '/product/';
    }
    
    /*
     * 获取总数字符串
     * 
     * @param int $type
     * @return string
     */
    public function getTotalStr($type)
    {
        switch ($type) {
            case 1:
                $this->upTotal = ($this->pageNum) * ($this->perNum) > $this->totalNum ? $this->totalNum : ($this->pageNum) * ($this->perNum);
                $this->downTotal = $this->pageNum <= 1 ? 1 : ($this->pageNum - 1) * ($this->perNum);
//                $this->totalStr = '<div class="pager_info_box"><em>' . $this->downTotal . '</em>-<i>' . $this->upTotal . '</i>条，共<b>' . $this->totalNum . '</b>条</div>';
                $this->totalStr = '<div class="pager_info_box">' . $this->downTotal . '-' . $this->upTotal . '条</div>';
                break;
            case 2:
                $this->totalStr = '<li class="no_mrgin">'. $this->pageNum. '/<span class="page_total_num_flag">'. $this->endNum. '</span></li>';
            case 3:
                $this->totalStr = '<li class="no_mrgin">'. $this->pageNum. '/'. $this->endNum. '</li><li class="no_mrgin m_r20">查询的图书总数：'. $this->totalNum. '</li>';
                break;
        }
        return $this->totalStr;
    }
    
    /**
     * 获取上一页字符串
     * 
     * @param int $type
     * @return string
     */
    public function getPreStr($type)
    {
        switch ($type) {
            case 1:
                if ($this->pageNum == 0 || $this->pageNum == 1) {
                    $this->previousStr = '';
                } else {
                    $preUrlFix = $this->fixStr . $this->customVarStr . $this->pageVar;
                    $preNum = $this->pageNum - 1;
                    $preUrl = $preUrlFix . $preNum. '/';
                    $this->previousStr = '<a title="上一页" href="' . $preUrl . '" class="pager_prev_btn">&lt; 上一页</a>';
                }
                break;
            case 2:
                if ($this->pageNum == 0 || $this->pageNum == 1) {
                    $this->previousStr = '<li class="left1"><span></span></li>';
                } else {
                    $preUrlFix = $this->fixStr . $this->customVarStr . $this->pageVar;
                    $preNum = $this->pageNum - 1;
                    $preUrl = $preUrlFix . $preNum. '/';
                    $this->previousStr = '<li class="left2"><a href="' . $preUrl . '" title="上一页"></a></li>';
                }
                break;
            case 3:
                if ($this->pageNum == 0 || $this->pageNum == 1) {
                    $this->previousStr = '<li class="left"> <a href="javascript:;" title="上一页" style=" display:none;"></a> <span></span> </li>';
                } else {
                    $preUrlFix = $this->fixStr . $this->customVarStr . $this->pageVar;
                    $preNum = $this->pageNum - 1;
                    $preUrl = $preUrlFix . $preNum. '/';
                    $this->previousStr = '<li class="left"> <a href="'. $preUrl. '" title="上一页" ></a> <span style=" display:none;"></span> </li>';
                }
                break;
        }
        
        return $this->previousStr;
        
    }
    
    /**
     * 获取下一页字符串
     * 
     * @param int $type
     * @return string
     */
    public function getNextStr($type)
    {
        switch ($type) {
            case 1:
                if ($this->pageNum >= $this->endNum) {
                    $this->nextStr = '';
                } else {
                    $nextUrlFix = $this->fixStr . $this->customVarStr . $this->pageVar;
                    $nextNum = $this->pageNum + 1;
                    $nextUrl = $nextUrlFix . $nextNum. '/';
                    $this->nextStr = '<a title="下一页" href="' . $nextUrl . '" class="m_r10 pager_next_btn">下一页 &gt;</a>';
                }
                break;
            case 2:
                if ($this->pageNum >= $this->endNum) {
                    $this->nextStr = '<li class="right1"><span></span></li>';
                } else {
                    $nextUrlFix = $this->fixStr . $this->customVarStr . $this->pageVar;
                    $nextNum = $this->pageNum + 1;
                    $nextUrl = $nextUrlFix . $nextNum. '/';
                    $this->nextStr = '<li class="right2"><a href="' . $nextUrl . '" title="下一页"></a></li>';
                }
                break;
            case 3:
                if ($this->pageNum >= $this->endNum) {
                    $this->nextStr = '<li class="right"> <a href="javascript:;" title="下一页" style=" display:none;"></a> <span></span> </li>';
                } else {
                    $nextUrlFix = $this->fixStr . $this->customVarStr . $this->pageVar;
                    $nextNum = $this->pageNum + 1;
                    $nextUrl = $nextUrlFix . $nextNum. '/';
                    $this->nextStr = '<li class="right"> <a href="'. $nextUrl. '" title="下一页" ></a> <span style=" display:none;"></span> </li>';
                }
                break;
        }
        
        return $this->nextStr;
    }
    
    /**
     * 获取中间页码字符串
     */
    public function getCenterStr()
    {
        $urlFix = $this->fixStr. $this->customVarStr. $this->pageVar;
        if($this->endNum <= 12) { //1 2 3 4 5 6 7 8 9 10 11 12
            for($i = 1; $i <= $this->endNum; $i++) {
                $url = $urlFix. $i. '/';
                if($i != $this->pageNum) {
                    $this->centerStr .= '<a title="'. $i. '" href="'. $url. '">'. $i. '</a>';
                } else {
                    $this->centerStr .= '<a title="'. $i. '" href="javascript:;" class="now">'. $i. '</a>';
                }
            }
        } else {
            $leftNum = $this->pageNum - 1;
            $rightNum = $this->endNum - $this->pageNum;
            if($leftNum > 5 && $rightNum > 5) { // 1 ... 3 4 5 6  7  8 9 10 11 ... 120
                $this->centerStr .= '<a title="1" href="'. $urlFix. strval(1). '/">1</a>';
                $this->centerStr .= '<span>…</span>';
                for($i = $this->pageNum - 4; $i <= $this->pageNum + 4; $i++) {
                    $url = $urlFix. $i. '/';
                    if($i != $this->pageNum) {
                        $this->centerStr .= '<a title="'. $i. '" href="'. $url. '">'. $i. '</a>';
                    } else {
                        $this->centerStr .= '<a title="'. $i. '" href="javascript:;" class="now">'. $i. '</a>';
                    }
                }
                $this->centerStr .= '<span>…</span>';
                $this->centerStr .= '<a title="'. $this->endNum. '" href="'. $urlFix. $this->endNum. '/">'. $this->endNum. '</a>';
            } elseif ($leftNum <= 5) { // 1 2 3 4 5  6  7 8 9 10 11 ... 120
                for ($i = 1; $i <= 11; $i++) {
                    $url = $urlFix . $i. '/';
                    if ($i != $this->pageNum) {
                        $this->centerStr .= '<a title="' . $i . '" href="' . $url . '">' . $i . '</a>';
                    } else {
                        $this->centerStr .= '<a title="' . $i . '" href="javascript:;" class="now">' . $i . '</a>';
                    }
                }
                $this->centerStr .= '<span>…</span>';
                $this->centerStr .= '<a title="' . $this->endNum . '" href="' . $urlFix . $this->endNum . '/">' . $this->endNum . '</a>';
            } elseif ($rightNum <= 5) { //1 ... 3 4 5 6 7  8  9 10 11 12 13
                $this->centerStr .= '<a title="1" href="'. $urlFix. strval(1). '/">1</a>';
                $this->centerStr .= '<span>…</span>';
                for ($i = $this->endNum - 10; $i <= $this->endNum; $i++) {
                    $url = $urlFix . $i. '/';
                    if ($i != $this->pageNum) {
                        $this->centerStr .= '<a title="' . $i . '" href="' . $url . '">' . $i . '</a>';
                    } else {
                        $this->centerStr .= '<a title="' . $i . '" href="javascript:;" class="now">' . $i . '</a>';
                    }
                }
            }
        }
        return $this->centerStr;
    }
    
    /**
     * 获取跳转字符串
     */
    public function getGoStr($type = '')
    {
        $urlFix = $this->fixStr. $this->customVarStr;
        switch($type) {
            case 3:
                $this->goStr = '<li><a href="javascript:;" class="btn_gray_43">确定</a></li><li class="input_box"><input name="" type="text" class=" input_55 m_r10 m_l10"></li>';
                break;
            default:
                $this->goStr = '<span class="m_r10">到<input name="" type="text" class="page_input onlyNum">页</span>
            <a class="pager_turn_btn" href="javascript:;">确定</a>';
                break;
        }
        return $this->goStr;
    }
    
    /**
     * 获取更多内容提示字符串
     */
    public function getMoreStr()
    {
        $url = $this->fixStr. $this->customVarStr. $this->pageVar. '101/';
        if($this->pageNum == 100) {
            $this->moreStr .= '<div class="kfz_pager_notice"><span>温馨提示：</span>限于网页篇幅，部分结果未显示。您可以<a href="'. $url. '">点击此处</a>查看未显示的结果。</div>';
        }
        return $this->moreStr;
    }
    
    /*
     * 生成分页字符串
     * 
     * @param int $type
     * @param string
     */
    public function page($type)
    {
        switch ($type) {
            case 1:
                $this->pageStr = '<div class="clearfix kfz_pager_box">'. $this->getTotalStr(1) . '<div class="pager_num_box">' . $this->getPreStr(1) . $this->getCenterStr() . $this->getNextStr(1) . $this->getGoStr() . '</div><div class="clear"></div></div>'. $this->getMoreStr();
                break;
            case 2:
                $this->pageStr = '<div class="page1 clearfix">
                                    <ul>'. $this->getNextStr(2). $this->getPreStr(2). $this->getTotalStr(2).
                                    '</ul>
                                    </div>';
                break;
            case 3:
                $this->pageStr = '<div class="f_right page1"><ul>'. $this->getGoStr(3). $this->getNextStr(3). $this->getPreStr(3). $this->getTotalStr(3). '</ul></div>';
                break;
        }
        return $this->pageStr;
    }
    
    /*
     * 设置fixStr
     */
    public function setFixStr($str = '')
    {
        $this->fixStr = $str;
    }
}

?>