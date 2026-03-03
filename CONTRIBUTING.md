# Contributing

## 1. How to contribute

- Check existing issues first to avoid duplicate work.
- For larger changes, open an issue before starting implementation.
- Keep contributions focused and small enough for clear review.
- Add or update tests for behavior changes.

## 2. Issues

- Use GitHub Issues: https://github.com/magicsunday/jsonmapper/issues
- A good issue includes:
  - clear problem statement
  - minimal reproduction input or steps
  - expected vs. actual behavior
  - environment details (PHP version, relevant context)
- If your issue is meant for agent execution, use the repository's agent issue template.

## 3. Pull requests

- Open a PR from a branch in your fork.
- Describe scope, motivation, and validation steps.
- Follow `.github/pull_request_template.md`.
- Before requesting review, run the mandatory checks locally:
  - `composer ci:test`
- Include tests for new behavior and regression tests for fixes.
- This pull-request workflow applies to regular external contributor submissions.

## 4. Development setup (minimal)

- PHP: `^8.3`
- Install dependencies:

```bash
composer install
```

- Run the project quality gate:

```bash
composer ci:test
```

## 5. Agent contributions

This repository has explicit agent rules. Do not duplicate or reinterpret them in PRs.

- Repository-wide agent rules: `AGENTS.md`

If a contribution is prepared or modified by an LLM/agent, it must comply with those files.
Where agent rules define a different workflow, the AGENTS files take precedence.
