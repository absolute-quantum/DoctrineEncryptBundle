# Configuration Reference

All available configuration options are listed below.

``` yaml
ambta_doctrine_encrypt:
    #  If you want, you can use your own Encryptor. Encryptor must implements EncryptorInterface interface
    #  Default: Halite
    encryptor_class: Halite
    
    # Path where to store the keyfiles
    # Default: '%kernel.project_dir%'
    secret_directory_path: '%kernel.project_dir%'   

    secret_key_name: '' # deafult is '' epected values is like CustomEncryptKey only the key file name without .key extension
```

