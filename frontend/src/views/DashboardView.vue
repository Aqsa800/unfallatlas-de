<template>
  <div class="dashboard">
    <div class="page-header">
      <div>
        <h1>Dashboard</h1>
        <p class="subtitle">German Road Accident Open Data Platform — Integrated from official sources</p>
      </div>
      <div class="header-meta">
        <span class="meta-badge meta-badge--green">2016 – 2024</span>
      </div>
    </div>

    <!-- KPI Cards -->
    <div class="grid-4 mb-2">
      <StatCard
        title="Total Accidents"
        :value="summary.total_accidents"
        icon="💥"
        color="var(--accent)"
      />
      <StatCard
        title="Earliest Year"
        :value="summary.earliest_year"
        icon="📅"
        color="var(--accent2)"
        subtitle="Data coverage start"
        :no-format="true"
      />
      <StatCard
        title="Districts"
        :value="summary.districts_count"
        icon="🗺️"
        color="var(--warning)"
      />
      <StatCard
        title="Data Sources"
        :value="summary.sources_count || 4"
        icon="📊"
        color="#a29bfe"
      />
    </div>

    <!-- Mandatory Exam Questions -->
    <div class="card mb-2">
      <h2 class="section-title">📋 Mandatory Exam Questions</h2>
      <div class="questions-grid">
        <div
          v-for="q in mandatoryQuestions"
          :key="q.id"
          class="question-card"
          :class="{ loading: q.loading }"
          @click="openQuestionFilters(q)"
        >
          <div class="q-number">Q{{ q.id }}</div>
          <div class="q-body">
            <div class="q-text">{{ q.question }}</div>
            <div class="q-answer" v-if="!q.loading">
              <span class="answer-value">{{ q.answer }}</span>
              <div class="answer-source">
                <span class="source-label">Source</span>
                <span class="source-chip">{{ q.sourceName }}</span>
                <span class="source-endpoint">{{ q.sourceEndpoint }}</span>
              </div>
            </div>
            <div v-else class="q-loading">
              <span class="spinner"></span>
              <span>Loading…</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Charts Row -->
    <div class="grid-2 mb-2">
      <AccidentChart
        :data="yoyData"
        type="line"
        title="📈 National Accident Trend (Year-over-Year)"
        height="280px"
      />
      <AccidentChart
        :data="topDistricts"
        type="bar"
        title="🏆 Top 10 Districts by Accidents (Latest Year)"
        height="280px"
      />
    </div>

    <!-- Data Sources & Licenses -->
    <div class="card">
      <h2 class="section-title">📜 Data Sources &amp; Licenses</h2>
      <p class="section-description">
        Official datasets and registries used to build the platform, including provenance, licence, and import status.
      </p>
      <div v-if="dataSources.length === 0" class="empty-state">
        <div class="empty-icon">📂</div>
        <p>No data source information available.</p>
      </div>
      <table v-else class="data-table">
        <thead>
          <tr>
            <th>Source</th>
            <th>Publisher</th>
            <th>Description</th>
            <th>License</th>
            <th>URL</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="src in dataSources" :key="src.key || src.id">
            <td><strong>{{ src.name }}</strong></td>
            <td>{{ src.publisher }}</td>
            <td class="description-cell">{{ src.description || '—' }}</td>
            <td><a href="https://www.govdata.de/dl-de/by-2-0" target="_blank" rel="noopener" class="badge badge-license license-link">{{ src.license }}</a></td>
            <td>
              <a v-if="src.url" :href="src.url" target="_blank" class="link">
                Link ↗
              </a>
              <span v-else class="text-muted">—</span>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Fallback static sources if API returns nothing -->
      <table v-if="dataSources.length === 0 && staticSources.length > 0" class="data-table">
        <thead>
          <tr>
            <th>Source</th>
            <th>Publisher</th>
            <th>Description</th>
            <th>License</th>
            <th>URL</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="src in staticSources" :key="src.key">
            <td><strong>{{ src.name }}</strong></td>
            <td>{{ src.publisher }}</td>
            <td class="description-cell">{{ src.description }}</td>
            <td><a href="https://www.govdata.de/dl-de/by-2-0" target="_blank" rel="noopener" class="badge badge-license license-link">{{ src.license }}</a></td>
            <td><a :href="src.url" target="_blank" class="link">Link ↗</a></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import StatCard from '../components/StatCard.vue'
import AccidentChart from '../components/AccidentChart.vue'
import {
  getSummary,
  getSources,
  getEarliestYear,
  getAggregates,
  getYearOverYear,
  getRankings
} from '../api/index.js'

const summary = ref({
  total_accidents: 0,
  earliest_year: 0,
  districts_count: 0,
  sources_count: 0
})
const dataSources = ref([])
const yoyData = ref([])
const topDistricts = ref([])
const router = useRouter()

const staticSources = ref([
  {
    key: 'unfallatlas',
    name: 'Unfallatlas',
    publisher: 'Statistische Ämter des Bundes und der Länder',
    license: 'dl-de/by-2-0',
    description: 'Point data for road accidents with personal injury across Germany, 2016–2024.',
    url: 'https://unfallatlas.statistikportal.de/'
  },
  {
    key: 'destatis',
    name: 'Destatis Road Safety',
    publisher: 'Statistisches Bundesamt',
    license: 'dl-de/by-2-0',
    description: 'Official road safety statistics and related releases from Destatis.',
    url: 'https://www.destatis.de/DE/Themen/Gesellschaft-Umwelt/Verkehrsunfaelle/_inhalt.html'
  },
  {
    key: 'bkg',
    name: 'Verwaltungsgebiete (BKG)',
    publisher: 'Bundesamt für Kartographie und Geodäsie',
    license: 'dl-de/by-2-0',
    description: 'Administrative boundary and geocoding reference data.',
    url: 'https://gdz.bkg.bund.de/'
  },
  {
    key: 'govdata',
    name: 'GovData Portal',
    publisher: 'Nationaler Datenkatalog',
    license: 'dl-de/by-2-0',
    description: 'Open data portal for German government datasets and references.',
    url: 'https://www.govdata.de/'
  }
])

const mandatoryQuestions = ref([
  {
    id: 1,
    question: 'What is the earliest accident year in the complete dataset?',
    answer: '—',
    sourceName: 'Unfallatlas (accidents)',
    sourceEndpoint: '/aggregates/earliest-year',
    filters: { year: 2016 },
    loading: true
  },
  {
    id: 2,
    question: 'How many accidents with personal injury occurred in Saxony (Sachsen) in 2023?',
    answer: '—',
    sourceName: 'Unfallatlas (accidents)',
    sourceEndpoint: '/aggregates/accidents',
    filters: { state_ags: '14', year: 2023 },
    loading: true
  },
  {
    id: 3,
    question: 'From which year onwards is data available for North Rhine-Westphalia (NRW)?',
    answer: '—',
    sourceName: 'Unfallatlas (accidents)',
    sourceEndpoint: '/aggregates/earliest-year',
    filters: { state_ags: '05', year: 2019 },
    loading: true
  },
  {
    id: 4,
    question: 'From which year onwards is data available for Mecklenburg-Vorpommern?',
    answer: '—',
    sourceName: 'Unfallatlas (accidents)',
    sourceEndpoint: '/aggregates/earliest-year',
    filters: { state_ags: '13', year: 2020 },
    loading: true
  },
  {
    id: 5,
    question: 'How many accidents involving pedestrians occurred in Berlin in 2023?',
    answer: '—',
    sourceName: 'Unfallatlas (accidents)',
    sourceEndpoint: '/aggregates/accidents',
    filters: { state_ags: '11', year: 2023, involves_pedestrian: true },
    loading: true
  },
  {
    id: 6,
    question: 'How many bicycle accidents occurred in Dresden in 2023?',
    answer: '—',
    sourceName: 'Unfallatlas (accidents)',
    sourceEndpoint: '/aggregates/accidents',
    filters: { state_ags: '14', year: 2023, involves_bicycle: true },
    loading: true
  }
])

const openQuestionFilters = (q) => {
  const query = {}
  Object.entries(q.filters || {}).forEach(([k, v]) => {
    if (v !== undefined && v !== null && v !== '') {
      query[k] = String(v)
    }
  })
  router.push({ name: 'accidents', query })
}

const safeAnswer = (idx, val, fallback = 'No data') => {
  mandatoryQuestions.value[idx].answer = val || fallback
  mandatoryQuestions.value[idx].loading = false
}

onMounted(async () => {
  // Load summary and sources in parallel
  try {
    const [sumRes, srcRes] = await Promise.allSettled([getSummary(), getSources()])
    if (sumRes.status === 'fulfilled') {
      summary.value = sumRes.value.data.data || sumRes.value.data || summary.value
    }
    if (srcRes.status === 'fulfilled') {
      const raw = srcRes.value.data.data || srcRes.value.data
      dataSources.value = Array.isArray(raw) ? raw : []
    }
  } catch (e) {
    console.error('Summary/Sources load error', e)
  }

  // Q1: Earliest year globally
  try {
    const ey = await getEarliestYear({})
    const eyData = ey.data.data || ey.data
    safeAnswer(0, String(eyData.earliest_year || eyData))
  } catch (e) {
    safeAnswer(0, 'Error loading')
  }

  // Q2: Saxony 2023 accidents (state_ags=14)
  try {
    const sax = await getAggregates({ state_ags: '14', year: 2023, level: 'state' })
    const saxData = sax.data.data || []
    const row = Array.isArray(saxData) ? saxData[0] : saxData
    const count = row?.accident_count ?? row?.total ?? null
    safeAnswer(1, count !== null ? count.toLocaleString('en-US') + ' accidents' : null)
  } catch (e) {
    safeAnswer(1, 'Error loading')
  }

  // Q3: NRW earliest year (state_ags=05)
  try {
    const nrw = await getEarliestYear({ state_ags: '05' })
    const nrwData = nrw.data.data || nrw.data
    safeAnswer(2, 'Since ' + (nrwData.earliest_year || nrwData))
  } catch (e) {
    safeAnswer(2, 'Error loading')
  }

  // Q4: Mecklenburg-Vorpommern earliest year (state_ags=13)
  try {
    const mv = await getEarliestYear({ state_ags: '13' })
    const mvData = mv.data.data || mv.data
    safeAnswer(3, 'Since ' + (mvData.earliest_year || mvData))
  } catch (e) {
    safeAnswer(3, 'Error loading')
  }

  // Q5: Berlin pedestrian accidents 2023 (state_ags=11)
  try {
    const ber = await getAggregates({ state_ags: '11', year: 2023, level: 'state' })
    const berData = ber.data.data || []
    const row = Array.isArray(berData) ? berData[0] : berData
    const count = row?.pedestrian_count ?? row?.involves_pedestrian_count ?? null
    safeAnswer(4, count !== null ? count.toLocaleString('en-US') + ' accidents' : null)
  } catch (e) {
    safeAnswer(4, 'Error loading')
  }

  // Q6: Dresden bicycle accidents 2023 (Sachsen districts, state_ags=14)
  try {
    const dres = await getAggregates({ level: 'district', year: 2023, state_ags: '14' })
    const dresData = dres.data.data || []
    const dresRow = Array.isArray(dresData)
      ? dresData.find(d => (d.name || d.district_name || '').toLowerCase().includes('dresden'))
      : null
    const count = dresRow?.bicycle_count ?? dresRow?.involves_bicycle_count ?? null
    safeAnswer(5, count !== null ? count.toLocaleString('en-US') + ' accidents' : null)
  } catch (e) {
    safeAnswer(5, 'Error loading')
  }

  // Year over year national trend
  try {
    const yoy = await getYearOverYear({})
    const raw = yoy.data.data || yoy.data || []
    yoyData.value = (Array.isArray(raw) ? raw : []).map(d => ({
      label: String(d.year),
      count: d.metric_value || d.total_count || d.accident_count || d.total || 0
    }))
  } catch (e) {
    console.error('YoY load error', e)
  }

  // Top districts by accident count
  try {
    const top = await getRankings({ year: 2023, metric: 'total', limit: 10 })
    const raw = top.data.data || top.data || []
    topDistricts.value = (Array.isArray(raw) ? raw : []).map(d => ({
      label: d.name || d.district_name || d.ags,
      count: d.metric_value || d.total_count || d.accident_count || d.total || 0
    }))
  } catch (e) {
    console.error('Rankings load error', e)
  }
})
</script>

<style scoped>
.dashboard {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
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

.header-meta {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.meta-badge {
  background: var(--surface2);
  border: 1px solid var(--border);
  color: var(--text-muted);
  padding: 0.3rem 0.8rem;
  border-radius: 20px;
  font-size: 0.82rem;
}

.meta-badge--green {
  border-color: rgba(46, 196, 182, 0.4);
  color: var(--accent2);
  background: rgba(46, 196, 182, 0.08);
}

.section-title {
  margin: 0 0 1rem;
  color: var(--text);
  font-size: 1.05rem;
  border-bottom: 1px solid var(--border);
  padding-bottom: 0.5rem;
}

.section-description {
  margin: -0.5rem 0 1rem;
  color: var(--text-muted);
  font-size: 0.88rem;
  line-height: 1.45;
}

.description-cell {
  color: var(--text-muted);
  max-width: 360px;
  white-space: normal;
}

.mb-2 {
  /* gap is handled by flex gap in parent */
}

.questions-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 1rem;
}

.question-card {
  background: var(--surface2);
  border-radius: var(--radius);
  padding: 1rem;
  display: flex;
  gap: 1rem;
  border: 1px solid var(--border);
  transition: border-color 0.2s;
  cursor: pointer;
}

.question-card:hover {
  border-color: rgba(46, 196, 182, 0.4);
}

.q-number {
  background: var(--accent);
  color: white;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 0.82rem;
  flex-shrink: 0;
}

.q-body {
  flex: 1;
  min-width: 0;
}

.q-text {
  color: var(--text-muted);
  font-size: 0.83rem;
  margin-bottom: 0.5rem;
  line-height: 1.4;
}

.q-answer {
  margin-top: 0.25rem;
}

.answer-value {
  color: var(--accent2);
  font-size: 1.15rem;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
}

.answer-source {
  margin-top: 0.35rem;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.35rem;
}

.source-label {
  color: var(--text-muted);
  font-size: 0.68rem;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.source-chip {
  display: inline-flex;
  align-items: center;
  padding: 0.12rem 0.45rem;
  border-radius: 999px;
  font-size: 0.72rem;
  color: var(--text-muted);
  background: rgba(255, 255, 255, 0.04);
  border: 1px solid var(--border);
}

.source-endpoint {
  display: inline-flex;
  align-items: center;
  padding: 0.12rem 0.45rem;
  border-radius: 999px;
  font-size: 0.72rem;
  color: var(--accent2);
  background: rgba(46, 196, 182, 0.1);
  border: 1px solid rgba(46, 196, 182, 0.25);
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;
}

.q-loading {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: var(--text-muted);
  font-style: italic;
  font-size: 0.85rem;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
}

.data-table th,
.data-table td {
  padding: 0.6rem 1rem;
  text-align: left;
  border-bottom: 1px solid var(--border);
  font-size: 0.88rem;
}

.data-table th {
  color: var(--text-muted);
  font-weight: 600;
  text-transform: uppercase;
  font-size: 0.72rem;
  letter-spacing: 0.06em;
  background: var(--surface2);
}

.link {
  color: var(--accent2);
  text-decoration: none;
  font-size: 0.85rem;
}

.link:hover {
  color: var(--accent);
}

.text-muted {
  color: var(--text-muted);
}
</style>
