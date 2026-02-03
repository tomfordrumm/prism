# Conventions

These are the non-negotiables that keep the codebase safe and consistent.

## Contents

- [Tenancy](#tenancy)
- [Controllers vs Services](#controllers-vs-services)
- [LLM Access](#llm-access)
- [Prompt Versioning](#prompt-versioning)
- [Frontend](#frontend)

## Tenancy

- Every tenant-owned query must be scoped by `tenant_id`.
- Controllers must reject cross-tenant access explicitly.

## Controllers vs Services

- Controllers validate with FormRequests and enforce tenant/project ownership.
- Business logic lives in Actions/Services (e.g., `RunChainAction`, `ChainViewService`).

## LLM Access

- All provider calls go through `LlmService`.
- Provider clients (e.g., `OpenAiProviderClient`) return `LlmResponseDto`.
- Never call providers directly from controllers or Vue.

## Prompt Versioning

- Prompt edits always create new `PromptVersion` records.
- Message building and variable resolution go through:
  - `App\Services\Runs\MessageBuilder`
  - `App\Services\Runs\VariableResolver`
- Schema validation is handled by `SchemaValidator`; validation errors are recorded.

## Frontend

- Vue components use `<script setup lang="ts">` and typed props.
- Use Inertia forms/router for CRUD.
- Use PrimeVue + Tailwind for layout.
- Avoid `any`; keep state local or in composables.
