<?php

namespace Ambta\DoctrineEncryptBundle\Tests\Functional;

use Ambta\DoctrineEncryptBundle\Encryptors\EncryptorInterface;
use Ambta\DoctrineEncryptBundle\Encryptors\HaliteEncryptor;

class AbstractBasicQueryHaliteTest extends AbstractBasicQueryTest
{
    protected function getEncryptor(): EncryptorInterface
    {
        return new HaliteEncryptor(__DIR__.'/fixtures/halite.key');
    }
}
