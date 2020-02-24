<?php

namespace Ambta\DoctrineEncryptBundle\Subscribers;

use Defuse\Crypto\Exception\CryptoException;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\UnitOfWork;
use ParagonIE\Halite\Alerts\HaliteAlert;
use ParagonIE\Halite\HiddenString;
use ReflectionClass;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\Common\Annotations\Reader;
use Doctrine\Common\Util\ClassUtils;
use Ambta\DoctrineEncryptBundle\Encryptors\EncryptorInterface;
use ReflectionProperty;
use Symfony\Component\PropertyAccess\PropertyAccess;

/**
 * Doctrine event subscriber which encrypt/decrypt entities
 */
class DoctrineEncryptSubscriber implements EventSubscriber
{
    /**
     * Appended to end of encrypted value
     */
    const ENCRYPTION_MARKER = '<ENC>';

    /**
     * Encryptor interface namespace
     */
    const ENCRYPTOR_INTERFACE_NS = 'Ambta\DoctrineEncryptBundle\Encryptors\EncryptorInterface';

    /**
     * Encrypted annotation full name
     */
    const ENCRYPTED_ANN_NAME = 'Ambta\DoctrineEncryptBundle\Configuration\Encrypted';

    /**
     * Encryptor
     * @var EncryptorInterface
     */
    private $encryptor;

    /**
     * Annotation reader
     * @var \Doctrine\Common\Annotations\Reader
     */
    private $annReader;

    /**
     * Used for restoring the encryptor after changing it
     * @var string
     */
    private $restoreEncryptor;

    /**
     * User for storing all entities we decrypted after flushing, so we know which ones to re-encrypt
     * @var ArrayCollection
     */
    private $decryptedEntities;

    /**
     * Count amount of decrypted values in this service
     * @var integer
     */
    public $decryptCounter = 0;

    /**
     * Count amount of encrypted values in this service
     * @var integer
     */
    public $encryptCounter = 0;

    /**
     * Initialization of subscriber
     *
     * @param Reader $annReader
     * @param EncryptorInterface|NULL $encryptor (Optional)  An EncryptorInterface.
     */
    public function __construct(Reader $annReader, EncryptorInterface $encryptor)
    {
        $this->annReader = $annReader;
        $this->encryptor = $encryptor;
        $this->restoreEncryptor = $this->encryptor;
        $this->decryptedEntities = new ArrayCollection();
    }

    /**
     * Change the encryptor
     *
     * @param EncryptorInterface $encryptor
     */
    public function setEncryptor(EncryptorInterface $encryptor = null)
    {
        $this->encryptor = $encryptor;
    }

    /**
     * Get the current encryptor
     *
     * @return EncryptorInterface returns the encryptor class or null
     */
    public function getEncryptor()
    {
        return $this->encryptor;
    }

    /**
     * Restore encryptor to the one set in the constructor.
     */
    public function restoreEncryptor()
    {
        $this->encryptor = $this->restoreEncryptor;
    }

    /**
     * Listen a postUpdate lifecycle event.
     * Decrypt entities property's values when post updated.
     *
     * So for example after form submit the preUpdate encrypted the entity
     * We have to decrypt them before showing them again.
     *
     * @param LifecycleEventArgs $args
     */
    public function postUpdate(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();
        $this->processFields($entity, false);
    }

    /**
     * Listen a preUpdate lifecycle event.
     * Encrypt entities property's values on preUpdate, so they will be stored encrypted
     *
     * @param PreUpdateEventArgs $args
     */
    public function preUpdate(PreUpdateEventArgs $args)
    {
        $entity = $args->getEntity();
        $this->processFields($entity);
    }

    /**
     * Listen a postLoad lifecycle event.
     * Decrypt entities property's values when loaded into the entity manger
     *
     * @param LifecycleEventArgs $args
     */
    public function postLoad(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();
        $this->processFields($entity, false);
    }

    /**
     * Listen to preflush event
     * Encrypt entities that are inserted into the database
     *
     * @param PreFlushEventArgs $preFlushEventArgs
     */
    public function preFlush(PreFlushEventArgs $preFlushEventArgs)
    {
        $unitOfWork = $preFlushEventArgs->getEntityManager()->getUnitOfWork();
        foreach ($unitOfWork->getScheduledEntityInsertions() as $entity) {
            $this->processFields($entity);
        }

        // Re-encrypt all previously decrypted entities
        foreach ($this->decryptedEntities as $entity) {
            $this->processFields($entity, true,$unitOfWork);
        }
    }

    /**
     * Listen to postFlush event
     * Decrypt entities after having been inserted into the database
     *
     * @param PostFlushEventArgs $postFlushEventArgs
     */
    public function postFlush(PostFlushEventArgs $postFlushEventArgs)
    {
        $unitOfWork = $postFlushEventArgs->getEntityManager()->getUnitOfWork();
        foreach ($unitOfWork->getIdentityMap() as $entityMap) {
            foreach ($entityMap as $entity) {
                $this->processFields($entity, false);
            }
        }
    }

    /**
     * Realization of EventSubscriber interface method.
     *
     * @return array Return all events which this subscriber is listening
     */
    public function getSubscribedEvents()
    {
        return array(
            Events::postUpdate,
            Events::preUpdate,
            Events::postLoad,
            Events::preFlush,
            Events::postFlush,
        );
    }

    /**
     * Process (encrypt/decrypt) entities fields
     *
     * @param Object $entity doctrine entity
     * @param Boolean $isEncryptOperation If true - encrypt, false - decrypt entity
     * @param UnitOfWork|null $unitOfWork
     *
     * @throws \RuntimeException
     *
     * @return object|null
     */
    public function processFields($entity, $isEncryptOperation = true, $unitOfWork = null)
    {
        if (!empty($this->encryptor)) {
            // Check which operation to be used
            $encryptorMethod = $isEncryptOperation ? 'encrypt' : 'decrypt';

            $realClass = ClassUtils::getClass($entity);

            // Get ReflectionClass of our entity
            $properties = $this->getClassProperties($realClass);

            // Foreach property in the reflection class
            foreach ($properties as $refProperty) {
                if ($this->annReader->getPropertyAnnotation($refProperty, 'Doctrine\ORM\Mapping\Embedded')) {
                    $this->handleEmbeddedAnnotation($entity, $refProperty, $isEncryptOperation);
                    continue;
                }

                /**
                 * If property is an normal value and contains the Encrypt tag, lets encrypt/decrypt that property
                 */
                if ($this->annReader->getPropertyAnnotation($refProperty, self::ENCRYPTED_ANN_NAME)) {
                    $pac = PropertyAccess::createPropertyAccessor();
                    $value = $pac->getValue($entity, $refProperty->getName());
                    if ($encryptorMethod == 'decrypt') {
                        if (!is_null($value) and !empty($value)) {
                            if (substr($value, -strlen(self::ENCRYPTION_MARKER)) == self::ENCRYPTION_MARKER) {
                                $this->decryptedEntities->add($entity);
                                $this->decryptCounter++;
                                $currentPropValue = $this->encryptor->decrypt(substr($value, 0, -5));
                                $pac->setValue($entity, $refProperty->getName(), $currentPropValue);
                            }
                        }
                    } else {
                        if (!is_null($value) and !empty($value)) {
                            if (substr($value, -strlen(self::ENCRYPTION_MARKER)) !== self::ENCRYPTION_MARKER) {
                                // Check if original unencrypted differs from new unencrypted value
                                if ($unitOfWork !== null && !$this->hasEncryptedFieldsChanged($unitOfWork, $entity,$refProperty)) {
                                    $originalData = $unitOfWork->getOriginalEntityData($entity);

                                    //Revert to original encrypted value if both unencrypted values are the same
                                    $pac->setValue($entity,$refProperty->getName(),$originalData[$refProperty->getName()]);
                                } else {
                                    $this->encryptCounter++;
                                    $currentPropValue = $this->encryptor->encrypt($value).self::ENCRYPTION_MARKER;
                                    $pac->setValue($entity, $refProperty->getName(), $currentPropValue);
                                }
                            }
                        }
                    }
                }
            }

            return $entity;
        }

        return $entity;
    }

    /**
     * Method that check if current encrypt values match with old ones
     * @param UnitOfWork $unitOfWork
     * @param $entity
     * @param ReflectionProperty $refProperty
     * @return bool
     */
    private function hasEncryptedFieldsChanged($unitOfWork, $entity, ReflectionProperty $refProperty)
    {
        $originalData = $unitOfWork->getOriginalEntityData($entity);

        //Get old value
        try{
            if(!isset($originalData[$refProperty->getName()])) {
                return true;
            }

            // Always encrypt when original-value is not encrypted
            if (substr($originalData[$refProperty->getName()], -strlen(self::ENCRYPTION_MARKER)) !== self::ENCRYPTION_MARKER) {
                return true;
            }

            $oldValue = $this->encryptor->decrypt(substr($originalData[$refProperty->getName()], 0, -5));
            if($oldValue instanceof HiddenString){
                $oldValue=$oldValue->getString();
            }
        } catch (HaliteAlert $e){
            $oldValue=$originalData[$refProperty->getName()];
        } catch (\TypeError $e) {
            $oldValue=$originalData[$refProperty->getName()];
        } catch (CryptoException $e ){
            $oldValue=$originalData[$refProperty->getName()];
        }

        //Get new value
        $pac = PropertyAccess::createPropertyAccessor();
        $newEntityValue = $pac->getValue($entity, $refProperty->getName());

        if (substr($newEntityValue, -strlen(self::ENCRYPTION_MARKER)) !== self::ENCRYPTION_MARKER) {
            $newValue = $newEntityValue;
        } else {
            try{
                $newValue = $this->encryptor->decrypt(substr($newEntityValue, 0, -5));
            } catch (HaliteAlert $e){
                $newValue = $newEntityValue;
            } catch (\TypeError $e) {
                $newValue = $newEntityValue;
            } catch (CryptoException $e ){
                $newValue = $newEntityValue;
            }
        }

        return $newValue != $oldValue;
    }


    private function handleEmbeddedAnnotation($entity, ReflectionProperty $embeddedProperty, bool $isEncryptOperation = true)
    {
        $propName = $embeddedProperty->getName();

        $pac = PropertyAccess::createPropertyAccessor();

        $embeddedEntity = $pac->getValue($entity, $propName);

        if ($embeddedEntity) {
            $this->processFields($embeddedEntity, $isEncryptOperation);
        }
    }

    /**
     * Recursive function to get an associative array of class properties
     * including inherited ones from extended classes
     *
     * @param string $className Class name
     *
     * @return array
     */
    private function getClassProperties($className)
    {
        $reflectionClass = new ReflectionClass($className);
        $properties      = $reflectionClass->getProperties();
        $propertiesArray = array();

        foreach ($properties as $property) {
            $propertyName = $property->getName();
            $propertiesArray[$propertyName] = $property;
        }

        if ($parentClass = $reflectionClass->getParentClass()) {
            $parentPropertiesArray = $this->getClassProperties($parentClass->getName());
            if (count($parentPropertiesArray) > 0) {
                $propertiesArray = array_merge($parentPropertiesArray, $propertiesArray);
            }
        }

        return $propertiesArray;
    }
}
