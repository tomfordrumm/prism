# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

### Changed

### Fixed

### Removed

## [0.1.0] - 2026-02-20

### Added
- Per-user chat Enter behavior setting (`send` vs `newline`) with persistence.
- Profile Settings control for chat keyboard behavior.
- Frontend unit tests (Vitest) for ChatUI keyboard behavior and shortcut hints.
- Backend feature tests for chat behavior setting persistence and validation paths.

### Changed
- Chat input behavior is now configurable:
  - `send` mode: `Enter` sends, `Ctrl/Cmd+Enter` inserts newline.
  - `newline` mode: `Enter` inserts newline, `Ctrl/Cmd+Enter` sends.
- Shared Inertia `auth.user` payload now uses explicit field mapping and safer datetime serialization.

### Fixed
- Chat reliability UX now includes `Try again` for failed model calls.
- Retry flow now prevents duplicate retries while one retry is in progress.
- Retry state transitions are surfaced clearly (`retrying`, `success`, `failed again`).
- macOS `Cmd+Enter` newline behavior in `send` mode now works consistently.

## [0.0.0] - 2026-02-19

### Added
- LaRecipe documentation structure and Quick Start guide.
- Docs pages for Architecture, Conventions, and Standard Workflow.

### Changed
- README updated with Agents feature and Quick Start screenshots.
- System settings now show save success/error toasts.

### Removed
- Internal draft docs from the repository root.
