# Minor Volunteer Support - Architecture Review

**Feature Branch:** `feature/minor-volunteer-support`
**Review Date:** 2026-01-06
**Status:** Implementation Complete, E2E Testing Passed

## Executive Summary

The Minor Volunteer Support feature implements comprehensive youth protection for volunteer management at events. The architecture follows a layered approach with clear separation between models, services, and controllers, integrating with both modern PSR-4 code and legacy procedural components.

**Overall Assessment:** Well-structured implementation with appropriate use of modern PHP patterns. Minor improvements recommended for long-term maintainability.

---

## 1. Component Overview

### 1.1 Database Layer (10 Migrations)

| Migration | Purpose |
|-----------|---------|
| `2026_01_01_000000_create_minor_categories_table` | Age-based restriction categories |
| `2026_01_01_000001_add_minor_category_to_users` | Link users to categories |
| `2026_01_01_000002_create_user_guardian_table` | Guardian-minor relationships |
| `2026_01_01_000003_create_user_supervisor_status_table` | Supervisor eligibility tracking |
| `2026_01_01_000004_add_minor_fields_to_shift_types` | Work categories on shift types |
| `2026_01_01_000005_add_minor_fields_to_shifts` | Per-shift overrides |
| `2026_01_01_000006_add_quota_flag_to_shift_entries` | Non-counting participation |
| `2026_01_01_000007_add_consent_fields_to_users` | Consent approval tracking |
| `2026_01_01_000008_seed_default_minor_categories` | Default category data |
| `2026_01_02_000000_add_guardian_privilege` | Guardian permission |

### 1.2 Model Layer

```
src/Models/
├── MinorCategory.php          # Age-based restrictions (hours, times, categories)
├── UserGuardian.php           # Guardian-minor pivot with temporal validity
├── UserSupervisorStatus.php   # Supervisor willingness/training status
└── User/User.php              # Extended with isMinor(), hasConsentApproved()

src/Models/Shifts/
├── Shift.php                  # Extended with minor-related fields
└── ShiftEntry.php             # Extended with supervision tracking
```

### 1.3 Service Layer

```
src/Services/
├── GuardianService.php        # Guardian-minor relationship management
├── MinorRestrictionService.php # Restriction enforcement & validation
├── MinorRestrictions.php      # Value object for restriction data
└── ShiftValidationResult.php  # Value object for validation results
```

### 1.4 Controller Layer

```
src/Controllers/
├── GuardianController.php     # Guardian dashboard & minor management
└── Admin/MinorManagementController.php  # Heaven oversight & consent approval

includes/
├── controller/shift_entries_controller.php  # Shift signup integration
└── pages/admin_user.php                     # Admin user edit integration
```

### 1.5 View Layer

```
resources/views/pages/guardian/
├── dashboard.twig            # Guardian home with linked minors
├── register-minor.twig       # Minor registration form
├── minor-profile.twig        # Minor detail view
├── minor-shifts.twig         # Minor shift schedule
├── consent-form.twig         # Printable consent form
└── link-minor.twig           # Link existing minor

resources/views/admin/minors/
└── index.twig                # Admin minor overview

includes/view/
├── Shifts_view.php           # MINOR_RESTRICTED alert display
└── ShiftCalendarShiftRenderer.php  # Minor indicators in calendar
```

---

## 2. Data Model

### 2.1 Entity Relationship Diagram

```
┌─────────────────┐       ┌──────────────────┐
│     User        │       │  MinorCategory   │
├─────────────────┤       ├──────────────────┤
│ id              │◄──────│ id               │
│ minor_category_id│       │ name             │
│ consent_approved│       │ min_shift_start  │
│ ...             │       │ max_shift_end    │
└────────┬────────┘       │ max_hours_per_day│
         │                │ allowed_categories│
         │                │ can_fill_slot    │
         │                │ requires_supervisor│
         │                │ can_self_signup  │
         │                └──────────────────┘
         │
         │ ┌──────────────────────┐
         ├─│    UserGuardian      │
         │ ├──────────────────────┤
         │ │ minor_user_id   ◄────┤ (User as minor)
         │ │ guardian_user_id◄────┤ (User as guardian)
         │ │ is_primary           │
         │ │ relationship_type    │
         │ │ can_manage_account   │
         │ │ valid_from/until     │
         │ └──────────────────────┘
         │
         │ ┌──────────────────────┐
         └─│ UserSupervisorStatus │
           ├──────────────────────┤
           │ user_id         ◄────┤
           │ willing_to_supervise │
           │ training_completed   │
           └──────────────────────┘

┌─────────────────┐       ┌──────────────────┐
│     Shift       │       │   ShiftEntry     │
├─────────────────┤       ├──────────────────┤
│ id              │◄──────│ shift_id         │
│ requires_supervisor│    │ user_id          │
│ minor_supervision_notes│ │ counts_toward_quota│
│ work_category_override│ │ supervised_by_user_id│
│ allows_accompanying│    └──────────────────┘
└─────────────────┘
```

### 2.2 Minor Category Configuration

| Category | Start | End | Max Hours | Work Categories | Fills Slot | Needs Supervisor |
|----------|-------|-----|-----------|-----------------|------------|------------------|
| Accompanying Child | - | - | 0 | None | No | Yes |
| Junior Angel (13-14) | 08:00 | 18:00 | 4 | A | No | Yes |
| Teen Angel (15-17) | 06:00 | 22:00 | 8 | A, B | Yes | Yes |
| Adult (18+) | - | - | - | A, B, C | Yes | No |

---

## 3. Key Workflows

### 3.1 Guardian Registration of Minor

```
Guardian                    GuardianController              GuardianService
   │                              │                              │
   ├─GET /guardian/register──────►│                              │
   │◄─────────────────────────────┤ render register form         │
   │                              │                              │
   ├─POST /guardian/register─────►│                              │
   │                              ├─validate input──────────────►│
   │                              │                              ├─create User
   │                              │                              ├─set minor_category
   │                              │◄─────────────────────────────┤ return minor
   │                              ├─linkGuardianToMinor─────────►│
   │                              │                              ├─create UserGuardian
   │                              │                              │  is_primary=true
   │◄─redirect /guardian/minor/X──┤                              │
```

### 3.2 Consent Approval

```
Admin                    admin_user.php              User Model
  │                           │                           │
  ├─POST consent_action=approve►│                         │
  │                           ├─find user by ID──────────►│
  │                           │◄──────────────────────────┤
  │                           ├─set consent_approved_by───►│
  │                           ├─set consent_approved_at───►│
  │                           │◄─save()───────────────────┤
  │◄─success message──────────┤                           │
```

### 3.3 Shift Signup Validation

```
User                    Shifts_model.php          MinorRestrictionService
  │                           │                           │
  ├─signup request───────────►│                           │
  │                           ├─isMinor()────────────────►│
  │                           │                           │
  │                           ├─canWorkShift()───────────►│
  │                           │                           ├─check consent
  │                           │                           ├─check work category
  │                           │                           ├─check start time
  │                           │                           ├─check end time
  │                           │                           ├─check daily hours
  │                           │                           ├─check supervisor
  │                           │◄─ShiftValidationResult────┤
  │                           │                           │
  │                           │ if !valid:                │
  │◄─MINOR_RESTRICTED────────┤  return restricted state  │
```

---

## 4. Architectural Assessment

### 4.1 Strengths

1. **Clean Separation of Concerns**
   - Models handle data and relationships
   - Services handle business logic
   - Controllers handle HTTP/routing
   - Views handle presentation

2. **Value Objects for Complex Data**
   - `MinorRestrictions` encapsulates restriction rules
   - `ShiftValidationResult` encapsulates validation outcomes
   - Immutable, testable, self-documenting

3. **Comprehensive Validation Pipeline**
   - 7 distinct validation checks
   - Clear error messages for each violation
   - Enforced at model level (not just UI)

4. **Proper Use of Eloquent Features**
   - Relationships: HasMany, BelongsTo
   - Scopes: `valid()`, `primary()`, `active()`, `minorOnly()`
   - Accessors: `getDisplayNameAttribute()`

5. **Integration with Existing System**
   - New `ShiftSignupStatus::MINOR_RESTRICTED` enum value
   - Legacy controller integration via service injection
   - Backward-compatible database changes

### 4.2 Concerns

1. **Mixed Legacy/Modern Code**
   - Some logic in `includes/` procedural files
   - Inconsistent patterns between old and new code
   - **Recommendation:** Continue migrating to PSR-4 services

2. **Controller Layer Thickness**
   - `GuardianController` has some business logic
   - **Recommendation:** Extract to `GuardianService` methods

3. **Missing Repository Pattern**
   - Complex queries embedded in services
   - **Recommendation:** Consider repositories for query encapsulation

4. **Test Coverage**
   - Good unit test coverage for models/services
   - Limited integration test coverage
   - **Recommendation:** Add feature tests for workflows

### 4.3 Code Quality Metrics

| Metric | Assessment |
|--------|------------|
| PSR-12 Compliance | Pass (after PHPCS fixes) |
| PHPStan Level 1 | Pass |
| Test Coverage (Unit) | 100% for new services |
| Cyclomatic Complexity | Low-Medium |
| Documentation | Good (PHPDoc on public methods) |

---

## 5. Integration Points

### 5.1 With Existing User System

- `User` model extended (not replaced)
- New fields added via migration
- Backward-compatible (defaults preserve existing behavior)

### 5.2 With Shift System

- `ShiftSignupStatus` enum extended with `MINOR_RESTRICTED`
- `ShiftSignupState` extended with `minorErrors` array
- Legacy `Shift_signup_allowed()` calls new service

### 5.3 With Permission System

- New `user_guardian` privilege added
- Assigned to Angel group by default
- Admin override logged via existing log system

### 5.4 With Translation System

- All user-facing strings use `__()` function
- Keys added to `additional.po` files
- Both EN and DE translations provided

---

## 6. Recommendations

### 6.1 Short-Term (Before Production)

1. **Add integration tests** for critical workflows (guardian registration, consent approval, shift signup)
2. **Review admin_user.php** consent UI for accessibility

### 6.2 Medium-Term (Next Sprint)

3. **Extract remaining business logic** from `GuardianController` to `GuardianService`
4. **Add repository classes** for complex guardian/minor queries
5. **Migrate shift_entries_controller.php** logic to a `ShiftEntryService`

### 6.3 Long-Term (Backlog)

6. **Consider event-driven architecture** for consent/guardian changes
7. **Add audit logging** for all guardian operations
8. **Implement caching** for frequently-accessed minor restrictions

---

## 7. File Reference

### Core Implementation Files

| File | Lines | Responsibility |
|------|-------|----------------|
| `src/Models/MinorCategory.php` | ~120 | Age-based restriction model |
| `src/Models/UserGuardian.php` | ~130 | Guardian-minor relationship |
| `src/Services/GuardianService.php` | ~500 | Guardian operations |
| `src/Services/MinorRestrictionService.php` | ~300 | Restriction enforcement |
| `src/Controllers/GuardianController.php` | ~500 | Guardian HTTP endpoints |

### Integration Points

| File | Changes | Purpose |
|------|---------|---------|
| `src/Models/User/User.php` | +50 | isMinor(), hasConsentApproved() |
| `includes/model/Shifts_model.php` | +20 | Call MinorRestrictionService |
| `includes/pages/admin_user.php` | +90 | Consent approval UI |

---

## Appendix A: Migration Schema Details

See individual migration files in `db/migrations/2026_01_*` for complete schema definitions.

## Appendix B: Route Configuration

See `config/routes.php` lines 57-83 (guardian routes) and 250-257 (admin routes).
