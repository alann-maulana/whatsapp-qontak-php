<?php

declare(strict_types=1);

namespace Inisiatif\WhatsappQontakPhp;

use Inisiatif\WhatsappQontakPhp\Message\Message;

final class MessageUtil
{
    /**
     * @var Message
     */
    private $message;

    public function __construct(Message $message)
    {
        $this->message = $message;
    }

    public static function makeRequestBody(Message $message): array
    {
        return (new self($message))->toRequestBody();
    }

    public function toRequestBody(): array
    {
        $params = [
            'body' => $this->makeBodyParams(),
            'buttons' => $this->makeButtonParams(),
        ];

        if ($this->message->getHeader()) {
            $params['header'] = $this->message->getHeader()->toArray();
        }

        $receiver = $this->message->getReceiver();

        if ($receiver->getContactListId() !== NULL) {
            return [
                'name' => $receiver->getName(),
                'contact_list_id' => $receiver->getContactListId(),
                'language' => [
                    'code' => $this->message->getLanguage()->getCode(),
                ],
                'parameters' => $params,
            ];
        }

        return [
            'to_name' => $this->message->getReceiver()->getName(),
            'to_number' => $this->message->getReceiver()->getTo(),
            'language' => [
                'code' => $this->message->getLanguage()->getCode(),
            ],
            'parameters' => $params,
        ];
    }

    private function makeBodyParams(): array
    {
        $params = [];

        foreach ($this->message->getBody() as $key => $body) {
            $iteration = (int) $key + 1;

            $params[] = [
                'key' => $iteration,
                'value' => $body->getKey() !== NULL ? $body->getKey() : \sprintf('param%s', $iteration),
            ] + $body->toArray();
        }

        return $params;
    }

    private function makeButtonParams(): array
    {
        $buttons = [];

        foreach ($this->message->getButtons() as $key => $button) {
            $buttons[] = [
                'index' => $key,
            ] + $button->toArray();
        }

        return $buttons;
    }
}
