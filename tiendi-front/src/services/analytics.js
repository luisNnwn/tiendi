import { apiRequest } from './api'

function toQuery(params = {}) {
  const search = new URLSearchParams()
  Object.entries(params).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== '') {
      search.set(key, String(value))
    }
  })
  const query = search.toString()
  return query ? `?${query}` : ''
}

export function fetchOverview(params = {}) {
  return apiRequest(`/analytics/overview${toQuery(params)}`)
}

export function fetchInsights(params = {}) {
  return apiRequest(`/analytics/insights${toQuery(params)}`, { method: 'POST' })
}

export function refreshAnalytics(params = {}) {
  return apiRequest(`/analytics/refresh${toQuery(params)}`, { method: 'POST' })
}

