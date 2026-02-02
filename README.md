# PRISM — Prompt IDE for building, testing, and improving LLM prompts

PRISM is an open-source Prompt IDE for prompt engineers and product teams. It helps you design prompt templates, run them through chains of LLM calls, test them across datasets, and iteratively improve them with AI-assisted feedback.

## Why PRISM

**Problem:** Prompt development is hard to version, test, and debug at scale.  
**Solution:** PRISM gives you a structured workspace with templates, chains, datasets, runs, and improvement workflows that make LLM systems reproducible and debuggable.

## Features

- Prompt templates with immutable versions and variable extraction.
- Chains of LLM calls with per-step models, params, and schema validation.
- Prompt runs without chains for quick iteration.
- Dataset test cases with batch runs.
- Run tracing: prompts, responses, tokens, timing, validation errors.
- Feedback and AI-assisted prompt improvement in a guided chat flow.
- Multi-tenant workspaces with strict tenant isolation.
- Provider credentials for OpenAI, Anthropic, Google Gemini, and OpenRouter.

## Architecture & Stack

- Backend: Laravel 12, PHP 8.2+
- Frontend: Inertia.js + Vue 3 + Vite
- UI: TailwindCSS + PrimeVue
- Queue/Cache in Docker: Redis
- Database in Docker: SQLite (default)
- LLM integration: centralized service layer with provider-specific clients

## Quick Start (Docker-first)

Prerequisites: Docker + Docker Compose.

```bash
docker compose up --build
```

What this does:
- Copies `.env.docker` to `.env` if missing.
- Installs PHP and JS dependencies.
- Creates SQLite DB and runs migrations.
- Starts the app server, Vite dev server, Redis, and queue worker.

Open:
- App: http://localhost:8000
- Vite HMR: http://localhost:5173

## Configuration: LLM Credentials and Improvement Defaults

PRISM requires provider credentials to run prompts and to generate improvements.

1) Add provider credentials in the UI (OpenAI, Anthropic, Google, OpenRouter).  
2) Set **System Settings → Improvement model** to choose the default provider + model for:
   - prompt improvement chats
   - feedback analysis

Optional environment overrides (fallbacks):
- `LLM_JUDGE_CREDENTIAL_ID` — provider credential ID for improvements
- `LLM_JUDGE_MODEL` — model name (default: `gemini-1.5-flash`)
- `LLM_JUDGE_PARAMS` — JSON string of model params

If no default improvement model is set, improvement workflows will fail.

## Contributing

Development setup:
- Use Docker as described above.
- The app uses Redis + SQLite by default in Docker; no extra services required.

Code style:
- PHP: PSR-12 (Laravel conventions).
- Frontend: ESLint + Prettier.

Useful commands:
```bash
composer test
npm run lint
npm run format:check
```

Issues and discussions:
- Please use [Issues](../../issues) for bug reports and feature requests.

## License

MIT — see `composer.json`.
