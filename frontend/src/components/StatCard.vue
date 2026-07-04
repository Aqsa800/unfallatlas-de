<template>
  <div class="stat-card card" :style="{ '--card-color': color }">
    <div class="stat-icon">{{ icon }}</div>
    <div class="stat-body">
      <div class="stat-value">{{ displayValue }}</div>
      <div class="stat-title">{{ title }}</div>
      <div v-if="subtitle" class="stat-subtitle">{{ subtitle }}</div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'

const props = defineProps({
  title: String,
  value: [Number, String],
  subtitle: String,
  icon: String,
  color: { type: String, default: 'var(--accent)' },
  noFormat: { type: Boolean, default: false }  // set true for years/codes
})

const displayValue = ref('—')

const fmt = (n) => props.noFormat ? String(n) : Number(n).toLocaleString('en-US')

const animateValue = () => {
  if (typeof props.value === 'number' && props.value > 0) {
    if (props.noFormat) {
      displayValue.value = String(props.value)
      return
    }
    let start = 0
    const end = props.value
    const duration = 1000
    const step = end / (duration / 16)
    const timer = setInterval(() => {
      start += step
      if (start >= end) {
        displayValue.value = fmt(end)
        clearInterval(timer)
      } else {
        displayValue.value = fmt(Math.floor(start))
      }
    }, 16)
  } else if (props.value !== undefined && props.value !== null && props.value !== 0) {
    displayValue.value = typeof props.value === 'number' ? fmt(props.value) : String(props.value)
  }
}

onMounted(animateValue)
watch(() => props.value, animateValue)
</script>

<style scoped>
.stat-card {
  display: flex;
  align-items: center;
  gap: 1.2rem;
  border-left: 4px solid var(--card-color);
  transition: transform 0.2s, box-shadow 0.2s;
}

.stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
}

.stat-icon {
  font-size: 2.5rem;
  flex-shrink: 0;
}

.stat-body {
  flex: 1;
  min-width: 0;
}

.stat-value {
  font-size: 2rem;
  font-weight: 700;
  color: var(--card-color);
  line-height: 1.1;
  font-variant-numeric: tabular-nums;
}

.stat-title {
  color: var(--text);
  font-weight: 600;
  font-size: 0.9rem;
  margin-top: 0.2rem;
}

.stat-subtitle {
  color: var(--text-muted);
  font-size: 0.8rem;
  margin-top: 0.15rem;
}
</style>
