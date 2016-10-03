<?php

namespace Adarts;


/**
 * Description of Result
 *
 * @author andares
 */
class Result {
    private $found;
    private $match;
    private $dict;

    private $words = '';

    public function __construct(int $found, array $match, Dictionary $dict) {
        $this->found    = $found;
        $this->match    = $match;
        $this->dict     = $dict;
    }

    public function exist(): int {
        return $this->found;
    }

    public function matchWords(): string {
        !$this->words && $this->words = $this->dict->translate($this->match);
        return $this->words;
    }

    public function toArray(): array {
        return [
            'exist' => $this->found,
            'match' => $this->match,
            'words' => $this->matchWords(),
        ];
    }

    public function __toString(): string {
        return json_encode($this->toArray());
    }

    public function __debugInfo(): array {
        return $this->toArray();
    }

}
