# Engelsystem Modernization Plan

## Overview

Work packages derived from Architecture Review and Security Review findings.
Each package is scoped to 2-4 hours of implementation time.

---

## Progress Tracking

| ID | Package | Priority | Status | Completed |
|----|---------|----------|--------|-----------|
| WP-01 | Enable HSTS Header | P0 | Completed | 2025-12-30 |
| WP-02 | Rate Limiting Middleware | P0 | In Review | |
| WP-03 | Deprecate API Key Query Param | P1 | Not Started | |
| WP-04 | Audit |raw Twig Filters | P1 | Not Started | |
| WP-05 | Toast Notification System | P1 | Not Started | |
| WP-06 | Touch Target Optimization | P1 | Not Started | |
| WP-07 | Skip Links & ARIA Landmarks | P1 | Not Started | |
| WP-08 | Form Validation ARIA | P1 | Not Started | |
| WP-09 | Loading States Component | P1 | Not Started | |
| WP-10 | Global Exception Handler | P2 | Not Started | |
| WP-11 | ShiftService Extraction | P2 | Not Started | |
| WP-12 | Mobile Shift List View | P2 | Not Started | |
| WP-13 | CSP Nonce Implementation | P2 | Not Started | |
| WP-14 | TypeScript Migration Setup | P2 | Not Started | |
| WP-15 | Legacy Page: user_shifts | P3 | Not Started | |
| WP-16 | Test Infrastructure: Database Port | P1 | Not Started | |
| WP-17 | Test Infrastructure: Pin Timezone | P1 | Not Started | |

---

## Work Package Summary

| ID | Package | Priority | Effort | Category |
|----|---------|----------|--------|----------|
| WP-01 | Enable HSTS Header | P0 | 1h | Security |
| WP-02 | Rate Limiting Middleware | P0 | 3-4h | Security |
| WP-03 | Deprecate API Key Query Param | P1 | 2h | Security |
| WP-04 | Audit |raw Twig Filters | P1 | 2-3h | Security |
| WP-05 | Toast Notification System | P1 | 2-3h | UX |
| WP-06 | Touch Target Optimization | P1 | 2h | UX |
| WP-07 | Skip Links & ARIA Landmarks | P1 | 2h | Accessibility |
| WP-08 | Form Validation ARIA | P1 | 3h | Accessibility |
| WP-09 | Loading States Component | P1 | 3h | UX |
| WP-10 | Global Exception Handler | P2 | 2-3h | Architecture |
| WP-11 | ShiftService Extraction | P2 | 3-4h | Architecture |
| WP-12 | Mobile Shift List View | P2 | 4h | UX |
| WP-13 | CSP Nonce Implementation | P2 | 3-4h | Security |
| WP-14 | TypeScript Migration Setup | P2 | 3h | Frontend |
| WP-15 | Legacy Page: user_shifts | P3 | 4h | Architecture |
| WP-16 | Test Infrastructure: Database Port | P1 | 1h | Testing |
| WP-17 | Test Infrastructure: Pin Timezone | P1 | 0.5h | Testing |

---

## Phase 1: Security Quick Wins (P0)

### WP-01: Enable HSTS Header
**Priority:** P0 | **Effort:** 1 hour | **Risk:** Low

**Problem:** HSTS header commented out, users vulnerable to SSL stripping.

**Files to modify:**
- `config/config.default.php`

**Tasks:**
1. Uncomment and update HSTS header configuration
2. Set appropriate max-age (31536000 = 1 year)
3. Add includeSubDomains directive
4. Document in deployment guide

**Implementation:**
```php
'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
```

**Testing:**
- Verify header present in response
- Test with securityheaders.com

---

### WP-02: Rate Limiting Middleware
**Priority:** P0 | **Effort:** 3-4 hours | **Risk:** Medium

**Problem:** No rate limiting - vulnerable to brute force attacks.

**Files to create/modify:**
- `src/Middleware/RateLimitMiddleware.php` (new)
- `src/Http/HttpServiceProvider.php` (register middleware)
- `config/config.default.php` (add rate limit config)
- `db/migrations/YYYY_MM_DD_create_rate_limits_table.php` (new)

**Tasks:**
1. Create rate_limits table (ip, endpoint, attempts, expires_at)
2. Create RateLimitMiddleware implementing PSR-15
3. Configure limits per endpoint:
   - `/login`: 5 attempts / 5 minutes
   - `/password/reset`: 3 attempts / 15 minutes
   - `/api/*`: 100 requests / minute
   - `/register`: 5 attempts / hour
4. Return 429 Too Many Requests with Retry-After header
5. Add cleanup command for expired records
6. Write unit tests

**Dependencies:** None

---

## Phase 2: Security Hardening (P1)

### WP-03: Deprecate API Key Query Parameter
**Priority:** P1 | **Effort:** 2 hours | **Risk:** Low

**Problem:** API keys in URLs get logged, leaked in referrers.

**Files to modify:**
- `src/Helpers/Authenticator.php`
- `doc/interfaces/rest-apis.md`

**Tasks:**
1. Add deprecation warning when query param used
2. Log warning for monitoring
3. Update API documentation
4. Plan removal in next major version

**Implementation:**
```php
if ($queryKey !== null) {
    $this->log->warning('Deprecated: API key passed as query parameter', [
        'ip' => $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown'
    ]);
}
```

---

### WP-04: Audit |raw Twig Filters
**Priority:** P1 | **Effort:** 2-3 hours | **Risk:** Medium

**Problem:** 16 usages of |raw filter bypass XSS escaping.

**Files to audit:**
- `resources/views/layouts/app.twig:44`
- `resources/views/macros/base.twig:18`
- `resources/views/macros/form.twig:237,268,332,334`
- `resources/views/pages/schedule/index.twig`
- `resources/views/admin/news/edit.twig`
- `resources/views/pages/news/overview.twig`

**Tasks:**
1. Document each |raw usage with justification
2. Identify user-generated content risks (news.text is HIGH risk)
3. Implement HTML Purifier for news content
4. Replace |raw with safe alternatives where possible
5. Add security comments to justified usages

**Deliverable:** Security audit document + code fixes

---

## Phase 3: UX Improvements (P1)

### WP-05: Toast Notification System
**Priority:** P1 | **Effort:** 2-3 hours | **Risk:** Low

**Problem:** No user feedback for async operations, errors go to console.

**Files to create/modify:**
- `resources/assets/js/components/Toast.js` (new)
- `resources/assets/scss/components/_toast.scss` (new)
- `resources/views/layouts/app.twig` (add toast container)

**Tasks:**
1. Create Toast component (show/hide/auto-dismiss)
2. Style with Bootstrap toast classes
3. Add ARIA live region for accessibility
4. Export global `showToast(message, type)` function
5. Integrate with existing dashboard.js error handling

**API:**
```javascript
window.showToast('Shift signup successful', 'success');
window.showToast('Error loading data', 'error');
```

---

### WP-06: Touch Target Optimization
**Priority:** P1 | **Effort:** 2 hours | **Risk:** Low

**Problem:** Interactive elements < 44x44px WCAG minimum.

**Files to modify:**
- `resources/assets/scss/_variables.scss`
- `resources/assets/scss/components/_buttons.scss`
- `resources/assets/scss/components/_forms.scss`

**Tasks:**
1. Add CSS custom property `--touch-target-min: 44px`
2. Update button min-height/padding
3. Update checkbox/radio sizing
4. Update number spinner controls
5. Add touch-action: manipulation to prevent double-tap delay
6. Test on mobile devices

---

### WP-07: Skip Links & ARIA Landmarks
**Priority:** P1 | **Effort:** 2 hours | **Risk:** Low

**Problem:** No skip navigation, missing landmark roles.

**Files to modify:**
- `resources/views/layouts/app.twig`
- `resources/views/layouts/parts/navbar.twig`
- `resources/assets/scss/components/_accessibility.scss` (new)

**Tasks:**
1. Add skip link as first focusable element
2. Add `role="banner"` to header
3. Add `role="navigation"` to nav
4. Add `role="main"` and `id="main-content"` to main area
5. Add `role="contentinfo"` to footer
6. Style skip link (visible on focus)

---

### WP-08: Form Validation ARIA
**Priority:** P1 | **Effort:** 3 hours | **Risk:** Low

**Problem:** No aria-invalid, aria-describedby for form errors.

**Files to modify:**
- `resources/views/macros/form.twig`
- `resources/assets/js/forms.js`

**Tasks:**
1. Update form macros to include error message IDs
2. Add `aria-invalid="true"` when validation fails
3. Add `aria-describedby` linking to error messages
4. Add `role="alert"` to error containers
5. Implement client-side validation with ARIA updates
6. Test with screen reader

---

### WP-09: Loading States Component
**Priority:** P1 | **Effort:** 3 hours | **Risk:** Low

**Problem:** No visual feedback during async operations.

**Files to create/modify:**
- `resources/assets/js/components/Loading.js` (new)
- `resources/assets/scss/components/_loading.scss` (new)
- `resources/views/macros/loading.twig` (new)

**Tasks:**
1. Create skeleton screen component
2. Create spinner overlay component
3. Create button loading state (disable + spinner)
4. Add aria-busy attribute support
5. Integrate with dashboard.js fetch calls
6. Document usage patterns

---

## Phase 4: Architecture (P2)

### WP-10: Global Exception Handler
**Priority:** P2 | **Effort:** 2-3 hours | **Risk:** Low

**Problem:** Inconsistent error handling between API and legacy pages.

**Files to create/modify:**
- `src/Middleware/ExceptionHandler.php` (enhance existing ErrorHandler)
- `src/Exceptions/` directory (new exception types)

**Tasks:**
1. Create custom exception classes (ValidationException, AuthorizationException)
2. Enhance ErrorHandler to return JSON for API requests
3. Ensure consistent HTTP status codes
4. Add structured error response format
5. Write tests

**Response format:**
```json
{
  "error": {
    "code": "validation_failed",
    "message": "Invalid input",
    "details": [...]
  }
}
```

---

### WP-11: ShiftService Extraction
**Priority:** P2 | **Effort:** 3-4 hours | **Risk:** Medium

**Problem:** Business logic scattered in models and controllers.

**Files to create/modify:**
- `src/Services/ShiftService.php` (new)
- `src/Models/Shifts/Shift.php` (delegate to service)
- `tests/Unit/Services/ShiftServiceTest.php` (new)

**Tasks:**
1. Create ShiftService class
2. Extract methods:
   - `isNightShift(Shift $shift): bool`
   - `calculateWorkHours(Shift $shift): float`
   - `canUserSignUp(User $user, Shift $shift): SignupResult`
   - `getAvailableSlots(Shift $shift, AngelType $type): int`
3. Register as singleton in ServiceProvider
4. Update existing callers
5. Write comprehensive tests

---

### WP-12: Mobile Shift List View
**Priority:** P2 | **Effort:** 4 hours | **Risk:** Medium

**Problem:** Calendar unusable on mobile (requires horizontal scrolling).

**Files to create/modify:**
- `resources/views/pages/shifts/list.twig` (new)
- `src/Controllers/ShiftsController.php` (add list action)
- `resources/assets/scss/pages/_shifts-list.scss` (new)

**Tasks:**
1. Create card-based list view template
2. Add view toggle (calendar/list) with preference storage
3. Implement location filter chips
4. Add day navigation (prev/next)
5. Style for mobile-first
6. Persist view preference in session

**Note:** Does not replace calendar, adds alternative view.

---

### WP-13: CSP Nonce Implementation
**Priority:** P2 | **Effort:** 3-4 hours | **Risk:** Medium

**Problem:** CSP uses 'unsafe-inline' for styles.

**Files to modify:**
- `src/Middleware/AddHeaders.php`
- `src/Renderer/Twig/Extensions/Globals.php`
- `resources/views/layouts/app.twig`
- `config/config.default.php`

**Tasks:**
1. Generate nonce per request in middleware
2. Pass nonce to Twig via global
3. Add nonce to all inline `<style>` tags
4. Update CSP header to use `'nonce-{value}'`
5. Remove 'unsafe-inline' from CSP
6. Test all pages for style loading

---

### WP-14: TypeScript Migration Setup
**Priority:** P2 | **Effort:** 3 hours | **Risk:** Low

**Problem:** JavaScript lacks type safety, hard to maintain.

**Files to create/modify:**
- `tsconfig.json` (new)
- `webpack.config.js` (add ts-loader)
- `resources/assets/ts/main.ts` (new entry point)
- `package.json` (add devDependencies)

**Tasks:**
1. Configure TypeScript with strict mode
2. Add ts-loader to webpack
3. Create initial types for API responses
4. Migrate one small file as proof of concept
5. Document migration path for other files
6. Update build scripts

---

## Phase 5: Legacy Migration (P3)

### WP-15: Migrate Legacy Page: user_shifts
**Priority:** P3 | **Effort:** 4 hours | **Risk:** Medium

**Problem:** `includes/pages/user_shifts.php` uses procedural patterns.

**Files to create/modify:**
- `src/Controllers/UserShiftsController.php` (new)
- `resources/views/pages/user/shifts.twig` (new)
- `config/routes.php` (update route)
- `includes/pages/user_shifts.php` (deprecate)

**Tasks:**
1. Create controller with dependency injection
2. Extract view logic to Twig template
3. Use ShiftService for business logic
4. Add integration tests
5. Update route to new controller
6. Keep legacy file as fallback (feature flag)

**Note:** Template for migrating other legacy pages.

---

## Phase 6: Test Infrastructure (P1)

### WP-16: Test Infrastructure: Database Port Configuration
**Priority:** P1 | **Effort:** 1 hour | **Risk:** Low

**Problem:** Feature tests cannot connect to database when using non-standard ports (e.g., minikube NodePort 31402). The `config_options` in `config/app.php` defines `database.host`, `database.database`, `database.username`, `database.password` but NOT `database.port`. This means `MYSQL_PORT` env var is ignored.

**Files to modify:**
- `config/app.php` (add database.port config option)

**Tasks:**
1. Add `database.port` to config_options with env var `MYSQL_PORT`
2. Set default to 3306
3. Test with minikube setup (port 31402)
4. Document in README

**Implementation:**
```php
'database.port' => [
    'type' => 'number',
    'default' => 3306,
    'env' => 'MYSQL_PORT',
    'write_back' => true,
    'min' => 1,
    'max' => 65535,
],
```

---

### WP-17: Test Infrastructure: Pin Timezone to UTC
**Priority:** P1 | **Effort:** 0.5 hours | **Risk:** Low

**Problem:** 9 unit tests fail when run in non-UTC timezones (e.g., CET). Tests in `CarbonTest`, `CarbonDayTest`, `ConfigControllerTest`, and `ScheduleControllerTest` create Carbon objects without explicit timezone, causing timestamp mismatches.

**Root cause:** `Carbon::createFromFormat()` uses system timezone when none specified. Tests expect UTC behavior but get local timezone (1-2 hour offset).

**Files to modify:**
- `phpunit.xml`

**Tasks:**
1. Add timezone pin to phpunit.xml
2. Verify all 9 failing tests pass
3. Document in contributing guide

**Implementation:**
```xml
<php>
    <ini name="date.timezone" value="UTC"/>
</php>
```

**Alternative (if more control needed):**
Add to `tests/bootstrap.php`:
```php
date_default_timezone_set('UTC');
```

---

## Implementation Order

**Week 1 (Critical):**
1. WP-01: HSTS (1h)
2. WP-02: Rate Limiting (4h)
3. WP-03: API Key Deprecation (2h)

**Week 2 (Security + Quick UX Wins):**
4. WP-04: |raw Audit (3h)
5. WP-05: Toast Notifications (3h)
6. WP-06: Touch Targets (2h)

**Week 3 (Accessibility):**
7. WP-07: Skip Links & Landmarks (2h)
8. WP-08: Form ARIA (3h)
9. WP-09: Loading States (3h)

**Week 4 (Architecture):**
10. WP-10: Exception Handler (3h)
11. WP-11: ShiftService (4h)

**Week 5+ (Larger Items):**
12. WP-12: Mobile List View (4h)
13. WP-13: CSP Nonces (4h)
14. WP-14: TypeScript Setup (3h)
15. WP-15: Legacy Migration (4h)

---

## Success Metrics

| Metric | Current | Target |
|--------|---------|--------|
| Security headers score | B | A |
| WCAG 2.1 AA violations | Many | 0 critical |
| Touch target compliance | ~20% | 100% |
| Rate limiting coverage | 0% | 100% critical endpoints |
| Test coverage | ~60% | 80%+ |
| Legacy code in includes/ | 47 files | Reduce by 10% per quarter |

---

## Notes

- Each WP should have its own feature branch
- PRs should include tests for new code
- UI changes should be reviewed on mobile devices
- Security changes should be documented in CHANGELOG
