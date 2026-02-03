# Overview

PRISM is an open-source Prompt IDE for prompt engineers and product teams. It helps you design prompt templates, build agents (chatbots), run chains of LLM calls, test them across datasets, and iteratively improve prompts with AI-assisted feedback.

- [What You Can Build](#what-you-can-build)
- [Key Concepts](#key-concepts)
- [Next Step](#next-step)

## What You Can Build

- Prompt templates with immutable versions and variable extraction.
- Agents for conversational workflows using dedicated system prompts.
- Chains of LLM calls with per-step models, params, and schema validation.
- Dataset test cases with batch runs and run tracing.

## Key Concepts

- **Projects** group prompts, chains, datasets, and runs for a single product or feature.
- **Prompt templates** are reusable text building blocks with version history.
- **Chains** are ordered LLM steps with explicit inputs and outputs.
- **Agents** are chatbots driven by a system prompt and model configuration.
- **Runs** snapshot the chain and record prompts, responses, and usage.

## Next Step

If this is your first time, start with the [Quick Start](/{{route}}/{{version}}/quick-start).
