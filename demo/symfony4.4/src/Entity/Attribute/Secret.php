<?php

namespace App\Entity\Attribute;

use Ambta\DoctrineEncryptBundle\Configuration\Encrypted;
use App\Repository\Attribute\SecretRepository;
use Doctrine\ORM\Mapping as ORM;


#[ORM\Table(name:"secrets_using_attributes")]
#[ORM\Entity(repositoryClass:SecretRepository::class)]
class Secret implements \App\Entity\SecretInterface
{
    #[ORM\Id()]
    #[ORM\Column(type:"string", nullable:false)]
    private $name;


    #[ORM\Column(type:"string",nullable:false)]
    #[Encrypted]
    private $secret;

    /**
     * Used to fill in value from repo
     *
     * @var string
     */
    private $rawSecret;

    public function getType()
    {
        return 'Attribute';
    }

    /**
     * return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     *
     * @return $this
     */
    public function setName($name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return string
     */
    public function getSecret()
    {
        return $this->secret;
    }

    /**
     * @param string $secret
     *
     * @return $this
     */
    public function setSecret($secret): self
    {
        $this->secret = $secret;

        return $this;
    }

    /**
     * @return string
     */
    public function getRawSecret()
    {
        return $this->rawSecret;
    }

    /**
     * @param mixed $rawSecret
     *
     * @return $this
     */
    public function setRawSecret($rawSecret)
    {
        $this->rawSecret = $rawSecret;

        return $this;
    }
}