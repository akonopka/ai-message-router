<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class RouteMessageRequest
{
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 180)]  // RFC 5321 dopuszcza max 254 znaki dla adresu
    public string $email;

    #[Assert\NotBlank]
    #[Assert\Length(max: 2000)]
    public string $message;

    #[Assert\Length(max: 300)]
    public ?string $subject = null;
}
