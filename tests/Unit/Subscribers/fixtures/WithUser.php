<?php

namespace Ambta\DoctrineEncryptBundle\Tests\Unit\Subscribers\fixtures;

use Ambta\DoctrineEncryptBundle\Configuration\Encrypted;
use Doctrine\ORM\Mapping as ORM;

class WithUser
{
    /**
     * @var string
     * @Encrypted()
     */
    public $name;

    /**
     * @var string|null
     */
    public $foo;

    /**
     * @var User
     * @ORM\Embedded()
     */
    public $user;

    public function __construct(string $name, string $foo, User $user)
    {
        $this->name = $name;
        $this->foo = $foo;
        $this->user = $user;
    }
}
