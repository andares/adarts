<?php

namespace Adarts;

/**
 *
 * @author andares
 */
class Seeker {
    use Common;

    private $check;
    private $base;
    private $fail_states;

    public function __construct(array $check, array $base, array $fail_states) {

        $this->check    = $check;
        $this->base     = $base;
        $this->fail_states  = $fail_states;
    }

    public function forFail(array $haystack): int {
        // 当前base
        $base   = $this->base[0];
        // 预计算实际匹配指针
        $cursor = 0;

        while (isset($haystack[$cursor])) {
            $state = $this->getState($base, $haystack[$cursor]);

            // 根据state取出base，查找下一个state
            if (!isset($this->base[$state]) || $this->check[$state] != $base) {
                return 0;
            }
            $base   = $this->base[$state];
            $cursor++;
        }
        return $state;
    }

    public function __invoke(array $haystack,
        int $limit = 0, int $skip = 0): \Generator {

        $it = $this->process($haystack, $limit, $skip);
        return $it;
    }

    private function process(array $haystack, int $limit = 0, int $skip = 0) {
        // 当前base
        $base   = $this->base[0];
        // 开始位指针
        $start  = $skip;
        // 检测位指针
        $verify = 0;
        // 预计算实际匹配指针
        $cursor = $start + $verify;
        // 初始置state为0
        $pre_state = 0;

        // 开始搜索
        $limit && $limit += $skip;
        while (isset($haystack[$cursor]) && (!$limit || $start < $limit)) {
            // 根据当前 base 与匹配指针位计算出 state
            // 未进入索引取不到 code 的 state = -1
            $state = isset($haystack[$cursor]) ?
                $this->getState($base, $haystack[$cursor]) : -1;

            // 根据 state 查找是否有 base
            // 并且使用 check 位校验父节点 base 是否匹配
            if (isset($this->base[$state]) && $this->check[$state] == $base) {
                // 根据 base 位不为负检查是否为叶子节点
                if ($this->base[$state] > 0) {
                    // 非叶子节点 base 置为下层节点 check
                    $base   = $this->base[$state];
                    // 检验位推进
                    $verify++;
                    // 设置 pre state 用于调用失败指针
                    $pre_state = $state;

                } else {
                    // 遇到叶子节点，匹配成功
                    yield $state;

                    // 重置搜索位
                    $base   = $this->base[0];
                    $start++;
                    $verify = 0;
                    $pre_state = 0;
                }
            } else {
                // state 检查失败
                if (isset($this->fail_states[$pre_state])) {
                    // 如果有 fail 指针，重置 base 到失败指针，退一步继续匹配
                    $base   = $this->check[$this->fail_states[$pre_state]];
                    $start += $verify - 1;

                } else {
                    // 无 fail 指针，重置 base 到 root
                    $base   = $this->base[0];
                    $verify ? ($start += $verify) : $start++;
                }
                // 重置检测位 pre state
                $verify     = 0;
                $pre_state  = 0;
            }

            // 计算出新的（或相同的）检测指针
            $cursor = $start + $verify;
//            du("$cursor = $start + $verify", '$cursor = $start + $verify');
        }

        // 搜索结束
        return 0;
    }
}
