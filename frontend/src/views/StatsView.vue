<template>
  <div class="stats-view">
    <div class="page-header">
      <h1>Statistics</h1>
      <p class="subtitle">Aggregated accident analysis by region, year, and participant type</p>
    </div>

    <!-- Tabs -->
    <div class="tabs">
      <button
        v-for="tab in tabs"
        :key="tab.id"
        class="tab-btn"
        :class="{ active: activeTab === tab.id }"
        @click="switchTab(tab.id)"
      >
        {{ tab.icon }} {{ tab.label }}
      </button>
    </div>

    <!-- Tab: Rankings -->
    <div v-if="activeTab === 'rankings'" class="tab-content">
      <div class="tab-controls card">
        <div class="controls-row">
          <div class="control-group">
            <label class="control-label">Year</label>
            <select v-model.number="rankYear" @change="loadRankings">
              <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
            </select>
          </div>
          <div class="control-group">
            <label class="control-label">Metric</label>
            <select v-model="rankMetric" @change="loadRankings">
              <option value="total">Total Accidents</option>
              <option value="fatal">Fatal Accidents</option>
              <option value="bicycle">Bicycle Accidents</option>
              <option value="pedestrian">Pedestrian Accidents</option>
            </select>
          </div>
          <div class="control-group">
            <label class="control-label">Limit</label>
            <select v-model.number="rankLimit" @change="loadRankings">
              <option :value="5">5</option>
              <option :value="10">10</option>
              <option :value="20">20</option>
            </select>
          </div>
        </div>
      </div>

      <div v-if="rankingsLoading" class="loading-state">
        <span class="spinner"></span> Loading rankings…
      </div>

      <div v-else class="rankings-grid">
        <AccidentChart
          :data="topData"
          type="bar"
          :title="`🏆 Top ${rankLimit} Districts — ${rankMetricLabel} (${rankYear})`"
          height="360px"
          color="#E63946"
        />
        <AccidentChart
          :data="bottomData"
          type="bar"
          :title="`📉 Bottom ${rankLimit} Districts — ${rankMetricLabel} (${rankYear})`"
          height="360px"
          color="#2EC4B6"
        />
      </div>

      <!-- Rankings table -->
      <div class="card" v-if="!rankingsLoading && rankingsData.length > 0">
        <h3 class="table-section-title">Full Rankings Table</h3>
        <table class="data-table">
          <thead>
            <tr>
              <th>#</th>
              <th>District</th>
              <th>State AGS</th>
              <th>Accidents</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(row, i) in rankingsData" :key="row.ags || i">
              <td>
                <span class="rank-badge" :class="{ 'rank-top3': i < 3 }">{{ i + 1 }}</span>
              </td>
              <td>{{ row.name || row.district_name }}</td>
              <td><code>{{ row.state_ags || row.ags?.slice(0,2) }}</code></td>
              <td><strong>{{ (row.accident_count ?? row.total ?? 0).toLocaleString('en-US') }}</strong></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Tab: Rates per 100k -->
    <div v-if="activeTab === 'rates'" class="tab-content">
      <div class="tab-controls card">
        <div class="controls-row">
          <div class="control-group">
            <label class="control-label">State</label>
            <select v-model="ratesStateAgs" @change="loadRates">
              <option value="">All States</option>
              <option v-for="s in statesList" :key="s.ags" :value="s.ags || s.state_ags">
                {{ s.name }}
              </option>
            </select>
          </div>
          <div class="control-group">
            <label class="control-label">Year</label>
            <select v-model.number="ratesYear" @change="loadRates">
              <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
            </select>
          </div>
          <div class="control-group">
            <label class="control-label">Per 100k of</label>
            <select v-model="ratesPer" @change="loadRates">
              <option value="population">👥 Population</option>
              <option value="registered_cars">🚗 Registered Cars</option>
            </select>
          </div>
        </div>
        <div v-if="ratesPer === 'registered_cars'" class="info-note">
          ⭐ Cross-source: accidents (Unfallatlas) ÷ registered cars (KBA/Regionalstatistik) — data available 2016–2023
        </div>
      </div>

      <div v-if="ratesLoading" class="loading-state">
        <span class="spinner"></span> Loading rates…
      </div>

      <div v-else-if="ratesData.length === 0" class="empty-state">
        <div class="empty-icon">📊</div>
        <p>No rate data available for the selected filters.</p>
      </div>

      <div v-else>
        <AccidentChart
          :data="ratesChartData"
          type="bar"
          :title="ratesPer === 'registered_cars' ? `Accidents per 100,000 Registered Cars (${ratesYear})` : `Accident Rate per 100,000 Inhabitants (${ratesYear})`"
          height="350px"
          color="#FF9F1C"
        />
        <div class="card" style="margin-top: 1.5rem;">
          <table class="data-table">
            <thead>
              <tr>
                <th>#</th>
                <th>District</th>
                <th>Rate per 100k</th>
                <th>Accidents</th>
                <th>{{ ratesPer === 'registered_cars' ? '🚗 Registered Cars' : '👥 Population' }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, i) in ratesData.slice(0, ratesShowAll ? ratesData.length : 20)" :key="row.ags || row.district_ags">
                <td class="rank-num">{{ i + 1 }}</td>
                <td>{{ row.name || row.district_name }}</td>
                <td style="color: var(--warning); font-weight: 600;">
                  {{ row.rate_per_100k != null ? Number(row.rate_per_100k).toFixed(1) : '—' }}
                </td>
                <td>{{ (row.accident_count ?? row.total ?? 0).toLocaleString('en-US') }}</td>
                <td>{{ row.denominator_value ? Number(row.denominator_value).toLocaleString('en-US') : (row.population ? Number(row.population).toLocaleString('en-US') : '—') }}</td>
              </tr>
            </tbody>
          </table>
          <div v-if="ratesData.length > 20" class="show-more-row">
            <button class="btn-ghost btn-sm" @click="ratesShowAll = !ratesShowAll">
              {{ ratesShowAll ? '▲ Show Top 20 only' : `▼ Show all ${ratesData.length} districts` }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Tab: Year-over-Year -->
    <div v-if="activeTab === 'yoy'" class="tab-content">
      <div class="tab-controls card">
        <div class="controls-row">
          <div class="control-group">
            <label class="control-label">State (optional)</label>
            <select v-model="yoyStateAgs" @change="loadYoy">
              <option value="">All Germany</option>
              <option v-for="s in statesList" :key="s.ags" :value="s.ags || s.state_ags">
                {{ s.name }}
              </option>
            </select>
          </div>
        </div>
      </div>

      <div v-if="yoyLoading" class="loading-state">
        <span class="spinner"></span> Loading trend data…
      </div>

      <div v-else-if="yoyData.length === 0" class="empty-state">
        <div class="empty-icon">📈</div>
        <p>No trend data available.</p>
      </div>

      <div v-else class="yoy-content">
        <AccidentChart
          :data="yoyData"
          type="line"
          :title="`National Accident Trend ${yoyStateAgs ? '— ' + selectedStateName : '— All Germany'}`"
          height="380px"
          color="#E63946"
        />

        <div class="card yoy-table-card">
          <h3 class="table-section-title">Year-over-Year Change</h3>
          <table class="data-table">
            <thead>
              <tr>
                <th>Year</th>
                <th>Accidents</th>
                <th>Change</th>
                <th>Change %</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, i) in yoyTableData" :key="row.year">
                <td><strong>{{ row.year }}</strong></td>
                <td>{{ row.count.toLocaleString('en-US') }}</td>
                <td :class="row.diff > 0 ? 'positive' : row.diff < 0 ? 'negative' : ''">
                  {{ row.diff !== null ? (row.diff > 0 ? '+' : '') + row.diff.toLocaleString('en-US') : '—' }}
                </td>
                <td :class="row.pct > 0 ? 'positive' : row.pct < 0 ? 'negative' : ''">
                  {{ row.pct !== null ? (row.pct > 0 ? '+' : '') + row.pct.toFixed(1) + '%' : '—' }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Tab: Zero Cases (Bonus) -->
    <div v-if="activeTab === 'zero'" class="tab-content">
      <div class="tab-controls card">
        <div class="controls-row">
          <div class="control-group">
            <label class="control-label">Year</label>
            <select v-model.number="zeroYear" @change="loadZeroCases">
              <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
            </select>
          </div>
        </div>
      </div>

      <div class="zero-header card">
        <div class="zero-kpi">
          <span class="zero-kpi-val" style="color: var(--accent2)">
            {{ zeroCases.length }}
          </span>
          <span class="zero-kpi-label">Districts with Zero Accidents in {{ zeroYear }}</span>
        </div>
      </div>

      <div v-if="zeroLoading" class="loading-state">
        <span class="spinner"></span> Loading zero-case districts…
      </div>

      <div v-else-if="zeroCases.length === 0" class="empty-state">
        <div class="empty-icon">✅</div>
        <p>No districts with zero accidents found for {{ zeroYear }}.</p>
        <p class="text-muted" style="margin-top: 0.5rem; font-size: 0.85rem;">
          This could mean all districts reported at least one accident, or data is unavailable.
        </p>
      </div>

      <div v-else class="card">
        <table class="data-table">
          <thead>
            <tr>
              <th>#</th>
              <th>District</th>
              <th>AGS</th>
              <th>State</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(row, i) in zeroCasesPaged" :key="row.ags || i">
              <td>{{ (zeroPage - 1) * zeroPerPage + i + 1 }}</td>
              <td>{{ row.name || row.district_name }}</td>
              <td><code>{{ row.ags || row.district_ags }}</code></td>
              <td>{{ row.state_name || row.state_ags || '—' }}</td>
            </tr>
          </tbody>
        </table>
        <div class="pagination">
          <button class="btn-ghost" :disabled="zeroPage === 1" @click="zeroPage--">← Prev</button>
          <span class="page-info">Page {{ zeroPage }} of {{ zeroTotalPages }}</span>
          <button class="btn-ghost" :disabled="zeroPage >= zeroTotalPages" @click="zeroPage++">Next →</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import AccidentChart from '../components/AccidentChart.vue'
import { getRankings, getRates, getYearOverYear, getZeroCases, getRegions } from '../api/index.js'

const tabs = [
  { id: 'rankings', label: 'Rankings', icon: '🏆' },
  { id: 'rates', label: 'Rates per 100k', icon: '📉' },
  { id: 'yoy', label: 'Year-over-Year', icon: '📈' },
  { id: 'zero', label: 'Zero Cases', icon: '⭕' }
]
const activeTab = ref('rankings')

const years = Array.from({ length: 9 }, (_, i) => 2016 + i).reverse()
const statesList = ref([])

// Rankings
const rankYear = ref(2023)
const rankMetric = ref('total')
const rankLimit = ref(10)
const rankingsData = ref([])
const rankingsLoading = ref(false)

const rankMetricLabel = computed(() => ({
  total: 'Total Accidents', fatal: 'Fatal', bicycle: 'Bicycle', pedestrian: 'Pedestrian'
})[rankMetric.value])

// Helper: extract count from ranking row regardless of field name
const rankVal = (d) => d.metric_value ?? d.total_count ?? d.accident_count ?? d.total ?? 0

// Rates
const ratesStateAgs = ref('')
const ratesYear = ref(2023)
const ratesPer = ref('population')
const ratesShowAll = ref(false)
const ratesData = ref([])
const ratesLoading = ref(false)

const ratesChartData = computed(() =>
  ratesData.value.slice(0, 20).map(d => ({
    label: (d.name || d.ags || '').slice(0, 18),
    count: d.rate_per_100k != null ? Number(d.rate_per_100k) : 0
  }))
)

// YoY
const yoyStateAgs = ref('')
const yoyData = ref([])
const yoyLoading = ref(false)

const selectedStateName = computed(() => {
  const s = statesList.value.find(s => (s.ags || s.state_ags) === yoyStateAgs.value)
  return s?.name || ''
})

const yoyTableData = computed(() => {
  return yoyData.value.map((row, i) => {
    const prev = i > 0 ? yoyData.value[i - 1].count : null
    const diff = prev !== null ? row.count - prev : null
    const pct = prev !== null && prev > 0 ? ((row.count - prev) / prev) * 100 : null
    return { ...row, diff, pct }
  })
})

// Zero cases
const zeroYear = ref(2023)
const zeroCases = ref([])
const zeroLoading = ref(false)
const zeroPage = ref(1)
const zeroPerPage = 20
const zeroTotalPages = computed(() => Math.max(1, Math.ceil(zeroCases.value.length / zeroPerPage)))
const zeroCasesPaged = computed(() => zeroCases.value.slice((zeroPage.value - 1) * zeroPerPage, zeroPage.value * zeroPerPage))

const switchTab = (id) => {
  activeTab.value = id
  if (id === 'rankings' && rankingsData.value.length === 0) loadRankings()
  if (id === 'rates' && ratesData.value.length === 0) loadRates()
  if (id === 'yoy' && yoyData.value.length === 0) loadYoy()
  if (id === 'zero' && zeroCases.value.length === 0) loadZeroCases()
}

const topData = ref([])
const bottomData = ref([])

const loadRankings = async () => {
  rankingsLoading.value = true
  try {
    const [topRes, botRes] = await Promise.all([
      getRankings({ year: rankYear.value, metric: rankMetric.value, limit: rankLimit.value, order: 'desc' }),
      getRankings({ year: rankYear.value, metric: rankMetric.value, limit: rankLimit.value, order: 'asc' }),
    ])
    topData.value = (topRes.data.data || []).map(d => ({
      label: (d.name || d.ags || '').slice(0, 22),
      count: rankVal(d)
    }))
    bottomData.value = (botRes.data.data || []).map(d => ({
      label: (d.name || d.ags || '').slice(0, 22),
      count: rankVal(d)
    }))
  } catch (e) {
    console.error('Rankings error', e)
    topData.value = []
    bottomData.value = []
  } finally {
    rankingsLoading.value = false
  }
}

const loadRates = async () => {
  ratesLoading.value = true
  ratesShowAll.value = false
  try {
    const params = { year: ratesYear.value, per: ratesPer.value }
    if (ratesStateAgs.value) params.state_ags = ratesStateAgs.value
    const res = await getRates(params)
    ratesData.value = res.data.data || []
  } catch (e) {
    console.error('Rates error', e)
    ratesData.value = []
  } finally {
    ratesLoading.value = false
  }
}

const loadYoy = async () => {
  yoyLoading.value = true
  try {
    const params = {}
    if (yoyStateAgs.value) params.state_ags = yoyStateAgs.value
    const res = await getYearOverYear(params)
    const raw = res.data.data || res.data || []
    yoyData.value = (Array.isArray(raw) ? raw : [])
      .sort((a, b) => a.year - b.year)
      .map(d => ({
        label: String(d.year),
        count: d.accident_count ?? d.total ?? 0,
        year: d.year
      }))
  } catch (e) {
    console.error('YoY error', e)
    yoyData.value = []
  } finally {
    yoyLoading.value = false
  }
}

const loadZeroCases = async () => {
  zeroLoading.value = true
  zeroPage.value = 1
  try {
    const res = await getZeroCases({ year: zeroYear.value })
    zeroCases.value = res.data.data || []
  } catch (e) {
    console.error('Zero cases error', e)
    zeroCases.value = []
  } finally {
    zeroLoading.value = false
  }
}

onMounted(async () => {
  // Load states for dropdowns
  try {
    const res = await getRegions({ level: 'state', per_page: 20 })
    statesList.value = res.data.data || []
  } catch (e) {
    console.error('States load error', e)
  }
  loadRankings()
})
</script>

<style scoped>
.stats-view {
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

.tabs {
  display: flex;
  gap: 0.5rem;
  border-bottom: 1px solid var(--border);
  padding-bottom: 0;
  flex-wrap: wrap;
}

.tab-btn {
  padding: 0.6rem 1.2rem;
  background: transparent;
  border: none;
  border-bottom: 2px solid transparent;
  color: var(--text-muted);
  font-size: 0.9rem;
  font-weight: 500;
  cursor: pointer;
  transition: color 0.2s, border-color 0.2s;
  font-family: var(--sans);
  margin-bottom: -1px;
}

.tab-btn:hover {
  color: var(--text);
}

.tab-btn.active {
  color: var(--accent);
  border-bottom-color: var(--accent);
}

.tab-content {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.tab-controls {
  padding: 1rem 1.5rem;
}

.controls-row {
  display: flex;
  gap: 1.5rem;
  flex-wrap: wrap;
  align-items: flex-end;
}

.control-group {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
  min-width: 150px;
}

.control-label {
  color: var(--text-muted);
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.rankings-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1.5rem;
}

.table-section-title {
  font-size: 0.95rem;
  color: var(--text);
  margin-bottom: 1rem;
}

.rank-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  border-radius: 50%;
  background: var(--surface2);
  font-size: 0.75rem;
  font-weight: 700;
  color: var(--text-muted);
}

.rank-badge.rank-top3 {
  background: rgba(230, 57, 70, 0.15);
  color: var(--accent);
}

.yoy-content {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.yoy-table-card {
  margin-top: 0;
}

.positive { color: #E63946; }
.negative { color: #2EC4B6; }

.zero-header {
  padding: 1.25rem 1.5rem;
}

.zero-kpi {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.zero-kpi-val {
  font-size: 2.5rem;
  font-weight: 700;
}

.zero-kpi-label {
  color: var(--text);
  font-size: 1rem;
}

.rank-num {
  color: var(--text-muted);
  font-size: 0.8rem;
  width: 2rem;
  text-align: center;
}

.show-more-row {
  text-align: center;
  padding: 0.75rem;
  border-top: 1px solid var(--border);
}

.info-note {
  background: rgba(46,196,182,0.08);
  border: 1px solid rgba(46,196,182,0.3);
  color: var(--accent2);
  border-radius: var(--radius);
  padding: 0.5rem 1rem;
  font-size: 0.85rem;
  margin-top: 0.5rem;
}

.loading-state {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.75rem;
  padding: 3rem;
  color: var(--text-muted);
}

.empty-state {
  text-align: center;
  padding: 3rem 1rem;
  color: var(--text-muted);
}

.empty-icon {
  font-size: 3rem;
  margin-bottom: 0.75rem;
}

.text-muted { color: var(--text-muted); }

@media (max-width: 768px) {
  .rankings-grid {
    grid-template-columns: 1fr;
  }
}
</style>
