<?php

namespace Ambta\DoctrineEncryptBundle\Tests\Functional;

use Ambta\DoctrineEncryptBundle\Encryptors\DefuseEncryptor;
use Ambta\DoctrineEncryptBundle\Encryptors\EncryptorInterface;

class AbstractBasicQueryDefuseTest extends AbstractBasicQueryTest
{
    protected function getEncryptor(): EncryptorInterface
    {
        return new DefuseEncryptor(__DIR__.'/fixtures/defuse.key');
    }
}
