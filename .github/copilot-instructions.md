# Copilot Instructions for NTPhone Codebase

This is a **Laravel 10 e-commerce platform** for phone sales with real-time chat, product variants, and AI chatbot integration.

## Architecture Overview

### Core Components

- **Laravel Backend** (`app/`): MVC framework with Eloquent ORM
  - Models: User, Product, Order, Cart, Category, ProductVariant, etc.
  - Controllers organized by domain: `admin/`, `customer/`, `staff/`, `auth/`, `chatbot/`, `guest/`
  - Observers: `OrderObserver` triggers email notifications on order status changes
- **Frontend**: Vue.js/JavaScript with Vite bundler, Tailwind CSS + Bootstrap 5
- **Real-time**: Laravel Reverb + Pusher.js for real-time notifications and messaging
- **AI Integration**: Gemini API for chatbot functionality (color translation, product search)

### Database Design

Key relationships:
- `Product` → `ProductVariant` (each product has multiple color/storage combos)
- `ProductVariant` → `Color`, `Storage` (variant attributes)
- `Order` → `OrderItem` → `Product` (order line items)
- `Product` → `Rating` (ratings through variants: `hasManyThrough`)
- `User` ↔ `Product` (favorites: many-to-many)
- `User` → `Order` (orders with timestamps: deleted_at for soft deletes)

**Important**: Products and Users have soft deletes enabled. Always use `withTrashed()` if you need to query deleted records.

## Development Workflow

### Setup

```bash
# PHP dependencies
composer install

# JavaScript dependencies
npm install

# Database
php artisan migrate --seed  # Creates DB and populates seeders

# Environment (if needed)
cp .env.example .env
php artisan key:generate
```

### Running

- **Development**: `npm run dev` (Vite watch mode) + Laravel app on port 8000
- **Build**: `npm run build` (Vite production build)
- **Artisan**: `php artisan migrate`, `php artisan tinker`, `php artisan serve`

### Testing

- PHPUnit located in `tests/` directory
- Run with: `php artisan test` or `./vendor/bin/phpunit`

## Project-Specific Patterns

### 1. **Eloquent Model Patterns**

Use `protected $fillable` for mass assignment. Example from User:
```php
protected $fillable = ['fullname', 'username', 'email', 'password', ...];
```

Use `HasFactory` and `SoftDeletes` traits for standard models:
```php
use HasFactory, SoftDeletes;
```

### 2. **Controllers Structure**

Controllers inherit from `App\Http\Controllers\Controller`. Method naming:
- `ask()` for API endpoints (ChatbotController)
- `getPrice()` for queries (CustomerController)
- Database queries use Eloquent relationships, not raw SQL

### 3. **Real-time & Events**

- `OrderObserver` watches `Order::updated` and sends emails if status changes
- Registered in `EventServiceProvider::boot()` with `Order::observe(OrderObserver::class)`
- Broadcasted events via Pusher/Reverb (configured in `.env`)

### 4. **AI Chatbot Integration**

Located in `app/Http/Controllers/chatbot/ChatbotController`:
- Uses Gemini API (`env('GEMINI_API_KEY')`)
- Maintains chat history in Laravel Cache with session-based keys: `chat_history_{sessionId}`
- Color mapping: Database stores English names (e.g., "black"), translates to Vietnamese for UI ("Đen")
- Can query products by attributes (color, storage) for intelligent recommendations

### 5. **File Organization**

- **Controllers**: `app/Http/Controllers/{domain}/{ControllerName}.php`
- **Models**: `app/Models/{ModelName}.php` (auto-loaded by PSR-4)
- **Views**: `resources/views/` (compiled by Vite)
- **Migrations**: `database/migrations/` (timestamp prefix required)
- **Seeders**: `database/seeders/` (populate test data)

## Critical Integration Points

### Email Notifications

- `OrderStatusChanged` mailable in `app/Mail/` triggered by `OrderObserver`
- Configure SMTP in `.env`: `MAIL_DRIVER`, `MAIL_HOST`, `MAIL_USERNAME`, `MAIL_PASSWORD`

### API Routes

- Located in `routes/api.php` with `auth:sanctum` middleware for authenticated endpoints
- Public endpoints (no middleware) for guest access
- Returns JSON responses via controller methods

### Database Migrations

- Naming: `{timestamp}_{snake_case_description}.php`
- Run: `php artisan migrate` (creates tables), `php artisan migrate:rollback` (reverts)
- Migrations are **non-reversible after deployment** — validate carefully

## Common Commands

```bash
# Database
php artisan migrate --seed          # Run all migrations + seeders
php artisan migrate:refresh --seed  # Drop + recreate tables
php artisan tinker                  # Interactive PHP shell

# Development
php artisan serve                   # Local server (port 8000)
npm run dev                         # Vite watch + hot reload
php artisan test                    # Run tests

# Code
php artisan cache:clear            # Clear all caches
php artisan config:clear           # Clear config cache
```

## Code Style & Conventions

- **PHP**: PSR-12 (enforced by Laravel Pint in `composer.json`)
- **Naming**: CamelCase for classes, snake_case for database columns and variables
- **Queries**: Use Eloquent relationships (`$model->relationship()`) over raw queries
- **Error handling**: Catch exceptions, log with `Log::error()`, return JSON responses with status codes

## Notes for AI Agents

1. **Timestamps**: Laravel automatically manages `created_at`, `updated_at`, `deleted_at` (soft deletes) — do not manually set these
2. **Passwords**: Always hash with `Hash::make()` before saving to database
3. **Cache**: Chat history stored in cache with session-based keys; clear if testing
4. **Variants**: Products with variants store each color/storage combo as separate `ProductVariant` records
5. **Soft Deletes**: When querying, remember `Product::where()` excludes soft-deleted records by default
