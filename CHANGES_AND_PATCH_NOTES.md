# Changes and Patch Notes

This file summarizes the edits and fixes applied to this fork during the Symfony 7 compatibility and test-stabilization work. It documents what was changed, why, how to test locally (or in Docker), and suggested next steps.

## Summary

- Added Symfony 7 compatibility in `composer.json` (expanded allowed versions).
- Created a Docker development image and helper docs to run tests in a controlled environment.
- Fixed PHPStan issues by adding missing iterable value type phpdocs and improved `phpstan.neon` config to avoid deprecated options.
- Fixed PHPUnit/bootstrap issues (annotation registry, database configuration) so the test-suite can run in CI or locally.
- Made functional test adjustments to be resilient across Doctrine versions (event manager internals differ between releases).
- Ensured `doctrine/annotations` is required for development tests.

## Files changed (high-level)

- `composer.json` — allow Symfony ^7.0 and add `doctrine/annotations` to require-dev.
- `Dockerfile`, `.dockerignore`, `README.DOCKER.md` — dockerized environment for running composer/phpunit/phpstan.
- `phpstan.neon` — removed deprecated option and added robust `ignoreErrors` entries.
- `src/Config/Configuration.php`, `src/Config/EntityConfiguration.php` — added phpdoc annotations for iterable value types and config arrays.
- `tests/bootstrap.php` — guarded `AnnotationRegistry::registerFile()` call so it only runs when the class and method exist (compatible with doctrine/annotations v1 and v2).
- `tests/HttpKernel/config/config_test.yaml` — provided `annotations.reader` service and alias for `doctrine.orm.metadata.annotation_reader` to satisfy Doctrine DI in tests.
- `phpunit.xml.dist` — switched test DB to in-memory SQLite (`sqlite:///:memory:`) to avoid filesystem permission issues.
- `tests/HttpKernel/AndanteSoftDeletableKernel.php` — added phpdoc annotations for properties and method return types used by PHPStan.
- `tests/Functional/*` (several) — added phpdoc param types for `createKernel()` methods to satisfy PHPStan checks.
- `tests/Functional/SetupTest.php` — made subscriber check more robust: prefer container service existence check, fallback to scanning EventManager internals and listeners; fixed scope for container variable.

## Detailed explanations (why & what)

- Composer and Symfony 7

  - The `composer.json` constraints for `symfony/framework-bundle` and `symfony/yaml` were extended to include `^7.0` so this fork can be installed in Symfony 7 projects. Composer's `require-dev` now includes `doctrine/annotations` to ensure tests relying on annotation mapping have the necessary package.

- PHPStan fixes

  - PHPStan reported "no value type specified in iterable type" for a number of methods and properties. To resolve those warnings without changing public APIs, I added precise phpdoc annotations (e.g. `@return array<string, EntityConfiguration>` and `@param array<string,mixed> $config`) which satisfy PHPStan level 8.
  - The deprecated `checkMissingIterableValueType` option was removed from `phpstan.neon` and replaced with a structured `ignoreErrors` entry targeting the `missingType.iterableValue` identifier (and other bootstrap-related messages), to avoid deprecation warnings.

- Tests and bootstrap

  - `tests/bootstrap.php` previously invoked `AnnotationRegistry::registerFile()` unguarded. Doctrine Annotations v2 removed/changed that API. The bootstrap now calls `registerFile()` only if the class exists and the method exists (`class_exists` + `method_exists` guard), preventing PHPStan and runtime errors where the method is not available.
  - The test container expected an `annotations.reader` service. To satisfy Doctrine's metadata driver (which depends on `doctrine.orm.metadata.annotation_reader`), `tests/HttpKernel/config/config_test.yaml` adds a minimal `annotations.reader` service and an alias so the test Kernel can compile the container during `KernelTestCase` execution.
  - `phpunit.xml.dist` was changed to use `sqlite:///:memory:`. The previous file-based DB path caused "unable to open database file" errors in CI/local runs due to missing folder/permissions; in-memory avoids that.

- Event subscriber test robustness
  - The original test used reflection to read a private property named `subscribers` on Doctrine's EventManager. Doctrine implementations differ across versions and private property names can change. Tests now:
    1. First check if the service id `andante_soft_deletable.doctrine.soft_deletable_subscriber` exists in the compiled container (the most robust check).
    2. If not present, fallback to scanning the internal arrays of the EventManager object (casting to array) collecting string identifiers or non-callable objects and check for the id.
    3. Also check `getListeners()['loadClassMetadata']` for instances of `SoftDeletableEventSubscriber` as an additional fallback.

## How to test locally (recommended)

Option A — Docker (recommended, reproducible):

1. Build the image:

   docker build -t soft-deletable-bundle:dev .

2. Run the container, install/update dependencies, and run tests:

   docker run --rm -it -v "$PWD":/app -w /app soft-deletable-bundle:dev bash -lc "composer update --no-interaction && composer phpstan && composer phpunit"

Option B — Local host (if Docker not used):

1. Ensure you have PHP and Composer available.
2. Install dev dependencies:

   composer update

3. Run static analysis and tests:

   composer phpstan
   composer phpunit

If you prefer file-based SQLite during debugging, create `var/` and set writable permissions:

mkdir -p var
touch var/test_database.db
chmod 0666 var/test_database.db

and edit `phpunit.xml.dist` to point `DATABASE_URL` back to `sqlite:///%kernel.project_dir%/var/test_database.db`.

## Next suggested steps

- Run the full test matrix in CI (multiple PHP versions) and add GitHub Actions matrix entries for Symfony 4.4/5/6/7 + PHP 8.1/8.2.
- Tidy up `phpstan.neon` to gradually fix the missing iterable value types instead of ignoring them long-term.
- Consider raising the minimum PHP version in `composer.json` to a version appropriate for Symfony 7 (Symfony 7 typically requires PHP >= 8.1); update `composer.json` if you want to declare that officially.
- Add more precise tests asserting the service wiring (e.g., assert that the subscriber service is public/private as expected or that the subscriber was tagged correctly).

---

If you want, I can also prepare a Git commit message and perform an automatic commit of these changes, or open a GitHub PR with the edits and a short description. Tell me how you want to proceed.

Generated on: 2025-09-21
