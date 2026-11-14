# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

`setono/sylius-cms-plugin` v2 is a CMS for Sylius 1.14, rebuilt from scratch on the `2.x` branch. Before working on it, read:

- **`GLOSSARY.md`**: the words the project uses. Use its terms in code, tests and issues, and avoid the words it lists under _Avoid_.
- **`docs/spec/README.md`**: the specification, which is the source of truth for v2. It covers the platform (PHP ≥ 8.2, Symfony 6.4, MySQL 8.0+ and PostgreSQL), the names (namespace `Setono\SyliusCMSPlugin`, config root `setono_sylius_cms`) and which contracts are public. Each build ticket links the chapters it implements.
- **`docs/adr/`**: the reasons behind the hard-to-reverse calls. Read the ones that touch the area you work in, and flag it explicitly if your work contradicts one.

## Conventions

The internals every slice follows, settled with the maintainer in #216. Prefer Sylius practice, then Symfony's, for anything they don't cover.

- **Layout.** Bundle files live in `src/Resources/{config,views,translations,public}`. Route files are PHP, at `src/Resources/config/routes/{admin,shop,media}.php`, imported as `@SetonoSyliusCMSPlugin/Resources/config/routes/*.php`. Template names are `@SetonoSyliusCMSPlugin/<path>` (`block/…`, `layout/…`, `admin/…`), and public assets are served from `/bundles/setonosyliuscmsplugin/…`.
- **Namespaces.** One per area, named as the spec names them (`Attribute`, `Field`, `Rendering`, `Value`, …). Entities and their interfaces go in `Model`.
- **Services.** XML, one file per area in `src/Resources/config/services/`, each imported from `services.xml`. Arguments are explicit, with no autowiring. Service ids are FQCNs, and every service a project may decorate or inject also gets its interface as an alias. Extension points reach projects through autoconfiguration, registered in `SetonoSyliusCMSExtension`.
- **Doctrine mapping.** XML, in `src/Resources/config/doctrine/model/`. Models are mapped superclasses that become entities as their Sylius resource's model, except the single-table-inheritance roots `Entry` and `EntryTranslation`. The plugin ships no migrations: projects generate their own, and the test app uses `doctrine:schema:update`.
- **The builder app.** Its source is in `assets/builder/`, with `package.json` and `package-lock.json` at the repo root. Vite builds it to `src/Resources/public/builder/builder.{js,css}`, which is committed: run `npm run build` after every change to it, and CI fails when the build doesn't match the sources. The working copy lives in a Zustand store and every edit goes through Immer, whose patches build the autosave change set (their inverses are kept for undo). Fields render through the app's own form renderer, with one component per `x-cms-type`. Styling is CSS Modules with `--builder-*` design tokens, plus Radix UI primitives for menus, dialogs and popovers.
- **Test data and UI tests.** See [Testing Requirements](#testing-requirements).

## Code Standards

Follow clean code principles and SOLID design patterns when working with this codebase:
- Write clean, readable, and maintainable code
- Apply SOLID principles (Single Responsibility, Open/Closed, Liskov Substitution, Interface Segregation, Dependency Inversion)
- Use meaningful variable and method names
- Keep methods and classes focused on a single responsibility
- Favor composition over inheritance
- Write code that is easy to test and extend

### Testing Requirements

The testing rule is in [the spec's Testing section](docs/spec/README.md#testing): **every build ticket ships with tests at each level that applies, and a slice isn't done without them.** There is no Behat. The levels are three PHPUnit suites:

| Suite | Directory | What it covers |
| --- | --- | --- |
| `unit` | `tests/Unit` | PHP classes in isolation |
| `functional` | `tests/Functional` | Symfony kernel and web test cases against the test application and a real database: controllers, routing, rendering, console commands |
| `ui` | `tests/UI` | A real browser driven by [`playwright-php/playwright-symfony`](https://github.com/playwright-php/playwright-symfony) through the storefront and the admin |

- Functional and UI tests start from the test app's `test` fixtures suite (`tests/Application/config/packages/sylius_fixtures.yaml`): the channel `WEB` on `localhost` with `en_US` and `USD`, and the admin user `sylius`. It's built from Sylius's own fixtures and loaded once before the suites. Every test runs in a database transaction that's rolled back to it afterwards (`dama/doctrine-test-bundle`). A test that needs more creates it with Sylius's example factories (`sylius.fixture.example_factory.*`), or with a resource's own factory for CMS entries (the helpers in `tests/Support/Entries.php`).
- UI tests extend `Setono\SyliusCMSPlugin\Tests\UI\UITestCase`, the one base test case for `playwright-php/playwright-symfony`. It runs the kernel in the same PHP process as the browser, so data a test creates is visible to the pages it visits. If playwright-symfony can't do something a test needs, stop and report it rather than switching tools.
- The builder app's TypeScript is unit tested with Vitest (`npm test`), next to its source in `assets/builder/tests`.
- Follow the BDD-style naming convention for test methods (e.g., `it_should_do_something_when_condition_is_met`)
- **MUST use Prophecy for mocking** - Use the `ProphecyTrait` and `$this->prophesize()` for all mocks, NOT PHPUnit's `$this->createMock()`
- **Form testing** - Use Symfony's best practices for form testing as documented at https://symfony.com/doc/current/form/unit_testing.html
  - Extend `Symfony\Component\Form\Test\TypeTestCase` for form type tests
  - Use `$this->factory->create()` to create form instances
  - Test form submission, validation, and data transformation
- Ensure tests are isolated and don't depend on external state
- Test both happy path and edge cases

### UI Verification
- **All UI changes MUST be verified with UI tests** - After making any change that affects the rendered UI (templates, forms, styling, layout, flash messages, etc.), cover it with a test in the `ui` suite (`tests/UI`) that drives a real browser through the test application with `playwright-php/playwright-symfony`, and run that suite
- Verify both the visual result and the interactive behavior (e.g. submitting forms, triggering flash messages)

## Development Commands

### Code Quality & Testing
- `composer analyse` - Run PHPStan static analysis (level max)
- `composer check-style` - Check code style with ECS (Easy Coding Standard)
- `composer fix-style` - Fix code style issues automatically with ECS
- `vendor/bin/rector process --dry-run` - Check for Rector changes
- `vendor/bin/composer-dependency-analyser` - Check that `composer.json` lists what `src/` uses
- `vendor/bin/phpunit --testsuite unit` (or `functional`, `ui`) - Run one test suite; `vendor/bin/phpunit` runs all three
- `symfony lsp:check` - Run Symfony-aware diagnostics (routes, services, templates, translations, config) against the plugin and its test application. Requires the Symfony CLI 5.20 or newer

The functional and UI suites need a database with the schema created, the `test` fixtures loaded (`(cd tests/Application && APP_ENV=test bin/console sylius:fixtures:load test --no-interaction)`) and built assets. The UI suite also needs the Playwright browser: `vendor/bin/playwright-install chromium` (Node 20+).

Infection (`vendor/bin/infection`) requires 100% MSI on the `unit` suite. It leaves out the code only the functional suite can test (`excludes` in `infection.json.dist`); everything else needs unit tests that kill every mutant.

### Static Analysis

#### PHPStan Configuration
PHPStan is configured in `phpstan.neon` with:
- **Analysis Level**: max (strictest)
- **Extensions**: Auto-loaded via `phpstan/extension-installer`
  - `phpstan/phpstan-symfony` - Symfony framework integration
  - `phpstan/phpstan-doctrine` - Doctrine ORM integration
  - `phpstan/phpstan-phpunit` - PHPUnit test integration
  - `jangregor/phpstan-prophecy` - Prophecy mocking integration
- **Symfony Integration**: Uses console application loader (`tests/PHPStan/console_application.php`)
- **Doctrine Integration**: Uses object manager loader (`tests/PHPStan/object_manager.php`). It boots the test kernel without `.env`, so export a `DATABASE_URL` that includes `serverVersion` (CI's does), or Doctrine tries to connect
- **Exclusions**: Test application directory
- **Baseline**: Generate with `composer analyse -- --generate-baseline` to track improvements

### Test Application
The plugin includes a test Symfony application in `tests/Application/` for development and testing:
- **Database**: the tests and CI run on MySQL 8.0. The app also boots on PostgreSQL, chosen by `DATABASE_URL`, but the suites aren't run there. Set `DATABASE_URL` in `tests/Application/.env.local` / `.env.test.local` or the environment, and always include `serverVersion` in it
- Create the schema with `(cd tests/Application && bin/console doctrine:database:create && bin/console doctrine:schema:update --force --complete)`. Use `schema:update` rather than `schema:create`, which fails on PostgreSQL. Run it again after changing the Doctrine mapping
- Run `(cd tests/Application && yarn install && yarn build && bin/console assets:install)` to build assets
- Use standard Symfony commands for the test app
- **Sylius Backend Credentials**: Username: `sylius`, Password: `sylius`

## Bash Tools Recommendations

Use the right tool for the right job when executing bash commands:

- **Finding FILES?** → Use `fd` (fast file finder)
- **Finding TEXT/strings?** → Use `rg` (ripgrep for text search)
- **Finding CODE STRUCTURE?** → Use `ast-grep` (syntax-aware code search)
- **SELECTING from multiple results?** → Pipe to `fzf` (interactive fuzzy finder)
- **Interacting with JSON?** → Use `jq` (JSON processor)
- **Interacting with YAML or XML?** → Use `yq` (YAML/XML processor)

Examples:
- `fd "*.php" | fzf` - Find PHP files and interactively select one
- `rg "function.*validate" | fzf` - Search for validation functions and select
- `ast-grep --lang php -p 'class $name extends $parent'` - Find class inheritance patterns

## Agent skills

### Issue tracker

Issues are tracked in GitHub Issues on `Setono/SyliusCMSPlugin`, using the `gh` CLI. See `docs/agents/issue-tracker.md`.

### Triage labels

The five default triage labels (`needs-triage`, `needs-info`, `ready-for-agent`, `ready-for-human`, `wontfix`). See `docs/agents/triage-labels.md`.

### Domain docs

Single-context: one `GLOSSARY.md` and `docs/adr/` at the repo root. See `docs/agents/domain.md`.
