# Test Application Strategy

## Architecture Overview

The plugin uses **`sylius/test-application`** (Composer package) as the host Sylius application. The kernel is `Sylius\TestApplication\Kernel`, located in `vendor/sylius/test-application/`. Plugin-specific configuration lives in `tests/TestApplication/config/` and is injected via the `SYLIUS_TEST_APP_CONFIGS_TO_IMPORT` environment variable (defined in `.env`).

## Symfony CLI as the Single Entry Point

All Makefile commands are prefixed with `symfony` to guarantee the PHP version defined in `.php-version` (8.2) is used, regardless of the developer's system PHP:

| Variable | Value |
|----------|-------|
| `COMPOSER` | `symfony composer` |
| `CONSOLE` | `symfony console` |
| `PHP` | `symfony php` |
| `TEST_CONSOLE` | `APP_ENV=test symfony console --env=test` |
| `TEST_PHP` | `APP_ENV=test symfony php` |

## Test Database Isolation (test vs dev)

This is the most subtle point. **The problem**: Symfony CLI detects Docker (via `.symfony.local.yaml` > `workers.docker_compose`) and automatically injects `DATABASE_URL` pointing to the **dev** database (`sylius_hipay_plugin`). This injection is a process-level environment variable that systematically overrides `.env.test` values.

**The solution** is implemented in 3 layers:

### a) Separate environment variable

`.env.test` defines `DATABASE_TEST_URL` (not `DATABASE_URL`):

```dotenv
DATABASE_TEST_URL=mysql://root@127.0.0.1:3306/sylius_hipay_plugin_test
```

### b) Conditional Doctrine configuration

`tests/TestApplication/config/config.yaml` activates `DATABASE_TEST_URL` only in the `test` environment:

```yaml
when@test:
    doctrine:
        dbal:
            url: '%env(resolve:DATABASE_TEST_URL)%'
```

### c) Forced `APP_ENV=test`

The `APP_ENV=test` prefix before every `symfony` command prevents the CLI from injecting `APP_ENV=dev`:

```makefile
TEST_CONSOLE=APP_ENV=test $(CONSOLE) --env=test
TEST_PHP=APP_ENV=test $(PHP)
```

In `phpunit.xml.dist`, `force="true"` ensures PHPUnit overrides process-level env vars:

```xml
<env name="APP_ENV" value="test" force="true" />
```

## Test Database Lifecycle

```bash
make test.db.create   # Creates sylius_hipay_plugin_test if it doesn't exist
make test.db.migrate  # Applies migrations
make test.db.init     # create + migrate (shortcut)
make test.db.drop     # Drops the test database
make test.db.reset    # drop + init (full reset)
```

The targets `test.phpunit.integration`, `test.phpunit.functional` and `test.behat` declare `test.db.init` as a Make dependency, so the database is always ready before tests run.

## Inter-test Isolation

- **PHPUnit**: each integration test uses `setUp()`/`tearDown()` with a Doctrine transaction for automatic rollback.
- **Behat**: the `sylius.behat.context.hook.doctrine_orm` context purges tables between each scenario.

## Test Suites

| Suite | Contents | Requires DB |
|-------|----------|-------------|
| `unit` | Pure unit tests | No |
| `integration` | DI, Doctrine mapping, Form Types | Yes |
| `functional` | (future) | Yes |
| Behat (`@managing_hipay_accounts&&@ui`) | Admin Account CRUD | Yes + web server |

## CI/CD

Dedicated targets orchestrate everything for a pipeline:

```bash
make ci.install       # composer install + test.db.init
make ci.test          # lint + PHPUnit
make ci.test.lint     # composer validate, phpstan, ecs, yaml, twig
make ci.test.phpunit  # all PHPUnit suites
make ci.test.behat    # starts server, runs Behat, stops server
```

## Flow Diagram

```
.php-version (8.2) ──→ symfony php/console (correct PHP version)
                              │
                    APP_ENV=test (forced)
                              │
                    ┌─────────┴──────────┐
                    │   when@test:        │
                    │   DATABASE_TEST_URL │ ← .env.test
                    │   (not DATABASE_URL)│
                    └─────────┬──────────┘
                              │
              ┌───────────────┼───────────────┐
              │               │               │
        test.db.init    PHPUnit (8.2)    Behat (8.2)
              │          force=true      BrowserKit
      sylius_hipay_     rollback tx      purge tables
      plugin_test
```
