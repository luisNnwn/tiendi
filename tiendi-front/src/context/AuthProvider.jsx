import { useCallback, useEffect, useMemo, useState } from 'react'
import { AuthContext } from './auth-context'
import { ApiError } from '../services/api'
import * as authService from '../services/auth'

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null)
  const [supplier, setSupplier] = useState(null)
  const [loading, setLoading] = useState(true)

  const clearSession = useCallback(() => {
    setUser(null)
    setSupplier(null)
  }, [])

  const loadSession = useCallback(async () => {
    try {
      const data = await authService.fetchMe()
      setUser(data.user)
      setSupplier(data.supplier)
      return true
    } catch (error) {
      if (error instanceof ApiError && error.status === 401) {
        clearSession()
      }
      return false
    }
  }, [clearSession])

  useEffect(() => {
    let active = true

    authService.fetchMe()
      .then((data) => {
        if (!active) return
        setUser(data.user)
        setSupplier(data.supplier)
      })
      .catch((error) => {
        if (!active) return
        if (error instanceof ApiError && error.status === 401) {
          clearSession()
        }
      })
      .finally(() => {
        if (active) {
          setLoading(false)
        }
      })

    return () => {
      active = false
    }
  }, [clearSession])

  const login = useCallback(async (email, password) => {
    const data = await authService.login(email, password)
    setUser(data.user)
    setSupplier(data.supplier)
    return data
  }, [])

  const logout = useCallback(async () => {
    await authService.logout()
    clearSession()
  }, [clearSession])

  const value = useMemo(
    () => ({
      user,
      supplier,
      loading,
      isAuthenticated: Boolean(user && supplier),
      login,
      logout,
      reloadSession: loadSession,
    }),
    [user, supplier, loading, login, logout, loadSession],
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}
