<?php

namespace Ambta\DoctrineEncryptBundle\Tests\Functional;

use Ambta\DoctrineEncryptBundle\Configuration\Encrypted;
use Ambta\DoctrineEncryptBundle\Encryptors\DefuseEncryptor;
use Ambta\DoctrineEncryptBundle\Encryptors\EncryptorInterface;
use Ambta\DoctrineEncryptBundle\Encryptors\HaliteEncryptor;
use Ambta\DoctrineEncryptBundle\Subscribers\DoctrineEncryptSubscriber;
use Ambta\DoctrineEncryptBundle\Tests\Functional\fixtures\User;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\DBAL\Logging\DebugStack;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Tests\OrmFunctionalTestCase;
use Doctrine\Tests\TestUtil;
use PHPUnit\Framework\Constraint\LogicalNot;
use PHPUnit\Framework\Constraint\StringContains;
use PHPUnit\Framework\Constraint\StringStartsWith;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Util\InvalidArgumentHelper;

abstract class AbstractBasicQueryTest extends OrmFunctionalTestCase
{
    protected static $_modelSets = [
        'doctrine-encrypt' => [
            User::class,
        ],
    ];

    /**
     * @var DoctrineEncryptSubscriber
     */
    private $subscriber;

    /**
     * @var EncryptorInterface|MockObject
     */
    private $encryptor;

    abstract protected function getEncryptor(): EncryptorInterface;

    protected function setUp() : void
    {
        // Trigger autoloader to fetch before annotations will find it
        new Encrypted();

        //$this->encryptor = new HaliteEncryptor(__DIR__.'/fixtures/halite.key');
        $this->encryptor = $this->getEncryptor();
        $this->subscriber = new DoctrineEncryptSubscriber(new AnnotationReader(),$this->encryptor);
        $this->useModelSet('doctrine-encrypt');

        /* From Parent */
        $this->setUpDBALTypes();

        if ( ! isset(static::$_sharedConn)) {
            static::$_sharedConn = TestUtil::getConnection();
        }

        if ( ! $this->_em) {
            $this->_em = $this->_getEntityManager();
            $this->_schemaTool = new SchemaTool($this->_em);
        }

        // Add our DoctrineEncryptSubscriber
        $this->_em->getEventManager()->addEventSubscriber($this->subscriber);

        $classes = [];

        foreach ($this->_usedModelSets as $setName => $bool) {
            if ( ! isset(static::$_tablesCreated[$setName])) {
                foreach (static::$_modelSets[$setName] as $className) {
                    $classes[] = $this->_em->getClassMetadata($className);
                }

                static::$_tablesCreated[$setName] = true;
            }
        }

        if ($classes) {
            $this->_schemaTool->createSchema($classes);
        }

        $this->_sqlLoggerStack->enabled = true;
        /* End of parent */

        parent::setUp();
    }

    protected function tearDown()
    {
        $conn = static::$_sharedConn;

        // In case test is skipped, tearDown is called, but no setup may have run
        if ( ! $conn) {
            return;
        }

        if ($this->_sqlLoggerStack instanceof DebugStack) {
            $this->_sqlLoggerStack->enabled = false;
        }

        if (isset($this->_usedModelSets['doctrine-encrypt'])) {
            $conn->executeUpdate('DELETE FROM users');
        }

        return parent::tearDown();
    }

    private function getLatestInsertQuery(): ?array
    {
        $insertQueries = array_values(array_filter($this->_sqlLoggerStack->queries, function ($queryData) {
            return stripos($queryData['sql'], 'INSERT ') === 0;
        }));

        return current(array_reverse($insertQueries)) ?? null;
    }

    private function getLatestUpdateQuery(): ?array
    {
        $insertQueries = array_values(array_filter($this->_sqlLoggerStack->queries, function ($queryData) {
            return stripos($queryData['sql'], 'UPDATE ') === 0;
        }));

        return current(array_reverse($insertQueries)) ?? null;
    }

    /**
     * Asserts that a string starts with a given prefix.
     *
     * @param string $stringn
     * @param string $string
     * @param string $message
     */
    public static function assertStringContains($needle, $string, $ignoreCase = false, $message = '')
    {
        if (!\is_string($needle)) {
            throw InvalidArgumentHelper::factory(1, 'string');
        }

        if (!\is_string($string)) {
            throw InvalidArgumentHelper::factory(2, 'string');
        }

        if (!\is_bool($ignoreCase)) {
            throw InvalidArgumentHelper::factory(3, 'bool');
        }

        $constraint = new StringContains(
            $needle,
            $ignoreCase
        );

        static::assertThat($string, $constraint, $message);
    }

    /**
     * Asserts that a string starts with a given prefix.
     *
     * @param string $stringn
     * @param string $string
     * @param string $message
     */
    public static function assertStringDoesNotContain($needle, $string, $ignoreCase = false, $message = '')
    {
        if (!\is_string($needle)) {
            throw InvalidArgumentHelper::factory(1, 'string');
        }

        if (!\is_string($string)) {
            throw InvalidArgumentHelper::factory(2, 'string');
        }

        if (!\is_bool($ignoreCase)) {
            throw InvalidArgumentHelper::factory(3, 'bool');
        }

        $constraint = new LogicalNot(new StringContains(
            $needle,
            $ignoreCase
        ));

        static::assertThat($string, $constraint, $message);
    }

    public function testPersistEntity()
    {
        $user = new User();
        $user
            ->setName('John')
            ->setPassword('test');
        $this->_em->persist($user);
        $this->_em->flush();

        // Start transaction; insert; commit
        $this->assertEquals('test',$user->getPassword());
        $this->assertEquals(3,$this->getCurrentQueryCount());
    }

    public function testNoUpdateOnReadEncrypted()
    {
        $this->_em->beginTransaction();
        $this->assertEquals(1,$this->getCurrentQueryCount());

        $user = new User();
        $user
            ->setName('John')
            ->setPassword('test');
        $this->_em->persist($user);
        $this->_em->flush();
        $this->assertEquals(2,$this->getCurrentQueryCount());

        // Test if no query is executed when doing nothing
        $this->_em->flush();
        $this->assertEquals(2,$this->getCurrentQueryCount());

        // Test if no query is executed when reading unrelated field
        $user->getName();
        $this->_em->flush();
        $this->assertEquals(2,$this->getCurrentQueryCount());

        // Test if no query is executed when reading related field and if field is valid
        $this->assertEquals('test',$user->getPassword());
        $this->_em->flush();
        $this->assertEquals(2,$this->getCurrentQueryCount());

        // Test if 1 query is executed when updating entity
        $user->setPassword('test change');
        $this->_em->flush();
        $this->assertEquals(3,$this->getCurrentQueryCount());
        $this->assertEquals('test change',$user->getPassword());

        $this->_em->rollback();
        $this->assertEquals(4,$this->getCurrentQueryCount());
    }

    public function testStoredDataIsEncrypted()
    {
        $user = new User();
        $user
            ->setName('John')
            ->setPassword('test');
        $this->_em->persist($user);
        $this->_em->flush();

        $queryData = $this->getLatestInsertQuery();
        $passwordData = array_values($queryData['params'])[1];
        $this->assertStringEndsWith(DoctrineEncryptSubscriber::ENCRYPTION_MARKER,$passwordData);
        $this->assertStringDoesNotContain('test',$passwordData);

        $user
            ->setPassword('test change');
        $this->_em->flush();

        $queryData = $this->getLatestUpdateQuery();
        $passwordData = array_values($queryData['params'])[0];
        $this->assertStringEndsWith(DoctrineEncryptSubscriber::ENCRYPTION_MARKER,$passwordData);
        $this->assertStringDoesNotContain('test',$passwordData);
    }
}
