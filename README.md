# Adarts 使用说明

基于静态Darts实现了AC自动机的字符串匹配类库，支持UTF-8编码，目前在项目中用于敏感词功能。

**该库只支持PHP7及以上版本**，之前版本因为有C扩展实现了类似功能，也不需要这个。

因为并不精通算法，实现上更偏向于业务代码风格，较多地利用了php数组双向链表的特性。算法实现上若有不对，或是有更好的优化方案，**请狠狠给个merge request！**

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
use Adarts;

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
$result = $dict->seek('get out! asshole!');
if ($result) {
    throw new \LogicException('you could not say it');
}
```

* seek() 搜索一个字串，看是否有字典中匹配的词。返回一个int型，找不到返回0

在上面的例子中，我们假设```asshole```在字典中，那么返回的```$result```即不为0

事实上，$result即Darts中的**叶子节点state**

## 根据state获取匹配词

稍稍改进一下上面的代码：

```
$result = $dict->seek('get out! asshole!');
if ($result) {
    throw new \LogicException('you could not say ' . $dict->getWordsByState($result));
}
```

* getWordsByState() 根据**叶子节点state**获取找到的匹配词，如果没意外上面取到的是asshole

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

$result = $dict->seek($some_words);
// ...搜索后的1000行业务代码
```

### 精简字典对象

显然，上面的做法还没有让速度达到最快，因为了字典对象在创建的过程中会产生大量中间数据。这其中一部分是在其他一些场景（非搜索）中有用的，有一些则目前看起来没什么用处。

所以如果确认这个字典对象的序列化数据只用在搜索这一场景中（判断有无），那么可以这样打包：

```
$packed = serialize($dict->simplify());
```

这样做会只留搜索必要的数据，让序列化后的数据更小，读出和反序列化更快。

> 注意```simplify()```之后的字典对象去掉了**词条states索引**，这会导致```getWordsByState()```方法不可用（总是返回空串）。所以对于有两种需求的情况来说，推荐精简与完整的字典序列化各存一份，以适用不同的场景。

## 感谢

感谢网上被复制了无数份的Trie PHP实现那个教程文档，虽然Trie树部分的代码完全没有参考价值，但我确实复制粘贴了原作者写的UTF-8拆分代码，并得到了指点。

还有[http://www.cnblogs.com/ooon/p/4883159.html](双数组Tire树(DART)详解)此文，参考的内容是最多的，刚看第一眼的时候其实并没有想读懂>_<

嗯，暂时就先写到这里，如果有坑请发issue给我，先谢过大家帮忙调试了！



