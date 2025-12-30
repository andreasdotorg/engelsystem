# Engelsystem Documentation

Engelsystem is a shift planning system designed for chaos events, managing volunteers ("angels"), shifts, and locations for large-scale events like the Chaos Communication Congress.

## Quick Navigation

- [System Overview](overview.md) - What Engelsystem does and key concepts
- [Architecture & Tech Stack](architecture.md) - Technical architecture and components
- [Interfaces & APIs](interfaces/index.md) - REST API and external integrations
- [Use Cases](use-cases/index.md) - Core functionality workflows
- [Data Flows](data-flows/index.md) - How data moves through the system
- [Configuration](configuration.md) - Configuration reference
- [Deployment](deployment.md) - Deployment guide
- [CI/CD](ci-cd.md) - Continuous integration and deployment
- [Architecture Review](architecture-review.md) - Architecture & UI/UX assessment with improvement recommendations
- [Security Review](security-review.md) - Security assessment with vulnerability analysis and recommendations

## About This Documentation

- **Generated on:** 2025-12-30
- **Repository:** engelsystem (https://engelsystem.de)
- **Version:** 4.0.0-dev
- **License:** GPL-3.0-or-later

## Quick Start

### Requirements
- PHP >= 8.2 with extensions: dom, json, mbstring, PDO with mysql driver, tokenizer, xml, xmlwriter
- MySQL/MariaDB database
- Node.js >= 20 with Yarn
- Composer for PHP dependencies

### Using Nix (Recommended)
```bash
# Enter development shell with all dependencies
nix develop

# Install dependencies
es-install

# Start database
es-db-start

# Run migrations
es-migrate

# Start development server
es-serve
```

### Manual Setup
```bash
# Install PHP dependencies
composer install

# Install frontend dependencies
yarn install

# Build frontend assets
yarn build

# Configure database in config/config.php
cp config/config.default.php config/config.php

# Run migrations
./bin/migrate

# Start development server
php -S 0.0.0.0:5080 -t public/
```

Access the application at http://localhost:5080
