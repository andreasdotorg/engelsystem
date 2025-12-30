# Deployment Guide

## Prerequisites

- PHP 8.2+ with required extensions
- MySQL 5.7+ / MariaDB 10.2+
- Web server (nginx/Apache) or PHP built-in server
- Composer
- Node.js 20+ and Yarn (for building assets)

## Deployment Methods

### 1. Traditional Deployment

```bash
# Clone repository
git clone https://github.com/engelsystem/engelsystem.git
cd engelsystem

# Install dependencies
composer install --no-dev --optimize-autoloader
yarn install
yarn build

# Configure
cp config/config.default.php config/config.php
# Edit config/config.php

# Set permissions
chmod -R 777 storage/

# Run migrations
./bin/migrate

# Configure web server
```

### 2. Docker Deployment

```bash
# Build image
docker build -t engelsystem .

# Run with docker-compose
docker-compose up -d
```

**docker-compose.yml example:**
```yaml
version: '3'
services:
  app:
    image: engelsystem
    ports:
      - "5080:80"
    environment:
      - DB_HOST=db
      - DB_DATABASE=engelsystem
      - DB_USERNAME=engelsystem
      - DB_PASSWORD=secret
    depends_on:
      - db

  db:
    image: mariadb:10.7
    environment:
      - MYSQL_DATABASE=engelsystem
      - MYSQL_USER=engelsystem
      - MYSQL_PASSWORD=secret
      - MYSQL_ROOT_PASSWORD=rootsecret
    volumes:
      - db_data:/var/lib/mysql

volumes:
  db_data:
```

### 3. Nix Deployment

```bash
# Build package
nix build .#engelsystem

# Run with configuration
nix run .#engelsystem-serve

# Use NixOS module
{
  imports = [ engelsystem.nixosModules.default ];

  services.engelsystem = {
    enable = true;
    domain = "engelsystem.example.com";
    database = {
      host = "localhost";
      name = "engelsystem";
      user = "engelsystem";
      passwordFile = "/run/secrets/engelsystem-db";
    };
  };
}
```

### 4. Kubernetes Deployment

The CI/CD pipeline includes Kubernetes deployment support.

**Key resources:**
- Deployment
- Service
- Ingress (optional)
- ConfigMap for configuration
- Secret for sensitive data

## Web Server Configuration

### Nginx

```nginx
server {
    listen 80;
    server_name engelsystem.example.com;
    root /var/www/engelsystem/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

### Apache

```apache
<VirtualHost *:80>
    ServerName engelsystem.example.com
    DocumentRoot /var/www/engelsystem/public

    <Directory /var/www/engelsystem/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

## Database Setup

```sql
CREATE DATABASE engelsystem CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'engelsystem'@'localhost' IDENTIFIED BY 'your_password';
GRANT ALL PRIVILEGES ON engelsystem.* TO 'engelsystem'@'localhost';
FLUSH PRIVILEGES;
```

Then run migrations:
```bash
./bin/migrate
```

## Initial Setup

After deployment:

1. Navigate to the application URL
2. Register the first user
3. Manually promote to admin group via database:
   ```sql
   INSERT INTO users_groups (user_id, group_id) VALUES (1, 2);
   -- Group 2 is typically the admin/bureaucrat group
   ```
4. Configure via Admin interface

## Environment Variables

The application supports configuration via environment variables:

| Variable | Description |
|----------|-------------|
| `MYSQL_HOST` | Database host |
| `MYSQL_DATABASE` | Database name |
| `MYSQL_USER` | Database username |
| `MYSQL_PASSWORD` | Database password |
| `APP_ENV` | Environment (production/development) |
| `APP_URL` | Application base URL |

## Monitoring

### Health Check

The application provides a basic status endpoint for health checks.

### Logging

Logs are written to:
- `storage/logs/` - Application logs
- PHP error log - PHP errors
- Web server logs - Access and error logs

Configure log level in `config/config.php`:
```php
'log_level' => 'warning', // debug, info, notice, warning, error, critical, alert, emergency
```

## Backup

### Database Backup
```bash
mysqldump -u engelsystem -p engelsystem > backup.sql
```

### File Backup
- `config/config.php` - Configuration
- `storage/` - Logs (optional)
- Database dump

## Updating

```bash
# Fetch updates
git pull origin main

# Update dependencies
composer install --no-dev --optimize-autoloader
yarn install
yarn build

# Run migrations
./bin/migrate

# Clear caches (if any)
# Restart PHP-FPM if needed
sudo systemctl restart php8.2-fpm
```
