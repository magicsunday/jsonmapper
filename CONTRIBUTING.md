# Contributing

Thanks for helping improve JsonMapper. This file covers the workflow specific to this repository;
the [magicsunday organisation contributing guide](https://github.com/magicsunday/.github/blob/main/CONTRIBUTING.md)
covers the general policy.

## Before you start

- Check the existing [issues](https://github.com/magicsunday/jsonmapper/issues) to avoid duplicate
  work, and open one before a larger change so the scope can be agreed first.
- Keep a change focused on a single concern — it is easier to review and to revert.
- Every behaviour change or fix ships with tests (positive **and** negative cases).

## Development setup

Requires PHP `^8.3`. Install the dependencies and run the quality gate:

```bash
composer install
composer ci:test
```

`ci:test` is the whole gate — PHPUnit, PHPStan (max level), Rector and PHP-CS-Fixer (dry-run),
`phplint`, and copy/paste detection. It must be green before every commit. Coverage is enforced in
CI at ≥ 90 %; run it locally with `composer ci:test:php:coverage:gate`.

## Commits and pull requests

- Branch from `main`. Name an issue-tied branch exactly `GH-<number>`.
- Commit subjects — and the pull-request title — are governed by the shared
  `commit-convention` gate; the normative rule lives in
  `magicsunday/.github/.github/workflows/commit-convention.yml@main` and is summarised in
  [`AGENTS.md`](AGENTS.md). In short: `GH-<number>: Add …` for issue-tied work, or `Add …`
  for a free change — a capitalised English imperative, no `feat:`/`fix:` prefixes.
- Group changes into logical commits — one concern each; keep style-only fixes separate from
  behaviour changes.
- Open the PR against `main`, describe the scope, the motivation, and how you verified it, and close
  the issue with `Closes #<number>`.

## Documentation

Update the README and `docs/` when behaviour or the public API changes. Public methods, constants
and attributes carry a real PHPDoc.

## Agent contributions

Any contribution prepared or modified by an LLM/agent must comply with [`AGENTS.md`](AGENTS.md),
which is authoritative and takes precedence — do not reinterpret its rules in a PR.
