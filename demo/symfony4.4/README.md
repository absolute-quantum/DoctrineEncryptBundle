This demo-installation demonstrates a simple symfony 4.4-application using both annotations and, when using php > 8.0, attributes.

# How to use
```shell
# run `composer install` in parent-directory to create symlinks for shared files
cd ../
composer install
cd -

# Install packages
composer install

# Serve the application
## Using symfony-cli
symfony serve
## Using php-builtin-server ()
php -S localhost:8000 -t public
## Other possibility
### Use a webserver like apache or nginx


# Run unittests
vendor/bin/simple-phpunit
```


