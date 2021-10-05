# Commands

To make your life a little easier we created some commands that you can use for encrypting and decrypting your current database.

## 1) Get status

You can use the comment `doctrine:encrypt:status` to get the current database and encryption information.

```
$ php bin/console doctrine:encrypt:status
```

This command will return the amount of entities and the amount of properties with the @Encrypted tag for each entity.
The result will look like this:

```
DoctrineEncrypt\Entity\User has 3 properties which are encrypted.
DoctrineEncrypt\Entity\UserDetail has 13 properties which are encrypted.

2 entities found which are containing 16 encrypted properties.
```

## 2) Encrypt current database

You can use the comment `doctrine:encrypt:database [encryptor]` to encrypt the current database.

* Optional parameter [encryptor]
    * An encryptor provided by the bundle (Defuse or Halite) or your own [encryption class](https://github.com/michaeldegroot/DoctrineEncryptBundle/blob/master/src/Resources/doc/custom_encryptor.md).
    * Default: Your encryptor set in the configuration file or the default encryption class when not set in the configuration file

```
$ php bin/console doctrine:encrypt:database
```

or you can provide an encryptor (optional).

```
$ php bin/console doctrine:encrypt:database Defuse
```

```
$ php bin/console doctrine:encrypt:database Halite
```

This command will return the amount of values encrypted in the database.

```
Encryption finished values encrypted: 203 values.
```


## 3) Decrypt current database

You can use the comment `doctrine:decrypt:database [encryptor]` to decrypt the current database.

* Optional parameter [encryptor]
    * An encryptor provided by the bundle (Defuse or Halite) or your own [encryption class](https://github.com/michaeldegroot/DoctrineEncryptBundle/blob/master/src/Resources/doc/custom_encryptor.md).
    * Default: Your encryptor set in the configuration file or the default encryption class when not set in the configuration file

```
$ php bin/console doctrine:decrypt:database
```

or you can provide an encryptor (optional).

```
$ php bin/console doctrine:decrypt:database Defuse
```

```
$ php bin/console doctrine:decrypt:database Halite
```

This command will return the amount of entities and the amount of values decrypted in the database.

```
Decryption finished entities found: 26, decrypted 195 values.
```

## Custom encryption class

You may want to use your own encryption class learn how here:

#### [Custom encryption class](https://github.com/michaeldegroot/DoctrineEncryptBundle/blob/master/src/Resources/doc/custom_encryptor.md)