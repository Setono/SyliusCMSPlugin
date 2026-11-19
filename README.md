# Setono Sylius CMS Plugin

[![Latest Version][ico-version]][link-packagist]
[![Build Status][ico-github-actions]][link-github-actions]
[![Code Coverage][ico-code-coverage]][link-code-coverage]
[![Mutation testing][ico-infection]][link-infection]

A CMS for Sylius stores. Developers define what content can look like in code, and merchants compose, publish and place
content in the Sylius admin.

v2 is a rebuild from scratch for Sylius 1.14, developed on the `2.x` branch.

- The [specification](docs/spec/README.md) is the source of truth for what v2 does.
- The [glossary](GLOSSARY.md) defines the words the specification and the code use.
- The [architecture decision records](docs/adr/) explain the hard-to-reverse calls.

v1 lives on the [`1.x`](https://github.com/Setono/SyliusCMSPlugin/tree/1.x) branch. v2 is a clean break, and there is
no migration from v1.

## Requirements

- Sylius 1.14, on Symfony 6.4
- PHP 8.2 or newer, with the `dom`, `filter`, `libxml`, `mbstring` and `zip` extensions
- MySQL 8.0 or newer, or PostgreSQL
- A Symfony Messenger worker, which a Sylius shop already needs for its catalog promotions
  ([below](#run-a-messenger-worker))

The builder ships prebuilt, so the shop needs no Node, Encore or Symfony UX for it.

## Installation

These steps start from a Sylius 1.14 shop made from [Sylius-Standard](https://github.com/Sylius/Sylius-Standard).
Everything else the plugin needs, such as LiipImagine, Flysystem and Messenger, is already there.

### 1. Require the package

```shell
composer require setono/sylius-cms-plugin
```

Until 2.0 is tagged, require the branch: `composer require setono/sylius-cms-plugin:2.x-dev`.

### 2. Register the bundle

Add the bundle to `config/bundles.php`, **before `SyliusGridBundle`**:

```php
return [
    // ...
    Sylius\Bundle\ResourceBundle\SyliusResourceBundle::class => ['all' => true],
    Setono\SyliusCMSPlugin\SetonoSyliusCMSPlugin::class => ['all' => true],
    Sylius\Bundle\GridBundle\SyliusGridBundle::class => ['all' => true],
    // ...
];
```

The plugin adds the grids of its entry lists to `SyliusGridBundle`'s config. Registered after it, the container fails to
build with `You have requested a non-existent parameter "setono_sylius_cms.model.entry.class"`.

### 3. Import the routes

Create `config/routes/setono_sylius_cms.yaml` with the plugin's three route files:

```yaml
setono_sylius_cms_admin:
    resource: '@SetonoSyliusCMSPlugin/Resources/config/routes/admin.php'
    prefix: '/%sylius_admin.path_name%'

setono_sylius_cms_media:
    resource: '@SetonoSyliusCMSPlugin/Resources/config/routes/media.php'

setono_sylius_cms_shop:
    resource: '@SetonoSyliusCMSPlugin/Resources/config/routes/shop.php'
    prefix: /{_locale}
    requirements:
        _locale: ^[A-Za-z]{2,4}(_([A-Za-z]{4}|[0-9]{3}))?(_([A-Za-z]{2}|[0-9]{3}))?$
```

- **The admin** goes under Sylius's admin prefix, so it sits behind the admin firewall, also on a shop that renamed the
  admin path with `SYLIUS_ADMIN_ROUTING_PATH_NAME`.
- **The media routes** have no prefix. They give documents and videos their permanent URLs, `/media/{uuid}/{filename}`.
- **The shop routes** go under the same locale prefix and requirement as `sylius_shop`. An entry's URL is matched after
  every other route, so the plugin never takes a URL from Sylius or another plugin.

Keep LiipImagine's routes imported, as Sylius-Standard does in `config/routes/liip_imagine.yaml`: image variants are made
through them.

### 4. Configure it, if you need to

The plugin works without any config of its own. Its defaults, and every key a project can set, are in the
[configuration reference](docs/spec/extending.md#configuration), and `bin/console config:dump-reference setono_sylius_cms`
prints them. A project adds what it changes to `config/packages/setono_sylius_cms.yaml`, for example:

```yaml
setono_sylius_cms:
    fallback_locales:
        de_CH: [de_DE]
    content_types:
        blog_post:
            prefix: 'news'
```

The plugin adds what it needs to the config of other bundles itself: its Flysystem storage, its LiipImagine filter sets,
its Messenger routing, its grids and its blocks on Sylius's template events. A project overrides any of it in its own
config.

### 5. Create the database tables

The plugin ships no Doctrine migrations, because a project's own content types add columns to the plugin's tables.
Generate a migration and run it:

```shell
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

Do the same after every upgrade of the plugin, and whenever you add or change a content type, and review what the
migration does before you run it.

### 6. Install the assets

```shell
bin/console assets:install
```

This copies the prebuilt builder, the shop's stylesheet and carousel script, and the admin's scripts to
`public/bundles/setonosyliuscmsplugin`. Run it on every deploy, as a Sylius deploy already does.

### 7. Keep the media storage

Media items are stored in the plugin's own Flysystem storage, `setono_sylius_cms.storage`. By default it's the local
directory `public/media/cms`, which the web server serves at `/media/cms`, beside Sylius's own `public/media/image`.

- **It holds the only copy of every file**, so keep `public/media/cms` across deploys, such as in a deploy tool's shared
  directories or on a persistent volume, as you keep `public/media/image`. The image variants are cached under
  `public/media/cms/cache`.
- **Exports uploaded for an import in the admin** are kept in the same storage until the import finishes or is
  abandoned: under `imports/`, written private, with a random name.
- **The Messenger worker needs the same storage** as the web servers, because it reads those uploads and the media files.
  On one server that's the same directory. With several servers, or a worker on another one, put the storage somewhere
  they share, such as S3.

To store media elsewhere, define a Flysystem storage in your own `flysystem` config, name it in
`setono_sylius_cms.media.storage`, and point `Setono\SyliusCMSPlugin\Media\MediaUrlGeneratorInterface` at a service
that builds the storage's public URLs. The shipped `MediaUrlGenerator` takes a base URL:

```yaml
# config/packages/setono_sylius_cms.yaml
flysystem:
    storages:
        app.cms_storage:
            adapter: 'aws'                 # league/flysystem-aws-s3-v3
            options:
                client: 'app.s3_client'    # your Aws\S3\S3Client service
                bucket: '%env(CMS_BUCKET)%'
                prefix: 'cms'

setono_sylius_cms:
    media:
        storage: 'app.cms_storage'

services:
    Setono\SyliusCMSPlugin\Media\MediaUrlGeneratorInterface:
        class: Setono\SyliusCMSPlugin\Media\MediaUrlGenerator
        arguments: ['https://%env(CMS_BUCKET)%.s3.amazonaws.com/cms']
```

The plugin's LiipImagine loader and resolver read and write through the storage, so image variants follow it. On a
storage other than the default, each variant gets LiipImagine's signed runtime URL, which makes it on its first request.
See [Image processing](docs/spec/media.md#image-processing).

### 8. Load demo content, if you want to

The Sylius fixture `setono_cms_import` imports an export folder or zip, and publishes what it imported. Add it to a
fixtures suite in `config/packages/sylius_fixtures.yaml`:

```yaml
sylius_fixtures:
    suites:
        default:
            fixtures:
                setono_cms_import:
                    options:
                        path: '%kernel.project_dir%/fixtures/cms'
```

Make the export with `bin/console setono:cms:export fixtures/cms --all` in a shop that has the content, or from the
admin's Transfer screen.

Then sign in to the admin: **Content** is a new item in its main menu.

## Running it

### Run a Messenger worker

The plugin sends its background work to Sylius's `main` Messenger transport, the one Sylius schedules its catalog
promotions on. A shop that runs a worker for Sylius already has one:

```shell
bin/console messenger:consume main
```

Sylius's own messages also use the `catalog_promotion_removal` transport, so a Sylius shop usually consumes both. Run
the worker under a process manager such as Supervisor or systemd, and stop it on every deploy with
`bin/console messenger:stop-workers`, so it restarts on the new code.

These are the plugin's messages, all dispatched on `sylius.event_bus`:

| Message | Does | Delayed |
| --- | --- | --- |
| `Setono\SyliusCMSPlugin\Publishing\ScheduledPublishDue` | Runs a scheduled publish | until the publish's time |
| `Setono\SyliusCMSPlugin\Media\DeleteReplacedFile` | Deletes a replaced media file and its variants | by `media.grace_period`, one day by default |
| `Setono\SyliusCMSPlugin\Transfer\Import\RunImport` | Checks, or runs, an import started in the admin | no |
| `Setono\SyliusCMSPlugin\Transfer\Import\AbandonImport` | Abandons an uploaded import no one confirmed, and deletes its zip | one day |

- **The transport must be asynchronous and support delays.** Sylius's default, `doctrine://default`, does, as do Redis
  and AMQP. On `sync://` a scheduled publish would publish at once, and an uploaded import would be abandoned before
  anyone confirmed it. Set the DSN in `SYLIUS_MESSENGER_TRANSPORT_MAIN_DSN`.
- **The plugin routes them in its own config.** To send them elsewhere, route them in your own
  `framework.messenger.routing`.
- **The worker needs** the same media storage as the web servers ([above](#7-keep-the-media-storage)), the `zip`
  extension, and room in PHP's temporary directory for a copy of an uploaded zip and of each media file it checks.
- **Without a worker**, scheduled publishes don't run, and the admin warns about each one five minutes after its time.
  Imports started in the admin wait, and replaced files are never deleted.
- **A message that keeps failing** ends in Sylius's `main_failed` transport, where `bin/console messenger:failed:show`
  lists it.

Uploads in the admin, media files and exports for an import alike, are capped by PHP's `upload_max_filesize` and
`post_max_size`. Import a bigger export with `bin/console setono:cms:import`.

### Run the cron commands

| Command | When | Does |
| --- | --- | --- |
| `bin/console setono:cms:bestsellers:compute` | Nightly, for example | Recomputes the bestseller scores of every channel, or of one with `--channel=CODE`. Until it first runs, every Bestsellers list is empty. |
| `bin/console setono:cms:revisions:prune` | Daily, for example | Deletes each entry's revisions beyond the newest `revisions.keep`, 50 by default. |

### Check routes at deploy and in CI

```shell
bin/console setono:cms:check-routes
```

An entry's URL is matched after every other route, so a route added later, by your code or a plugin, silently takes
the URL of an entry published before it. The command lists every entry URL another route now matches, with its channel,
locale and route, and exits non-zero when it finds any. Run it at every deploy, after the new code is in place, and in
CI. It also updates the problem counts the admin shows for those URLs.

A deploy of a shop with the plugin then runs, besides what Sylius needs:

```shell
bin/console doctrine:migrations:migrate --no-interaction
bin/console assets:install
bin/console setono:cms:check-routes
bin/console messenger:stop-workers
```

### Other commands

| Command | Does |
| --- | --- |
| `setono:cms:export <dir>` and `setono:cms:import <path>` | Move content between environments ([Transfer](docs/spec/transfer.md#commands)). `setono:cms:import --dry-run` exits with the code the import would, for CI and deploys. |
| `setono:cms:preset:validate` | Validates the code preset files of every registered directory, and exits non-zero on an error. Run it in CI when the project has code presets of its own. |
| `setono:cms:preset:export <preset> <dir>` | Writes an editor preset to a code preset file. |
| `setono:cms:upgrade [--dry-run]` | Runs the upgraders over all stored content and writes it back. Upgraders also run whenever stored content is read, so this is optional. |
| `setono:cms:lint` | Reports stored values that don't fit their field type, blocks of unknown types and broken references. Exits non-zero on any. Run it in CI or after a deploy that changes block types. |

## Extending

Developers add their own building blocks as PHP classes registered by attribute. Each guide is short, with code that
works:

- [Block types](docs/guides/block-types.md)
- [Layouts](docs/guides/layouts.md)
- [Content types](docs/guides/content-types.md)
- [Sources](docs/guides/sources.md)
- [Condition kinds](docs/guides/condition-kinds.md)
- [Bindable properties](docs/guides/bindable-properties.md)
- [Link targets](docs/guides/link-targets.md)
- [Field types and their field controls](docs/guides/field-types.md)
- [Styling](docs/guides/styling.md)
- [Overriding templates](docs/guides/overriding-templates.md)
- [Switching off what ships](docs/guides/switching-off.md)

The full contract is in [Extending](docs/spec/extending.md).

## Caching

v2.0 caches nothing it renders ([ADR 0021](docs/adr/0021-storefront-output-renders-on-every-request-in-v2-0.md)). A
shop that wants to save the queries behind its slots and entry URLs can add a cache:
[Caching placements and URL lookups](docs/cookbook/caching.md).

## Development

The plugin is developed against the Sylius test application in `tests/Application`. The tests run on MySQL 8.0. Set
`DATABASE_URL` in `tests/Application/.env.local` or `tests/Application/.env.test.local` to point it at your database.

```shell
composer install

(cd tests/Application && yarn install && yarn build)
(cd tests/Application && bin/console assets:install)

(cd tests/Application && bin/console doctrine:database:create)
(cd tests/Application && bin/console doctrine:schema:update --force --complete)

# The data the functional and UI suites run against. Every test is rolled back to it
(cd tests/Application && APP_ENV=test bin/console sylius:fixtures:load test --no-interaction)
```

The tests come in three PHPUnit suites:

```shell
vendor/bin/phpunit --testsuite unit
vendor/bin/phpunit --testsuite functional # needs the database, the test fixtures and built assets
vendor/bin/phpunit --testsuite ui         # also needs a browser: vendor/bin/playwright-install chromium
```

The builder is a React and TypeScript app in `assets/builder`. Its compiled assets in `src/Resources/public/builder`
are committed, so projects that install the plugin need no Node. After changing it, build it and commit the result:

```shell
npm ci
npm test
npm run build
```

[ico-version]: https://poser.pugx.org/setono/sylius-cms-plugin/v/stable
[ico-github-actions]: https://github.com/Setono/SyliusCMSPlugin/actions/workflows/build.yaml/badge.svg?branch=2.x
[ico-code-coverage]: https://codecov.io/gh/Setono/SyliusCMSPlugin/branch/2.x/graph/badge.svg
[ico-infection]: https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2FSetono%2FSyliusCMSPlugin%2F2.x

[link-packagist]: https://packagist.org/packages/setono/sylius-cms-plugin
[link-github-actions]: https://github.com/Setono/SyliusCMSPlugin/actions
[link-code-coverage]: https://codecov.io/gh/Setono/SyliusCMSPlugin
[link-infection]: https://dashboard.stryker-mutator.io/reports/github.com/Setono/SyliusCMSPlugin/2.x
