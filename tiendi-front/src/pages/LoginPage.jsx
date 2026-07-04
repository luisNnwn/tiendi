import { useState } from 'react'
import { Navigate, useLocation } from 'react-router-dom'
import { ApiError } from '../services/api'
import { useAuth } from '../hooks/useAuth'

export default function LoginPage() {
  const { login, isAuthenticated, loading } = useAuth()
  const location = useLocation()
  const [email, setEmail] = useState('proveedor@tiendi.com')
  const [password, setPassword] = useState('password')
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState('')

  if (!loading && isAuthenticated) {
    const redirectTo = location.state?.from?.pathname ?? '/productos'
    return <Navigate to={redirectTo} replace />
  }

  async function handleSubmit(event) {
    event.preventDefault()
    setSubmitting(true)
    setError('')

    try {
      await login(email, password)
    } catch (err) {
      if (err instanceof ApiError) {
        const message =
          err.data?.message ??
          err.data?.errors?.email?.[0] ??
          'No se pudo iniciar sesión.'
        setError(message)
      } else {
        setError('Error de conexión con el servidor.')
      }
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="auth-page">
      <div className="auth-card card">
        <div className="auth-brand">
          <span className="brand-mark">T</span>
          <div>
            <h1>Tiendi</h1>
            <p className="muted">Acceso para proveedores</p>
          </div>
        </div>

        <form className="auth-form" onSubmit={handleSubmit}>
          <label>
            Correo electrónico
            <input
              type="email"
              value={email}
              onChange={(event) => setEmail(event.target.value)}
              autoComplete="email"
              required
            />
          </label>

          <label>
            Contraseña
            <input
              type="password"
              value={password}
              onChange={(event) => setPassword(event.target.value)}
              autoComplete="current-password"
              required
            />
          </label>

          {error && <div className="alert alert-error">{error}</div>}

          <button type="submit" className="btn btn-primary btn-block" disabled={submitting}>
            {submitting ? 'Ingresando…' : 'Iniciar sesión'}
          </button>

          <a className="chatbot-link" href="/chatbot">
            Probar chatbot de pedidos (sin login)
          </a>
        </form>
      </div>
    </div>
  )
}
