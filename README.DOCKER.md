Setup Docker dev environment for tests

Build the image (run from project root):

```bash
docker build -t soft-deletable-bundle:dev .
```

Run an interactive shell inside the container (mount current dir to /app):

```bash
docker run --rm -it -v "$PWD":/app -w /app soft-deletable-bundle:dev
```

Common commands inside the container:

- Install/update dependencies:

  ```bash
  composer update
  ```

- Run tests:

  ```bash
  composer phpunit
  ```

- Run static analysis:
  ```bash
  composer phpstan
  ```

Notes:

- This image uses PHP 8.2. Adjust `FROM` in `Dockerfile` if you need a different PHP version for other matrix entries.
- You might need to install additional PHP extensions depending on your environment and the tests (e.g. pdo_sqlite). Add them to the Dockerfile if required.
