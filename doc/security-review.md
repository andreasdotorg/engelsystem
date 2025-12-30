# Engelsystem Security Review

**Review Date:** December 2024
**Codebase Version:** Current main branch
**Review Scope:** Authentication, Authorization, Input Validation, Session Security, API Security, Legacy Code

---

## Executive Summary

Engelsystem demonstrates solid security practices in its modern codebase (`src/`), with proper use of cryptographic primitives, session security, and CSRF protection. However, significant gaps exist in rate limiting, and the legacy `includes/` directory presents audit challenges. The application would benefit from implementing rate limiting and reviewing `|raw` Twig filter usage.

### Security Assessment

| Area | Rating | Summary |
|------|--------|---------|
| Authentication | **B+** | Strong password handling, secure token generation |
| Session Security | **A-** | Proper fixation protection, secure cookies |
| CSRF Protection | **A** | Timing-safe comparison, comprehensive coverage |
| Authorization | **B** | Permission-based, some legacy gaps |
| Input Validation | **B-** | ORM protection, some `|raw` filter risks |
| API Security | **C+** | Auth implemented, no rate limiting |
| Security Headers | **B** | Good defaults, HSTS disabled |
| Legacy Code | **C** | Harder to audit, uses escaping |

---

## 1. Authentication

### 1.1 Password Security

**Location:** `src/Helpers/Authenticator.php`

**Strengths:**
- Uses `PASSWORD_DEFAULT` algorithm (currently bcrypt, auto-upgrades)
- Automatic password rehashing when algorithm improves
- Minimum password length enforced via configuration

```php
// Password verification with auto-rehash
public function verifyPassword(User $user, string $password): bool
{
    if (!password_verify($password, $user->password)) {
        return false;
    }

    if (password_needs_rehash($user->password, $this->passwordAlgorithm)) {
        $user->password = password_hash($password, $this->passwordAlgorithm);
        $user->save();
    }

    return true;
}
```

**Rating:** Excellent

### 1.2 API Key Generation

**Location:** `src/Helpers/Authenticator.php:108-112`

**Implementation:**
```php
public function resetApiKey(User $user): void
{
    $user->api_key = bin2hex(random_bytes(32));
    $user->save();
}
```

**Assessment:**
- Uses CSPRNG (`random_bytes()`) - cryptographically secure
- 64-character hex string (256 bits of entropy) - sufficient
- API keys stored in plaintext in database

**Recommendation:** Consider hashing API keys (store hash, compare hash) to prevent exposure if database is compromised.

### 1.3 API Authentication Methods

**Location:** `src/Helpers/Authenticator.php:73-84`

```php
public function apiUser(string $parameter = 'api_key'): ?User
{
    $params = $this->request->getQueryParams();
    $queryKey = $params[$parameter] ?? null;
    $header = $this->request->getHeader('x-api-key');
    $header = array_shift($header);
    // Bearer token handling
    $authorizationHeader = $this->request->getHeader('Authorization');
    // ... extracts bearer token
}
```

**Concern:** API key can be passed as query parameter, which may be logged in:
- Web server access logs
- Proxy logs
- Browser history
- Referrer headers

**Recommendation:** Deprecate query parameter authentication, require header-based auth only.

---

## 2. Session Security

### 2.1 Session Configuration

**Location:** `src/Http/SessionServiceProvider.php:35-49`

```php
session_set_cookie_params([
    'lifetime' => $sessionConfig['lifetime'] * 24 * 60 * 60,
    'path'     => '/',
    'domain'   => '',
    'secure'   => $request->isSecure(),
    'httponly' => true,
    'samesite' => 'Lax',
]);
```

**Assessment:**
| Setting | Value | Status |
|---------|-------|--------|
| `httponly` | `true` | Prevents XSS cookie theft |
| `secure` | Dynamic | Based on request scheme |
| `samesite` | `Lax` | Partial CSRF protection |

**Rating:** Good

### 2.2 Session Fixation Protection

**Location:** `src/Controllers/AuthController.php:89-96`

```php
public function loginUser(User $user): Response
{
    $previousPage = $this->session->get('previous_page');
    $this->session->invalidate();  // Regenerates session ID
    $this->session->set('user_id', $user->id);
    $this->session->set('locale', $user->settings->language);
    // ...
}
```

**Assessment:** Session is invalidated (regenerated) on login, preventing fixation attacks.

**Rating:** Excellent

### 2.3 Session Invalidation on Password Reset

**Location:** `src/Controllers/PasswordResetController.php:97-98`

```php
// Invalidate all user sessions after password reset
$reset->user->sessions()->getQuery()->delete();
```

**Assessment:** All existing sessions are destroyed when password is reset - prevents compromised sessions from persisting.

**Rating:** Excellent

---

## 3. CSRF Protection

### 3.1 Token Validation

**Location:** `src/Middleware/VerifyCsrfToken.php:40-55`

```php
protected function tokensMatch(ServerRequestInterface $request): bool
{
    $body = $request->getParsedBody();
    $token = $body['_token'] ?? null;
    $header = $this->request->getHeader('X-CSRF-TOKEN');
    $header = array_shift($header);
    $token = $token ?: $header;

    $sessionToken = $this->session->get('_token');

    return is_string($token)
        && is_string($sessionToken)
        && hash_equals($sessionToken, $token);  // Timing-safe comparison
}
```

**Assessment:**
- Uses `hash_equals()` - prevents timing attacks
- Supports both form field and header token
- Applied via middleware to all state-changing requests

**Rating:** Excellent

### 3.2 Token Generation

**Location:** `src/Http/SessionServiceProvider.php:67`

```php
$session->set('_token', Str::random(42));
```

**Assessment:** 42-character random string using Laravel's `Str::random()` which uses CSPRNG.

**Rating:** Good

---

## 4. Authorization

### 4.1 Permission System

**Location:** `src/Helpers/Authenticator.php:116-140`

```php
public function can(string $privilege): bool
{
    $user = $this->user();
    if ($user === null) {
        return false;
    }

    return $user->privileges
        ->pluck('name')
        ->contains($privilege);
}
```

**Assessment:**
- Privilege-based access control
- Cached on user object
- Applied consistently in controllers

### 4.2 API Authorization

**Location:** `src/Controllers/Api/IndexController.php`

```php
if ($request->getAttribute('user') === null) {
    throw new HttpForbidden('api.not-authenticated', ['type' => 'api']);
}
```

**Assessment:** API endpoints properly check authentication before allowing access.

---

## 5. Input Validation & Output Encoding

### 5.1 SQL Injection Protection

**Primary Protection:** Eloquent ORM

**Assessment:** Modern code uses Eloquent ORM exclusively, which uses parameterized queries.

**Raw Query Usage:** Found in several locations but with proper parameter binding:

```php
// Example from src/Models/Shifts/Shift.php
->whereRaw('start < ? AND end > ?', [$start, $end])
```

**Rating:** Good - parameterized queries used throughout

### 5.2 XSS Protection - Twig Templating

**Default Behavior:** Twig auto-escapes output by default

**Concern:** `|raw` filter bypasses escaping

**Files using `|raw` filter:**
```
resources/views/layouts/app.twig:44         - {{ content|raw }}
resources/views/macros/base.twig:18         - {{ message|raw }}
resources/views/macros/form.twig:237        - {{ opt|raw }}
resources/views/macros/form.twig:268        - {{ opt|raw }}
resources/views/macros/form.twig:332        - {{ info|raw }}
resources/views/macros/form.twig:334        - {{ label|raw }}
resources/views/pages/schedule/index.twig   - Various raw outputs
resources/views/admin/news/edit.twig        - {{ news.text|raw }}
resources/views/pages/news/overview.twig    - {{ news.text|raw }}
```

**Risk Assessment:**
| Usage | Risk | Notes |
|-------|------|-------|
| `content\|raw` | Low | Rendered from Twig templates |
| `message\|raw` | Medium | Flash messages - verify source |
| `news.text\|raw` | High | User-generated content |
| `opt\|raw` | Medium | Form options - verify source |

**Recommendation:** Audit all `|raw` usages, especially for user-generated content. Consider using HTML Purifier for rich text fields.

### 5.3 Legacy Code Escaping

**Location:** `includes/pages/*.php`

**Pattern Found:**
```php
htmlspecialchars($user->name)
```

**Assessment:** Legacy code uses `htmlspecialchars()` for output escaping - provides basic XSS protection but inconsistent application.

---

## 6. Security Headers

### 6.1 Current Configuration

**Location:** `config/config.default.php:123-135`

```php
'headers' => [
    'X-Content-Type-Options'  => 'nosniff',
    'X-Frame-Options'         => 'sameorigin',
    'Referrer-Policy'         => 'strict-origin-when-cross-origin',
    'Content-Security-Policy' =>
        "default-src 'self'; "
        . " style-src 'self' 'unsafe-inline'; "
        . "img-src 'self' data:;",
    'X-XSS-Protection'        => '1; mode=block',
    //'Strict-Transport-Security' => 'max-age=7776000',
],
```

### 6.2 Header Assessment

| Header | Value | Assessment |
|--------|-------|------------|
| `X-Content-Type-Options` | `nosniff` | Prevents MIME sniffing |
| `X-Frame-Options` | `sameorigin` | Prevents clickjacking |
| `Referrer-Policy` | `strict-origin-when-cross-origin` | Good balance |
| `CSP` | See below | Functional but weak |
| `X-XSS-Protection` | `1; mode=block` | Legacy, not harmful |
| **HSTS** | **Commented out** | **CRITICAL GAP** |

### 6.3 CSP Analysis

**Current Policy:**
```
default-src 'self';
style-src 'self' 'unsafe-inline';
img-src 'self' data:;
```

**Issues:**
1. `'unsafe-inline'` for styles - allows style injection attacks
2. Missing `script-src` - defaults to `'self'` (acceptable)
3. Missing `frame-ancestors` - covered by X-Frame-Options
4. No `upgrade-insecure-requests` directive

**Recommendation:** Remove `'unsafe-inline'` by implementing nonces or hashes for inline styles.

### 6.4 HSTS Gap

**Critical Finding:** HSTS header is commented out in default configuration.

**Risk:** Without HSTS, users are vulnerable to SSL stripping attacks on first visit.

**Recommendation:** Enable HSTS with appropriate `max-age`:
```php
'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
```

---

## 7. OAuth 2.0 Security

### 7.1 State Parameter Validation

**Location:** `src/Controllers/OAuthController.php:95-103`

```php
if (
    !$this->session->get('oauth2_state')
    || $request->get('state') !== $this->session->get('oauth2_state')
) {
    $this->session->remove('oauth2_state');
    $this->log->warning('Invalid OAuth state');
    throw new HttpNotFound('oauth.invalid-state');
}
```

**Assessment:** Proper state parameter validation prevents CSRF attacks on OAuth flow.

**Rating:** Excellent

### 7.2 Token Handling

Uses League OAuth2 Client library - mature, well-audited implementation.

---

## 8. Rate Limiting

### 8.1 Current State

**Critical Finding:** No rate limiting implemented.

**Grep Results:**
```
$ grep -r "rate.limit\|throttle\|RateLimit" src/
# No results in source code
```

**Vulnerable Endpoints:**
| Endpoint | Risk |
|----------|------|
| `POST /login` | Brute force attacks |
| `POST /password/reset` | Email enumeration |
| `POST /api/*` | API abuse |
| `POST /register` | Registration spam |

### 8.2 Recommendation

Implement rate limiting middleware:

```php
// Suggested implementation
class RateLimitMiddleware implements MiddlewareInterface
{
    private const LIMITS = [
        '/login' => ['attempts' => 5, 'decay' => 300],
        '/api/*' => ['attempts' => 100, 'decay' => 60],
    ];
}
```

**Priority:** High - This is a significant security gap.

---

## 9. Error Handling & Information Disclosure

### 9.1 Sensitive Field Filtering

**Location:** `src/Middleware/ErrorHandler.php:92-98`

```php
protected array $formIgnore = [
    'password', 'password_confirmation', 'password2',
    'database_password', 'email_password', 'new_password',
    'new_password2', 'new_pw', 'new_pw2', 'app_key', '_token',
];
```

**Assessment:** Password fields and tokens are filtered from error reports.

**Rating:** Good

### 9.2 Password Reset Timing Attack Prevention

**Location:** `src/Controllers/PasswordResetController.php`

```php
// Same response regardless of email existence
if (!$user) {
    return $this->response->withView('pages/password/reset-success');
}
// ... send email ...
return $this->response->withView('pages/password/reset-success');
```

**Assessment:** Returns same response whether email exists or not - prevents user enumeration.

**Rating:** Excellent

---

## 10. File Upload Security

### 10.1 Current Implementation

File uploads are limited - primarily schedule imports (XML/JSON).

**Location:** `src/Controllers/Admin/ScheduleController.php`

**Protections:**
- File type validation via extension/mime
- Processed server-side (not stored for direct access)
- Temporary file handling

**Assessment:** Limited attack surface due to restricted upload functionality.

---

## 11. Legacy Code Security Concerns

### 11.1 Overview

The `includes/` directory contains 47+ files with legacy patterns:

**Concerns:**
1. Global state access (`global $user`)
2. Mixed concerns (harder to audit)
3. Inconsistent escaping patterns
4. No type safety (pre-PHP 8 style)

### 11.2 Sample Analysis

**Location:** `includes/pages/admin_user.php`

```php
function admin_user(): string
{
    $html = '';
    $request = request();
    // ... uses htmlspecialchars() for escaping
}
```

**Assessment:** Basic protections present but code is harder to audit systematically.

---

## 12. Recommendations Summary

### Critical (P0)

| Issue | Recommendation | Effort |
|-------|---------------|--------|
| No rate limiting | Implement rate limiting middleware | Medium |
| HSTS disabled | Enable HSTS header | Low |

### High (P1)

| Issue | Recommendation | Effort |
|-------|---------------|--------|
| API key in query params | Deprecate, require headers only | Low |
| CSP `unsafe-inline` | Implement nonces/hashes for styles | Medium |
| `\|raw` filter audit | Review all 16 usages for XSS | Medium |

### Medium (P2)

| Issue | Recommendation | Effort |
|-------|---------------|--------|
| API keys in plaintext | Hash API keys in database | Medium |
| Legacy code audit | Security review of `includes/` | High |
| Session timeout | Implement absolute session timeout | Low |

### Low (P3)

| Issue | Recommendation | Effort |
|-------|---------------|--------|
| Security logging | Centralized security event logging | Medium |
| Dependency audit | Automated vulnerability scanning in CI | Low |

---

## 13. Security Testing Recommendations

### Automated Testing

Add to CI pipeline:
1. **OWASP ZAP** - Dynamic application security testing
2. **PHPStan security rules** - Static analysis for security
3. **Composer audit** - Dependency vulnerability checks (already present)
4. **npm audit** - JS dependency checks (already present)

### Manual Testing Checklist

- [ ] Authentication bypass attempts
- [ ] Privilege escalation testing
- [ ] XSS testing on all user inputs
- [ ] CSRF token validation bypass
- [ ] Session fixation attempts
- [ ] SQL injection in search fields
- [ ] API authorization boundary testing

---

## Conclusion

Engelsystem's modern codebase demonstrates security awareness with proper implementations of authentication, session management, and CSRF protection. The primary gaps are:

1. **No rate limiting** - Critical vulnerability to brute force attacks
2. **HSTS disabled** - Risk of SSL stripping
3. **Legacy code** - Harder to audit, potential hidden vulnerabilities

The development team should prioritize implementing rate limiting and enabling HSTS. The `|raw` Twig filter usages warrant immediate review for potential XSS vulnerabilities.

---

*This review was conducted through static code analysis. Runtime penetration testing is recommended for comprehensive security validation.*
