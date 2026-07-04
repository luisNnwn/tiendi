import { apiRequest } from './api'

export async function fetchProducts(params = {}) {
  const search = new URLSearchParams()
  if (params.active !== undefined) {
    search.set('active', String(params.active))
  }

  const query = search.toString()
  const path = query ? `/products?${query}` : '/products'

  return apiRequest(path)
}

export async function createProduct(payload) {
  return apiRequest('/products', {
    method: 'POST',
    body: JSON.stringify(payload),
  })
}

export async function updateProduct(id, payload) {
  return apiRequest(`/products/${id}`, {
    method: 'PUT',
    body: JSON.stringify(payload),
  })
}

export async function deactivateProduct(id) {
  return apiRequest(`/products/${id}`, {
    method: 'DELETE',
  })
}
