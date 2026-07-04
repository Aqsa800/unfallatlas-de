<template>
  <div class="map-view">
    <div class="page-header">
      <h1>🗺️ Map View</h1>
      <p class="subtitle">Geographic visualization of accident density by district</p>
    </div>

    <div class="card map-card">
      <div class="map-placeholder">
        <div class="map-icon">🗺️</div>
        <h3>Interactive Choropleth Map</h3>
        <p>Requires GeoJSON district boundaries (PostGIS geometry).</p>
        <p class="p-muted">
          To enable: import GeoJSON from
          <a href="https://gdz.bkg.bund.de/" target="_blank">BKG Geodatenzentrum</a>
          and expose via <code>/api/v1/regions/geojson?level=district</code>
        </p>

        <div class="map-instructions">
          <div class="instruction-step">
            <div class="step-num">1</div>
            <div class="step-text">
              Download <strong>VG250</strong> district boundaries from
              <a href="https://gdz.bkg.bund.de/index.php/default/digitale-geodaten/verwaltungsgebiete.html" target="_blank">BKG Geodatenzentrum ↗</a>
            </div>
          </div>
          <div class="instruction-step">
            <div class="step-num">2</div>
            <div class="step-text">
              Import into PostGIS: <code>ogr2ogr -f PostgreSQL PG:"..." VG250_KRS.shp -nln regions</code>
            </div>
          </div>
          <div class="instruction-step">
            <div class="step-num">3</div>
            <div class="step-text">
              Expose GeoJSON endpoint at <code>/api/v1/regions/geojson?level=district</code>
            </div>
          </div>
          <div class="instruction-step">
            <div class="step-num">4</div>
            <div class="step-text">
              Render with Leaflet + choropleth coloring by accident rate
            </div>
          </div>
        </div>

        <div class="map-legend">
          <div class="legend-title">Accident Density Legend</div>
          <div class="legend-items">
            <div class="legend-item">
              <span class="legend-swatch" style="background:#E63946"></span>
              High accident density (&gt; 500/yr)
            </div>
            <div class="legend-item">
              <span class="legend-swatch" style="background:#FF9F1C"></span>
              Medium (200–500/yr)
            </div>
            <div class="legend-item">
              <span class="legend-swatch" style="background:#FFD700"></span>
              Low (50–200/yr)
            </div>
            <div class="legend-item">
              <span class="legend-swatch" style="background:#2EC4B6"></span>
              Very low (&lt; 50/yr)
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Static accident hotspot table -->
    <div class="card">
      <h3 class="section-title">📍 Known Accident Hotspot Cities</h3>
      <p class="section-subtitle">Major urban centres with historically high accident frequency</p>
      <div class="hotspot-grid">
        <div v-for="city in hotspotCities" :key="city.name" class="hotspot-card">
          <div class="hotspot-name">{{ city.name }}</div>
          <div class="hotspot-state">{{ city.state }}</div>
          <div class="hotspot-coords">
            <code>{{ city.lat }}° N, {{ city.lon }}° E</code>
          </div>
          <div class="hotspot-pop">Pop. ~{{ city.population }}</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
const hotspotCities = [
  { name: 'Berlin', state: 'Berlin', lat: '52.5200', lon: '13.4050', population: '3.7M' },
  { name: 'Hamburg', state: 'Hamburg', lat: '53.5753', lon: '10.0153', population: '1.9M' },
  { name: 'München', state: 'Bayern', lat: '48.1351', lon: '11.5820', population: '1.5M' },
  { name: 'Köln', state: 'NRW', lat: '50.9333', lon: '6.9500', population: '1.1M' },
  { name: 'Frankfurt am Main', state: 'Hessen', lat: '50.1109', lon: '8.6821', population: '760k' },
  { name: 'Stuttgart', state: 'BW', lat: '48.7758', lon: '9.1829', population: '635k' },
  { name: 'Dresden', state: 'Sachsen', lat: '51.0504', lon: '13.7373', population: '555k' },
  { name: 'Leipzig', state: 'Sachsen', lat: '51.3397', lon: '12.3731', population: '615k' }
]
</script>

<style scoped>
.map-view {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.page-header h1 {
  margin: 0;
  font-size: 1.8rem;
}

.subtitle {
  color: var(--text-muted);
  margin-top: 0.3rem;
  font-size: 0.9rem;
}

.map-card {
  min-height: 520px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.map-placeholder {
  text-align: center;
  color: var(--text-muted);
  max-width: 700px;
  width: 100%;
}

.map-icon {
  font-size: 5rem;
  margin-bottom: 1rem;
}

.map-placeholder h3 {
  color: var(--text);
  margin: 0 0 0.5rem;
  font-size: 1.3rem;
}

.map-placeholder p {
  margin: 0.3rem 0;
  font-size: 0.9rem;
}

.p-muted {
  color: var(--text-muted);
  font-size: 0.85rem !important;
}

.map-instructions {
  margin: 2rem auto;
  text-align: left;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  max-width: 560px;
}

.instruction-step {
  display: flex;
  align-items: flex-start;
  gap: 1rem;
  background: var(--surface2);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 0.75rem 1rem;
}

.step-num {
  background: var(--accent);
  color: white;
  width: 24px;
  height: 24px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.78rem;
  font-weight: 700;
  flex-shrink: 0;
}

.step-text {
  font-size: 0.85rem;
  color: var(--text);
  line-height: 1.4;
}

.step-text strong {
  color: var(--text);
}

.map-legend {
  margin-top: 1.5rem;
  text-align: left;
  display: inline-block;
}

.legend-title {
  font-size: 0.8rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--text-muted);
  margin-bottom: 0.5rem;
}

.legend-items {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
}

.legend-item {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  font-size: 0.82rem;
  color: var(--text);
}

.legend-swatch {
  width: 18px;
  height: 14px;
  border-radius: 3px;
  display: inline-block;
  flex-shrink: 0;
}

.section-title {
  margin: 0 0 0.3rem;
  font-size: 1rem;
  color: var(--text);
}

.section-subtitle {
  color: var(--text-muted);
  font-size: 0.85rem;
  margin-bottom: 1.25rem;
}

.hotspot-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
  gap: 1rem;
}

.hotspot-card {
  background: var(--surface2);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 0.85rem;
  transition: border-color 0.2s;
}

.hotspot-card:hover {
  border-color: var(--accent2);
}

.hotspot-name {
  font-weight: 700;
  color: var(--text);
  font-size: 0.95rem;
}

.hotspot-state {
  color: var(--accent2);
  font-size: 0.78rem;
  margin-top: 0.15rem;
}

.hotspot-coords {
  margin-top: 0.5rem;
}

.hotspot-coords code {
  font-size: 0.72rem;
  color: var(--text-muted);
}

.hotspot-pop {
  color: var(--text-muted);
  font-size: 0.78rem;
  margin-top: 0.25rem;
}
</style>
