<template>
  <div class="regions-view">
    <div class="page-header">
      <h1>Regional Browser</h1>
      <p class="subtitle">Explore accident data by state and district</p>
    </div>

    <div class="regions-layout">
      <!-- Column 1: States -->
      <div class="region-column">
        <div class="column-header">
          <span class="column-icon">🏛️</span>
          <span>Bundesländer (16 States)</span>
        </div>
        <div v-if="statesLoading" class="col-loading">
          <span class="spinner"></span>
        </div>
        <ul v-else class="region-list">
          <li
            v-for="state in states"
            :key="state.ags"
            class="region-item"
            :class="{ active: selectedState && (selectedState.ags === state.ags || selectedState.state_ags === state.ags) }"
            @click="selectState(state)"
          >
            <span class="region-name">{{ state.name }}</span>
            <span class="region-ags">{{ state.ags || state.state_ags }}</span>
          </li>
        </ul>
      </div>

      <!-- Column 2: Districts -->
      <div class="region-column">
        <div class="column-header">
          <span class="column-icon">🗺️</span>
          <span>{{ selectedState ? selectedState.name : 'Districts' }}</span>
        </div>
        <div v-if="!selectedState" class="col-placeholder">
          <p>← Select a state to view its districts</p>
        </div>
        <div v-else-if="districtsLoading" class="col-loading">
          <span class="spinner"></span>
        </div>
        <ul v-else class="region-list">
          <li
            v-for="district in districts"
            :key="district.ags"
            class="region-item"
            :class="{ active: selectedDistrict && selectedDistrict.ags === district.ags }"
            @click="selectDistrict(district)"
          >
            <span class="region-name">{{ district.name }}</span>
            <span class="region-ags">{{ district.ags }}</span>
          </li>
          <li v-if="districts.length === 0" class="region-empty">No districts found</li>
        </ul>
      </div>

      <!-- Column 3: Detail -->
      <div class="region-column detail-column">
        <div class="column-header">
          <span class="column-icon">📊</span>
          <span>{{ selectedDistrict ? selectedDistrict.name : 'District Statistics' }}</span>
        </div>

        <div v-if="!selectedDistrict" class="col-placeholder">
          <p>← Select a district to view statistics</p>
        </div>

        <div v-else-if="detailLoading" class="col-loading">
          <span class="spinner"></span>
          <span>Loading statistics…</span>
        </div>

        <div v-else class="detail-content">
          <!-- Summary Cards -->
          <div class="detail-stats-grid">
            <div class="detail-stat">
              <div class="ds-value" style="color: var(--accent)">
                {{ totalAccidents.toLocaleString('en-US') }}
              </div>
              <div class="ds-label">Total Accidents</div>
            </div>
            <div class="detail-stat">
              <div class="ds-value" style="color: var(--warning)">
                {{ latestPop ? latestPop.toLocaleString('en-US') : '—' }}
              </div>
              <div class="ds-label">Population ({{ latestPopYear }})</div>
            </div>
            <div class="detail-stat">
              <div class="ds-value" style="color: #74b9ff">
                {{ latestMale ? latestMale.toLocaleString('en-US') : '—' }}
              </div>
              <div class="ds-label">Male</div>
            </div>
            <div class="detail-stat">
              <div class="ds-value" style="color: #fd79a8">
                {{ latestFemale ? latestFemale.toLocaleString('en-US') : '—' }}
              </div>
              <div class="ds-label">Female</div>
            </div>
            <div class="detail-stat">
              <div class="ds-value" style="color: var(--accent2)">
                {{ rateValue }}
              </div>
              <div class="ds-label">Rate per 100k</div>
            </div>
          </div>

          <!-- Accidents by Year -->
          <div class="detail-section">
            <h4 class="detail-section-title">Accidents by Year</h4>
            <table class="detail-table">
              <thead>
                <tr>
                  <th>Year</th>
                  <th>Accidents</th>
                  <th>Fatal</th>
                  <th>Serious</th>
                  <th>Slight</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in yearlyData" :key="row.year">
                  <td><strong>{{ row.year }}</strong></td>
                  <td>{{ (row.accident_count || row.total || 0).toLocaleString('en-US') }}</td>
                  <td class="fatal-cell">{{ (row.fatal_count || 0).toLocaleString('en-US') }}</td>
                  <td class="serious-cell">{{ (row.serious_count || 0).toLocaleString('en-US') }}</td>
                  <td class="slight-cell">{{ (row.slight_count || 0).toLocaleString('en-US') }}</td>
                </tr>
                <tr v-if="yearlyData.length === 0">
                  <td colspan="5" class="text-center text-muted">No data available</td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Rate per 100k section -->
          <div class="detail-section" v-if="ratesData.length > 0 && selectedDistrict">
            <h4 class="detail-section-title">Rate per 100,000 Inhabitants</h4>
            <div v-if="!selectedDistrict.population && indicatorValues.length === 0" class="no-pop-note">
              ⚠️ Population data unavailable — rate cannot be calculated
            </div>
            <table class="detail-table">
              <thead>
                <tr><th>Year</th><th>Accidents</th><th>Rate / 100k</th><th>Total Pop.</th><th>Male</th><th>Female</th></tr>
              </thead>
              <tbody>
                <tr v-for="row in ratesData" :key="row.year">
                  <td><strong>{{ row.year }}</strong></td>
                  <td>{{ (row.accident_count || 0).toLocaleString('en-US') }}</td>
                  <td class="rate-cell">{{ row.rate_per_100k != null ? row.rate_per_100k.toFixed(1) : '—' }}</td>
                  <td>{{ row.population ? Number(row.population).toLocaleString('en-US') : '—' }}</td>
                  <td style="color:#74b9ff">{{ row.pop_male ? Number(row.pop_male).toLocaleString('en-US') : '—' }}</td>
                  <td style="color:#fd79a8">{{ row.pop_female ? Number(row.pop_female).toLocaleString('en-US') : '—' }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="detail-section" v-if="indicatorValues.length > 0">
            <h4 class="detail-section-title">Indicator Values</h4>
            <div v-for="group in groupedIndicators" :key="group.code" class="indicator-group">
              <div class="indicator-group-header">
                <span class="indicator-badge" :style="{ background: group.color + '22', color: group.color, border: '1px solid ' + group.color + '55' }">
                  {{ group.icon }} {{ group.label }}
                </span>
                <span class="indicator-unit">{{ group.unit }}</span>
              </div>
              <table class="detail-table">
                <thead>
                  <tr>
                    <th>Year</th>
                    <th>Value</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="row in group.rows" :key="row.year">
                    <td><strong>{{ row.year ?? '—' }}</strong></td>
                    <td>{{ row.value != null ? Number(row.value).toLocaleString('en-US') : '—' }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { getRegions, getRegion, getAggregates } from '../api/index.js'

const states = ref([])
const districts = ref([])
const selectedState = ref(null)
const selectedDistrict = ref(null)
const selectedDistrictDetail = ref(null)
const statesLoading = ref(false)
const districtsLoading = ref(false)
const detailLoading = ref(false)
const yearlyData = ref([])
const ratesData = ref([])

const indicatorValues = computed(() => selectedDistrictDetail.value?.indicator_values || [])

const INDICATOR_META = {
  population:        { label: 'Total Population',   icon: '👥', color: '#FF9F1C', unit: 'persons' },
  population_male:   { label: 'Male Population',    icon: '👨', color: '#74b9ff', unit: 'persons' },
  population_female: { label: 'Female Population',  icon: '👩', color: '#fd79a8', unit: 'persons' },
  registered_cars:   { label: 'Registered Cars',    icon: '🚗', color: '#2EC4B6', unit: 'vehicles' },
}

const groupedIndicators = computed(() => {
  const groups = {}
  for (const row of indicatorValues.value) {
    const code = row.indicator_code
    if (!groups[code]) {
      const meta = INDICATOR_META[code] || { label: code, icon: '📊', color: '#8B949E', unit: row.unit || '' }
      groups[code] = { code, ...meta, rows: [] }
    }
    groups[code].rows.push(row)
  }
  return Object.values(groups)
})

const totalAccidents = computed(() => {
  return yearlyData.value.reduce((sum, r) => sum + (r.accident_count || r.total || 0), 0)
})

// Extract latest population figures from indicator_values
const getIndByYear = (code) => {
  const detail = selectedDistrictDetail.value
  if (!detail?.indicator_values) return {}
  const rows = detail.indicator_values.filter(r => r.indicator_code === code)
  const map = {}
  rows.forEach(r => { map[r.year] = r.value })
  return map
}

const latestPopYear = computed(() => {
  const ys = Object.keys(getIndByYear('population')).map(Number)
  return ys.length ? Math.max(...ys) : null
})
const latestPop = computed(() => {
  const m = getIndByYear('population')
  return latestPopYear.value ? Math.round(m[latestPopYear.value]) : null
})
const latestMale = computed(() => {
  const m = getIndByYear('population_male')
  return latestPopYear.value ? Math.round(m[latestPopYear.value]) : null
})
const latestFemale = computed(() => {
  const m = getIndByYear('population_female')
  return latestPopYear.value ? Math.round(m[latestPopYear.value]) : null
})

// Rate per 100k: use latest year from computed ratesData
const rateValue = computed(() => {
  if (ratesData.value.length === 0) return '—'
  const latest = ratesData.value[ratesData.value.length - 1]
  const r = latest?.rate_per_100k ?? latest?.rate
  return r != null ? Number(r).toFixed(1) : '—'
})

onMounted(async () => {
  statesLoading.value = true
  try {
    const res = await getRegions({ level: 'state', per_page: 20 })
    states.value = res.data.data || []
  } catch (e) {
    console.error('Failed to load states', e)
  } finally {
    statesLoading.value = false
  }
})

const selectState = async (state) => {
  selectedState.value = state
  selectedDistrict.value = null
  selectedDistrictDetail.value = null
  yearlyData.value = []
  ratesData.value = []
  districts.value = []
  districtsLoading.value = true
  try {
    const stateAgs = state.ags || state.state_ags
    const res = await getRegions({ level: 'district', state_ags: stateAgs, per_page: 500 })
    districts.value = res.data.data || []
  } catch (e) {
    console.error('Failed to load districts', e)
  } finally {
    districtsLoading.value = false
  }
}

const selectDistrict = async (district) => {
  selectedDistrict.value = district
  selectedDistrictDetail.value = null
  yearlyData.value = []
  ratesData.value = []
  detailLoading.value = true
  try {
    const detailRes = await getRegion(district.id)
    selectedDistrictDetail.value = detailRes.data.data || null
    const indicatorRows = selectedDistrictDetail.value?.indicator_values || []
    const populationByYear = new Map(
      indicatorRows.filter(r => r.indicator_code === 'population')
        .map(r => [Number(r.year), Number(r.value)])
    )
    const maleByYear = new Map(
      indicatorRows.filter(r => r.indicator_code === 'population_male')
        .map(r => [Number(r.year), Number(r.value)])
    )
    const femaleByYear = new Map(
      indicatorRows.filter(r => r.indicator_code === 'population_female')
        .map(r => [Number(r.year), Number(r.value)])
    )

    // Load yearly aggregates (one API call covers both accidents table and rate computation)
    const aggRes = await getAggregates({
      level: 'district',
      district_ags: district.ags,
    })
    const aggData = aggRes.data.data || []
    yearlyData.value = (Array.isArray(aggData) ? aggData : []).sort((a, b) => a.year - b.year)

    // Compute per-year rates locally using district population (no extra API call)
    ratesData.value = yearlyData.value.map(row => ({
      year: row.year,
      accident_count: row.accident_count,
      population: populationByYear.get(Number(row.year)) || district.population || null,
      pop_male:   maleByYear.get(Number(row.year)) || null,
      pop_female: femaleByYear.get(Number(row.year)) || null,
      rate_per_100k: (populationByYear.get(Number(row.year)) || district.population || 0) > 0
        ? Math.round((row.accident_count / (populationByYear.get(Number(row.year)) || district.population)) * 100000 * 10) / 10
        : null,
    }))
  } catch (e) {
    console.error('Failed to load district detail', e)
  } finally {
    detailLoading.value = false
  }
}
</script>

<style scoped>
.no-pop-note {
  color: var(--warning);
  font-size: 0.82rem;
  padding: 0.4rem 0.6rem;
  background: rgba(255,159,28,0.08);
  border-radius: 4px;
  border: 1px solid rgba(255,159,28,0.3);
  margin-bottom: 0.8rem;
}

.regions-view {
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

.regions-layout {
  display: grid;
  grid-template-columns: 220px 260px 1fr;
  gap: 0;
  border: 1px solid var(--border);
  border-radius: var(--radius);
  overflow: hidden;
  min-height: 600px;
  background: var(--surface);
}

.region-column {
  display: flex;
  flex-direction: column;
  border-right: 1px solid var(--border);
  overflow: hidden;
}

.region-column:last-child {
  border-right: none;
}

.column-header {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem 1rem;
  background: var(--surface2);
  border-bottom: 1px solid var(--border);
  font-weight: 600;
  font-size: 0.85rem;
  color: var(--text);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.column-icon {
  flex-shrink: 0;
}

.region-list {
  list-style: none;
  overflow-y: auto;
  flex: 1;
  padding: 0;
}

.region-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.6rem 1rem;
  cursor: pointer;
  border-bottom: 1px solid var(--border);
  transition: background 0.15s;
  gap: 0.5rem;
}

.region-item:hover {
  background: rgba(255, 255, 255, 0.04);
}

.region-item.active {
  background: rgba(230, 57, 70, 0.1);
  border-left: 3px solid var(--accent);
}

.region-name {
  color: var(--text);
  font-size: 0.88rem;
  flex: 1;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.region-ags {
  color: var(--text-muted);
  font-size: 0.75rem;
  font-family: var(--mono);
  flex-shrink: 0;
}

.region-empty {
  padding: 1rem;
  color: var(--text-muted);
  font-size: 0.85rem;
  text-align: center;
}

.col-placeholder {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 2rem;
  color: var(--text-muted);
  font-size: 0.88rem;
  text-align: center;
}

.col-loading {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.75rem;
  color: var(--text-muted);
  font-size: 0.88rem;
}

.detail-column {
  background: var(--bg);
}

.detail-content {
  padding: 1.25rem;
  overflow-y: auto;
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.detail-stats-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1rem;
}

.detail-stat {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 0.85rem;
  text-align: center;
}

.ds-value {
  font-size: 1.4rem;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
}

.ds-label {
  color: var(--text-muted);
  font-size: 0.75rem;
  margin-top: 0.25rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.detail-section-title {
  font-size: 0.9rem;
  color: var(--text);
  margin-bottom: 0.6rem;
  font-weight: 600;
}

.indicator-group {
  margin-bottom: 1.2rem;
}

.indicator-group-header {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 0.4rem;
}

.indicator-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  padding: 0.25rem 0.75rem;
  border-radius: 20px;
  font-size: 0.82rem;
  font-weight: 600;
}

.indicator-unit {
  font-size: 0.75rem;
  color: var(--text-muted);
}

.detail-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.85rem;
}

.detail-table th,
.detail-table td {
  padding: 0.45rem 0.75rem;
  border-bottom: 1px solid var(--border);
  text-align: right;
  color: var(--text);
}

.detail-table th:first-child,
.detail-table td:first-child {
  text-align: left;
}

.detail-table th {
  color: var(--text-muted);
  font-size: 0.72rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  background: var(--surface2);
}

.fatal-cell { color: #E63946; }
.serious-cell { color: #FF9F1C; }
.slight-cell { color: #FFD700; }
.rate-cell { color: var(--accent2); font-weight: 600; }

.text-center { text-align: center !important; }
.text-muted { color: var(--text-muted); }

@media (max-width: 1000px) {
  .regions-layout {
    grid-template-columns: 1fr;
    grid-template-rows: auto auto 1fr;
  }
  .region-column {
    border-right: none;
    border-bottom: 1px solid var(--border);
    max-height: 300px;
  }
  .detail-column {
    max-height: none;
    min-height: 400px;
  }
  .detail-stats-grid {
    grid-template-columns: 1fr 1fr;
  }
}
</style>
