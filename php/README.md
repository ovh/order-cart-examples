Order Cli in PHP

## Prerequisites

Order-cli and extensions scripts depends on php-ovh library, please consult for further details :
[PHP-OVH](https://github.com/ovh/php-ovh)

``` bash
php composer.phar install
```

As well, these examples assumes an existing configuration with valid application_key, application_secret and consumer_key..
On top of project, you can:

``` bash
mv ovh.conf-dist ovh.conf
edit ovh.conf
```

## Launch

``` bash
./ovh-cli
```

<img alt="php-cli" width="700"
     src="img/php-cli.gif" />

should be enough, when rights are correct.

## TODO
- [ ] Unit Test
- [ ] CI integration
- [ ] Oriented object modeling/API
- [ ] How to generate documentation
- [ ] More products
