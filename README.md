# Sylius CMS Plugin

[![Build Status][ico-github-actions]][link-github-actions]
[![Code Coverage][ico-code-coverage]][link-code-coverage]

_The_ CMS plugin for your Sylius store!

## Installation

Install the package via composer:

```shell
composer require setono/sylius-cms-plugin
```

### Register bundle

Add the bundle to `bundles.php` if not done automatically. Be sure to add this line **before** `Sylius\Bundle\GridBundle\SyliusGridBundle::class`

```php
    Setono\SyliusCMSPlugin\SetonoSyliusCMSPlugin::class => ['all' => true],
    Sylius\Bundle\GridBundle\SyliusGridBundle::class => ['all' => true],
```

### Add configuration

Create the file `config/packages/setono_sylius_cms.yaml` and add the following:

```yaml
# config/packages/setono_sylius_cms.yaml
imports:
    - { resource: "@SetonoSyliusCMSPlugin/Resources/config/app/config.yaml" }
```

### Import the routes

Create the file `config/routes/setono_sylius_cms.yaml` and add the following:

```yaml
setono_sylius_cms:
    resource: "@SetonoSyliusCMSPlugin/Resources/config/routes.yaml"
```

### Install assets

```shell
php bin/console assets:install
php bin/console sylius:theme:assets:install
```

### Add migration

```shell
php bin/console doctrine:migration:diff
php bin/console doctrine:migration:migrate
```

[ico-github-actions]: https://github.com/Setono/SyliusCMSPlugin/workflows/build/badge.svg
[ico-code-coverage]: https://codecov.io/gh/Setono/SyliusCMSPlugin/branch/master/graph/badge.svg

[link-github-actions]: https://github.com/Setono/SyliusCMSPlugin/actions
[link-code-coverage]: https://codecov.io/gh/Setono/SyliusCMSPlugin
