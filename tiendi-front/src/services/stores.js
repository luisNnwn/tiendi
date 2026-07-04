import { apiRequest } from './api'

export async function fetchStores(params = {}) {
  const search = new URLSearchParams()
  if (params.active !== undefined) {
    search.set('active', String(params.active))
  }

  const query = search.toString()
  const path = query ? `/stores?${query}` : '/stores'

  return apiRequest(path)
}

export async function createStore(payload) {
  return apiRequest('/stores', {
    method: 'POST',
    body: JSON.stringify(payload),
  })
}

export async function updateStore(id, payload) {
  return apiRequest(`/stores/${id}`, {
    method: 'PUT',
    body: JSON.stringify(payload),
  })
}

export async function deactivateStore(id) {
  return apiRequest(`/stores/${id}`, {
    method: 'DELETE',
  })
}
