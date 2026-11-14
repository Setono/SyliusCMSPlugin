# Setono Sylius CMS Plugin

[![Latest Version][ico-version]][link-packagist]
[![Build Status][ico-github-actions]][link-github-actions]
[![Code Coverage][ico-code-coverage]][link-code-coverage]
[![Mutation testing][ico-infection]][link-infection]

A CMS for Sylius stores. Developers define what content can look like in code, and merchants compose, publish and place
content in the Sylius admin.

## v2 is in development

v2 is a rebuild from scratch for Sylius 1.14, developed on the `2.x` branch. It isn't ready for use yet.

- The [specification](docs/spec/README.md) is the source of truth for what v2 does.
- The [glossary](GLOSSARY.md) defines the words the specification and the code use.
- The [architecture decision records](docs/adr/) explain the hard-to-reverse calls.

v1 lives on the [`1.x`](https://github.com/Setono/SyliusCMSPlugin/tree/1.x) branch. v2 is a clean break, and there is
no migration from v1.

## Development

The plugin is developed against the Sylius test application in `tests/Application`. It runs on MySQL 8.0+ or
PostgreSQL. Set `DATABASE_URL` in `tests/Application/.env.local` or `tests/Application/.env.test.local` to choose one.

```shell
composer install

(cd tests/Application && yarn install && yarn build)
(cd tests/Application && bin/console assets:install)

(cd tests/Application && bin/console doctrine:database:create)
(cd tests/Application && bin/console doctrine:schema:update --force --complete)
```

The tests come in three PHPUnit suites:

```shell
vendor/bin/phpunit --testsuite unit
vendor/bin/phpunit --testsuite functional # needs the database and built assets
vendor/bin/phpunit --testsuite ui         # also needs a browser: vendor/bin/playwright-install chromium
```

[ico-version]: https://poser.pugx.org/setono/sylius-cms-plugin/v/stable
[ico-github-actions]: https://github.com/Setono/SyliusCMSPlugin/actions/workflows/build.yaml/badge.svg?branch=2.x
[ico-code-coverage]: https://codecov.io/gh/Setono/SyliusCMSPlugin/branch/2.x/graph/badge.svg
[ico-infection]: https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2FSetono%2FSyliusCMSPlugin%2F2.x

[link-packagist]: https://packagist.org/packages/setono/sylius-cms-plugin
[link-github-actions]: https://github.com/Setono/SyliusCMSPlugin/actions
[link-code-coverage]: https://codecov.io/gh/Setono/SyliusCMSPlugin
[link-infection]: https://dashboard.stryker-mutator.io/reports/github.com/Setono/SyliusCMSPlugin/2.x
