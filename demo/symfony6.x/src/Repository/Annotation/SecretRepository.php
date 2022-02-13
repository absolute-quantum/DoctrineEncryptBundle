<?php

namespace App\Repository\Annotation;

use App\Entity\Annotation\Secret;
use App\Repository\AbstractSecretRepository;

// Alias is needed because of test with both php 7.2, 7.4 and 8.0
if (!interface_exists('\Doctrine\Common\Persistence\ManagerRegistry')) {
    class_alias(
        '\Doctrine\Persistence\ManagerRegistry',
        '\Doctrine\Common\Persistence\ManagerRegistry'
    );
}

/**
 * @method Secret|null find($id, $lockMode = null, $lockVersion = null)
 * @method Secret|null findOneBy(array $criteria, array $orderBy = null)
 * @method Secret[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SecretRepository extends AbstractSecretRepository
{
    public function __construct(\Doctrine\Common\Persistence\ManagerRegistry $registry)
    {
        parent::__construct($registry, Secret::class);
    }
}