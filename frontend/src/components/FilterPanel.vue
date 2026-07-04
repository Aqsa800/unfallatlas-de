<template>
  <aside class="filter-panel">
    <div class="filter-header">
      <h3>🔍 Filters <span v-if="activeCount > 0" class="active-count">{{ activeCount }}</span></h3>
      <button @click="resetAll" class="btn-ghost btn-sm">Reset</button>
    </div>

    <div class="filter-section">
      <label class="filter-label">State (Bundesland)</label>
      <select v-model="filters.selectedState" @change="onStateChange">
        <option value="">All States</option>
        <option v-for="s in states" :key="s.ags" :value="s.state_ags || s.ags">{{ s.name }}</option>
      </select>
    </div>

    <div class="filter-section" v-if="filters.selectedState">
      <label class="filter-label">
        District (Landkreis)
        <span v-if="loadingDistricts" class="loading-hint">loading…</span>
      </label>
      <select v-model="filters.selectedDistrict" @change="onDistrictChange">
        <option value="">All Districts</option>
        <option v-for="d in districts" :key="d.ags" :value="d.ags">{{ d.name }}</option>
      </select>
    </div>


<div class="filter-section">
      <div class="year-row">
        <label class="filter-label">Year From</label>
        <strong class="year-val">{{ filters.yearFrom }}</strong>
      </div>
      <input type="range" min="2016" max="2024" step="1"
             :value="filters.yearFrom"
             @input="filters.yearFrom = Math.min(+$event.target.value, filters.yearTo)" />
      <div class="year-row" style="margin-top:0.5rem">
        <label class="filter-label">Year To</label>
        <strong class="year-val">{{ filters.yearTo }}</strong>
      </div>
      <input type="range" min="2016" max="2024" step="1"
             :value="filters.yearTo"
             @input="filters.yearTo = Math.max(+$event.target.value, filters.yearFrom)" />
    </div>

    <div class="filter-section">
      <label class="filter-label">Accident Category</label>
      <div class="radio-group">
        <label class="radio-item"><input type="radio" v-model="filters.selectedCategory" value="" /> All</label>
        <label class="radio-item"><input type="radio" v-model="filters.selectedCategory" value="1" /> 🔴 Fatal</label>
        <label class="radio-item"><input type="radio" v-model="filters.selectedCategory" value="2" /> 🟠 Serious</label>
        <label class="radio-item"><input type="radio" v-model="filters.selectedCategory" value="3" /> 🟡 Slight</label>
      </div>
    </div>

    <div class="filter-section">
      <label class="filter-label">Participants Involved</label>
      <div class="toggle-group">
        <span
          class="toggle"
          :class="{ active: filters.involvesBicycle }"
          @click="filters.involvesBicycle = !filters.involvesBicycle"
        >🚲 Bicycle</span>
        <span
          class="toggle"
          :class="{ active: inv_ped }"
          @click="inv_ped = !inv_ped"
        >🚶 Pedestrian</span>
        <span
          class="toggle"
          :class="{ active: inv_moto }"
          @click="inv_moto = !inv_moto"
        >🏍️ Motorcycle</span>
        <span
          class="toggle"
          :class="{ active: inv_car }"
          @click="inv_car = !inv_car"
        >🚗 Car</span>
        <span
          class="toggle"
          :class="{ active: inv_truck }"
          @click="inv_truck = !inv_truck"
        >🚚 Truck</span>
      </div>
    </div>

    <div class="filter-section">
      <label class="filter-label">Light Condition</label>
      <select v-model="selectedLight">
        <option value="">All</option>
        <option value="0">☀️ Daylight</option>
        <option value="1">🌅 Dusk / Dawn</option>
        <option value="2">🌙 Night</option>
      </select>
    </div>

    <div class="filter-section">
      <label class="filter-label">Road Condition <span class="hint">(2022+)</span></label>
      <select v-model="selectedRoadCond">
        <option value="">All</option>
        <option value="0">🟢 Dry</option>
        <option value="1">💧 Wet / Damp</option>
        <option value="2">❄️ Winter / Icy</option>
      </select>
    </div>

    <div class="filter-section">
      <label class="filter-label">Crash Type (Unfalltyp)</label>
      <select v-model="selectedCrashType">
        <option value="">All</option>
        <option value="1">1 — Single-vehicle</option>
        <option value="2">2 — Turning / Junction</option>
        <option value="3">3 — Crossing</option>
        <option value="4">4 — Pedestrian crossing</option>
        <option value="5">5 — Parking / Stationary</option>
        <option value="6">6 — In-lane / Overtaking</option>
        <option value="7">7 — Other</option>
      </select>
    </div>

    <button class="btn btn-primary w-full apply-btn" @click="applyFilters">Apply Filters</button>
  </aside>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useFiltersStore } from '../stores/filters.js'
import { getRegions } from '../api/index.js'

const emit = defineEmits(['filter-changed'])
const route = useRoute()

const filters = useFiltersStore()
const states = ref([])
const districts = ref([])
const loadingDistricts = ref(false)
const selectedLight = ref('')
const selectedRoadCond = ref('')
const selectedCrashType = ref('')
const inv_ped = ref(false)
const inv_moto = ref(false)
const inv_car = ref(false)
const inv_truck = ref(false)
const isHydratingFromQuery = ref(false)

const asBool = (v) => ['1', 'true', 'yes'].includes(String(v).toLowerCase())

onMounted(async () => {
  try {
    const res = await getRegions({ level: 'state', per_page: 20 })
    states.value = res.data.data || []
  } catch (e) {
    console.error('Failed to load states', e)
  }

  if (Object.keys(route.query || {}).length > 0) {
    isHydratingFromQuery.value = true
    const q = route.query

    if (q.state_ags) {
      filters.selectedState = String(q.state_ags)
      await onStateChange()
    }

    if (q.district_ags) {
      filters.selectedDistrict = String(q.district_ags)
      await onDistrictChange()
    }

    if (q.year) {
      const y = Number(q.year)
      if (!Number.isNaN(y)) {
        filters.yearFrom = y
        filters.yearTo = y
      }
    }
    if (q.year_from && !q.year) {
      const yFrom = Number(q.year_from)
      if (!Number.isNaN(yFrom)) filters.yearFrom = yFrom
    }
    if (q.year_to && !q.year) {
      const yTo = Number(q.year_to)
      if (!Number.isNaN(yTo)) filters.yearTo = yTo
    }

    if (q.category !== undefined) filters.selectedCategory = String(q.category)
    if (q.light_condition !== undefined) selectedLight.value = String(q.light_condition)
    if (q.road_condition !== undefined) selectedRoadCond.value = String(q.road_condition)
    if (q.crash_type !== undefined) selectedCrashType.value = String(q.crash_type)

    filters.involvesBicycle = asBool(q.involves_bicycle)
    inv_ped.value = asBool(q.involves_pedestrian)
    inv_moto.value = asBool(q.involves_motorcycle)
    inv_car.value = asBool(q.involves_car)
    inv_truck.value = asBool(q.involves_truck)

    isHydratingFromQuery.value = false
    buildAndEmit()
  }
})

const onStateChange = async () => {
  filters.selectedDistrict = null
  districts.value = []
  if (!filters.selectedState) return
  loadingDistricts.value = true
  try {
    const res = await getRegions({ level: 'district', state_ags: filters.selectedState, per_page: 500 })
    districts.value = res.data.data || []
  } catch (e) {
    console.error('Failed to load districts', e)
  } finally {
    loadingDistricts.value = false
  }
}

const onDistrictChange = async () => {
  if (!filters.selectedDistrict) return
}

const resetAll = () => {
  filters.reset()
  districts.value = []
  selectedLight.value = ''
  selectedRoadCond.value = ''
  selectedCrashType.value = ''
  inv_ped.value = false
  inv_moto.value = false
  inv_car.value = false
  inv_truck.value = false
  emit('filter-changed', {})
}

const buildAndEmit = () => {
  if (isHydratingFromQuery.value) return
  if (filters.yearFrom > filters.yearTo) {
    const tmp = filters.yearFrom; filters.yearFrom = filters.yearTo; filters.yearTo = tmp
  }
  const p = filters.buildParams()
  if (selectedLight.value !== '')     p.light_condition = selectedLight.value
  if (selectedRoadCond.value !== '')  p.road_condition  = selectedRoadCond.value
  if (selectedCrashType.value !== '') p.crash_type      = selectedCrashType.value
  if (inv_ped.value)   p.involves_pedestrian = true
  if (inv_moto.value)  p.involves_motorcycle = true
  if (inv_car.value)   p.involves_car = true
  if (inv_truck.value) p.involves_truck = true
  emit('filter-changed', p)
}

const activeCount = computed(() => {
  let n = 0
  if (filters.selectedState) n++
  if (filters.selectedDistrict) n++
  if (filters.selectedCategory) n++
  if (filters.involvesBicycle) n++
  if (inv_ped.value) n++
  if (inv_moto.value) n++
  if (inv_car.value) n++
  if (inv_truck.value) n++
  if (filters.yearFrom !== 2016 || filters.yearTo !== 2024) n++
  if (selectedLight.value !== '') n++
  if (selectedRoadCond.value !== '') n++
  if (selectedCrashType.value !== '') n++
  return n
})

// Keep the manual button working too
const applyFilters = buildAndEmit

// Auto-apply whenever any filter value changes (debounced 400ms for sliders)
let debounceTimer = null
const debounced = () => {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(buildAndEmit, 400)
}

watch(() => filters.selectedState,    buildAndEmit)
watch(() => filters.selectedDistrict, buildAndEmit)
watch(() => filters.selectedCategory, buildAndEmit)
watch(() => filters.involvesBicycle,  buildAndEmit)
watch(() => inv_ped.value,   buildAndEmit)
watch(() => inv_moto.value,  buildAndEmit)
watch(() => inv_car.value,   buildAndEmit)
watch(() => inv_truck.value, buildAndEmit)
watch(() => filters.yearFrom,   debounced)
watch(() => filters.yearTo,     debounced)
watch(selectedLight,     buildAndEmit)
watch(selectedRoadCond,  buildAndEmit)
watch(selectedCrashType, buildAndEmit)
</script>

<style scoped>
.filter-panel {
  width: 260px;
  min-width: 260px;
  background: var(--surface);
  border-right: 1px solid var(--border);
  padding: 1.5rem 1rem;
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
  min-height: calc(100vh - 60px);
  position: sticky;
  top: 60px;
  overflow-y: auto;
  flex-shrink: 0;
}

.filter-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.filter-header h3 {
  margin: 0;
  color: var(--text);
  font-size: 1rem;
}

.filter-section {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.filter-label {
  color: var(--text-muted);
  font-size: 0.78rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  display: block;
}

.year-val {
  color: var(--accent2);
  font-size: 0.9rem;
  margin-left: 0.25rem;
}

select {
  width: 100%;
  background: var(--bg);
  border: 1px solid var(--border);
  color: var(--text);
  padding: 0.45rem 0.6rem;
  border-radius: 4px;
  font-size: 0.88rem;
  font-family: inherit;
  outline: none;
  cursor: pointer;
}
select:focus {
  border-color: var(--accent2);
}

input[type="range"] {
  width: 100%;
  accent-color: var(--accent);
  cursor: pointer;
}

.radio-group {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.radio-item {
  color: var(--text);
  font-size: 0.88rem;
  display: flex;
  align-items: center;
  gap: 0.4rem;
  cursor: pointer;
}

.radio-item input[type="radio"] {
  accent-color: var(--accent);
  width: auto;
  cursor: pointer;
}

.toggle-group {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.toggle {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.35rem 0.7rem;
  border-radius: 4px;
  cursor: pointer;
  border: 1px solid var(--border);
  color: var(--text-muted);
  font-size: 0.85rem;
  transition: all 0.2s;
  user-select: none;
}

.toggle:hover {
  border-color: var(--text-muted);
  color: var(--text);
}

.toggle.active {
  border-color: var(--accent);
  color: var(--accent);
  background: rgba(230, 57, 70, 0.1);
}

.apply-btn {
  width: 100%;
  margin-top: auto;
}

.year-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.active-count {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: var(--accent);
  color: #fff;
  font-size: 0.7rem;
  font-weight: 700;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  margin-left: 0.4rem;
  vertical-align: middle;
}

.hint {
  color: var(--text-muted);
  font-size: 0.7rem;
  font-weight: 400;
  text-transform: none;
  letter-spacing: 0;
}

.loading-hint {
  color: var(--accent2);
  font-size: 0.72rem;
  font-style: italic;
  margin-left: 0.4rem;
  font-weight: 400;
  text-transform: none;
  letter-spacing: 0;
}
</style>
