# REST API Documentation

## Overview

Engelsystem provides a REST API for programmatic access to system data.

| Property | Value |
|----------|-------|
| Version | 0.2.0-beta |
| Base URL | `/api/v0-beta` |
| Format | JSON |
| Authentication | API Key |

## Authentication

All API endpoints require authentication via API key. The key can be provided in three ways:

1. **Bearer Token** (recommended):
   ```
   Authorization: Bearer <api_key>
   ```

2. **Custom Header**:
   ```
   x-api-key: <api_key>
   ```

3. **Query Parameter** (not recommended):
   ```
   ?api_key=<api_key>
   ```

Users can find their API key in their profile settings.

## Common Response Format

### Success Response
```json
{
  "data": [...],
  "links": {
    "self": "https://example.com/api/v0-beta/resource"
  }
}
```

### Error Response
```json
{
  "errors": [
    {
      "status": "404",
      "title": "Not Found",
      "detail": "Resource not found"
    }
  ]
}
```

## Endpoints

### System Information

#### GET /api/v0-beta
Returns API information and available versions.

**Response:**
```json
{
  "versions": {
    "0.0.1-beta": "/api/v0-beta"
  }
}
```

### Angel Types

#### GET /angeltypes
List all angel types visible to the authenticated user.

**Response Schema:** Array of `AngelType` objects

**Example Response:**
```json
{
  "data": [
    {
      "id": 1,
      "name": "Info Desk",
      "description": "Help desk for attendees",
      "restricted": false,
      "requires_driver_license": false,
      "requires_ifsg_certificate": false,
      "shift_self_signup": true,
      "url": "https://example.com/angeltypes/1"
    }
  ]
}
```

#### GET /angeltypes/{id}
Get a specific angel type.

**Parameters:**
- `id` (path, required): Angel type ID

#### GET /angeltypes/{id}/shifts
Get shifts for an angel type.

**Parameters:**
- `id` (path, required): Angel type ID

### Locations

#### GET /locations
List all locations.

**Response Schema:** Array of `Location` objects

**Example Response:**
```json
{
  "data": [
    {
      "id": 1,
      "name": "Main Hall",
      "description": "Primary event space",
      "dect": "1234",
      "map_url": "https://map.example.com/main-hall",
      "url": "https://example.com/locations/1"
    }
  ]
}
```

#### GET /locations/{id}
Get a specific location.

#### GET /locations/{id}/shifts
Get shifts at a location.

### Shift Types

#### GET /shifttypes
List all shift types.

**Response Schema:** Array of `ShiftType` objects

#### GET /shifttypes/{id}
Get a specific shift type.

#### GET /shifttypes/{id}/shifts
Get shifts of a type.

### Shifts

Shift endpoints are accessed via angel types, locations, or shift types.

**Shift Object Schema:**
```json
{
  "id": 1,
  "title": "Info Desk Morning",
  "description": "Morning shift at info desk",
  "url": "https://wiki.example.com/info-desk",
  "location": { ... },
  "shift_type": { ... },
  "starts_at": "2024-12-27T08:00:00+01:00",
  "ends_at": "2024-12-27T12:00:00+01:00",
  "entries": [ ... ],
  "needed_angel_types": [ ... ],
  "api_url": "https://example.com/api/v0-beta/shifts/1"
}
```

### Users

#### GET /users/{id}
Get a user by ID. Returns basic info for all users, detailed info for own profile.

**Response Schema:** `User` or `UserDetail` object

**User Object:**
```json
{
  "id": 1,
  "name": "example_user",
  "first_name": "Jane",
  "last_name": "Doe",
  "pronoun": "they/them",
  "url": "https://example.com/users/1"
}
```

**UserDetail Object (self only):**
```json
{
  "id": 1,
  "name": "example_user",
  "email": "jane@example.com",
  "tshirt": "L",
  "dates": { ... },
  "language": "en_US",
  "arrived": true,
  "got_shirt": false,
  "shift_state": { ... },
  "vouchers": 2,
  "dect": "5678",
  "mobile": "+49...",
  "contact_email": "jane.doe@example.com"
}
```

#### GET /users/{id}/angeltypes
Get angel types for a user.

#### GET /users/{id}/shifts
Get shifts for a user.

### News

#### GET /news
List news articles.

**Response Schema:** Array of `News` objects

#### GET /news/{id}
Get a specific news article.

#### GET /news/{id}/comments
Get comments on a news article.

### iCal Feeds

#### GET /ical
Get personal shift calendar (requires `ical_key` query parameter).

#### GET /locations/{id}/ical
Get shifts at a location as iCal feed.

## Rate Limiting

[NEEDS VERIFICATION] The API may implement rate limiting. Check response headers:
- `X-RateLimit-Limit`: Maximum requests per window
- `X-RateLimit-Remaining`: Remaining requests
- `X-RateLimit-Reset`: Window reset time

## API Versioning

The API uses URL path versioning:
- Current: `/api/v0-beta`
- Future versions will be at `/api/v1`, etc.

The beta designation indicates the API may change without notice.
