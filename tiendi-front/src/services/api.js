const TOKEN_KEY = 'tiendi_token'

export function getToken() {
  return localStorage.getItem(TOKEN_KEY)
}

export function setToken(token) {
  localStorage.setItem(TOKEN_KEY, token)
}

export function clearToken() {
  localStorage.removeItem(TOKEN_KEY)
}

export class ApiError extends Error {
  constructor(status, data) {
    super(data?.message ?? 'Error en la solicitud')
    this.status = status
    this.data = data
  }
}

export async function apiRequest(path, options = {}) {
  const token = getToken()
  const headers = {
    Accept: 'application/json',
    ...options.headers,
  }

  if (!(options.body instanceof FormData)) {
    headers['Content-Type'] = 'application/json'
  }

  if (token) {
    headers.Authorization = `Bearer ${token}`
  }

  const response = await fetch(`/api${path}`, {
    ...options,
    headers,
  })

  const data = await response.json().catch(() => ({}))

  if (response.status === 401) {
    clearToken()
  }

  if (!response.ok) {
    throw new ApiError(response.status, data)
  }

  return data
}
