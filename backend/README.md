# UnfallAtlas DE — Backend API

> Laravel 11 REST API for the German Road Accident Open Data Platform.  
> Integrates 4 official German open data sources into a unified, queryable API with full provenance tracking.

---

## Tech Stack

| Layer | Technology |
|---|---|
| Framework | Laravel 11 (PHP 8.3) |
| Database | PostgreSQL 14+ |
| API Docs | OpenAPI 3.0 via l5-swagger |
| ETL | Laravel Artisan Commands |
| Auth | None (public read-only API) |

---

## Requirements

- PHP 8.3+
- Composer
- PostgreSQL 14+

---

## Installation

```bash
cd backend
cp .env.example .env
```

Edit `.env` and set your database credentials:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=dmproject
DB_USERNAME=your_user
DB_PASSWORD=your_password
```

Then install and set up:

```bash
composer install
php artisan key:generate
php artisan migrate
php artisan db:seed-sources        # Insert data source metadata
php artisan db:seed                # Seed 16 states + indicators
```

---

## Running the Server

```bash
php artisan serve --port=8002
# API available at: http://127.0.0.1:8002/api/v1/
```

Generate Swagger docs:

```bash
php artisan l5-swagger:generate
# Swagger UI: http://127.0.0.1:8002/api/documentation
```

---

## Data Import (ETL Pipeline)

All import commands are idempotent — safe to run multiple times (uses upsert).

```bash
# 1. Region hierarchy (GV-ISys from Destatis)
php artisan import:gemeindeverzeichnis

# 2. Accident data (Unfallatlas — downloads ZIPs automatically)
php artisan import:unfallatlas                  # All years 2016–2024
php artisan import:unfallatlas --year=2024      # Single year only
php artisan import:unfallatlas --fresh          # Truncate + re-import all

# 3. Population data (GENESIS / Regionalstatistik CSV)
php artisan import:genesis

# 4. Registered cars (KBA via Regionalstatistik CSV)
php artisan import:registered-cars

# Run everything at once:
php artisan import:all
```

---

## API Endpoints

Base URL: `http://127.0.0.1:8002/api/v1/`

### Regions
| Method | Endpoint | Description |
|---|---|---|
| GET | `/regions` | List regions. Filter by `level`, `state_ags`, `district_ags`, `search` |
| GET | `/regions/{id}` | Single region with children count and indicator values |

### Accidents
| Method | Endpoint | Description |
|---|---|---|
| GET | `/accidents` | Paginated accident records with 15+ filters |

**Accident filters:** `state_ags`, `district_ags`, `year`, `year_from`, `year_to`, `month`, `weekday`, `hour`, `category`, `involves_bicycle`, `involves_pedestrian`, `involves_motorcycle`, `involves_car`, `involves_truck`, `light_condition`, `road_condition`, `crash_type`

### Aggregates
| Method | Endpoint | Description |
|---|---|---|
| GET | `/aggregates/accidents` | Accident counts by region and year |
| GET | `/aggregates/time-series` | Group by `year` / `month` / `weekday` / `hour` |
| GET | `/aggregates/rates` | Accident rate per 100,000 (`per=population` or `per=registered_cars`) |
| GET | `/aggregates/rankings` | Top/bottom N districts by accident count or rate |
| GET | `/aggregates/year-over-year` | Annual change percentage |
| GET | `/aggregates/earliest-year` | Earliest available year overall or per state |
| GET | `/aggregates/zero-cases` | Districts with zero accidents in a given year |

### Metadata
| Method | Endpoint | Description |
|---|---|---|
| GET | `/meta/sources` | All data sources with publisher and license |
| GET | `/import-runs` | Import history and provenance |
| GET | `/stats/summary` | Platform-wide statistics |

Full interactive documentation: **http://127.0.0.1:8002/api/documentation**

---

## Database Schema

```
regions              — State / District / Municipality hierarchy (AGS codes)
accidents            — 2.1M road accident records 2016–2024
indicators           — Indicator registry (population, registered_cars, etc.)
indicator_values     — Time-series values: region × indicator × year
data_sources         — Data source metadata + license
import_runs          — ETL provenance tracking
```

---

## Special Cases

### Berlin & Hamburg (City-States)
GV-ISys registers Berlin as `11000` and Hamburg as `02000` (single district each).  
Unfallatlas stores accidents under sub-codes (`11001–11012` for Berlin, `02101–02717` for Hamburg).

**Fix:** When `district_ags` ends in `'000'`, the API queries with `LIKE state_prefix%` to aggregate all sub-districts automatically.

```php
if (str_ends_with($v, '000') && strlen($v) === 5) {
    $q->whereRaw("district_ags LIKE ?", [substr($v, 0, 2) . '%']);
}
```

---

## Data Sources & License

| Source | Publisher | License |
|---|---|---|
| [Unfallatlas Germany](https://www.opengeodata.nrw.de/produkte/transport_verkehr/unfallatlas/) | Statistische Ämter des Bundes und der Länder | dl-de/by-2-0 |
| [GV-ISys Region Directory](https://www.destatis.de/DE/Themen/Laender-Regionen/Regionales/Gemeindeverzeichnis/) | Statistisches Bundesamt (Destatis) | dl-de/by-2-0 |
| [GENESIS-Online / Regional Statistics](https://www-genesis.destatis.de/) | Statistisches Bundesamt (Destatis) | dl-de/by-2-0 |
| [Regional Atlas Germany](https://regionalatlas.statistikportal.de/) | Statistische Ämter des Bundes und der Länder | dl-de/by-2-0 |

**License:** [Datenlizenz Deutschland – Namensnennung – Version 2.0](https://www.govdata.de/dl-de/by-2-0)  
Attribution required: *Statistische Ämter des Bundes und der Länder*

Every API response includes `source` and `license` fields for compliance.

---

## Project Structure

```
backend/
├── app/
│   ├── Console/Commands/       ETL Artisan import commands
│   │   ├── ImportUnfallatlas.php
│   │   ├── ImportGemeindeverzeichnis.php
│   │   ├── ImportGenesis.php
│   │   ├── ImportRegisteredCars.php
│   │   └── SeedDataSources.php
│   ├── Http/
│   │   ├── Controllers/Api/    REST API controllers
│   │   │   ├── AccidentController.php
│   │   │   ├── AggregateController.php
│   │   │   ├── RegionController.php
│   │   │   ├── MetaController.php
│   │   │   └── StatsController.php
│   │   └── Resources/          API response transformers
│   └── Models/                 Eloquent models
├── database/
│   ├── migrations/             PostgreSQL schema
│   └── seeders/
├── routes/
│   └── api.php                 All API routes
└── config/l5-swagger.php       OpenAPI configuration
```
