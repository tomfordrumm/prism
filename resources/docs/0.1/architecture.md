# Architecture

PRISM uses Laravel 12 with Inertia and Vue 3/TypeScript (PrimeVue + Tailwind) for the frontend. The backend follows MVC with thin controllers and dedicated service/action layers. Multi-tenancy is enforced by tenant-scoped models and explicit tenant checks.

- [Core Execution Paths](#core-execution-paths)
- [LLM Integration](#llm-integration)
- [Prompt Templating](#prompt-templating)
- [Folder Responsibilities](#folder-responsibilities)

## Core Execution Paths

### Chains (multi-step LLM workflows)

- Each ChainNode is a single LLM call with a clean message history.
- Inputs/outputs flow explicitly through variables between steps.
- `App\Services\Runs\RunStepRunner` builds messages, invokes the LLM, validates outputs, records run steps, and can stop on validation failure.

### Agents (chatbots)

- Agents are stateful chatbots built around a system prompt and a provider/model configuration.
- Requests go through `App\Services\Agents\AgentChatService`, which uses `App\Services\Llm\LlmService` and records token usage and analytics per agent.

### Prompt Improvement

- Improvements and prompt conversations are handled in:
  - `App\Services\Prompts\PromptConversationLlmService`
  - `App\Services\Prompts\PromptIdeaImproverService`
  - `App\Services\Feedback\PromptImproverService`

## LLM Integration

- All provider calls go through `App\Services\Llm\LlmService`.
- Provider-specific clients live under `App\Services\Llm` and return unified DTOs for content and usage.
- Controllers and Vue components must never call providers directly.

## Prompt Templating

- Prompt text is immutable via `PromptVersion` snapshots.
- Message building and variable resolution live in:
  - `App\Services\Runs\MessageBuilder`
  - `App\Services\Runs\VariableResolver`

## Folder Responsibilities

- `app/Http/Controllers`: HTTP endpoints; validate with FormRequests, enforce tenant/project ownership, delegate to services.
- `app/Actions`: Orchestrators for multi-step domain workflows (e.g., running chains) used by controllers/jobs.
- `app/Services`: Domain logic organized by subdomain (Agents, Chains, Runs, Llm, Prompts, ProviderCredentials, Feedback); no UI concerns.
- `app/Models`: Eloquent entities with `tenant_id` and relationships; queries are scoped to the current tenant.
- `app/Jobs`: Queueable tasks when/if async is needed.
- `resources/js`: Frontend (Inertia pages, layouts, components, composables, routes, types); TypeScript-first with `<script setup>`.
- `routes`: Web/Inertia routing using Laravel resource and nested project routes.
- `config`, `database`, `tests`: Environment, migrations/factories/seeders, and feature/unit tests.
