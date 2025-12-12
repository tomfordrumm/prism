[INSERT PROJECT NAME] uses Laravel 12 with Inertia and Vue 3/TypeScript (PrimeVue + Tailwind) for the frontend. The backend follows MVC with thin controllers and dedicated service/action layers. Multi-tenancy is enforced by tenant-scoped models and explicit tenant checks. LLM calls are centralized behind `App\Services\Llm\LlmService`, which routes to provider clients and returns a unified DTO for content and token usage. Chain execution is modeled as ordered `ChainNode` steps; `App\Services\Runs\RunStepRunner` builds messages, invokes the LLM, validates outputs, records run steps, and can stop on validation failure. Prompt templating uses immutable `PromptVersion` snapshots; variable resolution lives in `MessageBuilder`/`VariableResolver`.

Folder responsibilities
- `app/Http/Controllers`: HTTP endpoints; validate with FormRequests, enforce tenant/project ownership, delegate to services.
- `app/Actions`: Orchestrators for multi-step domain workflows (e.g., running chains) used by controllers/jobs.
- `app/Services`: Domain logic organized by subdomain (Chains, Runs, Llm, Prompts, ProviderCredentials, Feedback); no UI concerns.
- `app/Models`: Eloquent entities with `tenant_id` and relationships; queries are scoped to the current tenant.
- `app/Jobs`: Queueable tasks when/if async is needed.
- `resources/js`: Frontend (Inertia pages, layouts, components, composables, routes, types); TypeScript-first with `<script setup>`.
- `routes`: Web/Inertia routing using Laravel resource and nested project routes.
- `config`, `database`, `tests`: Environment, migrations/factories/seeders, and feature/unit tests.
