<?php

namespace Ambta\DoctrineEncryptBundle\Tests\Unit\Subscribers;

use Ambta\DoctrineEncryptBundle\Configuration\Encrypted;
use Ambta\DoctrineEncryptBundle\Encryptors\EncryptorInterface;
use Ambta\DoctrineEncryptBundle\Subscribers\DoctrineEncryptSubscriber;
use Ambta\DoctrineEncryptBundle\Tests\Unit\Subscribers\fixtures\ExtendedUser;
use Ambta\DoctrineEncryptBundle\Tests\Unit\Subscribers\fixtures\User;
use Ambta\DoctrineEncryptBundle\Tests\Unit\Subscribers\fixtures\WithUser;
use Doctrine\Common\Annotations\Reader;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\Embedded;
use Doctrine\ORM\UnitOfWork;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DoctrineEncryptSubscriberTest extends TestCase
{
    /**
     * @var DoctrineEncryptSubscriber
     */
    private $subscriber;

    /**
     * @var EncryptorInterface|MockObject
     */
    private $encryptor;

    public function testSetRestoreEncryptor()
    {
        $replaceEncryptor = $this->createMock(EncryptorInterface::class);

        self::assertSame($this->encryptor, $this->subscriber->getEncryptor());
        $this->subscriber->setEncryptor($replaceEncryptor);
        self::assertSame($replaceEncryptor, $this->subscriber->getEncryptor());
        $this->subscriber->restoreEncryptor();
        self::assertSame($this->encryptor, $this->subscriber->getEncryptor());
    }

    public function testProcessFieldsEncrypt()
    {
        $user = new User('David', 'Switzerland');

        $em = $this->createMock(EntityManagerInterface::class);

        $this->subscriber->processFields($user, $em, true);

        self::assertStringStartsWith('encrypted-', $user->name);
        self::assertStringStartsWith('encrypted-', $user->getAddress());
    }

    public function testProcessFieldsEncryptExtend()
    {
        $user = new ExtendedUser('David', 'Switzerland', 'extra');

        $em = $this->createMock(EntityManagerInterface::class);

        $this->subscriber->processFields($user, $em, true);

        self::assertStringStartsWith('encrypted-', $user->name);
        self::assertStringStartsWith('encrypted-', $user->getAddress());
        self::assertStringStartsWith('encrypted-', $user->extra);
    }

    public function testProcessFieldsEncryptEmbedded()
    {
        $withUser = new WithUser('Thing', 'foo', new User('David', 'Switzerland'));

        $em = $this->createMock(EntityManagerInterface::class);

        $this->subscriber->processFields($withUser, $em, true);

        self::assertStringStartsWith('encrypted-', $withUser->name);
        self::assertSame('foo', $withUser->foo);
        self::assertStringStartsWith('encrypted-', $withUser->user->name);
        self::assertStringStartsWith('encrypted-', $withUser->user->getAddress());
    }

    public function testProcessFieldsEncryptNull()
    {
        $user = new User('David', null);

        $em = $this->createMock(EntityManagerInterface::class);

        $this->subscriber->processFields($user, $em, true);

        self::assertStringStartsWith('encrypted-', $user->name);
        self::assertNull($user->getAddress());
    }

    public function testProcessFieldsNoEncryptor()
    {
        $user = new User('David', 'Switzerland');

        $em = $this->createMock(EntityManagerInterface::class);

        $this->subscriber->setEncryptor(null);
        $this->subscriber->processFields($user, $em, true);

        self::assertSame('David', $user->name);
        self::assertSame('Switzerland', $user->getAddress());
    }

    public function testProcessFieldsDecrypt()
    {
        $user = new User('encrypted-David<ENC>', 'encrypted-Switzerland<ENC>');

        $em = $this->createMock(EntityManagerInterface::class);

        $this->subscriber->processFields($user, $em, false);

        self::assertSame('David', $user->name);
        self::assertSame('Switzerland', $user->getAddress());
    }

    public function testProcessFieldsDecryptExtended()
    {
        $user = new ExtendedUser('encrypted-David<ENC>', 'encrypted-Switzerland<ENC>', 'encrypted-extra<ENC>');

        $em = $this->createMock(EntityManagerInterface::class);

        $this->subscriber->processFields($user, $em, false);

        self::assertSame('David', $user->name);
        self::assertSame('Switzerland', $user->getAddress());
        self::assertSame('extra', $user->extra);
    }

    public function testProcessFieldsDecryptEmbedded()
    {
        $withUser = new WithUser('encrypted-Thing<ENC>', 'foo', new User('encrypted-David<ENC>', 'encrypted-Switzerland<ENC>'));

        $em = $this->createMock(EntityManagerInterface::class);

        $this->subscriber->processFields($withUser, $em, false);

        self::assertSame('Thing', $withUser->name);
        self::assertSame('foo', $withUser->foo);
        self::assertSame('David', $withUser->user->name);
        self::assertSame('Switzerland', $withUser->user->getAddress());
    }

    public function testProcessFieldsDecryptNull()
    {
        $user = new User('encrypted-David<ENC>', null);

        $em = $this->createMock(EntityManagerInterface::class);

        $this->subscriber->processFields($user, $em, false);

        self::assertSame('David', $user->name);
        self::assertNull($user->getAddress());
    }

    public function testProcessFieldsDecryptNonEncrypted()
    {
        // no trailing <ENC> but somethint that our mock decrypt would change if called
        $user = new User('encrypted-David', 'encrypted-Switzerland');

        $em = $this->createMock(EntityManagerInterface::class);

        $this->subscriber->processFields($user, $em, false);

        self::assertSame('encrypted-David', $user->name);
        self::assertSame('encrypted-Switzerland', $user->getAddress());
    }

    /**
     * Test that fields are encrypted before flushing.
     */
    public function testOnFlush()
    {
        $user = new User('David', 'Switzerland');

        $uow = $this->createMock(UnitOfWork::class);
        $uow->expects(self::any())
            ->method('getScheduledEntityInsertions')
            ->willReturn([$user]);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::any())
            ->method('getUnitOfWork')
            ->willReturn($uow);
        $classMetaData = $this->createMock(ClassMetadata::class);
        $em->expects(self::once())->method('getClassMetadata')->willReturn($classMetaData);
        $uow->expects(self::once())->method('recomputeSingleEntityChangeSet');

        $onFlush = new OnFlushEventArgs($em);

        $this->subscriber->onFlush($onFlush);

        self::assertStringStartsWith('encrypted-', $user->name);
        self::assertStringStartsWith('encrypted-', $user->getAddress());
    }

    /**
     * Test that fields are decrypted again after flushing
     */
    public function testPostFlush()
    {
        $user = new User('encrypted-David<ENC>', 'encrypted-Switzerland<ENC>');

        $uow = $this->createMock(UnitOfWork::class);
        $uow->expects(self::any())
            ->method('getIdentityMap')
            ->willReturn([[$user]]);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::any())
            ->method('getUnitOfWork')
            ->willReturn($uow);
        $postFlush = new PostFlushEventArgs($em);

        $this->subscriber->postFlush($postFlush);

        self::assertSame('David', $user->name);
        self::assertSame('Switzerland', $user->getAddress());
    }

    protected function setUp()
    {
        $this->encryptor = $this->createMock(EncryptorInterface::class);
        $this->encryptor
            ->expects(self::any())
            ->method('encrypt')
            ->willReturnCallback(
                function (string $arg) {
                    return 'encrypted-' . $arg;
                }
            );
        $this->encryptor
            ->expects(self::any())
            ->method('decrypt')
            ->willReturnCallback(
                function (string $arg) {
                    return preg_replace('/^encrypted-/', '', $arg);
                }
            );

        $reader = $this->createMock(Reader::class);
        $reader->expects(self::any())
            ->method('getPropertyAnnotation')
            ->willReturnCallback(
                function (\ReflectionProperty $reflProperty, string $class) {
                    if (Encrypted::class === $class) {
                        return \in_array($reflProperty->getName(), ['name', 'address', 'extra']);
                    }
                    if (Embedded::class === $class) {
                        return 'user' === $reflProperty->getName();
                    }

                    return false;
                }
            );

        $this->subscriber = new DoctrineEncryptSubscriber($reader, $this->encryptor);
    }
}
