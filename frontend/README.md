# UnfallAtlas DE — Frontend

> Vue.js 3 + Vite single-page dashboard for the German Road Accident Open Data Platform.  
> Dark-themed analytics interface with interactive charts, filters, and a live API explorer.

---

## Tech Stack

| Layer | Technology |
|---|---|
| Framework | Vue.js 3 (Composition API) |
| Build Tool | Vite 5 |
| Charts | Chart.js via vue-chartjs |
| HTTP Client | Axios |
| Routing | Vue Router 4 |

---

## Requirements

- Node.js 18+
- npm or yarn
- Backend API running (see `../backend/README.md`)

---

## Installation

```bash
cd frontend
npm install
cp .env.example .env
```

`.env` defaults (edit if your backend runs on a different port):

```env
VITE_API_BASE_URL=http://127.0.0.1:8002
VITE_SWAGGER_URL=http://127.0.0.1:8002/api/documentation
```

---

## Running

```bash
npm run dev
# App available at: http://localhost:5173
```

Build for production:

```bash
npm run build
```

The Vite dev server proxies all `/api` requests to the backend — no CORS issues during development.

---

## Views

### Dashboard (`/`)
- Platform-wide KPI cards (total accidents, districts, states, date range)
- National trend chart (accidents per year 2016–2024)
- Top 10 districts by accident count
- Mandatory exam Q&A section with live API answers

### Accidents (`/accidents`)
- Paginated table of individual accident records
- 15+ filters: state, district, year range, month, weekday, hour, accident category, participant type (bicycle, pedestrian, car, motorcycle, truck), light condition, road condition, crash type
- Export-ready results

### Regions (`/regions`)
- Three-column drill-down: 16 States → Districts → District Statistics
- District detail: year-by-year population (male/female), area, AGS code
- Accident count by year, rate per 100,000 population and per 100,000 registered cars

### Statistics (`/stats`)
- District rankings by accident count or rate
- Accident rate chart: compare population-based vs. registered-car-based rates
- Year-over-year percentage change by district
- Zero-cases table: districts with zero reported accidents in a selected year (paginated)

### API Explorer (`/api-explorer`)
- Live Swagger UI link button
- Interactive endpoint browser with request builder
- Import run history with status and record counts

---

## Project Structure

```
frontend/
├── src/
│   ├── views/
│   │   ├── DashboardView.vue
│   │   ├── AccidentsView.vue
│   │   ├── RegionsView.vue
│   │   ├── StatsView.vue
│   │   └── ApiExplorerView.vue
│   ├── components/          Shared UI components
│   ├── router/index.js      Vue Router configuration
│   └── main.js
├── .env                     API URL configuration (gitignored)
├── .env.example             Template for .env
└── vite.config.js           Vite + proxy configuration
```

---

## Environment Variables

| Variable | Default | Description |
|---|---|---|
| `VITE_API_BASE_URL` | `http://127.0.0.1:8002` | Laravel backend base URL |
| `VITE_SWAGGER_URL` | `http://127.0.0.1:8002/api/documentation` | Swagger UI URL |

All `VITE_` prefixed variables are embedded at build time and accessible via `import.meta.env`.

---

## Data & License

All data is sourced from official German open data portals under the  
**[Datenlizenz Deutschland – Namensnennung – Version 2.0 (dl-de/by-2-0)](https://www.govdata.de/dl-de/by-2-0)**.

Attribution: *Statistische Ämter des Bundes und der Länder*
