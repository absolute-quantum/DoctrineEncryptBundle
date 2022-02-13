<?php

namespace App\Tests;

use Ambta\DoctrineEncryptBundle\Subscribers\DoctrineEncryptSubscriber;
use App\Entity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class SecretTest extends KernelTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel([]);
    }

    private function testSecretsAreEncryptedInDatabase(string $className)
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');

        // Make sure we do not store testdata
        $entityManager->beginTransaction();

        $name = 'test123';
        $secretString = 'i am a secret string';

        // Create entity to test with
        $newSecretObject = (new $className)
            ->setName($name)
            ->setSecret($secretString);

        $entityManager->persist($newSecretObject);
        $entityManager->flush();

        // Fetch the actual data
        $secretRepository = $entityManager->getRepository($className);
        $qb = $secretRepository->createQueryBuilder('s');
        $qb->select('s')
            ->addSelect('(s.secret) as rawSecret')
            ->where('s.name = :name')
            ->setParameter('name',$name)
            ->orderBy('s.name','ASC');
        $result = $qb->getQuery()->getSingleResult();

        $actualSecretObject = $result[0];
        $actualRawSecret = $result['rawSecret'];

        self::assertInstanceOf($className,$actualSecretObject);
        self::assertEquals($newSecretObject->getSecret(), $actualSecretObject->getSecret());
        self::assertEquals($newSecretObject->getName(), $actualSecretObject->getName());
        // Make sure it is encrypted
        self::assertNotEquals($newSecretObject->getSecret(),$actualRawSecret);
        self::assertStringEndsWith(DoctrineEncryptSubscriber::ENCRYPTION_MARKER,$actualRawSecret);
    }

    /**
     * @covers Entity\Annotation\Secret::getSecret
     * @covers Entity\Annotation\Secret::getName
     */
    public function testAnnotationSecretsAreEncryptedInDatabase()
    {
        $this->testSecretsAreEncryptedInDatabase(Entity\Annotation\Secret::class);
    }

    /**
     * @covers Entity\Attribute\Secret::getSecret
     * @covers Entity\Attribute\Secret::getName
     * @requires PHP 8.0
     */
    public function testAttributeSecretsAreEncryptedInDatabase()
    {
        $this->testSecretsAreEncryptedInDatabase(Entity\Attribute\Secret::class);
    }
}
