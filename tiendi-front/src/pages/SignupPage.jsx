import { useState } from 'react'
import { Link, Navigate } from 'react-router-dom'
import { ApiError } from '../services/api'
import * as authService from '../services/auth'
import { useAuth } from '../hooks/useAuth'

export default function SignupPage() {
  const { loading, isAuthenticated, reloadSession } = useAuth()
  const [form, setForm] = useState({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    supplier_name: '',
    phone_number: '',
  })
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState('')

  if (!loading && isAuthenticated) {
    return <Navigate to="/productos" replace />
  }

  function handleChange(event) {
    const { name, value } = event.target
    setForm((current) => ({ ...current, [name]: value }))
  }

  async function handleSubmit(event) {
    event.preventDefault()
    setSubmitting(true)
    setError('')

    try {
      await authService.signup(form)
      await reloadSession()
    } catch (err) {
      if (err instanceof ApiError) {
        const firstFieldError = err.data?.errors ? Object.values(err.data.errors)[0]?.[0] : null
        setError(firstFieldError ?? err.data?.message ?? 'No se pudo registrar la cuenta.')
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
            <h1>Crear proveedor</h1>
            <p className="muted">Registra tu cuenta y tu empresa proveedora.</p>
          </div>
        </div>

        <form className="auth-form" onSubmit={handleSubmit}>
          <label>
            Nombre de usuario
            <input name="name" value={form.name} onChange={handleChange} required />
          </label>

          <label>
            Correo electrónico
            <input type="email" name="email" value={form.email} onChange={handleChange} required />
          </label>

          <label>
            Nombre del proveedor
            <input name="supplier_name" value={form.supplier_name} onChange={handleChange} required />
          </label>

          <label>
            WhatsApp (+503)
            <input
              name="phone_number"
              value={form.phone_number}
              onChange={handleChange}
              placeholder="7123-4567"
              required
            />
          </label>

          <label>
            Contraseña
            <input
              type="password"
              name="password"
              value={form.password}
              onChange={handleChange}
              minLength={8}
              required
            />
          </label>

          <label>
            Confirmar contraseña
            <input
              type="password"
              name="password_confirmation"
              value={form.password_confirmation}
              onChange={handleChange}
              minLength={8}
              required
            />
          </label>

          {error && <div className="alert alert-error">{error}</div>}

          <button type="submit" className="btn btn-primary btn-block" disabled={submitting}>
            {submitting ? 'Registrando…' : 'Crear cuenta'}
          </button>

          <Link className="chatbot-link" to="/login">
            Ya tengo cuenta
          </Link>
        </form>
      </div>
    </div>
  )
}

