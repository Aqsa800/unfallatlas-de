<template>
  <div class="chart-wrapper">
    <h4 v-if="title" class="chart-title">{{ title }}</h4>
    <v-chart :option="option" :style="{ height }" autoresize />
  </div>
</template>

<script setup>
import { computed } from 'vue'
import VChart from 'vue-echarts'
import { use } from 'echarts/core'
import { BarChart, LineChart } from 'echarts/charts'
import {
  GridComponent,
  TooltipComponent,
  LegendComponent,
  TitleComponent
} from 'echarts/components'
import { CanvasRenderer } from 'echarts/renderers'

use([
  BarChart,
  LineChart,
  GridComponent,
  TooltipComponent,
  LegendComponent,
  TitleComponent,
  CanvasRenderer
])

const props = defineProps({
  data: { type: Array, default: () => [] },
  type: { type: String, default: 'bar' },
  title: { type: String, default: '' },
  height: { type: String, default: '300px' },
  color: { type: String, default: '#E63946' }
})

const option = computed(() => ({
  backgroundColor: 'transparent',
  tooltip: {
    trigger: 'axis',
    backgroundColor: '#1a1a2e',
    borderColor: '#2a2a4a',
    textStyle: { color: '#eaeaea' }
  },
  grid: {
    left: '5%',
    right: '5%',
    bottom: '12%',
    top: '8%',
    containLabel: true
  },
  xAxis: {
    type: 'category',
    data: (props.data || []).map(d => d.label),
    axisLabel: {
      color: '#8892a4',
      rotate: (props.data || []).length > 8 ? 30 : 0,
      fontSize: 11
    },
    axisLine: { lineStyle: { color: '#2a2a4a' } },
    axisTick: { lineStyle: { color: '#2a2a4a' } }
  },
  yAxis: {
    type: 'value',
    axisLabel: {
      color: '#8892a4',
      fontSize: 11,
      formatter: (val) => val >= 1000 ? (val / 1000).toFixed(0) + 'k' : val
    },
    splitLine: { lineStyle: { color: '#2a2a4a', type: 'dashed' } },
    axisLine: { show: false }
  },
  series: [
    {
      type: props.type,
      data: (props.data || []).map(d => d.count),
      itemStyle: {
        color: props.color,
        borderRadius: props.type === 'bar' ? [3, 3, 0, 0] : 0
      },
      smooth: props.type === 'line',
      lineStyle: props.type === 'line' ? { color: props.color, width: 2.5 } : undefined,
      areaStyle: props.type === 'line' ? {
        color: {
          type: 'linear',
          x: 0, y: 0, x2: 0, y2: 1,
          colorStops: [
            { offset: 0, color: props.color + '40' },
            { offset: 1, color: props.color + '05' }
          ]
        }
      } : undefined,
      symbol: props.type === 'line' ? 'circle' : undefined,
      symbolSize: props.type === 'line' ? 5 : undefined
    }
  ]
}))
</script>

<style scoped>
.chart-wrapper {
  background: var(--surface);
  border-radius: var(--radius);
  padding: 1rem;
  border: 1px solid var(--border);
}

.chart-title {
  color: var(--text);
  margin: 0 0 0.75rem;
  font-size: 0.92rem;
  font-weight: 600;
}
</style>
