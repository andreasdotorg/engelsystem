# Authentication Flow

## Session-Based Authentication (Web)

```mermaid
sequenceDiagram
    participant User
    participant Browser
    participant Middleware
    participant Authenticator
    participant Session
    participant Database

    User->>Browser: Enter credentials
    Browser->>Middleware: POST /login
    Middleware->>Authenticator: authenticate(username, password)
    Authenticator->>Database: Query user
    Database-->>Authenticator: User record
    Authenticator->>Authenticator: verify_password()

    alt Password Valid
        Authenticator->>Session: Store user_id
        Authenticator-->>Middleware: Success
        Middleware-->>Browser: Redirect to dashboard
    else Password Invalid
        Authenticator-->>Middleware: Failure
        Middleware-->>Browser: Show error
    end
```

## API Key Authentication

```mermaid
sequenceDiagram
    participant Client
    participant API
    participant Authenticator
    participant Database

    Client->>API: Request with API key
    Note right of Client: Authorization: Bearer <key>
    API->>Authenticator: userFromApi()

    alt Bearer Token
        Authenticator->>Authenticator: Extract from header
    else X-API-Key Header
        Authenticator->>Authenticator: Extract from header
    else Query Parameter
        Authenticator->>Authenticator: Extract from ?api_key=
    end

    Authenticator->>Database: Query user by api_key
    Database-->>Authenticator: User or null

    alt User Found
        Authenticator-->>API: User object
        API-->>Client: Response data
    else No User
        Authenticator-->>API: null
        API-->>Client: 401 Unauthorized
    end
```

## OAuth 2.0 Flow

```mermaid
sequenceDiagram
    participant User
    participant Engelsystem
    participant OAuthProvider

    User->>Engelsystem: Click OAuth login
    Engelsystem->>OAuthProvider: Redirect to authorize
    OAuthProvider->>User: Login prompt
    User->>OAuthProvider: Authenticate
    OAuthProvider->>Engelsystem: Redirect with code
    Engelsystem->>OAuthProvider: Exchange code for token
    OAuthProvider-->>Engelsystem: Access token
    Engelsystem->>OAuthProvider: Request user info
    OAuthProvider-->>Engelsystem: User profile

    alt New User
        Engelsystem->>Engelsystem: Create user account
    else Existing User
        Engelsystem->>Engelsystem: Update profile
    end

    Engelsystem->>Engelsystem: Create session
    Engelsystem-->>User: Redirect to dashboard
```

## Permission Checking

```mermaid
flowchart TD
    A[Request Action] --> B{User Authenticated?}
    B -->|No| C[Redirect to Login]
    B -->|Yes| D[Get User Groups]
    D --> E[Get Group Privileges]
    E --> F{Has Required Privilege?}
    F -->|Yes| G[Allow Action]
    F -->|No| H[403 Forbidden]
```

## Password Hashing

The system uses PHP's password_hash with automatic algorithm upgrades:

```php
// During login
if (password_needs_rehash($user->password, PASSWORD_DEFAULT)) {
    $user->password = password_hash($plaintext, PASSWORD_DEFAULT);
    $user->save();
}
```
