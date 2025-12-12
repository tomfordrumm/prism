Gold-standard flow: add a new tenant-scoped CRUD feature (model + UI + service logic).

1) Model & migration  
- Create a migration with `tenant_id`, foreign keys, indexes, timestamps, and domain fields.  
- Add an Eloquent model in `app/Models` with fillable/guarded fields, relationships, and tenant scoping helpers if needed.

2) Requests & routes  
- Add FormRequest classes in `app/Http/Requests/<Feature>/` for store/update validation (types, JSON structures, tenant ownership checks).  
- Register nested resource routes in `routes/web.php` (often under a Project) using Laravel resource routes to get index/create/store/show/edit/update/destroy.

3) Controller  
- Create a controller in `app/Http/Controllers` with constructor-injected services.  
- Each action: assert tenant/project ownership, use FormRequest for inputs, delegate to a service/action, and return Inertia views or redirects via route helpers.

4) Services/Actions  
- If logic exceeds basic CRUD, add a service/action in `app/Services/<Domain>` or `app/Actions/<Domain>` to encapsulate workflows (e.g., snapshot loading, validation, external calls).  
- Keep LLM interactions routed through `LlmService`; log failures without leaking secrets.

5) Inertia pages & routes  
- Add Inertia page components under `resources/js/pages/...` (e.g., `projects/<feature>/Index.vue`, `Create.vue`, `Show.vue`).  
- Use `<script setup lang="ts">`, typed props, `useForm` for submissions, and route helpers from `resources/js/routes` for navigation.  
- Add reusable components to `resources/js/components` and shared types to `resources/js/types` if needed.

6) Frontend data plumbing  
- Ensure controllers pass lean props (IDs, names, derived counts) instead of full models.  
- In Vue, keep state local, prefer computed lookups, and wire PrimeVue/Tailwind components for layout.

7) Tests  
- Add feature tests in `tests/Feature/<Feature>Test.php` covering tenant isolation, validation errors, and happy paths.  
- Add unit tests for complex services (e.g., variable resolution, schema validation) in `tests/Unit`.

8) Verify  
- Run `php artisan test` and `npm run build` (or `npm run lint && npm run build`) to ensure backend/frontend integrity.
