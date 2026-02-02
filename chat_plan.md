# Chat Plan

## Goal
Unify the "Idea → Prompt" and "Run Feedback" flows into a full chat experience with message history, persistent threads, and LLM-backed replies.

## Scope
- Backend: new conversation + message storage, endpoints, LLM integration with history.
- Frontend: shared chat UI component for both dashboard and run feedback.
- Multi-tenancy: all queries scoped by tenant and project.

## Steps
1. **Design the data model**
   - Add `prompt_conversations` table:
     - `id`, `tenant_id`, `project_id`, `type`, `run_id`, `run_step_id`, `target_prompt_version_id`,
       `status`, `created_at`, `updated_at`.
   - Add `prompt_messages` table:
     - `id`, `conversation_id`, `role`, `content`, `meta` (json), `created_at`, `updated_at`.
   - Add indexes/foreign keys on `tenant_id`, `project_id`, `conversation_id`, `run_id`, `run_step_id`.

2. **Create models and relationships**
   - `PromptConversation` hasMany `PromptMessage`.
   - `PromptConversation` belongsTo `Project`, `Run`, `RunStep` (optional).
   - `PromptMessage` belongsTo `PromptConversation`.

3. **Build conversation services**
   - Service to create or fetch an active conversation for a given context.
   - Service to append messages and fetch history.
   - Enforce tenant + project scoping on all lookups.

4. **LLM integration**
   - Add method to build system prompts per conversation type:
     - `idea`: reuse `idea_to_prompt_system` + user prompt template.
     - `run_feedback`: include prompt text, output, and user feedback.
   - Convert stored messages into provider messages.
   - Parse response JSON (analysis + improved_prompt); store:
     - Assistant message content = analysis (if present).
     - Suggested prompt in `meta` or in a dedicated field.

5. **API endpoints**
   - `POST /projects/{project}/prompt-conversations`
     - Create or fetch a conversation for the given context.
   - `GET /prompt-conversations/{conversation}`
     - Fetch conversation + messages.
   - `POST /prompt-conversations/{conversation}/messages`
     - Save user message, call LLM, save assistant message(s).

6. **Frontend chat component**
   - Build a shared `<PromptChat>` component:
     - message list, typing state, input bar pinned bottom.
     - optional right-side diff panel when `improved_prompt` is available.
   - Use in Dashboard (Idea → Prompt).
   - Use in Run Feedback modal (context: run step + prompt version).

7. **Migrate existing flows**
   - Dashboard: replace direct "prompt idea" call with chat endpoints.
   - Run Feedback: reuse same chat component and store messages in the conversation.
   - Preserve current ability to create new prompt version from suggestion.

8. **Testing**
   - Feature tests for:
     - creating conversations,
     - posting messages,
     - tenant isolation.
   - Unit test for message history builder (correct order and context).

## Notes
- Keep message storage minimal (no secrets).
- Truncate large responses before logging.
- Support later: continue conversations with multiple turns.
