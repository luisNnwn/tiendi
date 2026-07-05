import { apiRequest } from './api'

export async function fetchOrders(params = {}) {
  const search = new URLSearchParams()
  if (params.status) {
    search.set('status', params.status)
  }

  const query = search.toString()
  const path = query ? `/orders?${query}` : '/orders'

  return apiRequest(path)
}

export async function createOrder(payload) {
  return apiRequest('/orders', {
    method: 'POST',
    body: JSON.stringify(payload),
  })
}

export async function fetchOrder(id) {
  return apiRequest(`/orders/${id}`)
}

export async function updateOrderStatus(id, status) {
  return apiRequest(`/orders/${id}/status`, {
    method: 'PATCH',
    body: JSON.stringify({ status }),
  })
}

