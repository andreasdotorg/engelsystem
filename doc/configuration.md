# Configuration Reference

## Configuration Files

| File | Purpose |
|------|---------|
| `config/config.php` | Main configuration (not in git) |
| `config/config.default.php` | Default values |
| `config/app.php` | Application structure |
| `config/routes.php` | Route definitions |

## Creating Configuration

```bash
cp config/config.default.php config/config.php
# Edit config/config.php with your values
```

## Core Settings

### Application

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| `app_name` | string | "Engelsystem" | Application title |
| `environment` | string | "production" | "production" or "development" |
| `theme` | integer | 0 | Theme ID |
| `display_full_name` | boolean | false | Show full name instead of username |

### Database

```php
'database' => [
    'host'     => 'localhost',
    'port'     => 3306,
    'database' => 'engelsystem',
    'username' => 'engelsystem',
    'password' => 'secret',
],
```

### Email

```php
'email' => [
    'driver'     => 'smtp',           // mail, smtp, sendmail, log
    'host'       => 'localhost',
    'port'       => 587,
    'encryption' => 'tls',            // tls, ssl, null
    'username'   => '',
    'password'   => '',
    'from'       => [
        'address' => 'engelsystem@example.com',
        'name'    => 'Engelsystem',
    ],
],
```

### Session

```php
'session' => [
    'driver' => 'pdo',              // pdo (database), native (file)
    'name'   => 'engelsystem_session',
],
```

## Feature Toggles

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| `registration_enabled` | boolean | true | Allow self-registration |
| `signup_requires_arrival` | boolean | true | Must arrive before shift signup |
| `signup_advance_hours` | integer | 12 | Hours before shift signup closes |
| `signup_post_fraction` | float | 0.25 | Fraction of shift that can pass before no signup |
| `voucher_enabled` | boolean | true | Enable voucher system |
| `voucher_initial` | integer | 0 | Initial vouchers per user |
| `enable_dect` | boolean | true | Enable DECT phone field |
| `enable_mobile_show` | boolean | true | Allow showing mobile to others |
| `enable_email_show` | boolean | true | Allow showing email to others |

## Goodie/T-Shirt Configuration

```php
'goodie' => [
    'enabled' => true,
    'type'    => 'tshirt',          // tshirt, goodie, none
],

'goodie_tshirt' => [
    'enabled' => true,
    'hours'   => 12,                // Hours needed for goodie
],

'tshirt_sizes' => [
    'S'    => 'Small',
    'M'    => 'Medium',
    'L'    => 'Large',
    'XL'   => 'X-Large',
    'XXL'  => 'XX-Large',
    '3XL'  => '3X-Large',
],
```

## Night Shift Configuration

```php
'night_shifts' => [
    'enabled'    => true,
    'start'      => 2,              // Night starts at 02:00
    'end'        => 6,              // Night ends at 06:00
    'multiplier' => 2.0,            // Hour multiplier
],
```

## Security Settings

```php
'password_algorithm' => PASSWORD_DEFAULT,
'password_min_length' => 8,

'headers' => [
    'X-Content-Type-Options'  => 'nosniff',
    'X-Frame-Options'         => 'sameorigin',
    'Referrer-Policy'         => 'strict-origin-when-cross-origin',
    'Content-Security-Policy' => "default-src 'self'; ...",
],

'trusted_proxies' => [
    '127.0.0.1',
    '10.0.0.0/8',
    '172.16.0.0/12',
    '192.168.0.0/16',
],
```

## OAuth Configuration

```php
'oauth' => [
    'keycloak' => [
        'client_id'     => 'engelsystem',
        'client_secret' => 'secret',
        'url_auth'      => 'https://keycloak.example.com/realms/myrealm/protocol/openid-connect/auth',
        'url_token'     => 'https://keycloak.example.com/realms/myrealm/protocol/openid-connect/token',
        'url_info'      => 'https://keycloak.example.com/realms/myrealm/protocol/openid-connect/userinfo',
        'id'            => 'sub',
        'username'      => 'preferred_username',
        'email'         => 'email',
        'first_name'    => 'given_name',
        'last_name'     => 'family_name',
        'groups'        => [1],     // Default group IDs
        'mark_arrived'  => false,
    ],
],
```

## API Configuration

```php
'api' => [
    'enabled' => true,
],
```

## Themes

Available themes (historical CCC congress themes):

| ID | Name | Event |
|----|------|-------|
| 0 | Default (Bootstrap) | - |
| 1 | 36C3 | 36C3 Resource Exhaustion |
| 2 | 35C3 | 35C3 Refreshing Memories |
| ... | ... | ... |

## Locale Configuration

```php
'default_locale' => 'en_US',

'locales' => [
    'de_DE' => 'Deutsch',
    'en_US' => 'English',
    // ... more locales
],
```

## Admin Configuration Options

Many settings can be changed through the admin interface (Admin > Config). These are stored in the database and override file-based configuration.

Available options include:
- Event information (name, dates, welcome message)
- Registration settings
- Goodie settings
- Theme selection
- And many more...
