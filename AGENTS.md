# AGENTS.md — MagicSunday/JsonMapper

Guide for LLM-based assistants (Codex/Copilot/ChatGPT, etc.) working in this repository.
**Goal:** reproducible, safe, and lean **pull requests** with tests, static analysis, and clear guardrails.
**Reminder:** **no unified-diff patches** — always follow the **branch → commits → PR** workflow.

---

## 1) Scope & principles

**Project objective:** Stable JSON → PHP data mapping (DTOs, value objects, attributes) backed by Symfony PropertyInfo/PropertyAccess.

* Target PHP version: **PHP 8.3/8.4/8.5** — `declare(strict_types=1);`, PSR-12, Rector-ready.
* Keep the public API **compact**; no breaking changes without README/docs/PR notes.
* Avoid magic strings in type resolvers. Prefer dedicated value objects/enums for strategies, converters, and handlers.
* The mapper operates in-memory; do **not** add I/O or network calls.
* Forbid `empty()`, nested ternaries, and sprawling public APIs. Use expressive constants/enums instead.
* Avoid `mixed` **inside** the library. It is deliberate at the public boundary, where the payload
  type genuinely is unknown (`map()`, `TypeHandlerInterface::convert()`): a mapper that could
  declare its input type would not need to exist. Narrow it as early as possible behind that
  boundary rather than passing it through.
* One class per file. Test namespaces mirror the source tree (`MagicSunday\Test\…`).
* PHPDoc blocks and inline comments must be in **English**; inline comments only where the logic is non-trivial.

**JSON-mapping guardrails**

* Follow Symfony PropertyInfo/Access semantics; never bypass them with `setAccessible(true)` or ad-hoc reflection hacks.
* `JsonMapper` must not create dynamic properties. Use converters or type handlers.
* Type resolvers/analyzers must respect nullability, collections, and generic annotations.
* Attributes such as `ReplaceNullWithDefaultValue` must not override the absence of defaults.
* Be mindful of memory usage when working with collections; avoid blindly materialising huge iterables.
* All failures raise domain exceptions deriving from `MappingException` (`TypeMismatchException`,
  `MissingPropertyException`, `MissingConstructorArgumentException`, `ReadonlyPropertyException`,
  `UnknownPropertyException`, `CollectionMappingException`); never fall back to `trigger_error()`
  or `var_dump()`, and never let a native error (`TypeError`, `ValueError`, `ArgumentCountError`)
  escape - it bypasses error collection entirely.
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
| **Release**     | Prepare PR description, labels/milestone, "Closes #…", and tagging.                                       | In: final PR • Out: release artefacts                 |

> Roles act as **checklists**; one person may assume several roles.
---

## 3) Standard tooling & commands

* **Runtime:** PHP 8.3/8.4/8.5
* **Composer scripts:**
    * `composer ci:cgl`
    * `composer ci:rector`
    * `composer ci:test:php:lint`
    * `composer ci:test:php:phpstan`
    * `composer ci:test:php:rector`
    * `composer ci:test:php:cpd`
    * `composer ci:test:php:unit`
    * `composer ci:test:php:cgl`
    * `composer ci:test:php:unit:coverage`
    * `composer ci:test` — the aggregate the README points contributors at; runs lint, unit,
      PHPStan, Rector, CGL and CPD in that order
* **Node tooling:** `npx jscpd --config .jscpd.json --skip-comments --no-tips` (executed by `ci:test:php:cpd`; `npm install jscpd@^5.0.11` runs via `post-update-cmd`).

**Git flow (no ad-hoc diffs):**

* Branch naming for issue-tied work: `GH-<issue number>`, nothing appended.
* Commit subjects start with a capitalised imperative verb and carry a `GH-<issue number>: ` prefix
  when the commit belongs to an issue (`GH-55: Reject an enum value that does not match the backing
  type`). No `feat:`/`fix:`/`chore:` prefixes.
* One concern per commit; keep formatting-only changes out of a behavioural commit.
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
* `UnknownPropertyCollector` marks at most one property per class; it captures only keys that match no declared property (never its own key), and assigns the raw `array<string, mixed>` without running the per-value conversion pipeline.

**Error handling**

* Raise domain exceptions only - the `MappingException` hierarchy listed in §1. A native
  `TypeError`/`ValueError`/`ArgumentCountError` reaching the caller is a defect: it is invisible to
  error collection, so `mapWithReport()` cannot report it.
* A rejected value is recorded exactly once. Recording it and then continuing produces either a
  duplicate record further up or a native crash - throw instead, and let the single catch site
  record it.
* The write can fail after the conversion pipeline accepted the value, and it is guarded for that
  reason. Conversion runs against the type the resolver could DERIVE, which is not always the type
  the target declares: an intersection is modelled by neither PropertyInfo nor the reflection
  fallback, so it resolves to nullable `mixed` and accepts every payload, leaving the property to
  refuse it as a native `TypeError`. The accessor wraps that into an `InvalidTypeException` carrying
  the refused type, which the record then names - accurate even for a property reachable only
  through a setter, where the declared type read from reflection would wrongly be `mixed`. Only the
  accessor's own `InvalidTypeException` is caught. A raw `TypeError` is deliberately NOT: a
  variadic setter is spread-called directly (the accessor cannot unpack an array into `...$args`),
  and no `TypeError` is caught around that call - the elements are already converted to the resolved
  element type, so a refusal there means the docblock element type and the setter parameter type
  disagree, a DTO defect, and a `TypeError` raised inside any setter BODY is a bug in the setter.
  Both propagate as themselves rather than being re-labelled as a payload mismatch and buried in the
  report. Do NOT try to classify a variadic `TypeError` by its trace frame: an argument-binding
  refusal and a body error both report the setter as their innermost frame, so the two cannot be
  told apart that way, and the attempt masks the body bug it means to preserve.
* The abort-or-record policy lives in `MappingContext::throwOrRecord()`, whose name states the
  order because the order is the contract. A site that has something usable to hand back - an empty
  collection, an unconverted value - routes through it. The two that do not both record BEFORE
  raising, and each says so at its own call site: the shared catch, because it IS the catch the
  helper's throw reaches; and the collection element loop, because an aborting run would otherwise
  lose the element's own record. Finishing the centralisation past those two loses a record in each
  case, silently, since the caller still gets its exception - and on a nested payload a deeper
  record can survive and leave the report looking complete.
* Whether a recorded failure also aborts the run is the ENTRY POINT's decision, not the
  configuration's. `map()` raises on the first failure in strict mode; `mapWithReport()` always
  collects, because returning a report is its entire purpose. Strict mode decides only *what*
  counts as a failure. The switch is the context option `OPTION_ABORT_ON_ERROR`, deliberately kept
  out of `JsonMapperConfiguration` so a caller cannot configure a `mapWithReport()` that throws.
* A mapping message is for a developer reading a log, never for a response body: it embeds internal
  class names and reflects payload-chosen keys verbatim. Every exception therefore exposes what it
  knows through accessors, so a consumer can build client-facing text without parsing the message.
  A new exception type without them breaks that guarantee - `StructuredExceptionDataTest` is the
  inventory that catches it, derived from the directory so it cannot be satisfied by omission.
  Payload VALUES are never embedded; only `get_debug_type()` of them. That holds for the
  configuration exceptions too, which escape past the report entirely into a generic handler - a
  message that echoes a name a RESOLVER produced is echoing the payload.
* A target class is never derived from payload data. The mapper takes it from the call, from
  property metadata, or from a docblock - never from a value the payload supplied. The one place a
  consumer can break that is a class-map resolver, whose input IS the payload, so its documentation
  carries the warning and it accepts an allowlist that enforces what the warning asks for.
* Never rely on the silence operator, `trigger_error()`, or debugging prints.
* Partial updates must leave already mapped state consistent when errors occur.
* Mapping is deterministic: the same payload and configuration produce the same result on every
  host. Anything read from the ambient process - the default timezone, the locale, the current
  time - has to be supplied explicitly instead. A zoneless date format defaults to UTC for this
  reason; falling back to the process timezone made identical JSON decode to instants fourteen
  hours apart, and neither the payload nor the report showed that anything host-specific had
  happened.

---

## 5) Pipeline (agent playbook)

1. **Planner** — define file scope, non-goals, and guardrails; reference `tests/Classes/` and `tests/Fixtures/` if needed.
2. **Spec Writer** — describe acceptance criteria and test cases (nullable properties, collections, attributes, type handlers).
3. **Test Agent (RED)** — add/adjust tests (positive & negative) using synthetic fixtures.
4. **Implementer (GREEN)** — apply the minimal code change that satisfies the tests; refresh PHPDocs/enums/attributes where required.
5. **Static/QA** — run Rector/CS fixer/PHPStan/CPD and commit fixes.
6. **Security** — ensure no unsafe reflection, unchecked class instantiation, or deserialisation risks were introduced.
7. **Reviewer & Release** — review for minimality/AC coverage; draft the PR text and link the issue.

Write the PR body yourself: an overview of the defect or feature, the details of the change, how it
was verified, any behaviour change worth naming, and the closing reference. State what was measured
rather than what was intended.

---

## 6) Prompt templates

**Implementation (per issue)**

```
Role: Implementer. Complete issue “<TITLE>”.
Context: PHP 8.3/8.4/8.5, strict_types=1, PSR-12, JsonMapper library.
File scope: <list of authorised files>
Guards: Stable public API, safe reflection, no dynamic properties, no I/O.
Documentation: Maintain PHPDocs (English), expressive identifiers, inline comments for complex logic only.
Enums/value objects: Prefer them over magic strings for handler/converter configuration.
Output: Commits on a `GH-<issue number>` branch + pull request.
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
Apply the minimal code change within the allowed file scope to fix the failures; update PHPDocs/enums/attributes if needed; commit with a capitalised imperative subject and the `GH-<issue number>: ` prefix.
```

**PR text**

```
Role: Release. Draft the PR description (overview, details, tests, risks, “Closes #…”).
List changed API surfaces and relevant attributes/converters in the “References” section.
```

---

## 7) Domain cheat sheet

* **Property Info:** Use Symfony’s extractor stack (reflection, PHPDoc, type info). Respect the ordering and fallbacks.
* **Type handlers:** Implement `MagicSunday\JsonMapper\Value\TypeHandlerInterface`; handlers are stateless and must honour the `supports()`/`convert()` contract. This is the ONE public extension point for value conversion. The `Value\Strategy\*` classes and `ValueConversionStrategyInterface` are `@internal` - the conversion chain's order is an implementation detail, not a contract, and there is no public hook to register a strategy. A handler runs ahead of the built-in deciding strategies but AFTER null handling, so it never sees a null. Their `supports()` carries a `MappingContext` the handler contract does not; the two are not meant to align, since one is internal and one is public.
* **Attributes:**
    * `ReplaceNullWithDefaultValue` — only applies when a default exists.
    * `ReplaceProperty` — supports multiple alias names. They are collected into a map keyed by
      the alias, so declaration order carries no meaning; two attributes naming the same alias are
      a redeclaration in which the last wins.
* **Name converters:** `CamelCasePropertyNameConverter` is the only implementation shipped — ensure round-trip behaviour in tests.
* **Collections:** Combine legacy DocBlock annotations (`@var Collection<Type>`) with PHP 8.1+ `#[Type]` attributes.
* **Security:** No `eval`, no unchecked dynamic class instantiation, no serialisation side effects.

---

## 8) Definition of Done (DoD)

* ✅ PHPUnit green (positive & negative cases covered).
* ✅ **Coverage ≥ 90 %** — machine-enforced in CI by the `coverage` job via
  `composer ci:test:php:coverage:gate`, which runs `ci:test:php:unit:coverage` and fails the build
  when line coverage drops below the threshold.
* ✅ PHPStan passes (at least all modified files).
* ✅ Rector & CS fixer clean; commit formatting changes.
* ✅ **CPD** detects no relevant duplicates.
* ✅ Scope stays minimal and within the authorised files.
* ✅ Acceptance criteria met; README/docs updated when behaviour or API changes.
* ✅ Public API documented (PHPDoc + README where applicable).
* ✅ Type handlers/attributes documented and tested.
* ✅ Issue/milestone linked; Commit subjects follow the convention above and the PR includes “Closes #…”.

---

## 9) Sample card — “M3: Null handling for collections”

* **Input:** The issue requires `ReplaceNullWithDefaultValue` to work for collection properties without losing type information.
* **File scope:**
    * `src/JsonMapper.php`
    * `src/JsonMapper/Attribute/ReplaceNullWithDefaultValue.php`
    * `tests/JsonMapperTest.php` (see `mapNullToDefaultValueUsingAttribute`)
* **Guards:** No dynamic property creation; collection defaults remain immutable; throw clear exceptions for incompatible types.
* **Expectation:** Collections are replaced with defaults when source data is `null`; log/raise errors for incompatible types.
* **Documentation:** Update PHPDocs for the mapper; extend README section “Custom attributes”.
* **Output:** Branch `GH-<issue number>`, RED → GREEN commit chain, PR with green CI.

---

## 10) Common pitfalls & remedies

* **Direct property writes** → Always use PropertyAccessor or existing helpers.
* **Untested attributes** → Provide at least one positive and one negative test per attribute.
* **Magic strings** → Prefer converter/handler constants or enums.
* **Missing null checks** → Add tests and handling logic.
* **Ignoring Rector/CS findings** → Run the scripts and commit their fixes.
* **Forgetting README/docs updates** → Mandatory when behaviour or API changes.

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
* [ ] `composer ci:test:php:coverage:gate` (coverage ≥ 90 %, enforced in CI)
* [ ] No `mixed`, `empty()`, or nested ternaries.
* [ ] Classes/tests mirror namespaces; descriptive names; inline comments only when logic is complex.
* [ ] Value objects/enums instead of magic strings.
* [ ] Exceptions & error paths consistent.
* [ ] README/docs refreshed if required.
* [ ] Commit subjects and branch name follow §3; branch/PR workflow respected.

---

**Owner/Contact:** *MagicSunday* (Europe/Berlin)
**Structure:** `src/JsonMapper.php` (the mapper itself) and `src/JsonMapper/` split into
`Attribute`, `Collection`, `Configuration`, `Context`, `Converter`, `Exception`, `Report`,
`Resolver`, `Type` and `Value` (with `Value/Strategy` holding the conversion chain); plus
`tests/**` (fixtures under `tests/Classes` and `tests/Fixtures`), `docs/**` and `.github/**`.
