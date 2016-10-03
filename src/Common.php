<?php

namespace Adarts;

/**
 * Description of Common
 *
 * @author andares
 */
trait Common {

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

}
