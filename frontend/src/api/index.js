import axios from 'axios'

const api = axios.create({ baseURL: '/api/v1' })

export const getRegions = (p) => api.get('/regions', { params: p })
export const getRegion = (id) => api.get(`/regions/${id}`)
export const getAccidents = (p) => api.get('/accidents', { params: p })
export const getAggregates = (p) => api.get('/aggregates/accidents', { params: p })
export const getTimeSeries = (p) => api.get('/aggregates/time-series', { params: p })
export const getRates = (p) => api.get('/aggregates/rates', { params: p })
export const getRankings = (p) => api.get('/aggregates/rankings', { params: p })
export const getEarliestYear = (p) => api.get('/aggregates/earliest-year', { params: p })
export const getYearOverYear = (p) => api.get('/aggregates/year-over-year', { params: p })
export const getZeroCases = (p) => api.get('/aggregates/zero-cases', { params: p })
export const getSummary = () => api.get('/stats/summary')
export const getSources = () => api.get('/metadata/sources')
export const getImportRuns = (p) => api.get('/import-runs', { params: p })
