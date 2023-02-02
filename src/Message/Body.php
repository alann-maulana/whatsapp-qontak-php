<?php

declare(strict_types=1);

namespace Inisiatif\WhatsappQontakPhp\Message;

final class Body
{
    /**
     * @var string
     */
    private $value;

    private $key;

    public function __construct($value = NULL, $key = NULL)
    {
        $this->value = $value;
        $this->key = $key;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function getKey()
    {
        return $this->key;
    }

    public function toArray(): array
    {
        if (!isset($this->key)) {
            return [];
        }

        return [
            'value_text' => $this->getValue(),
        ];
    }
}
