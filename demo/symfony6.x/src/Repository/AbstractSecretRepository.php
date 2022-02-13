<?php

namespace App\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

abstract class AbstractSecretRepository extends ServiceEntityRepository
{
    /**
     * @return array<int, object> The objects.
     */
    public function findAll()
    {
        $qb = $this->createQueryBuilder('s');
        $qb->select('s')
            ->addSelect('(s.secret) as rawSecret')
            ->orderBy('s.name','ASC');
        $rawResult = $qb->getQuery()->getResult();

        $result = [];
        foreach ($rawResult as $row) {
            $secret = $row[0];
            $secret->setRawSecret($row['rawSecret']);
            $result[] = $secret;
        }

        return $result;
    }
}