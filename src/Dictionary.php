<?php

namespace Adarts;

/**
 * Description of Dictionary
 *
 * 双数组Trie
 * 失败指针，AC自动机
 * 当某一条路径只有这个串即关联数组数量为1时，可以压缩子树
 *
  UTF-8是一种变长字节编码方式。对于某一个字符的UTF-8编码，如果只有一个字节则其最高二进制位为0；如果是多字节，其第一个字节从最高位开始，连续的二进制位值为1的个数决定了其编码的位数，其余各字节均以10开头。UTF-8最多可用到6个字节。 
  如表： 
  1字节 0xxxxxxx 
  2字节 110xxxxx 10xxxxxx 
  3字节 1110xxxx 10xxxxxx 10xxxxxx 
  4字节 11110xxx 10xxxxxx 10xxxxxx 10xxxxxx 
  5字节 111110xx 10xxxxxx 10xxxxxx 10xxxxxx 10xxxxxx 
  6字节 1111110x 10xxxxxx 10xxxxxx 10xxxxxx 10xxxxxx 10xxxxxx 
  因此UTF-8中可以用来表示字符编码的实际位数最多有31位，即上表中x所表示的位。除去那些控制位（每字节开头的10等），这些x表示的位与UNICODE编码是一一对应的，位高低顺序也相同。 
  实际将UNICODE转换为UTF-8编码时应先去除高位0，然后根据所剩编码的位数决定所需最小的UTF-8编码位数。 
 *
 * @author andares
 */
class Dictionary {

    private $check  = [];
    private $base   = [];

    private $index  = [];
    private $index_count = 0;

    private $tmp_tree = [];
    private $begin_used = [];

    public function __construct() {
        // 构建根
        $state = $this->getState(0);
        $this->check[$state]    = 0;
        $this->base[$state]     = 1;
    }

    /**
     * 添加词进字典
     * @param string $words
     * @return self
     */
    public function add(string $words): self {
        // 构建临时树
        $tmp_tree = &$this->tmp_tree;
        foreach ($this->splitWords($words) as $char) {
            // 加入索引
            $code = $this->index($char);

            // 插树
            $tmp_tree = &$this->putNode($code, $tmp_tree);
        }

        return $this;
    }

    /**
     * 构建失败指针
     * @return self
     */
    public function prepare(): self {
        foreach ($this->traverseTree() as $a) {

        }
        return $this;
    }

    /**
     * 将临时Trie树压缩成Darts
     * @return \self
     */
    public function compress(): self {
        $base = $this->base[$this->getState(0)];
        $this->beginUse($base);
        $this->traverseTree($this->tmp_tree, $base);
        return $this;
    }

    /**
     * 遍历Trie树，生成check与base
     * @param array $tree
     * @param int $base
     */
    private function traverseTree(array &$tree, int $base) {
        // 先处理当前层级
        foreach ($tree as $code => $children_tree) {
            $state = $this->getState($base, $code);
            $this->check[$state] = $base;
        }

        // 再处理子级
        foreach ($tree as $code => $children_tree) {
            $state = $this->getState($base, $code);

            // 计算此子级的check 即当前节点的base
            $next_base = $this->searchBegin($children_tree);
            $this->base[$state] = $next_base;

            $this->traverseTree($children_tree, $next_base);
        }
    }

    /**
     * 暂时用步进法搜索
     * @return int
     */
    private function searchBegin(array &$tree): int {
        $base  = 1;
        $found      = false;
        while (!$found) {
            // 步进
            $base++;

            // 如有使用跳过
            if (isset($this->begin_used[$base])) {
                continue;
            }

            // 查找是否符合条件
            foreach ($tree as $child_code => $child_tree) {
                if (isset($this->check[$this->getState($base, $child_code)])) {
                    continue 2;
                }
            }

            $found = true;
        }
        return $base;
    }

    /**
     *
     * @param int $base
     * @return self
     */
    private function beginUse(int $base): self {
        $this->begin_used[$base] = 1;
        return $this;
    }

    /**
     * 往临时树里添加一个节点
     * @param int $code
     * @param array $tmp_tree
     * @return array
     */
    private function &putNode(int $code, array &$tmp_tree): array {
        !isset($tmp_tree[$code]) && $tmp_tree[$code] = [];
        return $tmp_tree[$code];
    }

    /**
     * 索引字符并返回code
     * @param string $char
     * @return int
     */
    private function index(string $char): int {
        if (isset($this->index[$char])) {
            return $this->index[$char];
        }

        $this->index_count++;
        $this->index[$char] = $this->index_count;
        return $this->index_count;
    }

    /**
     * 取state
     * @param int $offset
     * @param int $code
     * @return int
     */
    private function getState(int $offset, int $code = 0): int {
        $state = $offset + $code;
        // dart clone算法要求+1
        $state++;
        return $state;
    }

    /**
     * utf8拆字
     * @param string $words
     */
    private function splitWords(string $words) {
        $len = strlen($words);
        for ($i = 0; $i < $len; $i++) {
            $c = $words[$i];
            $n = ord($c);
            if (($n >> 7) == 0) {
                //0xxx xxxx, asci, single
                yield $c;
            } elseif (($n >> 4) == 15) { //1111 xxxx, first in four char
                if ($i < $len - 3) {
                    yield $c . $words[$i + 1] . $words[$i + 2] . $words[$i + 3];
                    $i += 3;
                }
            } elseif (($n >> 5) == 7) {
                //111x xxxx, first in three char
                if ($i < $len - 2) {
                    yield $c . $words[$i + 1] . $words[$i + 2];
                    $i += 2;
                }
            } elseif (($n >> 6) == 3) {
                //11xx xxxx, first in two char
                if ($i < $len - 1) {
                    yield $c . $words[$i + 1];
                    $i++;
                }
            }
        }
    }

}
