import { useState } from 'react'
import { buildPhonePayload, formatLocalPhone, toLocalPhoneInput } from '../utils/phone'

function StoreForm({ store, onSubmit, onCancel, submitting }) {
  const [form, setForm] = useState(() => ({
    name: store?.name ?? '',
    phone_number: toLocalPhoneInput(store?.phone_number),
    active: store?.active ?? true,
  }))

  function handleChange(event) {
    const { name, value, type, checked } = event.target

    if (name === 'phone_number') {
      setForm((current) => ({
        ...current,
        phone_number: formatLocalPhone(value),
      }))
      return
    }

    setForm((current) => ({
      ...current,
      [name]: type === 'checkbox' ? checked : value,
    }))
  }

  function handleSubmit(event) {
    event.preventDefault()

    onSubmit({
      name: form.name.trim(),
      ...buildPhonePayload(form.phone_number),
      ...(store ? { active: form.active } : {}),
    })
  }

  return (
    <form className="card form-card" onSubmit={handleSubmit}>
      <div className="form-header">
        <h2>{store ? 'Editar tienda' : 'Registrar tienda'}</h2>
        <p className="muted">
          El número se usará para identificar pedidos por WhatsApp.
        </p>
      </div>

      <div className="form-grid">
        <label>
          Nombre de la tienda
          <input
            name="name"
            value={form.name}
            onChange={handleChange}
            required
            placeholder="Abarrotes La Esquina"
          />
        </label>

        <label>
          Número de WhatsApp
          <div className="phone-input">
            <span className="phone-prefix">+503</span>
            <input
              name="phone_number"
              value={form.phone_number}
              onChange={handleChange}
              required
              inputMode="numeric"
              placeholder="7123-4567"
              maxLength={9}
            />
          </div>
        </label>
      </div>

      <p className="field-hint">
        Ingresa solo el número local de 8 dígitos. El código +503 se agrega automáticamente.
      </p>

      {store && (
        <label className="checkbox-row">
          <input name="active" type="checkbox" checked={form.active} onChange={handleChange} />
          Tienda activa
        </label>
      )}

      <div className="form-actions">
        <button type="button" className="btn btn-ghost" onClick={onCancel} disabled={submitting}>
          Cancelar
        </button>
        <button type="submit" className="btn btn-primary" disabled={submitting}>
          {submitting ? 'Guardando…' : store ? 'Guardar cambios' : 'Registrar tienda'}
        </button>
      </div>
    </form>
  )
}

export default StoreForm
