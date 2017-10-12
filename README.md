# CatalogTranslate
CatalogTranslate is magento2 extension developed by ElateBrain and designed by Safalweb. Using this extension you can translate your products and categories using Magento2 CLI command interface.

## How to install

**This extension is now available through *Packagist* ! You don't need to specify the repository anymore.**

Add the following lines into your composer.json
 
```
...
"require":{
    ...
    "elatebrain/catalogtranslate": "^1.0.0"
 }
```
or simply run this command 
```
composer require elatebrain/catalogtranslate
```
 
Then type the following commands from your Magento root:

```
$ composer update
$ ./bin/magento cache:disable
$ ./bin/magento module:enable Elatebrain_Catalogtranslate
$ ./bin/magento setup:upgrade
$ ./bin/magento cache:enable
```
 
