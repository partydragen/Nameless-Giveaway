<?php

class GiveawayEntryResult {
    private bool $_success;
    private string $_code;
    private array $_meta;
    private ?int $_cooldown;

    private function __construct(bool $success, string $code, array $meta = [], ?int $cooldown = null) {
        $this->_success = $success;
        $this->_code = $code;
        $this->_meta = $meta;
        $this->_cooldown = $cooldown;
    }

    public static function success(?int $cooldown = null, array $meta = []): self {
        return new self(true, 'successfully_entered_giveaway', $meta, $cooldown);
    }

    public static function failure(string $code, array $meta = [], ?int $cooldown = null): self {
        return new self(false, $code, $meta, $cooldown);
    }

    public function isSuccess(): bool {
        return $this->_success;
    }

    public function getCode(): string {
        return $this->_code;
    }

    public function getMeta(): array {
        return $this->_meta;
    }

    public function getCooldown(): ?int {
        return $this->_cooldown;
    }

    public function toArray(): array {
        return [
            'success' => $this->_success,
            'code' => $this->_code,
            'meta' => $this->_meta,
            'cooldown' => $this->_cooldown,
        ];
    }
}
