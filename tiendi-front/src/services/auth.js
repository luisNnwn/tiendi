import { apiRequest, setToken, clearToken } from './api'

export async function login(email, password) {
  const data = await apiRequest('/auth/login', {
    method: 'POST',
    body: JSON.stringify({ email, password }),
  })

  setToken(data.token)
  return data
}

export async function fetchMe() {
  return apiRequest('/auth/me')
}

export async function logout() {
  try {
    await apiRequest('/auth/logout', { method: 'POST' })
  } finally {
    clearToken()
  }
}
