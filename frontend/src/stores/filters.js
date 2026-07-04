import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useFiltersStore = defineStore('filters', () => {
  const selectedState = ref(null)
  const selectedDistrict = ref(null)
  const yearFrom = ref(2016)
  const yearTo = ref(2024)
  const selectedCategory = ref(null)
  const involvesBicycle = ref(false)
  const involvesTraffic = ref(false)
  const involvesType = ref(null)

  function reset() {
    selectedState.value = null
    selectedDistrict.value = null
    yearFrom.value = 2016
    yearTo.value = 2024
    selectedCategory.value = null
    involvesBicycle.value = false
    involvesTraffic.value = false
    involvesType.value = null
  }

  function buildParams() {
    const params = {}
    if (selectedState.value) params.state_ags = selectedState.value
    if (selectedDistrict.value) params.district_ags = selectedDistrict.value
    if (yearFrom.value && yearFrom.value !== 2016) params.year_from = yearFrom.value
    if (yearTo.value && yearTo.value !== 2024) params.year_to = yearTo.value
    if (selectedCategory.value) params.category = selectedCategory.value
    if (involvesBicycle.value) params.involves_bicycle = true
    if (involvesTraffic.value) params.involves_traffic = true
    if (involvesType.value) params.type = involvesType.value
    return params
  }

  return {
    selectedState,
    selectedDistrict,
    yearFrom,
    yearTo,
    selectedCategory,
    involvesBicycle,
    involvesTraffic,
    involvesType,
    reset,
    buildParams
  }
})
