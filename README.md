# Evenement Applicatie

## About

The Evenement Applicatie is a web application developed for Veiligheidsregio Zuid Limburg that manages permit applications for events. The system serves different user groups including:

- Event organizers
- Permit processors
- OOV (Public Order and Safety) staff
- Application administrators

This platform streamlines the entire permit application process for events in the South Limburg safety region.

## Technical Stack

- **Framework**: Laravel 12.x
- **PHP Version**: PHP 8.2+
- **Frontend**: Tailwind CSS, Vite
- **Admin Panel**: Filament 3.x
- **Database**: MySQL
- **Queue System**: Redis
- **Docker Support**: Laravel Sail

## Installation

### Requirements (Local or Docker)

- PHP 8.2+, Composer, Node.js/NPM, MySQL, Redis (for local setup)
- Or: [Laravel Sail](https://laravel.com/docs/sail) with Docker for easy environment setup

### Quick Start (Docker using Sail)

1. Clone the repository:
   ```bash
   git clone [repository-url]
   cd evenement-applicatie
   ```

2. Copy the environment file and set your settings:
   ```bash
   cp .env.example .env
   ```

3. Start the application using Sail:
   ```bash
   ./vendor/bin/sail up -d
   ```

4. Run migrations and install dependencies:
   ```bash
   ./vendor/bin/sail artisan migrate
   ./vendor/bin/sail npm install && ./vendor/bin/sail npm run dev
   ```

### Or run locally without Sail

If you prefer a local setup:
```bash
composer install
npm install && npm run dev
php artisan migrate
php artisan serve
```

## Development Tools

### Laravel Pint

Code style is enforced using Laravel Pint:
```bash
./vendor/bin/pint
```

### Pest

Pest is used for writing and running tests:
```bash
./vendor/bin/pest
```

### PHPStan

We use PHPStan for static code analysis to catch bugs early. It will be fully integrated soon. To run:
```bash
./vendor/bin/phpstan analyse --memory-limit=2G
```

### Rector

Rector helps upgrade and refactor the codebase for Laravel 12 compatibility:
```bash
./vendor/bin/rector process
```

### Pre-commit Hook

A pre-commit hook ensures code quality by running Pint, PHPStan, Rector (in dry-run mode), and Pest before commits.  
To activate the hooks:

```bash
git config core.hooksPath .githooks
```

This setup will automatically run the following on commit:

- Pint (code style)
- PHPStan (static analysis)
- Rector (dry-run, no changes made)
- Pest (tests)

You can find the hook script inside the `.githooks/` directory.

## Contributing

1. Create a feature branch from the `main` branch
2. Make your changes
3. Run the pre-commit checks
4. Open a pull request

## License

This is a proprietary application developed for Veiligheidsregio Zuid Limburg.
