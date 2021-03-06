<?php

namespace App\Message\Profile;

use Ramsey\Uuid\UuidInterface;

class SendChangeEmailRequestMail
{
    private $userId;

    public function __construct(UuidInterface $userId)
    {
        $this->userId = $userId;
    }

    public function getUserId(): UuidInterface
    {
        return $this->userId;
    }
}
