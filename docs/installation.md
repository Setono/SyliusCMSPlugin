Before you can install the plugin you need a packagist token. You get a token once you buy the plugin. If you haven't done so yet, go to [sylius-cms.com](https://sylius-cms.com) and hit the big buy button ;)

{% hint style="info" %}
If you have Flex installed and just want the plugin installed with the default settings, go directly to [Installing the plugin](#installing-the-plugin)
{% endhint %}

## Installing the plugin

Since the plugin is being provided through packagist.com you just have two steps to take before you can install the plugin:

**1. Add repository to composer.json**:
```shell
composer config repositories.private-packagist composer https://setono.repo.packagist.com/acme/
```
Remember to replace `acme` with the short name given to you.

**2. Add token to composer auth**:

```shell
composer config --global --auth http-basic.setono.repo.packagist.com token your_token
```

Remember to replace `your_token` with the token given to you.

Now you should be able to install the plugin using the normal `composer require` command:

```shell
composer require setono/sylius-consent-management-plugin
```

## Enabling the plugin

If you have Flex enabled the `composer require` will automatically add the bundles and the plugin to `bundles.php`.
If not you should manually add them:

```php
    // ...

    Setono\SyliusCMSPlugin\SetonoSyliusCMSPlugin::class => ['all' => true],
    Sylius\Bundle\GridBundle\SyliusGridBundle::class => ['all' => true],
    
    // ...
```

{% hint style="info" %}
**NOTICE: ** It's important that you add the plugin _before_ the `SyliusGridBundle`.
{% endhint %}

## Add configuration file

Create the file `config/packages/setono_sylius_cms.yaml` and add the following:

```yaml
# config/packages/setono_sylius_cms.yaml
imports:
    - { resource: "@SetonoSyliusCMSPlugin/Resources/config/app/config.yaml" }

    # Uncomment next line if you want some default fixtures for this plugin
    # - { resource: "@SetonoSyliusCMSPlugin/Resources/config/app/fixtures.yaml" }
```

## Include routes configuration

Create the file `config/routes/setono_sylius_cms.yaml` and add the following:

```yaml
# config/routes/setono_sylius_cms.yaml
setono_sylius_cms:
    resource: "@SetonoSyliusCMSPlugin/Resources/config/routes.yaml"
```

{% hint style="info" %}
The plugin also provides a routes file for non localized stores. All you do is to use
`@SetonoSyliusCMSPlugin/Resources/config/routes_no_locale.yaml` instead of
`@SetonoSyliusCMSPlugin/Resources/config/routes.yaml`
{% endhint %}
