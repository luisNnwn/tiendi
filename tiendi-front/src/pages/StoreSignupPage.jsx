import { useState } from 'react'
import { Link } from 'react-router-dom'
import { ApiError } from '../services/api'
import * as storesService from '../services/stores'

export default function StoreSignupPage() {
  const [form, setForm] = useState({
    name: '',
    phone_number: '',
  })
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState('')

  function handleChange(event) {
    const { name, value } = event.target
    setForm((current) => ({ ...current, [name]: value }))
  }

  async function handleSubmit(event) {
    event.preventDefault()
    setSubmitting(true)
    setError('')
    setSuccess('')

    try {
      await storesService.createStore(form)
      setSuccess('Tienda registrada correctamente.')
      setForm({ name: '', phone_number: '' })
    } catch (err) {
      if (err instanceof ApiError) {
        const firstFieldError = err.data?.errors ? Object.values(err.data.errors)[0]?.[0] : null
        setError(firstFieldError ?? err.data?.message ?? 'No se pudo registrar la tienda.')
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
            <h1>Registrar tienda</h1>
            <p className="muted">Registro público de tiendas para pedidos por WhatsApp.</p>
          </div>
        </div>

        <form className="auth-form" onSubmit={handleSubmit}>
          <label>
            Nombre de la tienda
            <input name="name" value={form.name} onChange={handleChange} required />
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

          {error && <div className="alert alert-error">{error}</div>}
          {success && <div className="alert alert-success">{success}</div>}

          <button type="submit" className="btn btn-primary btn-block" disabled={submitting}>
            {submitting ? 'Registrando…' : 'Registrar tienda'}
          </button>

          <Link className="chatbot-link" to="/login">
            Volver al login
          </Link>
        </form>
      </div>
    </div>
  )
}

