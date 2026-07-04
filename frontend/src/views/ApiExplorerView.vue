<template>
  <div class="api-explorer">
    <div class="page-header">
      <div>
        <h1>API Explorer</h1>
        <p class="subtitle">Interactively test all Laravel API endpoints</p>
      </div>
      <a
        :href="swaggerUrl"
        target="_blank"
        class="swagger-link"
      >
        📄 Full Swagger Docs ↗
      </a>
    </div>

    <div class="explorer-layout">
      <!-- Left panel: endpoint selector + params -->
      <div class="explorer-left card">
        <div class="form-group">
          <label class="field-label">Endpoint</label>
          <select v-model="selectedEndpoint" @change="onEndpointChange">
            <option v-for="ep in endpoints" :key="ep.path" :value="ep">
              {{ ep.method }} {{ ep.path }}
            </option>
          </select>
        </div>

        <div v-if="selectedEndpoint" class="endpoint-meta">
          <span class="meta-tag">{{ selectedEndpoint.tag }}</span>
          <p class="meta-desc">{{ selectedEndpoint.description }}</p>
        </div>

        <template v-if="selectedEndpoint && selectedEndpoint.params.length > 0">
          <div class="params-heading">Parameters</div>
          <div v-for="p in selectedEndpoint.params" :key="p.name" class="form-group">
            <label class="field-label">
              {{ p.name }}
              <span class="param-type">{{ p.type }}</span>
              <span v-if="p.required" class="required-star">*</span>
            </label>
            <select v-if="p.type === 'select'" v-model="paramValues[p.name]">
              <option value="">— any —</option>
              <option v-for="opt in p.options" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
            <input
              v-else
              v-model="paramValues[p.name]"
              :type="p.type === 'number' ? 'number' : 'text'"
              :placeholder="p.example ? 'e.g. ' + p.example : p.description"
            />
            <small class="param-hint">{{ p.description }}</small>
          </div>
        </template>

        <button
          class="btn-primary send-btn"
          @click="sendRequest"
          :disabled="sending"
        >
          {{ sending ? '⏳ Sending…' : '▶ Send Request' }}
        </button>
      </div>

      <!-- Right panel: response viewer -->
      <div class="explorer-right">
        <div v-if="!response && !errorBody" class="empty-state card">
          <div class="empty-icon">📡</div>
          <p>Select an endpoint and press <strong>Send Request</strong> to see the response here.</p>
        </div>

        <div v-else class="response-card card">
          <div class="response-header">
            <span :class="['status-pill', statusClass]">{{ responseStatus }}</span>
            <span class="response-time">{{ responseTime }} ms</span>
            <span class="response-url font-mono">{{ lastUrl }}</span>
          </div>

          <div v-if="responseLicense" class="license-strip">
            📜 Licence: <strong>{{ responseLicense }}</strong>
            <span v-if="responseSource"> — {{ responseSource }}</span>
          </div>

          <pre class="response-body">{{ displayBody }}</pre>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, reactive } from 'vue'
import axios from 'axios'

/* ------------------------------------------------------------------ */
/* Endpoint catalogue                                                   */
/* ------------------------------------------------------------------ */
const endpoints = [
  {
    path: '/v1/regions',
    method: 'GET',
    tag: 'Regions',
    description: 'List regions filtered by level, state, district or free-text search.',
    params: [
      { name: 'level', type: 'select', description: 'Region hierarchy level',
        options: [{ value: 'state', label: 'state' }, { value: 'district', label: 'district' }, { value: 'municipality', label: 'municipality' }] },
      { name: 'state_ags', type: 'string', description: '2-digit state AGS', example: '14' },
      { name: 'district_ags', type: 'string', description: '5-digit district AGS', example: '14612' },
      { name: 'search', type: 'string', description: 'Name search', example: 'Dresden' },
      { name: 'per_page', type: 'number', description: 'Results per page', example: '20' },
    ],
  },
  {
    path: '/v1/accidents',
    method: 'GET',
    tag: 'Accidents',
    description: 'Paginated accident records with full attribute filtering.',
    params: [
      { name: 'state_ags', type: 'string', description: '2-digit state code', example: '14' },
      { name: 'district_ags', type: 'string', description: '5-digit district code', example: '14612' },
      { name: 'year', type: 'number', description: 'Filter by year', example: '2023' },
      { name: 'month', type: 'number', description: 'Month 1–12', example: '6' },
      { name: 'category', type: 'select', description: 'Accident severity category',
        options: [{ value: '1', label: '1 – Fatal' }, { value: '2', label: '2 – Serious injury' }, { value: '3', label: '3 – Slight injury' }] },
      { name: 'involves_bicycle', type: 'select', description: 'Bicycle involved',
        options: [{ value: 'true', label: 'Yes' }, { value: 'false', label: 'No' }] },
      { name: 'involves_pedestrian', type: 'select', description: 'Pedestrian involved',
        options: [{ value: 'true', label: 'Yes' }, { value: 'false', label: 'No' }] },
      { name: 'involves_motorcycle', type: 'select', description: 'Motorcycle involved',
        options: [{ value: 'true', label: 'Yes' }, { value: 'false', label: 'No' }] },
      { name: 'per_page', type: 'number', description: 'Records per page (max 500)', example: '50' },
      { name: 'page', type: 'number', description: 'Page number', example: '1' },
    ],
  },
  {
    path: '/v1/aggregates/accidents',
    method: 'GET',
    tag: 'Aggregates',
    description: 'Accident counts grouped by region and year, optionally filtered by state or district.',
    params: [
      { name: 'level', type: 'select', description: 'Aggregation level',
        options: [{ value: 'state', label: 'state' }, { value: 'district', label: 'district' }] },
      { name: 'state_ags', type: 'string', description: 'State AGS', example: '14' },
      { name: 'district_ags', type: 'string', description: 'District AGS', example: '14612' },
      { name: 'year', type: 'number', description: 'Filter by year', example: '2023' },
    ],
  },
  {
    path: '/v1/aggregates/earliest-year',
    method: 'GET',
    tag: 'Aggregates',
    description: 'Returns the earliest year for which accident data exists — optionally filtered by state.',
    params: [
      { name: 'state_ags', type: 'string', description: '2-digit state AGS (optional)', example: '05' },
    ],
  },
  {
    path: '/v1/aggregates/rates',
    method: 'GET',
    tag: 'Aggregates',
    description: 'Accident rate per 100,000 inhabitants by district or state, joined with population figures.',
    params: [
      { name: 'level', type: 'select', description: 'Aggregation level',
        options: [{ value: 'state', label: 'state' }, { value: 'district', label: 'district' }] },
      { name: 'year', type: 'number', description: 'Year', example: '2023' },
      { name: 'state_ags', type: 'string', description: 'Filter by state', example: '14' },
    ],
  },
  {
    path: '/v1/aggregates/rankings',
    method: 'GET',
    tag: 'Aggregates',
    description: 'Top or bottom N districts ranked by a chosen accident metric.',
    params: [
      { name: 'year', type: 'number', description: 'Year', example: '2023' },
      { name: 'metric', type: 'select', description: 'Ranking metric',
        options: [
          { value: 'total', label: 'Total accidents' },
          { value: 'fatal', label: 'Fatal accidents' },
          { value: 'bicycle', label: 'Bicycle accidents' },
          { value: 'pedestrian', label: 'Pedestrian accidents' },
        ] },
      { name: 'limit', type: 'number', description: 'Number of results (default 10)', example: '10' },
      { name: 'order', type: 'select', description: 'Sort direction',
        options: [{ value: 'desc', label: 'Top (desc)' }, { value: 'asc', label: 'Bottom (asc)' }] },
    ],
  },
  {
    path: '/v1/aggregates/year-over-year',
    method: 'GET',
    tag: 'Aggregates',
    description: 'Annual accident totals with absolute and percentage change year-on-year.',
    params: [
      { name: 'state_ags', type: 'string', description: 'State AGS (optional — omit for all Germany)', example: '14' },
    ],
  },
  {
    path: '/v1/aggregates/zero-cases',
    method: 'GET',
    tag: 'Aggregates',
    description: 'Districts that recorded zero accidents in the given year (Bonus endpoint).',
    params: [
      { name: 'year', type: 'number', required: true, description: 'Year to check', example: '2023' },
      { name: 'level', type: 'select', description: 'Level',
        options: [{ value: 'district', label: 'district' }, { value: 'municipality', label: 'municipality' }] },
      { name: 'state_ags', type: 'string', description: 'Filter by state', example: '14' },
    ],
  },
  {
    path: '/v1/aggregates/time-series',
    method: 'GET',
    tag: 'Aggregates',
    description: 'Group accidents by a time dimension (year, month, weekday or hour).',
    params: [
      { name: 'group_by', type: 'select', description: 'Time grouping',
        options: [
          { value: 'year', label: 'year' },
          { value: 'month', label: 'month' },
          { value: 'weekday', label: 'weekday' },
          { value: 'hour', label: 'hour' },
        ] },
      { name: 'state_ags', type: 'string', description: 'State AGS (optional)', example: '14' },
      { name: 'district_ags', type: 'string', description: 'District AGS (optional)', example: '14612' },
      { name: 'year', type: 'number', description: 'Filter by year (optional)', example: '2023' },
    ],
  },
  {
    path: '/v1/metadata/sources',
    method: 'GET',
    tag: 'Metadata',
    description: 'All registered data sources with licence and provenance information.',
    params: [],
  },
  {
    path: '/v1/import-runs',
    method: 'GET',
    tag: 'Metadata',
    description: 'Import history — records of when data was ingested and from which source.',
    params: [
      { name: 'status', type: 'select', description: 'Filter by import status',
        options: [{ value: 'success', label: 'success' }, { value: 'failed', label: 'failed' }, { value: 'started', label: 'started' }] },
      { name: 'limit', type: 'number', description: 'Number of results', example: '10' },
    ],
  },
  {
    path: '/v1/stats/summary',
    method: 'GET',
    tag: 'Statistics',
    description: 'Overall platform summary: total accidents, districts covered, earliest year, source count.',
    params: [],
  },
]

/* ------------------------------------------------------------------ */
/* Reactive state                                                       */
/* ------------------------------------------------------------------ */
const swaggerUrl = import.meta.env.VITE_SWAGGER_URL || 'http://127.0.0.1:8002/api/documentation'

const selectedEndpoint = ref(endpoints[0])
const paramValues = reactive({})
const sending = ref(false)
const response = ref(null)
const errorBody = ref(null)
const responseStatus = ref('')
const responseTime = ref(0)
const lastUrl = ref('')
const responseLicense = ref('')
const responseSource = ref('')

/* ------------------------------------------------------------------ */
/* Computed                                                             */
/* ------------------------------------------------------------------ */
const statusClass = computed(() => {
  const code = parseInt(responseStatus.value)
  if (code >= 200 && code < 300) return 'status-ok'
  if (code >= 400) return 'status-err'
  return 'status-info'
})

const displayBody = computed(() => {
  if (errorBody.value) return errorBody.value
  if (!response.value) return ''
  return JSON.stringify(response.value, null, 2)
})

/* ------------------------------------------------------------------ */
/* Handlers                                                             */
/* ------------------------------------------------------------------ */
const onEndpointChange = () => {
  // Clear old param values
  Object.keys(paramValues).forEach(k => delete paramValues[k])
  response.value = null
  errorBody.value = null
  responseStatus.value = ''
  lastUrl.value = ''
  responseLicense.value = ''
  responseSource.value = ''
}

const sendRequest = async () => {
  if (!selectedEndpoint.value) return
  sending.value = true
  response.value = null
  errorBody.value = null

  // Build params — skip empty strings
  const params = {}
  ;(selectedEndpoint.value.params || []).forEach(p => {
    const val = paramValues[p.name]
    if (val !== undefined && val !== '') params[p.name] = val
  })

  const url = '/api' + selectedEndpoint.value.path
  const qs = Object.keys(params).length
    ? '?' + new URLSearchParams(params).toString()
    : ''
  lastUrl.value = url + qs

  const t0 = Date.now()
  try {
    const res = await axios.get(url, { params })
    responseStatus.value = String(res.status)
    responseTime.value = Date.now() - t0
    response.value = res.data
    responseLicense.value = res.data?.license || 'dl-de/by-2-0'
    responseSource.value = res.data?.source || 'Statistische Ämter des Bundes und der Länder'
  } catch (err) {
    responseStatus.value = String(err.response?.status || 'ERR')
    responseTime.value = Date.now() - t0
    errorBody.value = JSON.stringify(
      err.response?.data || { error: err.message },
      null,
      2
    )
  } finally {
    sending.value = false
  }
}
</script>

<style scoped>
/* ------------------------------------------------------------------ */
/* Layout                                                               */
/* ------------------------------------------------------------------ */
.api-explorer {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 1rem;
}

.page-header h1 {
  margin: 0;
  font-size: 1.8rem;
  color: var(--text);
}

.subtitle {
  color: var(--text-muted);
  margin: 0.3rem 0 0;
  font-size: 0.9rem;
}

.swagger-link {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.55rem 1.1rem;
  background: var(--accent2);
  color: #fff;
  border-radius: var(--radius);
  font-size: 0.9rem;
  font-weight: 600;
  text-decoration: none;
  white-space: nowrap;
  transition: opacity 0.2s;
  align-self: center;
}
.swagger-link:hover {
  opacity: 0.85;
  color: #fff;
}

.explorer-layout {
  display: grid;
  grid-template-columns: 380px 1fr;
  gap: 1.5rem;
  align-items: start;
}

/* ------------------------------------------------------------------ */
/* Left panel                                                           */
/* ------------------------------------------------------------------ */
.explorer-left {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
}

.field-label {
  color: var(--text-muted);
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
}

.param-type {
  color: var(--accent2);
  font-family: var(--mono);
  font-size: 0.7rem;
  margin-left: 0.35rem;
}

.required-star {
  color: var(--accent);
  margin-left: 0.2rem;
}

.param-hint {
  color: var(--text-muted);
  font-size: 0.75rem;
  line-height: 1.3;
}

.endpoint-meta {
  background: var(--surface2);
  border: 1px solid var(--border);
  border-radius: 6px;
  padding: 0.75rem;
}

.meta-tag {
  display: inline-block;
  background: rgba(162, 155, 254, 0.15);
  color: #a29bfe;
  border: 1px solid rgba(162, 155, 254, 0.4);
  padding: 0.15rem 0.5rem;
  border-radius: 4px;
  font-size: 0.75rem;
  font-weight: 600;
  margin-bottom: 0.4rem;
}

.meta-desc {
  color: var(--text-muted);
  font-size: 0.85rem;
  line-height: 1.4;
  margin: 0;
}

.params-heading {
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.07em;
  color: var(--text-muted);
  border-top: 1px solid var(--border);
  padding-top: 0.75rem;
}

.send-btn {
  width: 100%;
  margin-top: 0.5rem;
  padding: 0.65rem;
  font-size: 0.95rem;
}

/* ------------------------------------------------------------------ */
/* Right panel                                                          */
/* ------------------------------------------------------------------ */
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 300px;
  text-align: center;
  color: var(--text-muted);
  gap: 0.75rem;
}

.empty-icon {
  font-size: 3.5rem;
}

.empty-state p {
  font-size: 0.95rem;
  max-width: 360px;
  line-height: 1.5;
}

.response-card {
  padding: 0;
  overflow: hidden;
}

.response-header {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 0.75rem 1rem;
  background: var(--surface2);
  border-bottom: 1px solid var(--border);
  flex-wrap: wrap;
}

.status-pill {
  padding: 0.2rem 0.65rem;
  border-radius: 4px;
  font-size: 0.8rem;
  font-weight: 700;
  font-family: var(--mono);
  flex-shrink: 0;
}

.status-ok {
  background: rgba(46, 196, 182, 0.18);
  color: var(--accent2);
}

.status-err {
  background: rgba(230, 57, 70, 0.18);
  color: var(--accent);
}

.status-info {
  background: rgba(162, 155, 254, 0.18);
  color: #a29bfe;
}

.response-time {
  color: var(--text-muted);
  font-size: 0.82rem;
  white-space: nowrap;
}

.response-url {
  color: var(--text-muted);
  font-size: 0.78rem;
  word-break: break-all;
  flex: 1;
}

.license-strip {
  padding: 0.45rem 1rem;
  background: rgba(46, 196, 182, 0.06);
  border-bottom: 1px solid var(--border);
  font-size: 0.8rem;
  color: var(--text-muted);
}

.license-strip strong {
  color: var(--accent2);
}

.response-body {
  margin: 0;
  padding: 1rem;
  font-family: var(--mono);
  font-size: 0.8rem;
  line-height: 1.55;
  color: var(--text);
  background: var(--bg);
  max-height: 65vh;
  overflow: auto;
  white-space: pre-wrap;
  word-break: break-word;
}

/* ------------------------------------------------------------------ */
/* Responsive                                                           */
/* ------------------------------------------------------------------ */
@media (max-width: 1000px) {
  .explorer-layout {
    grid-template-columns: 1fr;
  }
}
</style>
