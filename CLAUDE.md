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

## Pending ToDos

- [ ] Complete P0 work packages (HSTS, Rate Limiting)
- [ ] Review all |raw Twig filter usages for XSS risks
- [ ] Add accessibility testing to CI pipeline

## Session Log

- 2025-12-30: Created architecture review, security review, and modernization plan
