AGENTS.md — Prompt IDE Platform

1. Project Overview & Business Context

This project is an internal Prompt IDE used to design, store and test LLM prompts and chains of LLM calls.

Core idea:
	•	User (developer / prompt engineer) creates Projects for their LLM-based tools.
	•	Inside a project they define:
	•	Prompt Templates (reusable text building blocks with variables).
	•	Chains (linear sequences of LLM calls, each step acting as an “agent”).
	•	Datasets & Test Cases (sets of inputs to test chains).
	•	Runs & Run Steps (executions of chains with real inputs).
	•	User connects their own LLM provider credentials (OpenAI, Anthropic, Google, etc.) and uses different models for different steps.
	•	For any Run, user can see prompts, responses, token usage, validation errors and give feedback.
	•	User can request LLM suggestions to improve prompts based on failed/unsatisfactory runs and create new prompt versions from those suggestions.

The app is not a public-facing product. It’s a tool to:
	•	Debug and iterate on prompts.
	•	Version and compare prompts.
	•	Keep LLM integrations structured and maintainable.

Multi-tenancy: multiple organizations (tenants) use the system; data is isolated per tenant.

⸻

2. Business Domains & Core Entities

2.1. Tenants & Users
	•	Tenant — organization / workspace.
	•	User — person with an account; can belong to one or more tenants.
	•	In V1, all members of a tenant share access to tenant data (no fine-grained permissions yet).

Key rules:
	•	Every business entity that belongs to a tenant must be scoped by tenant_id.
	•	Cross-tenant data access is forbidden.

⸻

2.2. Projects
	•	Logical container inside a tenant:
	•	groups prompt templates, chains, datasets, runs.
	•	Represent a real LLM-based product/feature the user is designing (e.g. “Quiz bot”, “Onboarding assistant”).

⸻

2.3. LLM Provider Credentials
	•	ProviderCredentials
	•	store encrypted API keys/config per tenant.
	•	contain provider type (openai, anthropic, google).
	•	can have multiple credentials per provider (e.g. “OpenAI Prod”, “OpenAI Sandbox”).

Business rule:
	•	User manages provider credentials via UI.
	•	Keys are stored encrypted and never logged.
	•	All LLM calls must go through a centralized integration layer that selects credential + model config defined on chain nodes.

⸻

2.4. Prompt Templates & Versions
	•	PromptTemplate
	•	belongs to a Project (and Tenant).
	•	is an abstract text template with variables, not tied to a specific LLM role (system/user) by itself.
	•	examples: "quiz_expand_topic_system", "quiz_generate_question_user".
	•	variables are derived automatically from {{ variable }} placeholders when saving PromptVersions.
	•	PromptVersion
	•	immutable snapshot of template content.
	•	has version number and changelog.
	•	all changes create a new version; previous versions are preserved.

Business rules:
	•	Templates are reused across chain steps.
	•	Role (system/user/assistant) is defined at the chain node level, not at template level.
	•	To change prompt text: always create a new version; do not edit existing content in-place.

⸻

2.5. Chains & Chain Nodes
	•	Chain
	•	belongs to a project.
	•	represents a linear sequence of LLM steps to achieve a business goal (e.g. expand topic → generate question → validate).
	•	in V1: no branching/conditional flows, only ordered steps.
	•	ChainNode (Step)
	•	belongs to a chain.
	•	represents a single LLM call (“agent”).
	•	defines:
	•	which provider credential is used,
	•	model name (string) and model params (JSON),
	•	how messages (system/user/assistant) are built from prompt versions.
	•	optional output JSON Schema for validation.
	•	whether to stop chain on validation error.
	•	messages_config is a JSON structure that maps LLM roles to PromptVersions.

Example conceptual config:

[
  { "role": "system", "prompt_version_id": 10 },
  { "role": "user",   "prompt_version_id": 11 }
]

Key conceptual rule:

Each ChainNode = separate LLM call with a clean message history.
The output of previous steps is passed forward explicitly via variables, not via shared chat history.

⸻

2.6. Datasets & Test Cases
	•	Dataset
	•	groups test cases for a project.
	•	each dataset corresponds to a testing scenario (e.g. “React quiz topics”, “Russian language questions”).
	•	TestCase
	•	contains input_variables (JSON object) used as inputs for chain run.
	•	may contain expected_output (for future golden answers).
	•	used to mass-run a chain and inspect results across multiple inputs.

⸻

2.7. Runs & Run Steps
	•	Run
	•	one execution of a chain (with a set of inputs).
	•	may be triggered manually with a single input, or via dataset (one Run per test case).
	•	stores:
	•	chain snapshot (structure + versions at the moment of run),
	•	input,
	•	overall status and metrics.
	•	RunStep
	•	one LLM call within a Run.
	•	stores:
	•	actual request payload (messages, params),
	•	raw response,
	•	parsed output (if JSON),
	•	validation errors,
	•	token usage and timing.

Business rules:
	•	Runs are used to debug & inspect behavior of prompts across different versions.
	•	Chain snapshot ensures reproducibility: later changes to chain do not affect historical runs.

⸻

2.8. Feedback & Prompt Improvement
	•	Feedback
	•	can be manual (user rating + comment) or auto (LLM suggestion).
	•	links to specific Run/RunStep.
	•	stores suggested_prompt_content when suggestion is generated by LLM-judge model.

Flow:
	1.	User inspects a RunStep and is not satisfied with the answer.
	2.	User writes what’s wrong (e.g. “too generic, not enough specificity on React hooks”).
	3.	System calls judge model (e.g. Gemini) with:
	•	current prompt,
	•	input variables,
	•	obtained answer,
	•	user’s comment.
	4.	Judge model returns improved prompt text suggestion.
	5.	User can create a new PromptVersion from this suggestion.

⸻

3. LLM Integration Principles
	•	All LLM calls must go through a single abstraction layer (e.g. LlmClient or similar service):
	•	selects provider credential and model,
	•	handles request building,
	•	parses responses,
	•	logs metrics without leaking secrets.
	•	Never call provider APIs directly from Controllers or Vue components.
	•	Seed / determinism:
	•	If provider allows, support seeding for more reproducible runs.
	•	Otherwise, keep flows designed to be tolerant to non-determinism (e.g. rely on patterns and validation).
	•	Do not log:
	•	API keys.
	•	Full request/response that might contain secrets (truncate large payloads, clean sensitive fields if needed).

⸻

4. Architecture & Stack Guidelines

4.1. Global Architectural Principles
	•	Keep clear separation:
	•	HTTP layer: Controllers + Inertia endpoints.
	•	Domain / Application logic: Services, Actions, domain classes.
	•	Persistence: Eloquent models, queries, scopes.
	•	Multi-tenancy is enforced at the data access layer, not only on the UI.
	•	Focus on:
	•	Readable, maintainable code.
	•	Strong domain boundaries (tenants, projects, prompts, chains, runs).
	•	Clear naming and explicitness over “magic”.

⸻

5. Backend (Laravel) Best Practices

5.1. General
	•	Use PSR-12 code style.
	•	Follow Laravel conventions for:
	•	Controllers,
	•	Eloquent models,
	•	Resource routes,
	•	Request validation.
	•	Use dependency injection in controllers and services.

5.2. Controllers
	•	Controllers should be thin:
	•	Request validation via FormRequest classes.
	•	Business logic delegated to dedicated service/action classes where complexity grows.
	•	Use resource controllers for CRUD where appropriate (Projects, PromptTemplates, Datasets, etc.).

5.3. Eloquent Models & Tenancy
	•	Every multi-tenant model must have tenant_id.
	•	Provide global or local scopes to enforce tenant filtering, e.g.:
	•	$query->where('tenant_id', currentTenantId()).
	•	Never query unscoped multi-tenant tables without explicitly scoping to the current tenant.

5.4. Validation & FormRequests
	•	All non-trivial requests use FormRequest classes.
	•	Validate:
	•	JSON structures (for messages_config, variables, output_schema, input_variables).
	•	that referenced IDs belong to the same tenant and project where applicable.

5.5. Database Migrations
	•	Use foreign keys and indexes for all relations.
	•	Use soft deletes only where business domain requires it (e.g. maybe not needed for Runs / RunSteps).

5.6. LLM Service Layer
	•	Implement a LlmClient (or similar) to abstract provider-specific differences:
	•	method inputs: model identifier, messages[], params.
	•	return: unified response (content, usage, metadata).
	•	All provider-specific logic is encapsulated here:
	•	OpenAI vs Anthropic vs Google.
	•	Authorization headers, endpoints, body shape.

⸻

6. Frontend (Inertia + Vue 3) Best Practices

6.1. Inertia Usage
	•	Use Inertia for all page navigation and server communication for page-level data:
	•	Inertia.get, Inertia.post, Inertia.put, etc.
	•	Avoid custom fetch/axios for operations that can be expressed as Inertia form submissions.
	•	Use Inertia forms for create/update actions (status handling, validation errors).

Do NOT:
	•	Build parallel “ad-hoc” API layer with raw fetch calls for standard CRUD flows.
	•	Mix Inertia responses with custom JSON endpoints for the same resource without clear reason.

6.2. Vue 3 Components
	•	Use <script setup> with composition API.
	•	Keep components small and focused:
	•	Page components (screens) for top-level views.
	•	Reusable components (forms, lists, modals) for repeated UI.

6.3. Type Safety
	•	If project uses TypeScript:
	•	Do not use any.
	•	Use proper interfaces/types for domain entities (PromptTemplate, ChainNode, Run, etc.).
	•	Use unknown + narrowing instead of any when needed.
	•	If project uses plain JS:
	•	Use JSDoc annotations to document shapes of props, responses, and configs.

6.4. State Management
	•	Prefer local component state and Inertia props.
	•	Use dedicated composables (useRuns, useChains, etc.) if state sharing is needed across multiple components.
	•	Avoid global mutable singletons; if global state is needed, introduce a clear pattern (Pinia or simple composable) with explicit usage.

⸻

7. Tailwind & UI
	•	Use TailwindCSS as the primary styling mechanism.
	•	Use PrimeVue as the primary UI-components library.
	•	Keep UI minimalistic and functional:
	•	Clear tables, cards and forms.
	•	Collapsible sections for large JSON/text (prompts, responses).
	•	Do not introduce additional CSS frameworks (e.g. Bootstrap, Vuetify) without architectural decision.

Do NOT:
	•	Write large inline styles or custom CSS if Tailwind utility classes suffice.
	•	Create heavy “theme systems” or styling abstractions in V1.

⸻

8. Testing & Quality
	•	Introduce feature tests for critical flows:
	•	creating/updating prompt templates,
	•	running chains,
	•	ensuring tenant isolation.
	•	Unit-test complex domain logic (e.g. building messages from templates and variables).
	•	Avoid mocking framework internals excessively; test behavior from the user’s perspective where possible.

⸻

9. Security & Data Protection
	•	All provider API keys must be stored encrypted in DB.
	•	Never expose provider credentials to the frontend.
	•	Never log:
	•	raw API keys,
	•	entire request bodies to LLM providers if they may contain secrets.
	•	Consider truncation or redaction in logs for large or sensitive content.
	•	Multi-tenancy:
	•	all queries must be constrained by tenant.
	•	controllers must never trust client-provided tenant IDs; use current tenant from auth context.

⸻

10. Performance & Observability
	•	For V1 runs can be synchronous, but business logic must be extracted into services to easily move to queues later.
	•	Collect and store:
	•	duration,
	•	token usage,
	•	per-step timing.
	•	Use this data for basic performance analysis and as groundwork for future dashboards.

⸻

11. Anti-Patterns & Things NOT To Do
	1.	No any (in TS).
	•	Use proper typing or unknown with narrowing.
	2.	No direct LLM calls from Controllers or Vue.
	•	All calls must go through centralized LLM service.
	3.	No unscoped multi-tenant queries.
	•	Never query all entities of a type without tenant_id filter.
	4.	No editing PromptVersion content in place.
	•	Versions are immutable. Only create new versions.
	5.	No mix of Inertia and arbitrary fetch for the same flows.
	•	Keep navigation & CRUD consistent via Inertia.
	6.	No extra CSS frameworks.
	•	Use Tailwind only (in V1).
	7.	No silent swallowing of validation errors.
	•	If JSON parsing or schema validation fails for a step, store errors in validation_errors and mark status appropriately.
	8.	No direct manipulation of chain snapshot in runs.
	•	Snapshot is immutable record of what was executed.

⸻

12. Goal for LLM Agents

When writing or modifying code in this project, always aim for:
	•	Consistency with domain model:
	•	Tenants → Projects → Prompts/Chains → Runs.
	•	Predictable and debuggable LLM flows:
	•	Each ChainNode is a clear, independent step.
	•	Inputs and outputs are explicit and traceable.
	•	Maintainability:
	•	Clean separation of concerns (HTTP / domain / persistence / integrations).
	•	Strong typing (where applicable) and clear naming.

Your objective as an LLM agent is to:
	•	Respect these rules,
	•	Preserve and strengthen the architecture,
	•	Produce code that is easy for human developers to understand, extend and debug.
