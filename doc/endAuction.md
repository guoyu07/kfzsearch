# 获取分类 #
---
## 调用地址 ##

http://search.kongfz.com/interface/Endauction/service

call:search
---
**参数列表**

参数名称|参数类型|参数描述|是否必填
------|------|------|------
action|string|getFPWithFilter[返回商品列表和聚类] 或 getPWithFilter[只返回商品列表]|true
bizFlag|string|endauctioin|true
requestParams*|array|查询参数|true
otherParams*|array|预留参数|false

>requestParams取值说明

```
$params['requestParams'] = array(
  array('key' => 'catnum', 'value' => '8'),//分类编号
  array('key' => 'author', 'value' => '冯仑'),//作者
  array('key' => 'press', 'value' => '出版社'),//作者
  array('key' => 'shopname', 'value' => '光滑古籍'),//店名
	array('key' => 'itemname', 'value' => '野蛮生长'),//拍品名称
  array('key' => 'pagenum', 'value' => 2),//分页页码
  array('key' => 'key', 'value' => '银象'),//笼统查询，传此参数时，会同时匹配拍品名称，作者，出版社，isbn选项
  array('key' => 'order', 'value' => '5'),
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
    $order = 'price asc';
    break;
  case 2:
    $order = 'price desc';
    break;
  case 3:
    $order = 'pubdate2 asc';
    break;
  case 4:
    $order = 'pubdate desc';
    break;
  case 5:
    $order = 'addtime asc';
    break;
  case 6:
    $order = 'addtime desc';
    break;
  case 7:
    $order = 'class desc';
    break;
```

---
**返回值**

返回值|返回值类型|返回值描述|||
------|------|------|------|------
result||||
......|queryRet|bool|接口处理状态 false-失败，true-成功
......|failDesc|string|错误描述
......|result[{}]|7 |array|拍品列表[含统计信息]
......|......|1|array|聚类列表
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
    array(3) {
      [7]=>
      array(5) {
        [0]=>
        array(56) {
          ["id"]=>
          string(6) "296961"
          ["pid"]=>
          string(5) "29973"
          ["userid"]=>
          string(5) "43803"
          ["auctionarea"]=>
          string(1) "2"
          ["specialarea"]=>
          string(1) "0"
          ["catid"]=>
          string(17) "20000000000000000"
          ["catid1"]=>
          string(17) "20000000000000000"
          ["catid2"]=>
          string(1) "0"
          ["catid3"]=>
          string(1) "0"
          ["catid4"]=>
          string(1) "0"
          ["vcatid"]=>
          string(1) "0"
          ["vcatid1"]=>
          string(1) "0"
          ["vcatid2"]=>
          string(1) "0"
          ["vcatid3"]=>
          string(1) "0"
          ["vcatid4"]=>
          string(1) "0"
          ["catid1g"]=>
          string(17) "20000000000000000"
          ["quality"]=>
          string(1) "0"
          ["itemname"]=>
          string(71) "中国社会科学出版社 &lt;&lt;范文澜历史论文选集&gt;&gt;"
          ["nickname"]=>
          string(3) "zuo"
          ["author"]=>
          string(0) ""
          ["author2"]=>
          string(0) ""
          ["press"]=>
          string(0) ""
          ["press2"]=>
          string(0) ""
          ["img"]=>
          string(0) ""
          ["hasimg"]=>
          string(1) "0"
          ["params"]=>
          string(14) "{"binding":29}"
          ["iauthor"]=>
          string(1) "0"
          ["ipress"]=>
          string(1) "0"
          ["pubdate"]=>
          string(1) "0"
          ["pubdate2"]=>
          string(8) "29991231"
          ["prestarttime"]=>
          string(1) "0"
          ["begintime"]=>
          string(10) "1129041791"
          ["endtime"]=>
          string(10) "1129293911"
          ["beginprice"]=>
          string(9) "15.000000"
          ["minaddprice"]=>
          string(8) "3.000000"
          ["iscreatetrade"]=>
          string(1) "0"
          ["itemstatus"]=>
          string(1) "0"
          ["isdeleted"]=>
          string(1) "0"
          ["addtime"]=>
          string(10) "1129042205"
          ["viewednum"]=>
          string(1) "8"
          ["bidnum"]=>
          string(1) "0"
          ["maxprice"]=>
          string(8) "0.000000"
          ["rank"]=>
          string(1) "0"
          ["isbn"]=>
          string(0) ""
          ["paper"]=>
          string(1) "0"
          ["printtype"]=>
          string(1) "0"
          ["binding"]=>
          string(2) "29"
          ["sort"]=>
          string(1) "0"
          ["material"]=>
          string(1) "0"
          ["form"]=>
          string(1) "0"
          ["years"]=>
          string(0) ""
          ["years2"]=>
          string(1) "0"
          ["area"]=>
          string(11) "14004010000"
          ["area1"]=>
          string(11) "14000000000"
          ["area2"]=>
          string(11) "14004000000"
          ["class"]=>
          string(1) "7"
        }
        [1]=>
        array(56) {
          ["id"]=>
          string(6) "330101"
          ["pid"]=>
          string(5) "30037"
          ["userid"]=>
          string(5) "43803"
          ["auctionarea"]=>
          string(1) "2"
          ["specialarea"]=>
          string(1) "0"
          ["catid"]=>
          string(17) "20000000000000000"
          ["catid1"]=>
          string(17) "20000000000000000"
          ["catid2"]=>
          string(1) "0"
          ["catid3"]=>
          string(1) "0"
          ["catid4"]=>
          string(1) "0"
          ["vcatid"]=>
          string(1) "0"
          ["vcatid1"]=>
          string(1) "0"
          ["vcatid2"]=>
          string(1) "0"
          ["vcatid3"]=>
          string(1) "0"
          ["vcatid4"]=>
          string(1) "0"
          ["catid1g"]=>
          string(17) "20000000000000000"
          ["quality"]=>
          string(1) "0"
          ["itemname"]=>
          string(130) "[商务印书馆资料室藏书]: 中国历史小丛书 中华书局 张秀民 龙顺宜 编&lt;&lt;活字印刷史&gt;&gt;品好!"
          ["nickname"]=>
          string(3) "zuo"
          ["author"]=>
          string(0) ""
          ["author2"]=>
          string(0) ""
          ["press"]=>
          string(0) ""
          ["press2"]=>
          string(0) ""
          ["img"]=>
          string(0) ""
          ["hasimg"]=>
          string(1) "0"
          ["params"]=>
          string(14) "{"binding":29}"
          ["iauthor"]=>
          string(1) "0"
          ["ipress"]=>
          string(1) "0"
          ["pubdate"]=>
          string(1) "0"
          ["pubdate2"]=>
          string(8) "29991231"
          ["prestarttime"]=>
          string(1) "0"
          ["begintime"]=>
          string(10) "1131768757"
          ["endtime"]=>
          string(10) "1131971400"
          ["beginprice"]=>
          string(8) "6.000000"
          ["minaddprice"]=>
          string(8) "3.000000"
          ["iscreatetrade"]=>
          string(1) "0"
          ["itemstatus"]=>
          string(1) "0"
          ["isdeleted"]=>
          string(1) "0"
          ["addtime"]=>
          string(10) "1131937795"
          ["viewednum"]=>
          string(2) "13"
          ["bidnum"]=>
          string(1) "1"
          ["maxprice"]=>
          string(8) "6.000000"
          ["rank"]=>
          string(1) "0"
          ["isbn"]=>
          string(0) ""
          ["paper"]=>
          string(1) "0"
          ["printtype"]=>
          string(1) "0"
          ["binding"]=>
          string(2) "29"
          ["sort"]=>
          string(1) "0"
          ["material"]=>
          string(1) "0"
          ["form"]=>
          string(1) "0"
          ["years"]=>
          string(0) ""
          ["years2"]=>
          string(1) "0"
          ["area"]=>
          string(11) "14004010000"
          ["area1"]=>
          string(11) "14000000000"
          ["area2"]=>
          string(11) "14004000000"
          ["class"]=>
          string(1) "7"
        }
        ["total"]=>
        string(3) "200"
        ["total_found"]=>
        string(3) "466"
        ["time"]=>
        string(5) "0.002"
      }
      [0]=>
      array(34) {
        [0]=>
        array(2) {
          ["cid"]=>
          string(16) "3000000000000000"
          ["num"]=>
          string(2) "98"
        }
        [1]=>
        array(2) {
          ["cid"]=>
          string(16) "4000000000000000"
          ["num"]=>
          string(2) "66"
        }
        [2]=>
        array(2) {
          ["cid"]=>
          string(16) "1000000000000000"
          ["num"]=>
          string(2) "41"
        }
        ["total"]=>
        string(2) "31"
        ["total_found"]=>
        string(2) "31"
        ["time"]=>
        string(5) "0.002"
      }
      ["catTpl"]=>
      int(0)
    }
    ["failDesc"]=>
    string(0) ""
  }
  ["error"]=>
  string(0) ""
}


```