# AGENTS.md — MagicSunday/JsonMapper

Guide for LLM-based assistants (Codex/Copilot/ChatGPT, etc.) working in this repository.
**Goal:** reproducible, safe, and lean **pull requests** with tests, static analysis, and clear guardrails.
**Reminder:** **no unified-diff patches** — always follow the **branch → commits → PR** workflow.

---

## 1) Scope & principles

**Project objective:** Stable JSON → PHP data mapping (DTOs, value objects, attributes) backed by Symfony PropertyInfo/PropertyAccess.

* Target PHP version: **PHP 8.3/8.4** — `declare(strict_types=1);`, PSR-12, Rector-ready.
* Keep the public API **compact**; no breaking changes without README/CHANGELOG/PR notes.
* Avoid magic strings in type resolvers. Prefer dedicated value objects/enums for strategies, converters, and handlers.
* The mapper operates in-memory; do **not** add I/O or network calls.
* Forbid `mixed`, `empty()`, nested ternaries, and sprawling public APIs. Use expressive constants/enums instead.
* One class per file. Test namespaces mirror the source tree (`MagicSunday\Test\…`).
* PHPDoc blocks and inline comments must be in **English**; inline comments only where the logic is non-trivial.

**JSON-mapping guardrails**

* Follow Symfony PropertyInfo/Access semantics; never bypass them with `setAccessible(true)` or ad-hoc reflection hacks.
* `JsonMapper` must not create dynamic properties. Use converters or type handlers.
* Type resolvers/analyzers must respect nullability, collections, and generic annotations.
* Attributes such as `ReplaceNullWithDefaultValue` must not override the absence of defaults.
* Be mindful of memory usage when working with collections; avoid blindly materialising huge iterables.
* All failures raise domain exceptions (`MappingError`, `ResolverException`, etc.); never fall back to `trigger_error()` or `var_dump()`.
* Transformations must remain pure and stateless. No singletons or global state.

---

## 2) Agent roles

| Agent           | Responsibility                                                                                            | In/Out                                                |
|-----------------|-----------------------------------------------------------------------------------------------------------|-------------------------------------------------------|
| **Planner**     | Read the issue/milestone, define file scope, non-goals, guardrails.                                       | In: issue • Out: sub-tasks + file scope               |
| **Spec Writer** | Clarify acceptance criteria & tests (missing properties, invalid types, attribute flows, error handling). | In: planner • Out: test specification                 |
| **Test Agent**  | **RED** phase: write PHPUnit tests first; synthetic DTOs/fixtures; cover edge cases & failure paths.      | In: spec • Out: commits under `tests/**`              |
| **Implementer** | **GREEN** phase: implement inside the authorised scope; update docs/attributes/enums.                     | In: failing tests • Out: commits that turn them green |
| **Static/QA**   | Run PHPStan, Rector, CS fixer; address findings with minimal semantic changes.                            | In: tooling output • Out: cleanup commits             |
| **Security**    | Review mapping/deserialisation surface: no unsafe reflection, no unchecked class names.                   | In: PR diff • Out: review notes/mini-commits          |
| **Reviewer**    | Check for minimality, readability, API stability, acceptance-criteria coverage, and attribute handling.   | In: PR • Out: review comments/mini-commits            |
| **Release**     | Prepare PR description, changelog entry, labels/milestone, "Closes #…", and tagging.                      | In: final PR • Out: release artefacts                 |

> Roles act as **checklists**; one person may assume several roles.
---

## 3) Standard tooling & commands

* **Runtime:** PHP 8.3/8.4
* **Composer scripts:**
    * `composer ci:cgl`
    * `composer ci:rector`
    * `composer ci:test:php:lint`
    * `composer ci:test:php:phpstan`
    * `composer ci:test:php:rector`
    * `composer ci:test:php:cpd`
    * `composer ci:test:php:unit`
    * `composer ci:test:php:unit:coverage`
    * `composer ci:compliance`
* **Node tooling:** `npx jscpd --config .jscpd.json` (executed by the composer scripts; `npm install` runs via `post-update-cmd`).

**Git flow (no ad-hoc diffs):**

* Branch naming: `feat/<area>-<slug>`, `fix/<area>-<slug>`, `chore/<area>-<slug>`.
* Commits follow **Conventional Commits** (e.g. `feat(mapper): support readonly properties`).
* Open PRs early, keep CI green, assign reviewers.

---

## 4) Guardrails for all changes

**General**

* Touch only files that belong to the issue scope; preserve the public API.
* Do not add external processes, extensions, or binaries.
* Run tests and static checks before committing.

**Mapper & reflection**

* Avoid direct manipulation of Symfony PropertyAccessor internals.
* Use existing helpers for reflection; avoid `setAccessible(true)`.
* Property name converters must be idempotent (same input → same output).
* When registering `TypeHandler`s, verify FQCNs exist.

**Attributes & annotations**

* Support PHP attributes and legacy DocBlock annotations side by side for backwards compatibility.
* Introduce new attributes only with documentation and dedicated tests.
* `ReplaceProperty` must not recurse infinitely; ensure termination.

**Error handling**

* Raise domain exceptions only (`MappingError`, `ResolverException`, `TypeError`, …).
* Never rely on the silence operator, `trigger_error()`, or debugging prints.
* Partial updates must leave already mapped state consistent when errors occur.

---

## 5) Pipeline (agent playbook)

1. **Planner** — define file scope, non-goals, and guardrails; reference `tests/fixtures/` if needed.
2. **Spec Writer** — describe acceptance criteria and test cases (nullable properties, collections, attributes, type handlers).
3. **Test Agent (RED)** — add/adjust tests (positive & negative) using synthetic fixtures.
4. **Implementer (GREEN)** — apply the minimal code change that satisfies the tests; refresh PHPDocs/enums/attributes where required.
5. **Static/QA** — run Rector/CS fixer/PHPStan/CPD and commit fixes.
6. **Security** — ensure no unsafe reflection, unchecked class instantiation, or deserialisation risks were introduced.
7. **Reviewer & Release** — review for minimality/AC coverage; draft the PR text, update changelog, and link the issue.

Always build the PR body from `.github/pull_request_template.md` (default branch) and insert the section “M# Sweep — Verify compliance for this milestone” **above** the template content before submission.

---

## 6) Prompt templates

**Implementation (per issue)**

```
Role: Implementer. Complete issue “<TITLE>”.
Context: PHP 8.3/8.4, strict_types=1, PSR-12, JsonMapper library.
File scope: <list of authorised files>
Guards: Stable public API, safe reflection, no dynamic properties, no I/O.
Documentation: Maintain PHPDocs (English), expressive identifiers, inline comments for complex logic only.
Enums/value objects: Prefer them over magic strings for handler/converter configuration.
Output: Conventional commits on a branch + pull request.
```

**Tests first**

```
Role: Test Agent. Add PHPUnit tests only for “<TITLE>”.
Use synthetic DTOs/fixtures; include negative paths (unexpected types, missing defaults, collection handling).
Output: Commits under tests/**; summarise CI output briefly.
```

**Fix loop**

```
Role: Implementer. PHPUnit output (red):
<OUTPUT>
Apply the minimal code change within the allowed file scope to fix the failures; update PHPDocs/enums/attributes if needed; commit using Conventional Commits.
```

**PR text**

```
Role: Release. Draft the PR description (overview, details, tests, risks, changelog, “Closes #…”).
List changed API surfaces and relevant attributes/converters in the “References” section.
```

---

## 7) Domain cheat sheet

* **Property Info:** Use Symfony’s extractor stack (reflection, PHPDoc, type info). Respect the ordering and fallbacks.
* **Type handlers:** Implement `MagicSunday\JsonMapper\Value\TypeHandlerInterface`; handlers are stateless and must honour the `supports`/`map` contract.
* **Attributes:**
    * `ReplaceNullWithDefaultValue` — only applies when a default exists.
    * `ReplaceProperty` — supports multiple alias names; ordering defines priority.
* **Name converters:** `CamelCasePropertyNameConverter`, etc. — ensure round-trip behaviour in tests.
* **Collections:** Combine legacy DocBlock annotations (`@var Collection<Type>`) with PHP 8.1+ `#[Type]` attributes.
* **Security:** No `eval`, no unchecked dynamic class instantiation, no serialisation side effects.

---

## 8) Definition of Done (DoD)

* ✅ PHPUnit green (positive & negative cases covered).
* ✅ **Coverage ≥ 90 %** (`composer ci:test:php:unit:coverage`).
* ✅ PHPStan passes (at least all modified files).
* ✅ Rector & CS fixer clean; commit formatting changes.
* ✅ **CPD** detects no relevant duplicates.
* ✅ Scope stays minimal and within the authorised files.
* ✅ Acceptance criteria met; README/CHANGELOG/docs updated when behaviour or API changes.
* ✅ Public API documented (PHPDoc + README where applicable).
* ✅ Type handlers/attributes documented and tested.
* ✅ Issue/milestone linked; PR uses **Conventional Commits** and includes “Closes #…”.

---

## 9) Sample card — “M3: Null handling for collections”

* **Input:** The issue requires `ReplaceNullWithDefaultValue` to work for collection properties without losing type information.
* **File scope:**
    * `src/JsonMapper.php`
    * `src/Attribute/ReplaceNullWithDefaultValue.php`
    * `tests/JsonMapper/ReplaceNullWithDefaultValueTest.php`
* **Guards:** No dynamic property creation; collection defaults remain immutable; throw clear exceptions for incompatible types.
* **Expectation:** Collections are replaced with defaults when source data is `null`; log/raise errors for incompatible types.
* **Documentation:** Update PHPDocs for the mapper; extend README section “Custom attributes”.
* **Output:** Branch `feat/mapper-null-defaults`, RED → GREEN commit chain, PR with green CI.

---

## 10) Common pitfalls & remedies

* **Direct property writes** → Always use PropertyAccessor or existing helpers.
* **Untested attributes** → Provide at least one positive and one negative test per attribute.
* **Magic strings** → Prefer converter/handler constants or enums.
* **Missing null checks** → Add tests and handling logic.
* **Ignoring Rector/CS findings** → Run the scripts and commit their fixes.
* **Forgetting README/CHANGELOG updates** → Mandatory when behaviour or API changes.

---

## 11) Pre-commit checklist

* [ ] Only authorised files changed.
* [ ] Public API untouched or documented and communicated.
* [ ] Tests (including negative cases) added/updated; coverage ≥ 90 %.
* [ ] `composer ci:cgl` & `composer ci:rector` executed; changes committed.
* [ ] `composer ci:test:php:lint`
* [ ] `composer ci:test:php:phpstan`
* [ ] `composer ci:test:php:rector`
* [ ] `composer ci:test:php:cpd`
* [ ] `composer ci:test:php:unit`
* [ ] `composer ci:test:php:unit:coverage`
* [ ] No `mixed`, `empty()`, or nested ternaries.
* [ ] Classes/tests mirror namespaces; descriptive names; inline comments only when logic is complex.
* [ ] Value objects/enums instead of magic strings.
* [ ] Exceptions & error paths consistent.
* [ ] README/docs refreshed if required.
* [ ] Conventional commits; branch/PR workflow respected.

---

**Owner/Contact:** *MagicSunday* (Europe/Berlin)
**Structure:** `src/Attribute`, `src/Converter`, `src/JsonMapper`, `src/Value`, `tests/**`, `docs/**`, `scripts/**`.
