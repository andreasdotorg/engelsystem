# Shift Assignment Flow

## Shift Creation to Completion

```mermaid
flowchart TD
    subgraph Creation
        A[Admin Creates Shift] --> B[Define Requirements]
        B --> C[Shift Available]
    end

    subgraph Signup
        C --> D[Angel Views Shifts]
        D --> E[Selects Shift]
        E --> F{Qualified?}
        F -->|No| G[Cannot Signup]
        F -->|Yes| H{Slots Available?}
        H -->|No| I[Shift Full]
        H -->|Yes| J{Time Conflict?}
        J -->|Yes| K[Conflict Error]
        J -->|No| L[Create ShiftEntry]
    end

    subgraph Execution
        L --> M[Shift Starts]
        M --> N[Angel Works]
        N --> O[Shift Ends]
    end

    subgraph Completion
        O --> P{Attended?}
        P -->|Yes| Q[Credit Hours]
        P -->|No| R[Mark Freeloaded]
        Q --> S[Update Statistics]
        R --> S
    end
```

## Shift Entry State Machine

```mermaid
stateDiagram-v2
    [*] --> SignedUp: User signs up
    SignedUp --> Working: Shift starts
    SignedUp --> Cancelled: User cancels
    Working --> Completed: Shift ends + attended
    Working --> Freeloaded: Shift ends + no-show
    Completed --> [*]
    Freeloaded --> [*]
    Cancelled --> [*]
```

## Angel Type Requirement Resolution

Shifts can get their angel type requirements from multiple sources:

```mermaid
flowchart TD
    A[Shift Needs Angels] --> B{Direct Requirements?}
    B -->|Yes| C[Use Shift Requirements]
    B -->|No| D{From Schedule?}
    D -->|Yes| E{Use Shift Type?}
    E -->|Yes| F[Use Shift Type Requirements]
    E -->|No| G[Use Location Requirements]
    D -->|No| H[Use Location Requirements]

    C --> I[Final Requirements]
    F --> I
    G --> I
    H --> I
```

## Work Hour Calculation

```mermaid
flowchart LR
    A[Shift Duration] --> B{Night Shift?}
    B -->|Yes| C[Apply Multiplier]
    B -->|No| D[Standard Hours]
    C --> E[Final Hours]
    D --> E
    E --> F[Add to User Total]
    F --> G{Goodie Threshold?}
    G -->|Yes| H[Enable Goodie Claim]
    G -->|No| I[Continue Working]
```
