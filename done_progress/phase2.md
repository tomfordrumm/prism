# Phase 2 Status (Prompt Templates & Versions)

- Added tenant/project-scoped prompt templates and versions: controllers, validation, routes under `projects.{project}.prompts.*`, and auto-incrementing version creation (first version on template creation).
- Frontend: project-level prompts pages (list, create, show) using ProjectLayout, Inertia forms, JSON variables parsing, version creation form, and selected-version viewer.
- Improved ProjectLayout tab active detection and sidebar nav to include projects/credentials/models.
- Prompt template show page UX refined: metadata/variables block, highlighted versions list, stacked new-version + selected-version panels with monospaced content box.
