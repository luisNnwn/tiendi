import { useState } from 'react'

const emptyForm = {
  name: '',
  category: '',
  unit: '',
  price: '',
  active: true,
}

function buildForm(product) {
  if (!product) {
    return emptyForm
  }

  return {
    name: product.name ?? '',
    category: product.category ?? '',
    unit: product.unit ?? '',
    price: product.price ?? '',
    active: product.active ?? true,
  }
}

export default function ProductForm({ product, onSubmit, onCancel, submitting }) {
  const [form, setForm] = useState(() => buildForm(product))

  function handleChange(event) {
    const { name, value, type, checked } = event.target
    setForm((current) => ({
      ...current,
      [name]: type === 'checkbox' ? checked : value,
    }))
  }

  function handleSubmit(event) {
    event.preventDefault()
    onSubmit({
      name: form.name.trim(),
      category: form.category.trim() || null,
      unit: form.unit.trim(),
      price: Number(form.price),
      active: form.active,
    })
  }

  return (
    <form className="card form-card" onSubmit={handleSubmit}>
      <div className="form-header">
        <h2>{product ? 'Editar producto' : 'Nuevo producto'}</h2>
        <p className="muted">Completa los datos del catálogo.</p>
      </div>

      <div className="form-grid">
        <label>
          Nombre
          <input
            name="name"
            value={form.name}
            onChange={handleChange}
            required
            placeholder="Coca Cola"
          />
        </label>

        <label>
          Categoría
          <input
            name="category"
            value={form.category}
            onChange={handleChange}
            placeholder="Bebidas"
          />
        </label>

        <label>
          Unidad
          <input
            name="unit"
            value={form.unit}
            onChange={handleChange}
            required
            placeholder="caja"
          />
        </label>

        <label>
          Precio
          <input
            name="price"
            type="number"
            min="0"
            step="0.01"
            value={form.price}
            onChange={handleChange}
            required
            placeholder="250.00"
          />
        </label>
      </div>

      {product && (
        <label className="checkbox-row">
          <input name="active" type="checkbox" checked={form.active} onChange={handleChange} />
          Producto activo
        </label>
      )}

      <div className="form-actions">
        <button type="button" className="btn btn-ghost" onClick={onCancel} disabled={submitting}>
          Cancelar
        </button>
        <button type="submit" className="btn btn-primary" disabled={submitting}>
          {submitting ? 'Guardando…' : product ? 'Guardar cambios' : 'Crear producto'}
        </button>
      </div>
    </form>
  )
}
