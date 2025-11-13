# CI Verification Checklist

**Purpose:** Verify AGENTS.md compliance through automated tooling  
**Branch:** copilot/check-branch-agents-requirements

## Required CI Checks

Run these commands in order and verify all pass:

### 1. PHP Lint ✓
```bash
composer ci:test:php:lint
```
**Expected:** No syntax errors in any PHP files

### 2. PHPStan Static Analysis ✓
```bash
composer ci:test:php:phpstan
```
**Expected:** No type errors or violations  
**Note:** All generics annotations should be properly specified per TASKS.md

### 3. Rector Check ✓
```bash
composer ci:test:php:rector
```
**Expected:** No modernization issues  
**Note:** Dry-run mode - suggests improvements without applying them

### 4. PHP CS Fixer (PSR-12) ✓
```bash
composer ci:test:php:cgl
```
**Expected:** All files comply with PSR-12 coding standard  
**Note:** Dry-run mode - shows violations without fixing them

### 5. Copy-Paste Detection (CPD) ✓
```bash
composer ci:test:php:cpd
```
**Expected:** No significant code duplication found  
**Threshold:** Check `.jscpd.json` for thresholds

### 6. Unit Tests ✓
```bash
composer ci:test:php:unit
```
**Expected:** All tests pass  
**Note:** Verify positive and negative test cases

### 7. Code Coverage ✓
```bash
composer ci:test:php:unit:coverage
```
**Expected:** Coverage ≥ 90%  
**Output:** HTML coverage report in `.build/coverage/`  
**Action:** Review coverage report and identify any uncovered critical paths

### 8. Full Test Suite ✓
```bash
composer ci:test
```
**Expected:** All checks pass in sequence

## AGENTS.md Compliance Verification

After running all CI checks, verify these AGENTS.md requirements:

### Code Quality
- [ ] All files have `declare(strict_types=1);`
- [ ] No `empty()` function calls
- [ ] No nested ternary operators
- [ ] No `trigger_error()` or `var_dump()` calls
- [ ] No `setAccessible(true)` reflection hacks
- [ ] PHPDoc comments in English only

### Architecture
- [ ] One class per file
- [ ] Test namespaces mirror `MagicSunday\Test\...`
- [ ] No I/O or network operations in mapper
- [ ] Pure, stateless transformations
- [ ] Domain exceptions only (no error suppression)

### Type Safety
- [ ] `mixed` used only where semantically appropriate (JSON values)
- [ ] Specific union types preferred over `mixed` where possible
- [ ] All generics properly annotated
- [ ] Nullable types properly handled

### Testing
- [ ] Both positive and negative test cases
- [ ] Edge cases covered
- [ ] Error handling tested
- [ ] Attribute behavior tested
- [ ] Converter idempotence tested

### Documentation
- [ ] Public API documented with PHPDoc
- [ ] README updated if behavior changed
- [ ] CHANGELOG updated if API changed
- [ ] Custom attributes documented

## Handling Failures

### If PHPStan Fails
1. Review reported type errors
2. Add missing generic type annotations
3. Fix nullable type handling
4. Re-run and verify

### If Rector Fails
1. Review suggested modernizations
2. Apply suggestions with `composer ci:rector`
3. Verify changes don't break functionality
4. Commit with message: `refactor: apply rector modernizations`

### If CS Fixer Fails
1. Review style violations
2. Apply fixes with `composer ci:cgl`
3. Commit with message: `style: apply PSR-12 formatting`

### If CPD Fails
1. Review duplicated code sections
2. Extract common logic to shared methods/classes
3. Ensure refactoring doesn't break tests
4. Commit with message: `refactor: eliminate code duplication`

### If Tests Fail
1. Review failure messages and stack traces
2. Fix broken functionality
3. Add missing test cases
4. Commit with message: `test: fix failing tests`

### If Coverage < 90%
1. Identify uncovered code paths
2. Add tests for uncovered scenarios
3. Focus on critical business logic
4. Commit with message: `test: improve coverage to 90%+`

## Post-Verification Actions

Once all checks pass:

1. **Review Changes**
   - Ensure all changes align with AGENTS.md
   - Verify minimal scope
   - Check commit messages use Conventional Commits

2. **Update PR Description**
   - List all checks that passed
   - Note any deviations and justifications
   - Reference compliance report

3. **Request Review**
   - Tag appropriate reviewers
   - Link to AGENTS_COMPLIANCE_REPORT.md
   - Highlight any architectural decisions

## Notes

- **Build Issues:** If dependencies fail to install, check network connectivity and GitHub authentication
- **Timeout Issues:** Some operations (PHPStan, tests with coverage) may take several minutes
- **Node Tooling:** CPD requires `npm install jscpd` which runs via `composer post-update-cmd`

## Success Criteria

✅ **PASSED** when:
- All 8 CI checks pass without errors
- Code coverage ≥ 90%
- No regressions introduced
- All AGENTS.md requirements verified
- Conventional commits used
- PR ready for review

---

**Last Updated:** 2025-11-13  
**Related:** AGENTS_COMPLIANCE_REPORT.md, AGENTS.md, TASKS.md
