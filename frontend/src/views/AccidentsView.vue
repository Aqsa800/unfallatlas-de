<template>
  <div class="accidents-layout">
    <FilterPanel @filter-changed="onFilterChange" />
    <div class="accidents-content">
      <div class="page-header">
        <h1>Accident Records</h1>
        <div class="header-actions">
          <span class="total-badge">{{ total.toLocaleString('en-US') }} records</span>
          <button class="btn-ghost btn-sm" @click="exportCsv">⬇ Export CSV</button>
        </div>
      </div>

      <div v-if="loading" class="loading-state">
        <span class="spinner"></span>
        <span>Loading accidents…</span>
      </div>

      <div v-else-if="accidents.length === 0" class="empty-state">
        <div class="empty-icon">🔍</div>
        <p>No accidents found for the current filters.</p>
      </div>

      <div v-else>
        <div class="table-wrapper">
          <table class="data-table">
            <thead>
              <tr>
                <th>Year</th>
                <th>Month</th>
                <th>Hour</th>
                <th>District AGS</th>
                <th>Category</th>
                <th>Light</th>
                <th>Road</th>
                <th>Crash Type</th>
                <th>Participants</th>
                <th>Coordinates</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="acc in accidents" :key="acc.id">
                <td>{{ acc.year }}</td>
                <td>{{ acc.month }}</td>
                <td>{{ acc.hour ?? '—' }}</td>
                <td><code>{{ acc.district_ags }}</code></td>
                <td>
                  <span :class="['badge', categoryClass(acc.category)]">
                    {{ categoryLabel(acc.category) }}
                  </span>
                </td>
                <td :title="lightLabel(acc.light_condition)">
                  <span class="icon-cell">{{ lightIcon(acc.light_condition) }}</span>
                </td>
                <td :title="roadLabel(acc.road_condition)">
                  <span class="icon-cell">{{ roadIcon(acc.road_condition) }}</span>
                </td>
                <td class="mono-sm" :title="crashTypeLabel(acc.crash_type)">
                  {{ crashTypeLabel(acc.crash_type) }}
                </td>
                <td class="participants">
                  <span v-if="acc.involves_bicycle" title="Bicycle">🚲</span>
                  <span v-if="acc.involves_pedestrian" title="Pedestrian">🚶</span>
                  <span v-if="acc.involves_motorcycle" title="Motorcycle">🏍️</span>
                  <span v-if="acc.involves_car" title="Car">🚗</span>
                  <span v-if="acc.involves_truck" title="Truck">🚚</span>
                  <span
                    v-if="!acc.involves_bicycle && !acc.involves_pedestrian && !acc.involves_motorcycle && !acc.involves_car && !acc.involves_truck"
                    class="text-muted"
                  >—</span>
                </td>
                <td class="coords">
                  <span v-if="acc.lat && acc.lon">
                    {{ Number(acc.lat).toFixed(4) }},&nbsp;{{ Number(acc.lon).toFixed(4) }}
                  </span>
                  <span v-else class="text-muted">—</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="pagination">
          <button
            :disabled="page === 1"
            @click="page--; load()"
            class="btn-ghost"
          >← Prev</button>
          <span class="page-info">Page {{ page }} of {{ totalPages }}</span>
          <button
            :disabled="page >= totalPages"
            @click="page++; load()"
            class="btn-ghost"
          >Next →</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import FilterPanel from '../components/FilterPanel.vue'
import { getAccidents } from '../api/index.js'

const accidents = ref([])
const total = ref(0)
const page = ref(1)
const perPage = 100
const loading = ref(false)
const activeParams = ref({})
const totalPages = ref(1)

const categoryLabel = (c) => ({ 1: 'Fatal', 2: 'Serious', 3: 'Slight' }[c] || String(c || '—'))
const categoryClass = (c) => ({ 1: 'badge-fatal', 2: 'badge-serious', 3: 'badge-slight' }[c] || '')

const lightLabel = (v) => ({ 0: 'Daylight', 1: 'Dusk/Dawn', 2: 'Night' }[v] ?? '—')
const lightIcon  = (v) => ({ 0: '☀️', 1: '🌅', 2: '🌙' }[v] ?? '—')

const roadLabel = (v) => v != null ? ({ 0: 'Dry', 1: 'Wet/Damp', 2: 'Winter/Icy' }[v] ?? '—') : 'N/A (pre-2022)'
const roadIcon  = (v) => v != null ? ({ 0: '🟢', 1: '💧', 2: '❄️' }[v] ?? '—') : '—'

const crashTypeLabel = (v) => ({
  1: 'Single-vehicle', 2: 'Turning/Junction', 3: 'Crossing',
  4: 'Pedestrian', 5: 'Parking', 6: 'In-lane', 7: 'Other'
}[v] ?? '—')

const onFilterChange = (p) => {
  activeParams.value = p
  page.value = 1
  load()
}

const load = async () => {
  loading.value = true
  try {
    const res = await getAccidents({ ...activeParams.value, page: page.value, per_page: perPage })
    accidents.value = res.data.data || []
    const meta = res.data.meta || res.data.pagination || {}
    total.value = meta.total || res.data.total || 0
    totalPages.value = meta.last_page || Math.ceil(total.value / perPage) || 1
  } catch (e) {
    console.error('Accidents load error', e)
    accidents.value = []
  } finally {
    loading.value = false
  }
}

const exportCsv = () => {
  const params = new URLSearchParams({ ...activeParams.value, per_page: 10000, format: 'csv' })
  window.open('/api/v1/accidents?' + params.toString())
}

onMounted(load)
</script>

<style scoped>
.icon-cell { font-size: 1.1rem; }
.mono-sm   { font-family: monospace; font-size: 0.85rem; color: var(--text-muted); text-align: center; }

.accidents-layout {
  display: flex;
  gap: 0;
  min-height: calc(100vh - 60px);
  margin: -2rem;
}

.accidents-content {
  flex: 1;
  padding: 2rem;
  overflow: auto;
  min-width: 0;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
  flex-wrap: wrap;
  gap: 1rem;
}

.page-header h1 {
  margin: 0;
  color: var(--text);
  font-size: 1.6rem;
}

.header-actions {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.total-badge {
  background: var(--surface2);
  padding: 0.3rem 0.8rem;
  border-radius: 20px;
  font-size: 0.88rem;
  color: var(--accent2);
  border: 1px solid rgba(46, 196, 182, 0.4);
}

.table-wrapper {
  overflow-x: auto;
  border-radius: var(--radius);
  border: 1px solid var(--border);
}

.data-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.88rem;
  min-width: 700px;
}

.data-table th,
.data-table td {
  color: var(--text);
  padding: 0.6rem 1rem;
  border-bottom: 1px solid var(--border);
  text-align: left;
}

.data-table th {
  color: var(--text-muted);
  text-transform: uppercase;
  font-size: 0.72rem;
  font-weight: 600;
  letter-spacing: 0.06em;
  background: var(--surface2);
  position: sticky;
  top: 0;
}

.data-table tr:hover td {
  background: rgba(255, 255, 255, 0.025);
}

.data-table tbody tr:last-child td {
  border-bottom: none;
}

.badge {
  padding: 0.2rem 0.5rem;
  border-radius: 4px;
  font-size: 0.78rem;
  font-weight: 600;
  white-space: nowrap;
}

.badge-fatal {
  background: rgba(230, 57, 70, 0.18);
  color: #E63946;
}

.badge-serious {
  background: rgba(255, 159, 28, 0.18);
  color: #FF9F1C;
}

.badge-slight {
  background: rgba(255, 215, 0, 0.15);
  color: #FFD700;
}

.participants {
  font-size: 1.05rem;
  letter-spacing: 0.15em;
}

.coords {
  font-family: var(--mono);
  font-size: 0.78rem;
  color: var(--text-muted);
  white-space: nowrap;
}

.text-muted {
  color: var(--text-muted);
}

.pagination {
  display: flex;
  align-items: center;
  gap: 1rem;
  justify-content: center;
  padding: 1.5rem;
}

.page-info {
  color: var(--text-muted);
  font-size: 0.9rem;
  min-width: 120px;
  text-align: center;
}

.loading-state {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.75rem;
  padding: 4rem;
  color: var(--text-muted);
  font-size: 1rem;
}

.empty-state {
  text-align: center;
  padding: 4rem 1rem;
  color: var(--text-muted);
}

.empty-icon {
  font-size: 3rem;
  margin-bottom: 0.75rem;
}
</style>
