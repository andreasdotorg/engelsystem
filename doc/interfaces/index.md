# Interfaces Overview

Engelsystem exposes several interfaces for integration:

## REST API

The primary programmatic interface is a REST API:

- **Version:** v0-beta (0.2.0-beta)
- **Base Path:** `/api/v0-beta`
- **Documentation:** OpenAPI 3.0 specification at `resources/api/openapi.yml`
- **Authentication:** API key (bearer token or header)

[Full REST API Documentation](rest-apis.md)

## External Integrations

### OAuth 2.0 Providers
Support for external authentication via OAuth 2.0:
- Configurable providers
- Automatic user creation
- Group assignment based on OAuth response

### Schedule Import (Frab/Pretalx)
Import shifts from external schedule systems:
- Frab format support
- Pretalx format support
- Automatic shift creation and updates

### iCal Export
Calendar feeds for shifts:
- Personal shift calendar
- Location-based calendars

[External Systems Documentation](external-systems.md)
