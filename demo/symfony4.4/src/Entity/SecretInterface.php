<?php

namespace App\Entity;

interface SecretInterface
{
    public function getType();
    public function getName();
    public function getSecret();
    public function getRawSecret();
}