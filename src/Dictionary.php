<?php

namespace Adarts;

/**
 *
 *
 * @author andares
 */
class Dictionary implements \Serializable {
    use Common;

    private $check  = [];
    private $base   = [];
    private $index  = [];
    private $fail_states = [];

    private $index_count    = 0;
    private $index_code     = [];
    private $tmp_tree       = [];
    private $words_states   = [];
    private $begin_used     = [];

    /**
     *
     */
    public function __construct() {
        // 构建根
        $this->check[0]    = 0;
        $this->base[0]     = 1;
    }

    /**
     *
     * @param string $sample
     * @return int
     */
    public function search(string $sample): int {
        // 先生成用于搜索的转义串
        $haystack   = [];
        foreach ($this->splitWords($sample) as $char) {
            $haystack[] = $this->index[$char] ?? 0;
        }

        $seeker   = new Seeker($this->check, $this->base, $this->fail_states);
        $found =  $seeker($haystack);
        return $found;
    }

    /**
     * 精简词典对像，仅保留搜索必要的数据
     * @return self
     */
    public function simplify(): self {
        $this->index_code =
            $this->tmp_tree =
            $this->words_states =
            $this->begin_used = [];
        $this->index_count = 0;
        return $this;
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
     * 根据叶子节点 state 拿words
     * @param int $state
     * @return string
     */
    public function getWordsByState(int $state): string {
        return $this->words_states[$state] ?? '';
    }

    /**
     *
     * @param array $haystack
     * @return string
     */
    public function translate(array $haystack): string {
        $this->indexCode();

        $result = '';
        foreach ($haystack as $code) {
            $result .= $this->index_code[$code];
        }
        return $result;
    }

    /**
     * 为 index 创建 code 反向索引
     * @param bool $force
     */
    private function indexCode($force = false) {
        if (!$this->index_code || $force) {
            foreach ($this->index as $words => $code) {
                $this->index_code[$code] = $words;
            }
        }
    }

    /**
     * 添加完毕
     * @return self
     */
    public function confirm(): self {
        return $this->compress()->makeFailStates();
    }

    /**
     * 创建失败指针
     * @return self
     */
    private function makeFailStates(): self {
        $seeker   = new Seeker($this->check, $this->base, []);
        $this->traverseTreeForMakeFailCursor($this->tmp_tree, [], $seeker);
        return $this;
    }

    /**
     * 遍历tmp_tree创建失败指针
     * @param array $tree
     * @param array $haystack
     * @param \Adarts\Seeker $seeker
     * @param int $code
     */
    private function traverseTreeForMakeFailCursor(array &$tree,
        array $haystack, Seeker $seeker, int $code = 0) {

        $code && $haystack[] = $code;
        foreach ($tree as $code => $children_tree) {
            $this->searchFailCursor($haystack, $seeker, $code);
            if ($children_tree) {
                $this->traverseTreeForMakeFailCursor($children_tree,
                    $haystack, $seeker, $code);
            }
        }
    }

    /**
     * 搜索失败指针
     * @param array $haystack
     * @param \Adarts\Seeker $seeker
     * @param int $code
     * @return void
     */
    private function searchFailCursor(array $haystack,
        Seeker $seeker, int $code) {

        if (!$haystack) {
            return;
        }

        $haystack[] = $code;
        $self = $seeker->forFail($haystack);
        array_shift($haystack);
        do {
            $state = $seeker->forFail($haystack);
            if ($state) {
                $this->fail_states[$self] = $state;
                break;
            }
            array_shift($haystack);
        } while ($haystack);
    }

    /**
     * 将临时Trie树压缩成Darts
     * @return self
     */
    private function compress(): self {
        $base = $this->base[0];
        $this->beginUse($base);

        $this->indexCode();
        $this->traverseTreeForCompress($this->tmp_tree, $base);
        return $this;
    }

    /**
     * 遍历Trie树，生成check与base
     * @param array $tree
     * @param int $base
     */
    private function traverseTreeForCompress(array &$tree, int $base,
        string $prefix = '') {

        // 先处理当前层级
        foreach ($tree as $code => $children_tree) {
            $state = $this->getState($base, $code);
            $this->check[$state] = $base;
        }

        // 再处理子级
        foreach ($tree as $code => $children_tree) {
            $state = $this->getState($base, $code);
            $words = $prefix . $this->index_code[$code];

            // 计算此子级的check 即当前节点的base
            if ($children_tree) {
                $next_base = $this->findBegin($children_tree);
                $this->beginUse($next_base);
                $this->base[$state] = $next_base;

                $this->traverseTreeForCompress($children_tree, $next_base, $words);
            } else {
                // 叶节点
                $this->base[$state] = -$this->check[$state];

                // 创建 words 的 state 索引
                $this->words_states[$state] = $words;
            }
        }
    }

    /**
     * 暂时用步进法搜索
     * @return int
     */
    private function findBegin(array &$tree): int {
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

    /**
     * 序列化
     * @return string
     */
    public function serialize(): string {
        $data['check']          = $this->check;
        $data['base']           = $this->base;
        $data['index']          = $this->index;
        $data['fail_states']    = $this->fail_states;
        $data['index_count']    = $this->index_count;
        $data['index_code']     = $this->index_code;
        $data['tmp_tree']       = $this->tmp_tree;
        $data['words_states']   = $this->words_states;
        $data['begin_used']     = $this->begin_used;
        return msgpack_pack($data);
    }

    /**
     * 反序列化
     * @param string $serialized
     */
    public function unserialize($serialized) {
        $data = msgpack_unpack($serialized);
        $this->check        = $data['check'];
        $this->base         = $data['base'];
        $this->index        = $data['index'];
        $this->fail_states  = $data['fail_states'];
        $this->index_count  = $data['index_count'];
        $this->index_code   = $data['index_code'];
        $this->tmp_tree     = $data['tmp_tree'];
        $this->words_states = $data['words_states'];
        $this->begin_used   = $data['begin_used'];
    }

}
