# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

`setono/sylius-cms-plugin` v2 is a CMS for Sylius 1.14, rebuilt from scratch on the `2.x` branch. Before working on it, read:

- **`GLOSSARY.md`**: the words the project uses. Use its terms in code, tests and issues, and avoid the words it lists under _Avoid_.
- **`docs/spec/README.md`**: the specification, which is the source of truth for v2. It covers the platform (PHP ≥ 8.2, Symfony 6.4, MySQL 8.0+ and PostgreSQL), the names (namespace `Setono\SyliusCMSPlugin`, config root `setono_sylius_cms`) and which contracts are public. Each build ticket links the chapters it implements.
- **`docs/adr/`**: the reasons behind the hard-to-reverse calls. Read the ones that touch the area you work in, and flag it explicitly if your work contradicts one.

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

- Functional and UI tests create their data with `Setono\SyliusCMSPlugin\Tests\Support\Fixtures` (extend it as needed). Every test runs in a database transaction that is rolled back afterwards (`dama/doctrine-test-bundle`). UI tests run the kernel in the same PHP process as the browser, so data a test creates is visible to the pages it visits.
- Follow the BDD-style naming convention for test methods (e.g., `it_should_do_something_when_condition_is_met`)
- **MUST use Prophecy for mocking** - Use the `ProphecyTrait` and `$this->prophesize()` for all mocks, NOT PHPUnit's `$this->createMock()`
- **Form testing** - Use Symfony's best practices for form testing as documented at https://symfony.com/doc/current/form/unit_testing.html
  - Extend `Symfony\Component\Form\Test\TypeTestCase` for form type tests
  - Use `$this->factory->create()` to create form instances
  - Test form submission, validation, and data transformation
- Ensure tests are isolated and don't depend on external state
- Test both happy path and edge cases

### UI Verification
- **All UI changes MUST be verified using the Playwright MCP** - After making any change that affects the rendered UI (templates, forms, styling, layout, flash messages, etc.), use the Playwright MCP to navigate the running test application and confirm the change renders and behaves as expected
- Run the test application (see [Test Application](#test-application)) and use the Playwright MCP `browser_navigate`, `browser_snapshot`, and `browser_take_screenshot` tools to inspect the affected pages
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

The functional and UI suites need a database with the schema created, and built assets. The UI suite also needs the Playwright browser: `vendor/bin/playwright-install chromium` (Node 20+).

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
- **Doctrine Integration**: Uses object manager loader (`tests/PHPStan/object_manager.php`)
- **Exclusions**: Test application directory
- **Baseline**: Generate with `composer analyse -- --generate-baseline` to track improvements

### Test Application
The plugin includes a test Symfony application in `tests/Application/` for development and testing:
- **Database**: MySQL 8.0+ or PostgreSQL, chosen by `DATABASE_URL`. Set it in `tests/Application/.env.local` / `.env.test.local` or the environment, and always include `serverVersion` in it
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
