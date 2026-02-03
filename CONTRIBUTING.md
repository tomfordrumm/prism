# Contributing to PRISM

Thanks for taking the time to contribute. This project aims to keep LLM prompt workflows predictable, debuggable, and multi-tenant safe.

## Development Setup

Prerequisites:
- Docker + Docker Compose

Start the stack:
```bash
docker compose up --build
```

This will install dependencies, run migrations, and start the app server and Vite dev server.

## Code Style

Backend:
- PSR-12, Laravel conventions.
- Run formatter: `vendor/bin/pint --dirty`.

Frontend:
- ESLint + Prettier.
- Run: `npm run lint` and `npm run format:check`.

## Tests

Use PHPUnit and run the smallest relevant test set:
```bash
php artisan test --compact tests/Feature/SomeTest.php
```

If you change prompt/chain/run behavior, add or update tests for:
- happy paths
- failure paths
- edge cases

## Pull Requests

- Keep PRs focused and small.
- Explain the why and the user impact.
- Avoid breaking tenant scoping or prompt version immutability.
- Never include secrets or real API keys.

## Need Help?

Open an issue with reproduction steps, expected behavior, and logs (redact secrets).
