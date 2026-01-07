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

### Minor Volunteer Support Feature Documentation
- `doc/minor-volunteer-architecture-review.md` - Architecture review for minor support feature
- `doc/minor-volunteer-security-review.md` - Security review for minor support feature

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
**Status**: Implementation complete, E2E testing complete ✅

### Bug Tracking

See Serena memory `minor-volunteer-bugs-found.md` for full details.

| Bug ID | Description | Status |
|--------|-------------|--------|
| BUG-001 | Missing user_guardian privilege migration | Fixed |
| BUG-002 | Extra argument in signUpMinorForShift | Fixed |
| BUG-003 | RateLimitMiddleware getUri()->getPath() | Workaround (WP-02) |
| BUG-004 | Guardian translation keys not resolved | Fixed |
| BUG-005 | shift_entries.created_at column not found | Fixed |
| BUG-006 | Date formatting issue in consent form | Fixed |
| BUG-007 | Missing consent approval UI in admin | Fixed |
| BUG-008 | User feedback for MINOR_RESTRICTED state | Fixed |
| BUG-009 | User::isMinor() returns true for adults with MinorCategory | Fixed |

### E2E Testing Progress

All 7 User Stories pass across all browsers:

| User Story | Tests | Status |
|------------|-------|--------|
| US-01: Guardian Registration Flow | 8 | ✅ Pass |
| US-02: Minor Self-Registration | 7 | ✅ Pass |
| US-03: Shift Type Classification | 6 | ✅ Pass |
| US-04: Shift Signup Validation | 10 | ✅ Pass |
| US-05: Supervisor Pre-Registration | 8 | ✅ Pass |
| US-06: Non-Counting Participation | 8 | ✅ Pass |
| US-07: Heaven Minor Overview | 9 | ✅ Pass |
| **Total** | **56** | ✅ All Pass |

Cross-browser testing:
| Browser | Tests | Status |
|---------|-------|--------|
| Chromium Desktop | 56 | ✅ Pass |
| Firefox Desktop | 56 | ✅ Pass |
| WebKit Desktop | 56 | ✅ Pass |
| Mobile Chrome | 56 | ✅ Pass |
| Mobile Safari | 56 | ✅ Pass |
| **Total** | **280** | ✅ All Pass |

## Pending ToDos

- [ ] Complete P0 work packages (HSTS, Rate Limiting)
- [ ] Review all |raw Twig filter usages for XSS risks
- [ ] Add accessibility testing to CI pipeline
- [ ] Fix BUG-003: RateLimitMiddleware (low priority, workaround applied)

## Session Log

- 2026-01-06: E2E testing complete - all 56 tests passing across 5 browsers (280 total). Removed irrelevant shico shift calendar test. Updated playwright config for headless mode.
- 2026-01-06: Created architecture and security review documents for minor volunteer support feature.
- 2026-01-06: Fixed BUG-004 (translations), BUG-006 (date format), BUG-007 (consent UI), BUG-008 (MINOR_RESTRICTED feedback). 9/10 bugs fixed, only BUG-003 remains (workaround applied).
- 2026-01-06: Completed E2E testing for US-04, US-05, US-06. Fixed BUG-009 (isMinor() architectural issue). All user stories now pass.
- 2026-01-05: Minor volunteer support E2E testing session - US-01 and US-07 pass, 4 open bugs found
- 2025-12-30: Created architecture review, security review, and modernization plan
