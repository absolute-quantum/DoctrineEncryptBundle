<?php


namespace Ambta\DoctrineEncryptBundle\Tests\Functional\fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 */
class VehicleBicycle extends AbstractVehicle
{
    /**
     * @ORM\Column(type="boolean")
     * @var bool
     */
    private $hasSidewheels = false;

    /**
     * @return bool
     */
    public function hasSidewheels()
    {
        return $this->hasSidewheels;
    }

    /**
     * @param bool $hasSidewheels
     * @return $this
     */
    public function setSidewheels($hasSidewheels): self
    {
        $this->hasSidewheels = $hasSidewheels;
        return $this;
    }
}