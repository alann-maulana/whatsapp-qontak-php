<?php

declare(strict_types=1);

namespace Inisiatif\WhatsappQontakPhp\Message;

final class Receiver
{
    /**
     * @var string|null
     */
    private $to;

    /**
     * @var string
     */
    private $name;

    /**
     * @var string|null
     */
    private $contact_list_id;

    public function __construct(string $to, string $name, $contact_list_id = NULL)
    {
        $this->to = $to;
        $this->name = $name;
        $this->contact_list_id = $contact_list_id;
    }

    public function getTo()
    {
        return $this->to;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getContactListId()
    {
        return $this->contact_list_id;
    }
}
