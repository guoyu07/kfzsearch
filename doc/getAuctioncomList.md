# 获取分类 #
---
## 调用地址 ##

http://search.kongfz.com/interface/Auctioncom/service

---
**参数列表**

参数名称|参数类型|参数描述|是否必填
------|------|------|------
action|string|getAuctioncomList|true
bizFlag|string|auctioncom|true
requestParams*|array|查询参数|true
otherParams*|array|预留参数|false

>requestParams取值说明

```
$params['requestParams'] = array(
	array('key' => 'key', 'value' => '银象'),//笼统查询，传此参数时，会同时匹配拍品名称，拍卖公司名称和年代三个选项
	array('key' => 'itemname', 'value' => '11'),//指定拍品名称查询
	array('key' => 'comname', 'value' => '银象'),//指定拍卖公司名称查询
	array('key' => 'decade', 'value' => '清代'),//指定年代查询
	array('key' => 'order', 'value' => '5'),
	array('key' => 'pagenum', 'value' => 2),//分页页码
	array('key' => 'comid', 'value' => 1234),//拍卖公司id，多个的话用h链接，例如123h234
	);
```
>otherParams取值说明

```
$params['otherParams'] = array(
	'pageSize'=>50,//分页大小
	'expire'=>600,//指定缓存时间
	);
```
>排序说明：

```
    case 1:
        $order = 'beginprice asc';//起拍价升序
        break;
    case 2:
        $order = 'beginprice desc';//起拍价降序
        break;
    case 3:
        $order = 'beginrefprice asc';//参考价升序
        break;
    case 4:
        $order = 'beginrefprice desc';//参考价降序
        break;
    case 5:
        $order = 'bargainprice asc';//成交价升序
        break;
    case 6:
        $order = 'bargainprice desc';//成交价降序
        break;
    case 7:
        $order = 'begintime2 asc';//开拍时间升序
        break;
    case 8:
        $order = 'begintime desc';//开拍时间降序
        break;
    case 9:
        $order = 'viewednum asc';//点击数升序
        break;
    case 10:
        $order = 'viewednum desc';//点击数降序
        break;
    default:
        $order = 'begintime2 asc';
        break;
```

---
**返回值**

返回值|返回值类型|返回值描述|||
------|------|------|------|------
result||||
......|queryRet|bool|接口处理状态 false-失败，true-成功
......|failDesc|string|错误描述
......|result[{}]|list |array|拍品列表
......|......|stat|array|结果统计
error|string|rpc错误信息


---
**返回结果样例**
*成功结果*
```
array(2) {
  ["result"]=>
  array(3) {
    ["queryRet"]=>
    bool(true)
    ["result"]=>
    array(2) {
      ["list"]=>
      array(10) {
        [0]=>
        array(21) {
          ["id"]=>
          string(3) "202"
          ["pid"]=>
          string(1) "7"
          ["comid"]=>
          string(1) "1"
          ["comname"]=>
          string(27) "海王村拍卖有限公司"
          ["userid"]=>
          string(3) "111"
          ["cusid"]=>
          string(1) "2"
          ["itemname"]=>
          string(18) "弘正四杰诗集"
          ["catid"]=>
          string(1) "1"
          ["author"]=>
          string(14) "(清)张百熙"
          ["decade"]=>
          string(48) "清光绪二十一年(1895)张氏湘雨楼刊本"
          ["beginprice"]=>
          string(8) "5,000.00"
          ["beginrefprice"]=>
          string(4) "0.00"
          ["endrefprice"]=>
          string(4) "0.00"
          ["bargainprice"]=>
          string(4) "0.00"
          ["bigimg"]=>
          string(0) ""
          ["ishidden"]=>
          string(1) "0"
          ["viewednum"]=>
          string(1) "0"
          ["speid"]=>
          string(1) "1"
          ["isdeleted"]=>
          string(1) "0"
          ["begintime"]=>
          string(1) "0"
          ["begintime2"]=>
          string(8) "29991231"
        }
        [1]=>
        array(21) {
          ["id"]=>
          string(3) "204"
          ["pid"]=>
          string(1) "8"
          ["comid"]=>
          string(1) "1"
          ["comname"]=>
          string(27) "海王村拍卖有限公司"
          ["userid"]=>
          string(3) "111"
          ["cusid"]=>
          string(1) "2"
          ["itemname"]=>
          string(18) "弘正四杰诗集"
          ["catid"]=>
          string(1) "1"
          ["author"]=>
          string(14) "(清)张百熙"
          ["decade"]=>
          string(48) "清光绪二十一年(1895)张氏湘雨楼刊本"
          ["beginprice"]=>
          string(8) "5,000.00"
          ["beginrefprice"]=>
          string(4) "0.00"
          ["endrefprice"]=>
          string(4) "0.00"
          ["bargainprice"]=>
          string(4) "0.00"
          ["bigimg"]=>
          string(0) ""
          ["ishidden"]=>
          string(1) "0"
          ["viewednum"]=>
          string(1) "0"
          ["speid"]=>
          string(1) "1"
          ["isdeleted"]=>
          string(1) "0"
          ["begintime"]=>
          string(1) "0"
          ["begintime2"]=>
          string(8) "29991231"
        }
        [2]=>
        array(21) {
          ["id"]=>
          string(3) "206"
          ["pid"]=>
          string(1) "9"
          ["comid"]=>
          string(1) "1"
          ["comname"]=>
          string(27) "海王村拍卖有限公司"
          ["userid"]=>
          string(3) "111"
          ["cusid"]=>
          string(1) "2"
          ["itemname"]=>
          string(18) "弘正四杰诗集"
          ["catid"]=>
          string(1) "1"
          ["author"]=>
          string(14) "(清)张百熙"
          ["decade"]=>
          string(48) "清光绪二十一年(1895)张氏湘雨楼刊本"
          ["beginprice"]=>
          string(8) "5,000.00"
          ["beginrefprice"]=>
          string(4) "0.00"
          ["endrefprice"]=>
          string(4) "0.00"
          ["bargainprice"]=>
          string(4) "0.00"
          ["bigimg"]=>
          string(0) ""
          ["ishidden"]=>
          string(1) "0"
          ["viewednum"]=>
          string(1) "0"
          ["speid"]=>
          string(1) "1"
          ["isdeleted"]=>
          string(1) "0"
          ["begintime"]=>
          string(1) "0"
          ["begintime2"]=>
          string(8) "29991231"
        }
      }
      ["stat"]=>
      array(2) {
        ["total"]=>
        string(2) "33"
        ["total_found"]=>
        string(2) "33"
      }
    }
    ["failDesc"]=>
    string(0) ""
  }
  ["error"]=>
  string(0) ""
}

```