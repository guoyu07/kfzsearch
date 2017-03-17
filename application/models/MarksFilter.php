<?php

/**
 * 审核数据项过滤类（处理方式保持和原JAVA审核程序一致，否则历史数据可能失效）
 * 
 * @author      xinde <zxdxinde@gmail.com>
 * @date        2014年9月2日11:59:30
 */
class MarksFilterModel extends Kfz_System_ModelAbstract
{
    public function __construct()
    {
        ;
    }
    
    /**
     * 过滤文本中的标记字符
     * 
     * @param string $text
     */
    public static function filteText($text)
    {
        // 1、清除半角、全角括号及其内容
        $text = preg_replace('/（.*?）|【.*?】|<.*?>|〈.*?〉|〔.*?〕|［.*?］|｛.*?｝|｛.*?\\}|\(.*?\)|\[.*?\]|\{.*?\}/is', '', $text);
        // 2、清除转义字符
        $text = preg_replace('/&[a-z]+;/is', '', $text);
        // 3、过滤中、英文标点符号和其它特殊符号
        $text = self::filteMarks($text);
        // 4、全角转半角
        $text = self::fullWidth2halfWidth($text);
        // 5、英文的大写字母转为小写字母
        $text = strtolower($text);
        // 6、去空格
        $text = preg_replace('/[(\xc2\xa0)|\s]+/u', '', $text);
        return $text;
    }
    
    /**
     * 过滤标记符号
     * 
     * @param string $text
     */
    private static function filteMarks($text)
    {
        $marks = array(
            '[', ']', '-', '\\',
            ' ', '.', '?', '|', '(', ')', '{', '}', '$', '*', '+', '^', '!', '"', '#', '%', '&', '\'', ',', '/', ':', ';', '<', '=', '>', '@', '_', '`', '~', '‖', '¨', '°', '±', '·', '×', '÷', 'ˇ', 'ˉ', 'ˊ', 'ˋ', '˙', '‐', '–', '—', '―', '－', '‘', '’', '“', '”', '‥', '…', '、', '，', '。', '：', '；', '！', '？', '〈', '〉', '《', '》', '「', '」', '『', '』', '【', '】', '〔', '〕', '［', '］', '〖', '〗', '（', '）', '・', '＂', '＇', '＃', '＄', '％', '＆', '＊', '＋', '．', '／', '＼', '＾', '＿', '｀', '｛', '｜', '｝', '～', '￠', '￡', '￢', '￣', '￤', '￥',
            'Ⅰ', 'Ⅱ', 'Ⅲ', 'Ⅳ', 'Ⅴ', 'Ⅵ', 'Ⅶ', 'Ⅷ', 'Ⅸ', 'Ⅹ', 'Ⅺ', 'Ⅻ', 'ⅰ', 'ⅱ', 'ⅲ', 'ⅳ', 'ⅴ', 'ⅵ', 'ⅶ', 'ⅷ', 'ⅸ', 'ⅹ', '㈠', '㈡', '㈢', '㈣', '㈤', '㈥', '㈦', '㈧', '㈨', '㈩', '①', '②', '③', '④', '⑤', '⑥', '⑦', '⑧', '⑨', '⑩', '⑴', '⑵', '⑶', '⑷', '⑸', '⑹', '⑺', '⑻', '⑼', '⑽', '⑾', '⑿', '⒀', '⒁', '⒂', '⒃', '⒄', '⒅', '⒆', '⒇', '⒈', '⒉', '⒊', '⒋', '⒌', '⒍', '⒎', '⒏', '⒐', '⒑', '⒒', '⒓', '⒔', '⒕', '⒖', '⒗', '⒘', '⒙', '⒚', '⒛',
            '¤', '§', '‰', '′', '″', '‵', '※', '℃', '％', '℅', '℉', '№', '℡', '←', '↑', '→', '↓', '↖', '↗', '↘', '↙', '∈', '∏', '∑', '∕', '√', '∝', '∞', '∟', '∠', '∣', '∥', '∧', '∨', '∩', '∪', '∫', '∮', '∴', '∵', '∶', '∷', '∽', '≈', '≌', '≒', '≠', '≡', '≤', '≥', '≦', '≧', '≮', '≯', '⊙', '⊥', '⊿', '⌒', '㈱', '㊣', '㎎', '㎏', '㎜', '㎝', '㎞', '㎡', '㏄', '㏎', '㏑', '㏒', '㏕', '─', '━', '│', '┃', '┄', '┅', '┆', '┇', '┈', '┉', '┊', '┋', '┌', '┍', '┎', '┏', '┐', '┑', '┒', '┓', '└', '┕', '┖', '┗', '┘', '┙', '┚', '┛', '├', '┝', '┞', '┟', '┠', '┡', '┢', '┣', '┤', '┥', '┦', '┧', '┨', '┩', '┪', '┫', '┬', '┭', '┮', '┯', '┰', '┱', '┲', '┳', '┴', '┵', '┶', '┷', '┸', '┹', '┺', '┻', '┼', '┽', '┾', '┿', '╀', '╁', '╂', '╃', '╄', '╅', '╆', '╇', '╈', '╉', '╊', '╋', '═', '║', '╒', '╓', '╔', '╕', '╖', '╗', '╘', '╙', '╚', '╛', '╜', '╝', '╞', '╟', '╠', '╡', '╢', '╣', '╤', '╥', '╦', '╧', '╨', '╩', '╪', '╫', '╬', '╭', '╮', '╯', '╰', '╱', '╲', '╳', '▁', '▂', '▃', '▄', '▅', '▆', '▇', '█', '▉', '▊', '▋', '▌', '▍', '▎', '▏', '▓', '▔', '▕', '■', '□', '▲', '△', '▼', '▽', '◆', '◇', '○', '◎', '●', '◢', '◣', '◤', '◥', '★', '☆', '☉', '♀', '♁', '♂', '〒', '〓', '〃', '々', '〆', '〇'
        );
        $text = str_replace($marks, '', $text);
        return $text;
    }
    
    /**
     * 全角字符转为半角字符
     * 
     * @param string $text
     */
    private static function fullWidth2halfWidth($text)
    {
        $search = array('０', '１', '２', '３', '４', '５', '６', '７', '８', '９', 'Ａ', 'Ｂ', 'Ｃ', 'Ｄ', 'Ｅ', 'Ｆ', 'Ｇ', 'Ｈ', 'Ｉ', 'Ｊ', 'Ｋ', 'Ｌ', 'Ｍ', 'Ｎ', 'Ｏ', 'Ｐ', 'Ｑ', 'Ｒ', 'Ｓ', 'Ｔ', 'Ｕ', 'Ｖ', 'Ｗ', 'Ｘ', 'Ｙ', 'Ｚ', 'ａ', 'ｂ', 'ｃ', 'ｄ', 'ｅ', 'ｆ', 'ｇ', 'ｈ', 'ｉ', 'ｊ', 'ｋ', 'ｌ', 'ｍ', 'ｎ', 'ｏ', 'ｐ', 'ｑ', 'ｒ', 'ｓ', 'ｔ', 'ｕ', 'ｖ', 'ｗ', 'ｘ', 'ｙ', 'ｚ');
        $replace = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z');
        $text = str_replace($search, $replace, $text);
        return $text;
    }
    
    /**
     * 过滤书名
     * 
     * @param string $bookName
     */
    public static function filteBookName($bookName)
    {
        // 先取使用书名号《》括起来的书名
        $result = self::getBookNameInMark('/《(.*)?》/is', $bookName);
        if(!$result) {
            $result = self::getBookNameInMark('/<<(.*)?>>/is', $bookName);
        }
        // 过滤特殊符号
        if($result) {
            $result = self::filteText($result);
        } else {
            $result = self::filteText($bookName);
        }
        // 过滤一些干扰文字
        $result = preg_replace('/[总第笫全共印大重]+[0-9一二三四五六七八九十○]+[期册本卷回集部辑版开斤]/isu', '', $result);
        $result = preg_replace('/[0-9一二三四五六七八九十○两中华民国]+[年版代月日号期函种万册全冊本卷回集部辑印元开本品成新页面折幅套枚]{1,2}/isu', '', $result);
        $result = preg_replace('/[卷期函册本回种][0-9一二三四五六七八九十○]+/isu', '', $result);
        $result = preg_replace('/\d+[厘米|公分|cm]/isu', '', $result);
        $result = preg_replace('/[x]\d+/isu', '', $result);
        $result = preg_replace('/合订本|精装本|硬精装|精装|精印本|插图本|典藏本|线装|平装|石印|油印本|漆布面|白宣纸|布面|道林纸|全彩|铜版/isu', '', $result);
        $result = preg_replace('/竖排本|竖版|民国版|民国初版|香港原版书|台湾版|香港版|港台版|台版|港版|成人版|初版|再版|厚册|仅印/isu', '', $result);
        $result = preg_replace('/非馆藏|馆藏|大开本|大开|上卷|下卷|上册|下册|上中下|稀见|全函|缩印/isu', '', $result);
        $result = preg_replace('/全新正版|正版|复印件|特惠价销售|特价|原价|包邮挂费|包邮挂|保邮挂|包邮|合售|网上最便宜为/isu', '', $result);
        $result = preg_replace('/封面精美|扉页有签|皮面有签|带护封|护封|内有很多|木刻插图|无字迹勾画|只缺版权页|缺版权页|版权页遗失|无封面和封底/isu', '', $result);
        $result = preg_replace('/请见叙述|相见描述|品见描述|见描述|版权见图及描述|内有插图|有插图|内多图|详细见图|其余完好|如图|有图|详见/isu', '', $result);
        $result = preg_replace('/品相看图|品相见图|品看图|品如图|品极佳|品相佳|品一般|品好|好品|品佳|补图|看图片|内画|几乎全图|见图|书衣旧|内新/isu', '', $result);
        $result = preg_replace('/[a-z]{1,3}\d{1,}/isu', '', $result);
        $result = preg_replace('/\d{2,}/isu', '', $result);
        if(!$result) {
            $result = self::getBookNameInMark('/【(.*)?】/is', $bookName);
        }
        if(!$result) {
            $result = self::getBookNameInMark('/［(.*)?］/is', $bookName);
        }
        if(!$result) {
            $result = self::getBookNameInMark('/\[(.*)?\]/is', $bookName);
        }
        if(!$result) {
            $result = self::getBookNameInMark('/<(.*)?>/is', $bookName);
        }
        $result = trim($result);
        
        return $result;
    }
    
    /**
     * 获取标记中书名
     * 
     * @param string $preg
     * @param string $bookName
     */
    private static function getBookNameInMark($preg, $bookName)
    {
        preg_match($preg, $bookName, $matches);
        if($matches) {
            return $matches[1];
        } else {
            return '';
        }
    }
    
    /**
     * 过滤"出版社"
     * 
     * @param string $text
     */
    public static function filtePress($text)
    {
        $text = self::filteText($text);
        $searchArr = array(
            '出版社',
            '出版'
        );
        return str_replace($searchArr, '', $text);
    }
    
    /**
     * 过滤html及空格
     * 
     * @param string $text
     */
    public static function filteHtml($text)
    {
        return strip_tags(trim($text));
    }
}
?>