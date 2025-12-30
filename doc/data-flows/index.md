# Data Flows Overview

This section documents how data moves through Engelsystem.

## Key Data Flows

- [Authentication Flow](authentication.md) - How users authenticate
- [Shift Assignment Flow](shift-assignment.md) - How shifts get assigned to users

## System Integration Points

```mermaid
flowchart LR
    subgraph External
        OAuth[OAuth Providers]
        Frab[Frab/Pretalx]
        Cal[Calendar Apps]
    end

    subgraph Engelsystem
        Auth[Authentication]
        Schedule[Schedule Import]
        API[REST API]
        iCal[iCal Export]
    end

    OAuth --> Auth
    Frab --> Schedule
    API --> Cal
    iCal --> Cal
```
