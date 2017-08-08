# Adarts 使用说明

基于静态Darts实现了AC自动机的字符串匹配类库，支持UTF-8编码，目前在项目中用于敏感词功能。

**该库只支持PHP7及以上版本**，之前版本因为有C扩展实现了类似功能，也不需要这个。

因为并不精通算法，实现上更偏向于业务代码风格，较多地利用了php数组双向链表的特性。算法实现上若有不对，或是有更好的优化方案，**请狠狠给个merge request！**

## 更新与路线图

|功能描述|实现版本|
|---|---|
|修正在双数组模式下导入同路径词条引发的问题| 1.6 |
|调整部分方法及属性命名| 1.5 |
|搜索时增加```$limit```和```$skip```参数| 1.4 |
|增加批量查找功能，可一次获取所有命中词| 1.3 |
|修正查找失败时的指针偏移bug| 1.3 |

## 更新内容详述

### 1.6版本改动

在此库已稳定将近9个月后居然在工作中发现了```重要BUG```，运营在字典中添加了一个单个字的词条后，居然查找不到这个词。

经过排查，发现这是压缩为```Double Array```后的正常现象。双数组结构可以获取更高的执行效率，同时也让算法实现的功能更为单一：拿一个字典与匹配字串进行碰撞，判断字串是否有包含字典中的词条。

因此，经过双数组压缩过后的字典中的每个节点，是不能同时为过路节点又为末节点的。

也就是说，如果往字典中添加```小猪```和```小猪狗```这两个词条，执的结果是无法检查到```小猪```这个词条的。

经过取舍，为了保证该库和算法的设计本意，对字典的构建算法做了更改，当同时添加上述两个词条时，只有```小猪```会被加入。因为后者已经包含前者，所以并不会对“是否包含字典中的词条”这一检查目的造成影响。

### 1.5版本改动

> * 变更```getWordsByState()```方法更名为```getWordByState()```，原方法名做兼容性留存

## 安装

一个简单的composer库而已：

```
composer require andares/adarts
```

序列化功能用到了msgpack，这个东西我很喜欢，推荐使用：

```
pecl install msgpack-2.0.1
```

安装完后在php.ini或是conf.d里加上```extension=msgpack.so```即可，线上环境莫忘重启fpm

## 创建字典

```
$dict = new \Adarts\Dictionary();
$dict->add('word1')
    ->add('word2')
    ->add('word3')
    ->add('word4')
    ->confirm();
```

* add() 向字典中添加一个词条，返回$this，可像上面那样重复调用。
* confirm() 当词条添加完后生成darts树及失败指针。

当然我知道大家实际中一般都会这么用：

```
<?php
use Adarts\Dictionary;

// ...这里是从mysql中读取词条列表到$collection变量的1000行代码
$collection = MySQL::get();

$dict = new Dictionary();
foreach ($collection as $word) {
    $dict->add($word);
}
$dict->confirm();
```

## 搜索匹配

字典创建完后就可以用于搜索，例如：

```
$result = $dict->seek('get out! asshole!')->current();
if ($result) {
    throw new \LogicException('you could not say it');
}
```

* seek() 搜索一个字串，看是否有字典中匹配的词。返回一个生成器对象，所以请使用```current()```方法获取第一个匹配词。

在上面的例子中，我们假设```asshole```在字典中，那么```current()```方法即可得到一个不为0的整数。

> 事实上，$result即Darts中的**叶子节点state**

**如果传入的字串中未包含字典中的内容，由于迭代器特性，则会返回一个null值，这点需要注意！**

### 限制搜索范围

搜索时传入```$limit```参数，可限制只搜索到某个第几个字**作为开头**，例如：

```{php}
// 假设“违法”和“犯规”两字在字典中
$limit  = 1;
$result = $dict->seek('违法犯规', $limit)->current();

// 这时$result结果只有违法
```

搜索时传入第二个参数```$skip```，可跳过几个字符开始搜索，例如：

```{php}
$limit  = 1;
$skip   = 2;
$result = $dict->seek('违法犯规', $limit, $skip)->current();

// 这时$result结果是犯规
```

这里有几个要点需要理解：

1. limit限制的数字，是指查找起始点，而非结束点。所以这里limit=1会从第一个字"违"查起，但只要能一直匹配到成功，不会在“违”字结束匹配。
2. 传入的数字是UTF-8字符数，不是字节数。
3. skip和limit均不支持负数。


## 根据state获取匹配词

稍稍改进一下上面的代码：

```
$result = $dict->seek('get out! asshole!')->current();
if ($result) {
    throw new \LogicException('you could not say ' . $dict->getWordByState($result));
}
```

* getWordByState() 根据**叶子节点state**获取找到的匹配词，如果没意外上面取到的是asshole

## 查找多个命中词

```
foreach ($dict->seek('get out! asshole!') as $result) {
    echo "you could not say ' . $dict->getWordByState($result);
}
```

利用迭代器特性，foreach返回的生成器对象即可获取所有命中词条。

### 关于找到的位置

因为支持失败指针，所以state的转换不是线性的，当通过失败指针跳到其他词条（的某个节点）时，还没找到好的方法（有效率地）逆推到起始节点的办法。

因此```seek()```只能告诉你是否有找到，最多带一个找到了什么，如果需要实现知道位置的功能，可以使用找到词条另外调php方法去处理。在已经明确结果的 下，单词条的查询效率不会有什么问题。

## 序列化

Trie树的策略就是提前对字典做分析，在搜索的时候以最少的步数进行匹配搜索。所以每次搜索时都实时建立字典显然有违初衷，我们可以通过在输入词条列表时创建字典，并调```confirm()```生成分析后的Darts数据，然后对Dictionary进行序列化后保存（用你最爱的持久化方案，MySQL、mongodb、redis、memcache、leveldb等等等等）。

这样当需要搜索时，只需要读出字典对象直接搜索就行了。

Adarts使用msgpack进行字典序列化，以获得比php序列化或json更好的I/O性能。

```
// 这是创建$dict并添加词条并confirm()的1000行代码

$packed = serialize($dict);

// 这是把序列化后的数据持久化的1000行代码
// 嗯，也许可能长这样
redis()->set('dict', $packed);
```

现在我们要用了，那么：

```
// 可能长成这样的读出序列化数据代码
$packed = redis()->get('dict');
$dict = unserialize($packed);

$result = $dict->seek($str)->current();
// ...搜索后的1000行业务代码
```

### 精简字典对象

显然，上面的做法还没有让速度达到最快，因为了字典对象在创建的过程中会产生大量中间数据。这其中一部分是在其他一些场景（非搜索）中有用的，有一些则目前看起来没什么用处。

所以如果确认这个字典对象的序列化数据只用在搜索这一场景中（判断有无），那么可以这样打包：

```
$packed = serialize($dict->simplify());
```

这样做会只留搜索必要的数据，让序列化后的数据更小，读出和反序列化更快。

> 注意```simplify()```之后的字典对象去掉了**词条states索引**，这会导致```getWordByState()```方法不可用（总是返回空串）。所以对于有两种需求的情况来说，推荐精简与完整的字典序列化各存一份，以适用不同的场景。

## 感谢

感谢网上被复制了无数份的Trie PHP实现那个教程文档，虽然Trie树部分的代码完全没有参考价值，但我确实复制粘贴了原作者写的UTF-8拆分代码，并得到了指点。

还有[http://www.cnblogs.com/ooon/p/4883159.html](双数组Tire树(DART)详解)此文，参考的内容是最多的，刚看第一眼的时候其实并没有想读懂>_<

嗯，暂时就先写到这里，如果有坑请发issue给我，先谢过大家帮忙调试了！
