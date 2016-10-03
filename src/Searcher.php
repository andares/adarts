<?php

namespace Adarts;

/**
 *
 * @author andares
 */
class Searcher {
    use Common;

    private $check;
    private $base;
    private $fail_states;

    private $match = [];

    public function __construct(array $check, array $base, array $fail_states) {

        $this->check    = $check;
        $this->base     = $base;
        $this->fail_states  = $fail_states;
    }

    public function getMatch(): array {
        return $this->match;
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

    public function __invoke(array $haystack): int {
        // 确定为ac自动机模式
        $acm_mode = true;

        // 当前base
        $base   = $this->base[0];
        // 开始位指针
        $start  = 0;
        // 检测位指针
        $verify = 0;
        // 预计算实际匹配指针
        $cursor = $start + $verify;
        // 初始置state为0
        $pre_state = 0;

        $count = 0;
        while (isset($haystack[$cursor])) {
            $count++;
            if ($count > 1200) {
                throw new \Exception('error');
            }

            // 取code
            $state = isset($haystack[$cursor]) ?
                $this->getState($base, $haystack[$cursor]) : -1;
//            du(">> $cursor | $pre_state / $state = {$haystack[$cursor]} ++ $base", ">> cursor | pre_state / state = code ++ base");

            // 根据state取出base，查找下一个state
            if (isset($this->base[$state]) && $this->check[$state] == $base) {
                $this->match[] = $haystack[$cursor];
                if ($this->base[$state] > 0) {
                    $base   = $this->base[$state];
                    $verify++;
                    $pre_state = $state;
                } else { // 遇到叶子节点，匹配成功
                    return $state;
                }
            } else {
                // 未找到state，匹配失败
                if (isset($this->fail_states[$pre_state])) {
                    // 如果有fail指针，退一格跳转并重置开始位
                    $base   = $this->check[$this->fail_states[$pre_state]];
                    $start += $verify - 1;
                    du("$start += $verify - 1");
                } else {
                    $base   = $this->base[0];
                    if ($acm_mode) {
                        // ac自动机模式下不回滚匹配进度
                        $start += $verify;
                    } else { // 否则开始位前移一步继续匹配
                        $start++;
                    }
                }
                $verify     = 0;
                $pre_state  = 0;
                $this->match = [];
            }
            $cursor = $start + $verify;
            du("$cursor = $start + $verify", '$cursor = $start + $verify');
        }
        return 0;
    }
}
