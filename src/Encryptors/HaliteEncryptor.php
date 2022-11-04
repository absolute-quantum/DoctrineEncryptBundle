<?php

namespace Ambta\DoctrineEncryptBundle\Encryptors;

use ParagonIE\Halite\Alerts\CannotPerformOperation;
use ParagonIE\Halite\Symmetric\EncryptionKey;
use ParagonIE\HiddenString\HiddenString;
use ParagonIE\Halite\KeyFactory;
use ParagonIE\Halite\Symmetric\Crypto;

/**
 * Class for encrypting and decrypting with the halite library
 *
 * @author Michael de Groot <specamps@gmail.com>
 */

class HaliteEncryptor implements EncryptorInterface
{
    private ?EncryptionKey $encryptionKey = null;
    private string $keyFile;

    /**
     * {@inheritdoc}
     */
    public function __construct(string $keyFile)
    {
        $this->keyFile = $keyFile;
    }

    /**
     * {@inheritdoc}
     */
    public function encrypt(string $data): string
    {
        return Crypto::encrypt(new HiddenString($data), $this->getKey());
    }

    /**
     * {@inheritdoc}
     */
    public function decrypt(string $data): string
    {
        $data = Crypto::decrypt($data, $this->getKey());

        if ($data instanceof HiddenString)
        {
            $data = $data->getString();
        }

        return $data;
    }

    private function getKey(): EncryptionKey
    {
        if ($this->encryptionKey === null) {
            try {
                $this->encryptionKey = KeyFactory::loadEncryptionKey($this->keyFile);
            } catch (CannotPerformOperation $e) {
                $this->encryptionKey = KeyFactory::generateEncryptionKey();
                KeyFactory::save($this->encryptionKey, $this->keyFile);
            }
        }

        return $this->encryptionKey;
    }
}
