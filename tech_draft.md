Техническое задание: Prompt IDE v1

1. Цель продукта

Создать веб-приложение, позволяющее пользователю создавать, хранить и тестировать промпты и цепочки вызовов LLM, анализировать качество ответов, собирать фидбек и улучшать промпты на базе предложений модели. Приложение должно поддерживать многопользовательский доступ и изоляцию данных между организациями (multi-tenant).

Приложение служит инструментом для локальной разработки и отладки промптов, а не полноценным LLM-продуктом для конечных пользователей.

⸻

2. Технический стек
	•	Backend: Laravel 12
	•	Frontend: Inertia.js + Vue 3 + Vite
	•	UI: TailwindCSS
	•	DB: PostgreSQL (предпочтительно) / MySQL
	•	Обмен с LLM: через интеграционные сервисы (HTTP API)
	•	Хранение ключей: в базе данных (зашифрованно)

⸻

3. Multi-Tenancy и пользователи

3.1. Модель tenancy
	•	Каждый пользователь принадлежит к одному или нескольким tenant’ам.
	•	Все данные (проекты, шаблоны промптов, цепочки, датасеты, креды провайдеров и т.д.) привязаны к tenant’у.
	•	Сущности не пересекаются между tenant’ами.

3.2. Права доступа (V1)
	•	Доступная модель: Owner → Members
	•	Все участники tenant’а имеют доступ ко всем данным внутри него.
	•	В V1 нет granular-ролей и permissions — либо доступ есть, либо нет.

⸻

4. Управление LLM-провайдерами и ключами

Пользователь создаёт учетные данные LLM через UI.

4.1. ProviderCredentials
	•	tenant_id
	•	provider (enum: openai, anthropic, google)
	•	name (string)
	•	encrypted_api_key
	•	metadata (json, nullable — например baseUrl)
	•	created_at

4.2. Конфигурация модели на шаге цепочки
	•	Отдельной сущности LLM Models нет.
	•	Каждый ChainNode хранит:
		• provider_credential_id
		• model_name (string, например “gpt-4.1”)
		• model_params (jsonb: temperature, max_tokens, top_p, penalty и т.д.)

⸻

5. Основные сущности домена

5.1. Projects
	•	tenant_id
	•	name
	•	description

Проект — контейнер для цепочек, промптов и датасетов.

⸻

5.2. Prompt Templates

Шаблон промпта — текст с плейсхолдерами, не привязан к роли system/user.

prompt_templates
- id
- tenant_id
- project_id
- name
- description
- variables (jsonb nullable)
  // [{name:"topic", type:"string", description:"Тема квиза"}, ...]
  // наполняется автоматически из {{ variable }} плейсхолдеров в тексте версии
- created_at, updated_at

PromptVersions (immutable)
Каждая версия хранит текст без изменений после создания.

prompt_versions
- id
- prompt_template_id
- version (int)
- content (text)
- changelog (text nullable)
- created_by
- created_at

Только создание новых версий. Откат = выбор предыдущей версии.

⸻

5.3. Chains (цепочки)

Цепочка — последовательность шагов. Каждый шаг — отдельный LLM вызов.

chains
- id
- tenant_id
- project_id
- name
- description
- is_active (bool)
- created_at, updated_at


⸻

5.4. Chain Nodes (шаги цепочки)

Каждый шаг — отдельный агент.
System/user/assistant роли определяются на уровне узла.

chain_nodes
- id
- chain_id
- provider_credential_id
- model_name (string)
- model_params (jsonb)
- name
- order_index
- messages_config (jsonb)
  // Пример:
  // [
  //   {role:"system", prompt_version_id: 3},
  //   {role:"user",   prompt_version_id: 5}
  // ]
- output_schema (jsonb nullable)
- stop_on_validation_error (bool)
- created_at, updated_at

Правила:
	•	messages_config — это конфигурация message→promptVersion→role.
	•	При Run модель получает чистый контекст:
	•	system(message1)
	•	user(message2)
	•	Результаты прошлых шагов передаются через подстановку переменных или в user-сообщение следующего шага.

⸻

5.5. Datasets & TestCases

Наборы входов для массового прогонa цепочек.

datasets
- id
- tenant_id
- project_id
- name
- description
- created_at, updated_at

test_cases
- id
- dataset_id
- name
- input_variables (jsonb)
- expected_output (jsonb nullable)
- tags (jsonb nullable)
- created_at, updated_at


⸻

6. Запуски цепочек (Runs)

6.1. Run

Единичный запуск цепочки. Для входа можно использовать ручной JSON или датасет.

runs
- id
- tenant_id
- project_id
- chain_id
- dataset_id (nullable)
- test_case_id (nullable)
- input (jsonb)
- chain_snapshot (jsonb)
  // содержит фиксированную структуру цепочки на момент запуска
- status: pending|running|success|failed
- error_message (nullable)
- total_tokens_in
- total_tokens_out
- total_cost
- duration_ms
- created_at, updated_at
- started_at
- finished_at

6.2. RunStep

Один вызов LLM.

run_steps
- id
- run_id
- chain_node_id
- order_index
- request_payload (jsonb) // messages[], params
- response_raw (jsonb)
- parsed_output (jsonb nullable)
- tokens_in
- tokens_out
- duration_ms
- validation_errors (jsonb nullable)
- status: pending|success|failed
- created_at, updated_at


⸻

7. Фидбэк и улучшение промптов

feedback
- id
- tenant_id
- user_id
- run_id
- run_step_id (nullable)
- type: manual|llm_suggestion
- rating (int nullable)
- comment (text nullable)
- suggested_prompt_content (text nullable)
- created_at

Механизм:
	1.	Пользователь выбирает шаг RunStep.
	2.	Нажимает «Improve prompt».
	3.	Вводит комментарий: что не понравилось.
	4.	Backend вызывает LLM-judge модель:
	•	промпт (PromptVersion)
	•	входные переменные
	•	ответ модели
	•	комментарий пользователя
	5.	Сохраняется новый feedback с suggested_prompt_content.
	6.	В UI можно создать новую PromptVersion на основе suggestion.

⸻

8. Принципы работы цепочек
	•	Каждый шаг — отдельный вызов LLM.
	•	Контекст не переносится автоматически с шага на шаг.
	•	Если нужно передать данные:
	•	вывод предыдущего шага используется как переменная в шаблоне следующего.
	•	System prompt задаётся на уровне шага.
	•	Количество system-промптов = количество шагов — это нормально (каждый — отдельный агент).

⸻

9. Интерфейсы (web-pages)

9.1. Projects
	•	каталог проектов
	•	создание проекта
	•	экран проекта: подсекции Prompts / Chains / Datasets / Runs

9.2. Prompt Templates
	•	список шаблонов
	•	просмотр подробностей: список версий
	•	просмотр версии (immutable)
	•	создание новой версии

9.3. Chains
	•	список цепочек
	•	редактор цепочки:
	•	список шагов
	•	настройка шагов: модель, messages_config, schema
	•	кнопка Run

9.4. Datasets
	•	список наборов
	•	список test cases
	•	создание/edit/delete

9.5. Runs
	•	список запусков
	•	просмотр Run:
	•	input
	•	шаги с промптами, ответами, токенами, временем
	•	ошибки

9.6. Feedback/Improve
	•	карточка шага
	•	кнопка “Improve prompt”
	•	ввод комментария
	•	просмотр предложений
	•	Create new PromptVersion

⸻

10. API роутинг (уровень логики, не строго)
	•	GET /projects
	•	POST /projects
	•	GET /projects/{project}
	•	GET /projects/{project}/prompts
	•	POST /projects/{project}/prompts
	•	POST /prompts/{template}/versions
	•	GET /projects/{project}/chains
	•	POST /projects/{project}/chains
	•	POST /chains/{chain}/nodes
	•	PUT /chains/{chain}/nodes/{node}
	•	GET /projects/{project}/datasets
	•	POST /projects/{project}/datasets
	•	POST /datasets/{dataset}/testcases
	•	POST /chains/{chain}/run
	•	GET /runs/{run}
	•	POST /runsteps/{runStep}/feedback
	•	POST /promptversions/{version}/apply-suggestion

⸻

11. Выполнение цепочки
	1.	Создать Run.
	2.	Сохранить chain_snapshot: список узлов, версии шаблонов, выбранные модели.
	3.	По всем Node (по order_index):
	•	собрать messages на основе PromptVersions
	•	выполнить LLM
	•	сохранить RunStep
	•	если validate fail + stop_on_error → остановиться
	4.	Обновить Run.status, duration, токены.

⸻

12. Нефункциональные требования
	•	Шифрование API-ключей.
	•	Логи LLM без ключей.
	•	Вынос работы цепочки в отдельный сервисный класс (подготовка к очередям).
	•	UI минималистичный: таблицы, формы, collapsible блоки.
	•	Валидация JSON-схемы на уровне RunStep.

⸻

13. Что не входит в v1
	•	Ветвящиеся графы (if/else).
	•	Роли/permissions внутри tenant (только общий доступ).
	•	Автоматические эксперименты A/B.
	•	Автооценка датасетов без ручного триггера.
	•	Обучение моделей или fine-tuning.
