# Use Cases Overview

This section documents the primary use cases of Engelsystem.

## Core Workflows

### Volunteer (Angel) Use Cases
- [Shift Management](shift-management.md) - How shifts are created and managed
- [Volunteer Signup](volunteer-signup.md) - How volunteers sign up for shifts
- [User Management](user-management.md) - User registration and role management

### Administrative Use Cases
- Shift creation and bulk management
- Schedule import from external systems
- Angel type management
- Location configuration
- News and announcements

### Communication
- Internal messaging
- News announcements
- Question and answer system

## User Journey Overview

```mermaid
flowchart TD
    A[Register Account] --> B[Complete Profile]
    B --> C[Apply for Angel Types]
    C --> D{Approval Required?}
    D -->|Yes| E[Wait for Supporter Approval]
    D -->|No| F[Browse Available Shifts]
    E --> F
    F --> G[Sign Up for Shifts]
    G --> H[Work Shift]
    H --> I[Shift Marked Complete]
    I --> J[Hours Credited]
    J --> K{Enough Hours?}
    K -->|Yes| L[Claim Goodie/T-Shirt]
    K -->|No| F
```
