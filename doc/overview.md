# System Overview

## What is Engelsystem?

Engelsystem is a shift planning and volunteer management system originally developed for the Chaos Communication Congress. It helps organize volunteers ("angels") who help run large events by:

- Managing shift schedules across multiple locations
- Tracking volunteer signups and qualifications
- Handling different volunteer types (angel types) with specific skills
- Recording work hours and providing "goodie" rewards
- Facilitating communication between organizers and volunteers

## Key Concepts

### Angels (Volunteers)
Users who sign up to help at events. Each angel has:
- A profile with contact information
- Qualifications (angel types they're approved for)
- Work history and accumulated hours
- Goodie/t-shirt eligibility based on worked hours

### Angel Types
Categories of volunteer work requiring specific skills or qualifications:
- Some are self-signup (anyone can join)
- Some require supporter approval
- Some require specific training or background checks
- Examples: "Info Desk", "Bar", "Security", "Tech Support"

### Shifts
Time-bounded work assignments at specific locations:
- Have start/end times
- Belong to a shift type (defines the kind of work)
- Located at a specific location
- Require certain numbers of specific angel types
- Can be imported from external schedules (Frab/Pretalx)

### Locations
Physical or virtual places where shifts occur:
- Have names and descriptions
- Can have DECT phone numbers and map URLs
- Associated with needed angel types

### Shift Types
Templates defining categories of work:
- Used to group similar shifts
- Can define default angel type requirements

### Schedules
External event schedules (from Frab/Pretalx) that can be imported to create shifts automatically.

## User Roles and Permissions

Engelsystem uses a group-based permission system:

### Groups
- **Guest** - Unauthenticated visitors
- **Angel** - Registered volunteers (default group for new users)
- **Shift Coordinator** - Can manage shifts
- **Supporter** - Can approve angel type applications
- **Bureaucrat** - Administrative tasks
- **Developer** - System administration

### Privileges
Fine-grained permissions assigned to groups:
- `user.arrive` - Mark users as arrived
- `shifts.edit` - Edit shift details
- `angel_types` - Manage angel types
- `admin_groups` - Manage user groups
- And many more...

## Core Features

### Shift Management
- Create, edit, delete shifts
- Import from Frab/Pretalx schedules
- Automatic angel type requirement inheritance
- Night shift detection with bonus multipliers

### Volunteer Management
- Self-registration with email verification
- Angel type applications and approvals
- Work hour tracking
- Goodie/reward distribution

### Communication
- Internal messaging system
- News announcements
- Questions and answers system

### Reporting
- Work hour statistics
- Angel type coverage
- Shift fill rates

## Architecture Overview

Engelsystem is a PHP web application using:
- Custom MVC-like architecture with service providers
- Laravel Illuminate components (not full Laravel)
- Eloquent ORM for database access
- Twig templating engine
- Bootstrap 5 frontend
