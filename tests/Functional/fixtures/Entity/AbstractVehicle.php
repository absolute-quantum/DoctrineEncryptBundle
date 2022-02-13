<?php

namespace Ambta\DoctrineEncryptBundle\Tests\Functional\fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 * @ORM\InheritanceType("SINGLE_TABLE")
 * @ORM\DiscriminatorColumn(name="type", type="string")
 * @ORM\DiscriminatorMap({"car" = "VehicleCar","bike" = "VehicleBicycle"})
 *
 */
abstract class AbstractVehicle
{
    /**
     * @var int
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue
     */
    private $id;

    /**
     * @Ambta\DoctrineEncryptBundle\Configuration\Encrypted()
     * @ORM\Column(type="string", nullable=true)
     */
    private $secret;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    private $notSecret;

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getSecret()
    {
        return $this->secret;
    }

    /**
     * @param mixed $secret
     */
    public function setSecret($secret): void
    {
        $this->secret = $secret;
    }

    /**
     * @return mixed
     */
    public function getNotSecret()
    {
        return $this->notSecret;
    }

    /**
     * @param mixed $notSecret
     * @return $this
     */
    public function setNotSecret($notSecret): self
    {
        $this->notSecret = $notSecret;
        return $this;
    }
}