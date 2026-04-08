# Shilla — Developer Notes

## Tech Stack

- **Backend:** Marko PHP 8.5+ (modular framework)
- **Database:** PostgreSQL (data + PubSub via `marko/pubsub-pgsql`)
- **Templating:** Latte (`marko/view-latte`), module-namespaced (`foundation::layout/game`)
- **Frontend:** Tailwind CSS v4 + Alpine.js (vendored) + vanilla JS
- **Testing:** Pest PHP + Mockery (unit/feature), Playwright on Windows (E2E)
- **Design:** Twilight theme — see `DESIGN.md`

## Running Locally

```bash
# Start PostgreSQL
docker start shilla-postgres
# Or first time: docker run -d --name shilla-postgres -e POSTGRES_USER=shilla -e POSTGRES_PASSWORD=shilla -e POSTGRES_DB=shilla -p 5432:5432 postgres:17-alpine

# Start dev server
php -S localhost:8000 -t public/

# Build CSS (after changing templates)
npm run build:css

# Watch CSS
npm run dev:css

# Run tests
./vendor/bin/pest

# Run migrations
./vendor/bin/marko db:migrate

# Run seeders
./vendor/bin/marko db:seed
```

## Marko Framework Patterns

### Entity Initialization (IMPORTANT)

Marko's `EntityHydrator::extract()` reads ALL entity properties before insert. Uninitialized typed properties will throw. Follow these rules for every entity:

```php
// Auto-increment IDs — MUST be nullable with null default
#[Column(primaryKey: true, autoIncrement: true)]
public ?int $id = null;

// Timestamps — MUST be nullable even with DB defaults
// DB defaults are ignored because Marko always includes all columns in INSERT
#[Column(name: 'created_at', default: 'CURRENT_TIMESTAMP')]
public ?DateTimeImmutable $createdAt = null;

// When creating entities, ALWAYS set createdAt before save()
$entity->createdAt = new \DateTimeImmutable();
$repository->save($entity);
```

### String Primary Keys

The `SystemConfigRepository` uses raw SQL for insert/update because `EntityHydrator::isNew()` checks if the PK is null — string PKs are always set before save, so the hydrator thinks it's an update. See `SystemConfigRepository::setValue()` for the pattern.

### Repository Pattern

- Entities are plain PHP objects extending `Marko\Database\Entity\Entity`
- Repositories extend `Marko\Database\Repository\Repository`
- Use `$this->findOneBy(['column' => $value])` for simple lookups
- Use `$this->query()` for complex queries — returns `RepositoryQueryBuilder`
- `getEntities()` hydrates results, `count()` returns count
- No nested where closures — use flat `where()`/`orWhere()`/`whereNull()`

### Testing Pattern

Marko has no HTTP test client. Test controllers as units with mocked/stubbed dependencies:

```php
// Create stub implementations of ViewInterface, HasherInterface, etc.
// Instantiate the controller directly with stubs
// Call controller methods with new Request(post: [...])
// Assert on Response status codes, headers, and stub state
```

See `app/foundation/tests/Feature/` for examples.

## Module Structure

```
app/foundation/           # Foundation module
  src/                    # PSR-4 autoloaded (App\Foundation\)
  Seed/                   # Seeders (discovered from app/*/Seed/)
  config/                 # Module-specific config
  resources/views/        # Latte templates (foundation:: namespace)
  tests/                  # Pest tests
  composer.json           # Module registration (marko.module: true)
  module.php              # Bindings and singletons
```

## Documentation

- `docs/SPEC.md` — master spec (the map)
- `docs/spec/NN-name/README.md` — sub-project specs
- `DESIGN.md` — twilight theme design system
- See `docs/SPEC.md` "Documentation Standards" for file organization rules
