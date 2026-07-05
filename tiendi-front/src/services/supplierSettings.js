import { apiRequest } from './api'

export function fetchSupplierSettings() {
  return apiRequest('/supplier/settings')
}

export function updateSupplierSettings(payload) {
  return apiRequest('/supplier/settings', {
    method: 'PUT',
    body: JSON.stringify(payload),
  })
}

