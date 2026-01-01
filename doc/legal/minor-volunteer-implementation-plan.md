# Minor Volunteer Support Implementation Plan

## Overview

Implementation plan for supporting kids and adolescents as volunteers ("angels") in Engelsystem, based on German youth labor protection law (JArbSchG, KindArbSchV) as documented in `doc/legal/`.

## Design Decisions (User Confirmed)

1. **Guardian UI**: Linked account model - minors have their own user record but guardians control it through their dashboard
2. **Enforcement**: Hard enforcement for minors/guardians - they cannot violate policy. Only Heaven staff can override restrictions when necessary.
3. **Non-counting participation**: Flag on ShiftEntry - boolean flag marking entries as non-counting toward quota
4. **Supervisor consent**: Pre-registration - adults pre-register as willing supervisors, minors can only join if supervisor present
5. **Consent handling**: Paper-based offline consent with arrival validation. Minors cannot take shifts until marked as "arrived" by Heaven after consent form verification. System only records who approved the minor.

---

## Data Model Changes

### Design Principle: Category-Based Restrictions

Rather than storing personal data (date of birth, school status) and calculating restrictions, we define **minor categories** with their associated restrictions. The guardian or self-registering minor selects the applicable category during signup. This follows Datensparsamkeit (data minimization) and simplifies enforcement logic.

### New/Modified Tables

#### 1. `minor_categories` (new, config table)
Defines the available minor restriction categories. Populated via config/seeder, admin-editable.
- `id` INT PK
- `name` VARCHAR(100) - e.g., "Junior Angel", "Teen Angel", "Accompanying Child"
- `description` TEXT - explanation shown during selection
- `min_shift_start_hour` INT (0-23) - earliest shift start allowed (e.g., 8 for 8:00)
- `max_shift_end_hour` INT (0-23) - latest shift end allowed (e.g., 18 for 18:00)
- `max_hours_per_day` INT - maximum shift hours per day (e.g., 2)
- `allowed_work_categories` JSON - array of angel type categories allowed (e.g., ["A"] or ["A", "B"])
- `can_fill_slot` BOOLEAN default true - if false, always counted as extra/accompanying
- `requires_supervisor` BOOLEAN default true - must have supervisor on shift
- `can_self_signup` BOOLEAN default true - if false, only guardian can sign up
- `display_order` INT - for UI ordering
- `is_active` BOOLEAN default true
- `created_at`, `updated_at`

**Default categories (seeded):**
| Name | Hours | Time | Work Cat | Fill Slot | Notes |
|------|-------|------|----------|-----------|-------|
| Accompanying Child | 0 | — | — | No | Under 13, no work, guardian must be present |
| Junior Angel | 2 | 8:00-18:00 | A | Yes | 13-14, or 15-17 school-age during school term |
| Teen Angel | 8 | 6:00-20:00 | A, B | Yes | 15-17 post-school, or school-age during holidays |
| Adult | — | — | A, B, C | Yes | 18+, no restrictions |

#### 2. `users` or `users_personal_data` (modify)
Add:
- `minor_category_id` INT FK → minor_categories.id nullable (null = adult/no restrictions)

#### 3. `user_guardian` (new)
Guardian-minor relationship table (supports multiple guardians per minor):
- `id` INT PK
- `minor_user_id` INT FK → users.id
- `guardian_user_id` INT FK → users.id
- `is_primary` BOOLEAN default false (one primary guardian per minor)
- `relationship_type` ENUM('parent', 'legal_guardian', 'delegated') default 'parent'
- `can_manage_account` BOOLEAN default true (whether this guardian can edit minor's profile)
- `valid_from` DATETIME nullable (for delegated supervision periods)
- `valid_until` DATETIME nullable (for delegated supervision periods)
- `created_at`, `updated_at`
- UNIQUE constraint on (`minor_user_id`, `guardian_user_id`)

#### 3b. `users` (modify for consent tracking)
Add fields for arrival-based consent verification:
- `consent_approved_by_user_id` INT FK → users.id nullable (Heaven user who verified consent)
- `consent_approved_at` DATETIME nullable

Note: Actual consent is captured via paper form and stored offline. System only records that consent was verified and by whom. Minor cannot take shifts until `consent_approved_by_user_id` is set (via arrival process).

#### 4. `user_supervisor_status` (new)
Tracks who is willing to supervise minors:
- `id` INT PK
- `user_id` INT FK → users.id (unique)
- `willing_to_supervise` BOOLEAN default false
- `supervision_training_completed` BOOLEAN default false
- `created_at`, `updated_at`

#### 5. `angel_types` (modify)
Add:
- `work_category` ENUM('A', 'B', 'C') default 'C'
  - 'A' = Suitable for all minors 13+ (light, no hazards)
  - 'B' = Suitable for teen angels only (moderate responsibility)
  - 'C' = Adults only (hazards, alcohol, security, etc.)

#### 6. `shifts` (modify)
Add:
- `requires_supervisor_for_minors` BOOLEAN default true
- `minor_supervision_notes` TEXT nullable

#### 7. `shift_entries` (modify)
Add:
- `counts_toward_quota` BOOLEAN default true
- `supervised_by_user_id` INT FK → users.id nullable (who is supervising this minor)

---

## Implementation Phases

### Phase 1: Core Data Model (Foundation)
**Files:**
- `db/migrations/YYYY_MM_DD_000000_create_minor_categories_table.php`
- `db/migrations/YYYY_MM_DD_000001_add_minor_category_to_users.php`
- `db/migrations/YYYY_MM_DD_000002_create_user_guardian_table.php`
- `db/migrations/YYYY_MM_DD_000003_create_user_supervisor_status_table.php`
- `db/migrations/YYYY_MM_DD_000004_add_work_category_to_angel_types.php`
- `db/migrations/YYYY_MM_DD_000005_add_minor_fields_to_shifts.php`
- `db/migrations/YYYY_MM_DD_000006_add_quota_flag_to_shift_entries.php`
- `src/Models/MinorCategory.php` (new)
- `src/Models/User/User.php` (modify - add minor_category relation)
- `src/Models/UserGuardian.php` (new)
- `src/Models/UserSupervisorStatus.php` (new)
- `src/Models/AngelType.php` (modify - add work_category)
- `src/Models/Shifts/Shift.php` (modify)
- `src/Models/Shifts/ShiftEntry.php` (modify)

### Phase 2: Minor Category Service
**Files:**
- `src/Models/MinorCategory.php` (new)
- `src/Services/MinorRestrictionService.php` (new)
- `db/migrations/YYYY_MM_DD_000006_seed_default_minor_categories.php` (seeder)

**Functionality:**
- `getCategory(User $user): ?MinorCategory` - returns user's category or null if adult
- `isMinor(User $user): bool` - true if user has a minor_category_id set
- `canWorkShift(User $user, Shift $shift): ValidationResult` - checks all restrictions
- `canWorkAngelType(User $user, AngelType $angelType): bool` - checks work category
- `getRestrictions(User $user): MinorRestrictions` - returns restriction object
- `getDailyHoursRemaining(User $user, Carbon $date): int` - checks accumulated hours

**MinorRestrictions value object:**
```php
class MinorRestrictions {
    public ?int $minShiftStartHour;      // null = no restriction
    public ?int $maxShiftEndHour;        // null = no restriction
    public ?int $maxHoursPerDay;         // null = no restriction
    public array $allowedWorkCategories; // ['A'], ['A','B'], or ['A','B','C']
    public bool $canFillSlot;            // false = always extra/accompanying
    public bool $requiresSupervisor;
    public bool $canSelfSignup;
}
```

**No school holiday logic needed:** The guardian/minor selects the appropriate category at signup time. If a 15-17 year old is still in school but the event is during holidays, the guardian simply selects "Teen Angel" instead of "Junior Angel". The system doesn't need to know why - just what restrictions apply.

### Phase 3: Guardian Management
**Files:**
- `src/Controllers/GuardianController.php` (new)
- `src/Services/GuardianService.php` (new)
- `resources/views/pages/guardian/` (new directory)
  - `dashboard.twig` - Guardian's view of linked minors
  - `link-minor.twig` - Form to link a minor account
  - `consent-form.twig` - Parental consent capture
  - `minor-profile.twig` - View/edit minor's profile
- `config/routes.php` (modify - add guardian routes)

**Functionality:**
- Guardian dashboard showing linked minors
- Link existing minor account or register new minor
- Parental consent capture and storage
- Guardian can sign up minors for shifts
- Guardian can view minor's shift history

### Phase 4: Registration Flow Updates
**Files:**
- `src/Controllers/RegistrationController.php` (modify)
- `src/Factories/User.php` (modify)
- `resources/views/pages/registration.twig` (modify)

**Changes:**
- Add minor category selection to registration
- If minor category selected:
  - Show guardian-managed vs self-managed workflow
  - For self-managed: require guardian linking post-registration
  - For guardian registering minor: create linked account
- Category descriptions explain applicable restrictions

### Phase 5: Angel Type Work Category Classification
**Files:**
- `src/Controllers/Admin/AngelTypesController.php` (modify)
- `src/Controllers/AngelTypesController.php` (modify)
- `resources/views/admin/angeltypes/edit.twig` (modify)
- `resources/views/pages/angeltypes/about.twig` (modify)

**Changes:**
- Add work category selector to angel type admin
- Display work category restrictions on angel type pages
- Filter angel types by user's minor category eligibility

### Phase 6: Supervisor Pre-Registration
**Files:**
- `src/Controllers/SettingsController.php` (modify)
- `resources/views/pages/settings/settings.twig` (modify)
- `src/Services/SupervisorService.php` (new)

**Changes:**
- Add "willing to supervise minors" checkbox in user settings
- Track supervision training status (manual flag by admin)
- Query for available supervisors

### Phase 7: Shift Signup Validation
**Files:**
- `src/Services/ShiftSignupService.php` (new or modify existing)
- `includes/pages/user_shifts.php` (modify - legacy page)
- `src/Controllers/ShiftsController.php` (modify)

**Validation Rules:**
1. Check angel type work category vs user's minor category allowed work categories
2. Check shift time vs permitted hours from user's minor category
3. Check daily hour accumulation vs max_hours_per_day
4. Check if supervisor is present (if requires_supervisor = true)
5. Check consent approval status (must be approved before signup)

**Enforcement Model:**
- **Hard enforcement** for minors and guardians - violations block signup
- Authorized users (Heaven) can override restrictions
- All overrides are logged with reason

### Phase 8: Non-Counting Participation
**Files:**
- `includes/pages/user_shifts.php` (modify)
- `src/Controllers/Admin/ShiftsController.php` (modify)
- Shift calendar views (modify)

**Changes:**
- Add "counts toward quota" toggle on shift entry creation
- Default: true for 13+ minors, false for accompanying under-13
- Display distinction in shift views
- Quota calculations respect the flag

### Phase 9: Shift Supervision Tracking
**Files:**
- `src/Controllers/ShiftEntryController.php` (new or enhance)
- Shift detail views (modify)

**Changes:**
- When minor signs up, must select supervisor from shift
- Supervisor must have `willing_to_supervise = true`
- Guardian automatically eligible as supervisor
- Track supervision relationship in `shift_entries.supervised_by_user_id`

### Phase 10: Dashboard & Reporting
**Files:**
- `src/Controllers/Admin/MinorManagementController.php` (new)
- `resources/views/admin/minors/` (new directory)
- Heaven dashboard updates

**Functionality:**
- List all registered minors with their guardians
- View consent status
- Daily hour tracking per minor
- Supervision gap detection (shifts with minors but no supervisor)

---

## User Stories

### US-01: Guardian Registration Flow
**As a** guardian
**I want to** register my minor child as an angel
**So that** they can participate in volunteer shifts under my supervision

**Acceptance Criteria:**

*Account Creation:*
- Guardian can create a linked minor account from their dashboard
- Guardian selects appropriate minor category (Accompanying Child, Junior Angel, Teen Angel)
- Minor receives their own user ID but is marked as guardian-managed
- Minor's username is unique and follows system naming conventions
- Guardian is automatically set as primary guardian with `relationship_type = 'parent'`
- Category selection shows clear descriptions of restrictions for each option

*Consent Handling:*
- System generates printable consent form template based on policy requirements
- Guardian prints, completes, and brings paper form to event
- Minor account is created but marked as "pending consent verification"
- Consent is verified in person at arrival by Heaven staff
- Heaven staff marks minor as "arrived" and records who approved the consent
- Minor cannot sign up for shifts until consent is verified and arrival is marked

*Category Selection:*
- Guardian selects minor category from available options
- Category determines all restrictions (hours, time of day, work categories)
- Category can be changed later from guardian dashboard (e.g., upgrading from Junior to Teen Angel)
- System does not ask why a category is chosen - just which restrictions apply

*Multiple Guardians:*
- Additional guardians can be added after initial registration
- Each guardian relationship has a `relationship_type` (parent, legal_guardian, delegated)
- Delegated guardians can have time-limited validity (`valid_from`, `valid_until`)
- Secondary guardians can optionally have `can_manage_account = false` (supervision only)
- Only primary guardian can remove other guardians

*Error Handling:*
- Clear error message if "Accompanying Child" category cannot perform volunteer work
- Validation prevents duplicate minor accounts for same guardian
- Session timeout handling preserves form data

### US-02: Minor Self-Registration (13+)
**As a** minor aged 13-17
**I want to** register my own account
**So that** I can participate as an angel with appropriate restrictions

**Acceptance Criteria:**

*Registration Form:*
- Standard registration form with minor category selection
- Available categories: Junior Angel, Teen Angel (not Accompanying Child, as that requires guardian)
- Clear explanation of restrictions for each category
- Account is created but marked as "pending guardian consent"

*Category Selection:*
- Minor selects their own category based on their situation
- Descriptions guide selection (e.g., "Junior Angel: 13-14, or 15-17 still in school during term time")
- No system verification of why category was chosen - user responsibility
- Category stored in `minor_category_id`

*Guardian Linking:*
- After registration, minor is prompted to link a guardian
- Minor can enter guardian's email or username to send a link request
- Alternative: guardian can enter minor's registration code to link
- Multiple guardians can be linked (both parents, etc.)
- At least one guardian must be linked before arrival/consent process

*Consent Process:*
- Linked guardian receives notification with link to printable consent form
- Guardian prints, completes, and brings paper form to event
- Minor account shows status: "Awaiting consent verification at arrival"
- Consent is verified in person by Heaven staff at arrival
- Heaven staff marks minor as "arrived" and records who approved

*Pre-Arrival State:*
- Minor can log in and browse angel types and shifts
- Minor cannot sign up for shifts until arrival is confirmed
- Dashboard shows clear status: "Awaiting arrival check-in"
- Profile shows "Pending" status visible to Heaven staff
- Can update own profile information

### US-03: Angel Type Work Category Classification
**As an** admin
**I want to** classify angel types by work category
**So that** minors are only shown shifts they can legally work

**Acceptance Criteria:**

*Admin Configuration:*
- Admin edit form includes work category dropdown: A, B, C
- Default category for new angel types is C (adults only) - fail-safe
- Category selection includes tooltip explaining each category's meaning:
  - A: Suitable for all minors 13+ (light work, no hazards)
  - B: Teen Angels only (moderate responsibility)
  - C: Adults only (hazards, alcohol, security, etc.)
- Bulk edit capability to update multiple angel types at once
- Audit log records category changes with timestamp and admin user

*Category Display:*
- Angel type list view shows work category badge/icon
- Color coding: A = green, B = yellow, C = red (or similar visual distinction)
- Category tooltip shows full description on hover
- Public angel type pages clearly display category and who is eligible
- Search/filter by work category in admin view

*User Filtering:*
- Angel type listings only show types user is eligible for based on their minor category
- Filtering uses `minor_category.allowed_work_categories` JSON field
- Ineligible angel types can optionally be shown greyed out with explanation
- Guardian dashboard shows all categories but marks which their minor is eligible for

*Validation Rules:*
- Cannot assign work category A angel type if user's minor category doesn't include A
- Cannot assign work category B angel type if user's minor category doesn't include B
- Cannot assign work category C angel type to any user with a minor category set
- Validation prevents saving shift entry if work category doesn't match user eligibility

*Migration & Defaults:*
- Migration sets all existing angel types to Category C (safest default)
- Admin must explicitly review and recategorize each angel type
- System flag tracks whether angel type review has been completed

### US-04: Shift Signup Validation
**As a** minor or guardian
**I want to** understand why I cannot sign up for certain shifts
**So that** I can find appropriate shifts within my restrictions

**Acceptance Criteria:**

*Time Restriction Validation:*
- Block signup when shift starts before `minor_category.min_shift_start_hour`
- Block signup when shift ends after `minor_category.max_shift_end_hour`
- Error message shows user's specific permitted hours from their category
- Message shows how many minutes/hours the shift violates the restriction

*Daily Hour Validation:*
- Block signup when adding shift would exceed `minor_category.max_hours_per_day`
- Error shows current accumulated hours for the day + proposed shift hours
- Error includes breakdown of other shifts already scheduled that day
- System considers all shifts across all angel types for daily total

*Supervisor Validation:*
- Block signup when no willing supervisor is currently signed up for the shift (if `minor_category.requires_supervisor = true`)
- Block signup when shift requires supervision but minor hasn't selected a supervisor
- If selected supervisor drops out of shift after minor signed up, alert minor and Heaven
- Guardian counts as automatic supervisor - no block if guardian on same shift
- Show list of available supervisors on the shift (if any)

*Work Category Validation:*
- Block signup if angel type work category not in `minor_category.allowed_work_categories`
- Clear message explaining which categories the user is permitted

*Consent Validation:*
- Block signup if `consent_approved_by_user_id` is null (consent not yet verified)
- Message: "Your consent must be verified at arrival before you can sign up for shifts"

*Hard Enforcement for Minors/Guardians:*
- All policy violations block signup - minors and guardians cannot override
- Clear error messages explain what restriction was violated
- Suggestions shown for alternative shifts that meet restrictions
- Shift calendar can filter to show only eligible shifts

*Heaven Override Capability:*
- Heaven staff can override any restriction when signing up a minor
- Override requires selecting a reason from predefined list or entering custom reason
- All overrides are logged with: timestamp, minor, shift, restriction type, overriding staff, reason
- Override log is searchable and exportable
- Guardian is notified when Heaven overrides a restriction for their minor

*Shift Calendar Integration:*
- Shifts user is ineligible for are visually distinguished (greyed out, different icon)
- Hover/click shows reason for ineligibility
- Filter option: "Show only shifts I can sign up for"
- Color-coded indicators for time restrictions, work category restrictions

### US-05: Supervisor Pre-Registration
**As an** adult angel
**I want to** indicate my willingness to supervise minors
**So that** I can be assigned as a supervisor on shifts

**Acceptance Criteria:**

*User Settings:*
- Checkbox in user settings: "I am willing to supervise minor volunteers during my shifts"
- Setting is only visible to users without a minor_category (adults)
- Setting explanation includes summary of supervisor responsibilities
- Link to full supervisor responsibilities document/policy
- Setting can be changed at any time (on/off)

*Training Tracking:*
- Optional "supervision training completed" flag (admin-only edit)
- Some events may require training before supervision is allowed
- Training completion date is recorded
- Admin can bulk-update training status (e.g., after training session)
- Training status shown in user's profile to Heaven staff

*Guardian Auto-Eligibility:*
- Users with linked minors are automatically eligible to supervise their own minors
- Guardian can supervise their own minor without checking the general willingness box
- System recognizes guardian relationship and bypasses supervisor requirement
- Guardian supervising own minor is recorded as supervision relationship

*Supervisor Assignment:*
- When minor signs up for shift (and category requires supervision), system shows list of willing supervisors on that shift
- Minor (or guardian) selects supervisor from dropdown
- Selected supervisor receives notification of supervision assignment
- Supervisor can decline supervision (with notification to minor/guardian)
- If supervisor drops out of shift, system alerts minor and Heaven

*Shift View Integration:*
- Shift detail shows count of willing supervisors signed up
- Supervisor icon/badge on shift calendar for shifts with supervisors
- Heaven can filter shifts by "needs supervisor for minors"
- Warning when shift has minors but no willing supervisors

*Limits & Ratios:*
- Optional configuration: maximum minors per supervisor (e.g., 3:1 ratio)
- Warning when supervisor ratio would be exceeded
- Heaven can override ratio limits with logging

### US-06: Non-Counting Participation
**As a** guardian with an accompanying child
**I want to** register my child as accompanying me on a shift
**So that** they can be present without counting toward the shift quota

**Acceptance Criteria:**

*Registration of Accompanying Minor:*
- Guardian can add accompanying minor from shift signup flow
- Accompanying minor must be linked to guardian's account with "Accompanying Child" category
- System validates minor has `can_fill_slot = false` in their category
- For other minor categories, "non-counting" can be set as an optional flag with justification
- Non-counting option requires written explanation when used for categories with `can_fill_slot = true`

*Shift Entry Creation:*
- `counts_toward_quota = false` is set based on `minor_category.can_fill_slot`
- Shift entry still records the minor's participation (for tracking, not quota)
- Entry shows guardian as automatic supervisor
- Entry includes note that this is accompanying participation

*Quota Calculations:*
- Shift "filled" calculation excludes non-counting entries
- Display shows separate counts: "3/5 + 2 accompanying"
- Shift is not shown as "full" based on non-counting entries
- Statistics and reports can filter by counting vs non-counting

*Visual Distinction:*
- Non-counting entries shown in different color/style in shift roster
- Icon or badge indicating "accompanying" status
- Tooltip explains what non-counting means
- List views can toggle showing/hiding non-counting entries

*Activity Restrictions for Accompanying Minors:*
- Per policy, guardian can only take certain shifts when accompanied (per Section 7.2 of policy)
- System shows warning if guardian signs up for incompatible shift with accompanying minor
- Incompatible activities: Bar, Security, NOC, night shifts, activities requiring undivided attention
- Guardian must acknowledge restrictions when adding accompanying minor

*Guardian Assignment Validation:*
- Guardian must be signed up for the same shift to have accompanying minor
- If guardian drops out of shift, warning about accompanying minor
- Option to auto-remove accompanying minor if guardian drops out
- Only guardian (not delegated supervisors) can have accompanying children

*Documentation:*
- Registration of accompanying minor requires acknowledgment of:
  - Child will not perform volunteer work
  - Guardian maintains direct supervision
  - Guardian is responsible for child's safety and behavior
  - Guardian has plan for child's needs (food, rest, restroom)
- Documentation stored with shift entry

### US-07: Heaven Minor Overview
**As a** Heaven staff member
**I want to** see all minors, their guardians, and consent status
**So that** I can ensure compliance with policies

**Acceptance Criteria:**

*Dashboard Overview:*
- Dedicated "Minors" section in Heaven admin area
- Table listing all registered minors with: name, minor category, consent status
- Quick stats: total minors, by category, pending consent, currently on shift
- Filter by: minor category, consent status, has guardian on-site
- Search by minor name or guardian name

*Guardian Information:*
- Each minor row shows linked guardians
- Primary guardian highlighted
- Guardian contact information accessible (phone, email)
- Indication of which guardians are currently at the event (logged in recently)
- Multiple guardians shown with relationship type

*Consent Status Display:*
- Clear status indicators: Approved / Pending Arrival
- Approval date and approving staff member shown
- Minor category and its restrictions shown
- Flag for consent issues or notes
- Note: Actual paper consent forms stored offline per Datensparsamkeit

*Daily Hour Tracking:*
- Current day's accumulated hours per minor
- Progress bar showing hours used vs limit (from `minor_category.max_hours_per_day`)
- Warning when approaching limit (e.g., 80% used)
- Alert when limit exceeded (with override log if any)
- Historical daily hours viewable by date

*Supervision Monitoring:*
- List of shifts with minors (who require supervision) but no supervisor assigned
- Alert panel for supervision gaps
- Quick action: assign supervisor from available willing adults
- Filter shifts by: has minor, needs supervisor, supervision OK

*Override Audit Log:*
- Searchable log of all policy overrides
- Fields: timestamp, minor, shift, warning type, overriding staff, reason
- Export capability (CSV, PDF)
- Highlight recent overrides for review

*Real-Time Status:*
- Currently active shifts with minors
- Minor check-in/check-out status (if integrated)
- Location of minors (which shift/area)
- Supervisor status for each active minor

*Reporting:*
- Export minor participation report
- Compliance summary for event organizers
- Incident log for minor-related issues
- Statistics: hours worked by category, category distribution

*Quick Actions:*
- Approve minor at arrival (verify paper consent, mark as approved)
- Add guardian to minor
- Change minor's category
- Sign minor up for shift (with override capability)
- Remove minor from shift
- Mark supervision training complete for adult
- View/add notes to minor's record

---

## Critical Files Summary

### New Models
- `src/Models/MinorCategory.php`
- `src/Models/UserGuardian.php`
- `src/Models/UserSupervisorStatus.php`

### New Services
- `src/Services/MinorRestrictionService.php`
- `src/Services/GuardianService.php`
- `src/Services/SupervisorService.php`
- `src/Services/ShiftSignupService.php`

### New Controllers
- `src/Controllers/GuardianController.php`
- `src/Controllers/Admin/MinorManagementController.php`

### Modified Models
- `src/Models/User/User.php` (add minor_category relation)
- `src/Models/AngelType.php` (add work_category)
- `src/Models/Shifts/Shift.php` (add minor supervision fields)
- `src/Models/Shifts/ShiftEntry.php` (add quota flag, supervisor relation)

### Modified Controllers
- `src/Controllers/RegistrationController.php`
- `src/Controllers/SettingsController.php`
- `src/Controllers/Admin/AngelTypesController.php`
- `src/Controllers/ShiftsController.php`

### New Views
- `resources/views/pages/guardian/dashboard.twig`
- `resources/views/pages/guardian/link-minor.twig`
- `resources/views/pages/guardian/consent-form.twig`
- `resources/views/admin/minors/index.twig`

### Modified Views
- `resources/views/pages/registration.twig`
- `resources/views/pages/settings/settings.twig`
- `resources/views/admin/angeltypes/edit.twig`
- Various shift-related views

---

## Implementation Order (Recommended)

| Order | Phase | Description | Dependencies |
|-------|-------|-------------|--------------|
| 1 | Phase 1 | Core data model migrations | None |
| 2 | Phase 2 | Minor category service | Phase 1 |
| 3 | Phase 5 | Angel type work category classification | Phase 1, 2 |
| 4 | Phase 6 | Supervisor pre-registration | Phase 1 |
| 5 | Phase 4 | Registration flow updates | Phase 1, 2 |
| 6 | Phase 3 | Guardian management | Phase 1, 2, 4 |
| 7 | Phase 7 | Shift signup validation | Phase 2, 3, 5, 6 |
| 8 | Phase 8 | Non-counting participation | Phase 7 |
| 9 | Phase 9 | Shift supervision tracking | Phase 6, 7 |
| 10 | Phase 10 | Dashboard & reporting | All previous |

---

## Notes

- Implementation based on German youth labor protection law (JArbSchG, KindArbSchV)
- Category-based approach follows Datensparsamkeit (data minimization) principle
- No school holiday logic needed - user selects appropriate category at signup
- Consent forms are paper-based and stored offline; system only tracks approval
- Consider i18n from the start for UI elements

## Roles and Permissions

| Action | Minor | Guardian | Heaven | Admin |
|--------|-------|----------|--------|-------|
| Create minor account | Self-register | Create linked | Create any | Create any |
| Select minor category | Yes (self) | Yes (for linked) | Yes | Yes |
| Link guardian to minor | Request | Accept/initiate | Assign | Assign |
| Sign up for shift | If consent approved | For linked minor | For any minor | For any minor |
| Override policy violations | No | No | Yes (logged) | Yes (logged) |
| Approve minor consent (arrival) | No | No | Yes | Yes |
| Change minor category | No | Yes (for linked) | Yes | Yes |
| View minor dashboard | Own only | Linked minors | All minors | All minors |
| Mark willing to supervise | N/A | Yes (if adult) | Yes | Yes |

**Key permission rules:**
- Minors and guardians cannot bypass/override policy restrictions
- Only Heaven and Admin can override restrictions (all overrides are logged)
- Minor cannot take shifts until consent is approved during arrival check
- Guardian can act on behalf of linked minor for most actions except policy override
