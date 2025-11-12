# Repository-wide engineering guidelines

This project targets PHP 8.3+ and follows strict typing with PSR-12 formatting. Every PHP file **must** start with `declare(strict_types=1);`. Keep the public API compact and avoid needless abstractions.

## Design & architecture principles
- Adhere to KISS, SOLID, DRY, YAGNI, GRASP, Law of Demeter, Separation of Concerns, and Convention over Configuration.
- One class per PHP file. Use interfaces when they provide meaningful seams, and mark classes `readonly` when all promoted properties are immutable (remove redundant `readonly` modifiers).
- Avoid mixed types, `empty()` calls, nested ternary operators, and dynamic calls to static methods. Prefer constants with descriptive names where appropriate.
- Remove redundant casts, unused code (classes/methods), superfluous default arguments, and unnecessary braces in string interpolation.
- Type-hint class constants and handle potential null-pointer scenarios defensively.

## Documentation & naming
- Provide PHPDoc blocks (in English) for every class and method, including parameter and return descriptions. Supply concise inline comments in English only for non-trivial logic. Use meaningful, self-describing identifiers for variables and constants.

## Testing & directories
- Mirror the `src/` namespace structure inside `tests/` using PHPUnit attributes. Write unit tests for every class that you add or modify.
- Maintain ≥ 90% coverage when running the coverage suite. Improve coverage if it drops.

## Tooling & quality gates (run **before every commit**)
1. `composer ci:cgl` — apply and commit formatting changes.
2. `composer ci:rector` — apply and commit changes.
3. `composer ci:test:php:phpstan` — ensure no failures for changed files; project uses maximum PHPStan level.
4. `composer ci:test:php:cpd` (or `npx jscpd --config .jscpd.json`) — keep the duplication check green.
5. `composer ci:test:php:unit:coverage` — ensure green status with ≥ 90% coverage.

Address all findings produced by these commands before committing. Run `composer ci:rector` when automated refactoring is appropriate and commit accepted results.

## Additional conventions
- Prefer fully qualified function calls and replace long qualifiers with imports where it improves clarity.
- Avoid introducing vendor lock-in beyond the established dependencies without discussion.
- Ensure each change is covered by corresponding tests and keep the repository free of unused code.
