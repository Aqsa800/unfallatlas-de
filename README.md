# 🚦 UnfallAtlas DE — German Road Accident Open Data Platform

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-11-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" />
  <img src="https://img.shields.io/badge/Vue.js-3-42b883?style=for-the-badge&logo=vue.js&logoColor=white" />
  <img src="https://img.shields.io/badge/PostgreSQL-14+-336791?style=for-the-badge&logo=postgresql&logoColor=white" />
  <img src="https://img.shields.io/badge/PHP-8.3-777BB4?style=for-the-badge&logo=php&logoColor=white" />
  <img src="https://img.shields.io/badge/License-dl--de%2Fby--2.0-green?style=for-the-badge" />
</p>

<p align="center">
  A full-stack open data platform integrating <strong>2,098,019 road accident records</strong> from official German government sources.<br/>
  Built as a university project at <strong>TU Chemnitz</strong> — DBW (Datenbanken und Web-Techniken).
</p>

---

## 📸 Screenshots

> Dashboard · Accidents · Regions · Statistics · API Explorer

| Dashboard | Accidents |
|---|---|
| ![Dashboard](docs/dashboard.png) | ![Accidents](docs/accidents.png) |

| Regions | Statistics |
|---|---|
| ![Regions](docs/regions.png) | ![Statistics](docs/stats.png) |

---

## ✨ Features

- 📊 **2,098,019 accident records** from 2016–2024 across all 16 German states
- 🗺️ **412 districts** with full AGS region hierarchy (state → district → municipality)
- 🔍 **15+ filters** — year, month, weekday, hour, accident category, participant type, light condition, road condition, crash type
- 📈 **Accident rates** per 100,000 population and per 100,000 registered cars (cross-source analysis)
- 🏆 **District rankings** — top/bottom by count or rate
- 📉 **Year-over-year trends** with percentage change
- 🔎 **Zero-cases detection** — districts with no accidents in a given year
- 📖 **OpenAPI/Swagger** documentation at `/api/documentation`
- 🔗 **Full data provenance** — every API response includes source and license

---

## 🏗️ Architecture

```
┌──────────────────────────────────────────┐
│   Vue.js 3 + Vite  (localhost:5173)      │
│   Dashboard · Filters · Charts           │
└─────────────────┬────────────────────────┘
                  │  HTTP /api/v1/*
┌─────────────────▼────────────────────────┐
│   Laravel 11 REST API  (port 8002)       │
│   Controllers · Resources · OpenAPI      │
└─────────────────┬────────────────────────┘
                  │  Eloquent ORM
┌─────────────────▼────────────────────────┐
│   PostgreSQL 14+                         │
│   accidents · regions · indicators       │
│   indicator_values · import_runs         │
└──────────────────────────────────────────┘
          ▲  ETL (Laravel Artisan)
┌─────────┴────────────────────────────────┐
│   Official German Open Data Sources      │
│   Unfallatlas · GV-ISys · GENESIS · KBA  │
└──────────────────────────────────────────┘
```

---

## 📂 Repository Structure

```
unfallatlas-de/
├── backend/                  Laravel 11 REST API
│   ├── app/
│   │   ├── Console/Commands/ ETL import scripts
│   │   ├── Http/Controllers/ API controllers
│   │   └── Models/           Eloquent models
│   ├── database/migrations/  PostgreSQL schema
│   ├── routes/api.php        All API routes
│   └── README.md             Backend setup guide
├── frontend/                 Vue.js 3 + Vite SPA
│   ├── src/views/            Dashboard, Accidents, Regions, Stats, API Explorer
│   ├── src/components/       Reusable UI components
│   └── README.md             Frontend setup guide
├── .gitignore
└── README.md                 ← You are here
```

---

## 🚀 Quick Start

### Prerequisites

- PHP 8.3+, Composer
- PostgreSQL 14+
- Node.js 18+, npm

### 1. Clone & Setup Database

```bash
git clone https://github.com/YOUR_USERNAME/unfallatlas-de.git
cd unfallatlas-de
```

```sql
CREATE DATABASE dmproject;
CREATE USER dmuser WITH PASSWORD 'yourpassword';
GRANT ALL PRIVILEGES ON DATABASE dmproject TO dmuser;
```

### 2. Backend

```bash
cd backend
cp .env.example .env
# Edit .env: set DB_USERNAME and DB_PASSWORD
composer install
php artisan key:generate
php artisan migrate
php artisan db:seed-sources
php artisan serve --port=8002
```

### 3. Import Data

```bash
php artisan import:gemeindeverzeichnis     # Region hierarchy
php artisan import:unfallatlas            # Accident records 2016–2024
php artisan import:genesis                # Population data
php artisan import:registered-cars        # Registered cars data
```

### 4. Frontend

```bash
cd frontend
npm install
cp .env.example .env
npm run dev
# → http://localhost:5173
```

> Full setup details: [`backend/README.md`](backend/README.md) · [`frontend/README.md`](frontend/README.md)

---

## 🔌 API Endpoints

Base URL: `http://localhost:8002/api/v1/`

| Endpoint | Description |
|---|---|
| `GET /accidents` | Paginated records with 15+ filters |
| `GET /regions` | State / district / municipality hierarchy |
| `GET /aggregates/accidents` | Counts by region and year |
| `GET /aggregates/time-series` | Group by year / month / weekday / hour |
| `GET /aggregates/rates?per=population` | Rate per 100,000 inhabitants |
| `GET /aggregates/rates?per=registered_cars` | Rate per 100,000 registered cars ⭐ |
| `GET /aggregates/rankings` | Top / bottom districts |
| `GET /aggregates/year-over-year` | Annual change % |
| `GET /aggregates/earliest-year` | Earliest data year (overall or per state) |
| `GET /aggregates/zero-cases` | Districts with zero accidents |
| `GET /meta/sources` | Data sources and licenses |
| `GET /import-runs` | ETL import history |
| `GET /stats/summary` | Platform-wide statistics |

📖 **Interactive Swagger docs:** `http://localhost:8002/api/documentation`

---

## 🗄️ Data Sources

All data is published by German federal and state statistical offices under open data licenses.

| Source | Publisher | Content | License |
|---|---|---|---|
| [Unfallatlas Germany](https://www.opengeodata.nrw.de/produkte/transport_verkehr/unfallatlas/) | Statistische Ämter des Bundes und der Länder | Road accident point data 2016–2024 | dl-de/by-2-0 |
| [GV-ISys Region Directory](https://www.destatis.de/DE/Themen/Laender-Regionen/Regionales/Gemeindeverzeichnis/) | Statistisches Bundesamt (Destatis) | Official AGS region hierarchy | dl-de/by-2-0 |
| [GENESIS-Online](https://www-genesis.destatis.de/) | Statistisches Bundesamt (Destatis) | Population by district and year | dl-de/by-2-0 |
| [Regional Atlas Germany](https://regionalatlas.statistikportal.de/) | Statistische Ämter des Bundes und der Länder | Registered cars by district | dl-de/by-2-0 |

**License:** [Datenlizenz Deutschland – Namensnennung – Version 2.0](https://www.govdata.de/dl-de/by-2-0)  
**Attribution required:** *Statistische Ämter des Bundes und der Länder*

---

## 🗃️ Database Schema

```
accidents        2,098,019 rows — year, month, hour, category, participant flags,
                               light/road condition, crash type, district_ags, lat/lon
regions          Hierarchical: state → district → municipality (AGS codes)
indicators       Metric registry (population, registered_cars, ...)
indicator_values Time-series: region × indicator × year
data_sources     Source metadata + license
import_runs      ETL provenance tracking
```

---

## ⚙️ Technical Highlights

**City-State Special Case (Berlin & Hamburg)**  
GV-ISys registers Berlin as `11000` and Hamburg as `02000`, but Unfallatlas splits them into sub-district codes (`11001–11012`, `02101–02717`). The API detects any 5-digit AGS ending in `000` and automatically widens the query:

```php
if (str_ends_with($ags, '000') && strlen($ags) === 5) {
    $query->whereRaw("district_ags LIKE ?", [substr($ags, 0, 2) . '%']);
}
```

**Cross-Source Analysis**  
Accident rate per 100,000 registered cars combines two independent sources:  
`(accidents ÷ registered_cars) × 100,000` — answers which districts are most dangerous relative to actual traffic volume.

---

## 📋 Exam Questions (TU Chemnitz DBW)

The dashboard answers all mandatory exam questions live from the API:

| # | Question | Answer |
|---|---|---|
| 1 | Earliest accident year in dataset? | **2016** |
| 2 | Accidents in Saxony 2023? | **12,513** |
| 3 | NRW data available since? | **2019** |
| 4 | Mecklenburg-Vorpommern since? | **2020** |
| 5 | Pedestrian accidents Berlin 2023? | **1,182** |
| 6 | Bicycle accidents Dresden 2023? | Live from API |

---

## 📄 License

Source code: **MIT**  
Data: [Datenlizenz Deutschland – Namensnennung – Version 2.0 (dl-de/by-2-0)](https://www.govdata.de/dl-de/by-2-0)

---

<p align="center">Built with ❤️ at TU Chemnitz · DBW Project 2024/25</p>
