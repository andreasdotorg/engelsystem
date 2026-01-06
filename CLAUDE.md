# Engelsystem - Claude Code Project Context

## Modernization Progress

Progress is tracked in `doc/modernization-plan.md`. Update the Progress Tracking table as work packages are completed.

### Current Status

| ID | Package | Status |
|----|---------|--------|
| WP-01 | Enable HSTS Header | Completed |
| WP-02 | Rate Limiting Middleware | In Review |
| WP-03 | Deprecate API Key Query Param | Not Started |
| WP-04 | Audit \|raw Twig Filters | Not Started |
| WP-05 | Toast Notification System | Not Started |
| WP-06 | Touch Target Optimization | Not Started |
| WP-07 | Skip Links & ARIA Landmarks | Not Started |
| WP-08 | Form Validation ARIA | Not Started |
| WP-09 | Loading States Component | Not Started |
| WP-10 | Global Exception Handler | Not Started |
| WP-11 | ShiftService Extraction | Not Started |
| WP-12 | Mobile Shift List View | Not Started |
| WP-13 | CSP Nonce Implementation | Not Started |
| WP-14 | TypeScript Migration Setup | Not Started |
| WP-15 | Legacy Page: user_shifts | Not Started |
| WP-16 | Test Infrastructure: Database Port | Not Started |
| WP-17 | Test Infrastructure: Pin Timezone | Not Started |

### Next Priority Items (P1)
1. **WP-16**: Database Port Config (1h) - Add `database.port` to config_options
2. **WP-17**: Pin Timezone in Tests (0.5h) - Add UTC timezone to phpunit.xml

## Quality Gates (Project-Specific Override)

**This project has stricter requirements than the default workflow prompts.**

Before any PR can be created or merged, ALL of the following must pass:

| Gate | Requirement | Command |
|------|-------------|---------|
| **Test Coverage** | **100% line & method coverage** for new/modified code | `nix run .#test -- --coverage-text` |
| **All Tests Pass** | 100% of tests must pass (no failures) | `nix run .#test` |
| **PHPStan** | No errors at level 1 | `composer phpstan` |
| **PHPCS** | No code style violations (PSR-12) | `composer run phpcs` |
| **JS Linting** | No ESLint/Prettier errors | `yarn lint` |

### Coverage Verification
```bash
# Check coverage for specific class (must show 100% in Methods and Lines)
nix develop -c vendor/bin/phpunit --filter ClassName --coverage-text

# Full coverage report
nix run .#test -- --coverage-html coverage-html
```

### Important Notes
- Every test class must have `@covers` annotation pointing to tested class
- Every test method should have `@covers` annotation for specific method tested
- See Serena memory `coverage-testing-guide` for detailed instructions

## Key Documentation

- `doc/index.md` - Documentation index
- `doc/architecture-review.md` - Architecture & UI/UX assessment
- `doc/security-review.md` - Security assessment
- `doc/modernization-plan.md` - Work packages with detailed tasks

## Development Notes

### Branches
- `main` - Main development branch
- `ela` - Feature branch for documentation and modernization work

### Commands (Nix)
```bash
nix develop          # Enter dev shell
es-install           # Install dependencies
es-db-start          # Start database
es-migrate           # Run migrations
es-serve             # Start dev server
```

## Minor Volunteer Support Feature (Phases 1-10)

**Branch**: `feature/minor-volunteer-support`
**Status**: Implementation complete, E2E testing in progress

### Bug Tracking

See Serena memory `minor-volunteer-bugs-found.md` for full details.

| Bug ID | Description | Status |
|--------|-------------|--------|
| BUG-001 | Missing user_guardian privilege migration | Fixed |
| BUG-002 | Extra argument in signUpMinorForShift | Fixed |
| BUG-003 | RateLimitMiddleware getUri()->getPath() | Workaround (WP-02) |
| BUG-004 | Guardian translation keys not resolved | Open |
| BUG-005 | shift_entries.created_at column not found | Fixed |
| BUG-006 | Date formatting issue in consent form | Open |
| BUG-007 | Missing consent approval UI in admin | Open |

### E2E Testing Progress

| User Story | Status |
|------------|--------|
| US-01: Guardian Registration Flow | Pass (5/5) |
| US-07: Heaven Minor Overview | Pass (6/6) |
| US-04: Shift Signup Validation | Blocked (needs test data) |
| US-05: Supervisor Pre-Registration | Blocked (needs test data) |
| US-06: Non-Counting Participation | Blocked (needs test data) |

## Pending ToDos

- [ ] Complete P0 work packages (HSTS, Rate Limiting)
- [ ] Review all |raw Twig filter usages for XSS risks
- [ ] Add accessibility testing to CI pipeline
- [ ] Fix BUG-004: Guardian translation keys
- [ ] Fix BUG-006: Date formatting in consent form
- [ ] Implement BUG-007: Consent approval UI in admin

## Session Log

- 2026-01-05: Minor volunteer support E2E testing session - US-01 and US-07 pass, 4 open bugs found
- 2025-12-30: Created architecture review, security review, and modernization plan
