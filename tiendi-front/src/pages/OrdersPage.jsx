import { useEffect, useMemo, useState } from 'react'
import { ApiError } from '../services/api'
import * as ordersService from '../services/orders'
import * as productsService from '../services/products'
import * as storesService from '../services/stores'
import * as supplierSettingsService from '../services/supplierSettings'

const EMPTY_ITEM = { product_id: '', quantity: 1 }

const STATUS_OPTIONS = [
  { value: '', label: 'Todos' },
  { value: 'pending', label: 'Pendiente' },
  { value: 'confirmed', label: 'Confirmado' },
  { value: 'delivered', label: 'Entregado' },
  { value: 'cancelled', label: 'Cancelado' },
]

const STATUS_EDIT_OPTIONS = [
  { value: 'pending', label: 'Pendiente' },
  { value: 'confirmed', label: 'Confirmado' },
  { value: 'delivered', label: 'Entregado' },
  { value: 'cancelled', label: 'Cancelado' },
]

const WEEKDAY_OPTIONS = [
  { value: 0, label: 'Domingo' },
  { value: 1, label: 'Lunes' },
  { value: 2, label: 'Martes' },
  { value: 3, label: 'Miércoles' },
  { value: 4, label: 'Jueves' },
  { value: 5, label: 'Viernes' },
  { value: 6, label: 'Sábado' },
]

export default function OrdersPage() {
  const [orders, setOrders] = useState([])
  const [stores, setStores] = useState([])
  const [products, setProducts] = useState([])
  const [loading, setLoading] = useState(true)
  const [loadingDependencies, setLoadingDependencies] = useState(true)
  const [error, setError] = useState('')
  const [actionMessage, setActionMessage] = useState('')
  const [statusFilter, setStatusFilter] = useState('')
  const [selectedOrder, setSelectedOrder] = useState(null)
  const [updatingStatusId, setUpdatingStatusId] = useState(null)
  const [settings, setSettings] = useState({
    delivery_weekdays: [5],
    lead_time_days: 2,
  })
  const [savingSettings, setSavingSettings] = useState(false)

  const [form, setForm] = useState({
    store_id: '',
    raw_message: '',
    items: [{ ...EMPTY_ITEM }],
  })
  const [submitting, setSubmitting] = useState(false)

  async function loadDependencies() {
    setLoadingDependencies(true)
    try {
      const [storesData, productsData] = await Promise.all([
        storesService.fetchStores({ active: true }),
        productsService.fetchProducts({ active: true }),
      ])
      setStores(storesData.data ?? [])
      setProducts(productsData.data ?? [])
      const supplierData = await supplierSettingsService.fetchSupplierSettings()
      setSettings({
        delivery_weekdays: supplierData.delivery_weekdays ?? [5],
        lead_time_days: Number(supplierData.lead_time_days ?? 2),
      })
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'No se pudieron cargar tiendas y productos.')
    } finally {
      setLoadingDependencies(false)
    }
  }

  async function loadOrders(filter = statusFilter) {
    setLoading(true)
    setError('')
    try {
      const data = await ordersService.fetchOrders(filter ? { status: filter } : {})
      setOrders(data.data ?? [])
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'No se pudieron cargar los pedidos.')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    loadDependencies()
  }, [])

  useEffect(() => {
    loadOrders(statusFilter)
  }, [statusFilter])

  const totalPreview = useMemo(() => {
    return form.items.reduce((sum, item) => {
      const product = products.find((p) => String(p.id) === String(item.product_id))
      if (!product) return sum
      const qty = Number(item.quantity)
      if (!Number.isFinite(qty) || qty <= 0) return sum
      return sum + Number(product.price) * qty
    }, 0)
  }, [form.items, products])

  function resetForm() {
    setForm({
      store_id: '',
      raw_message: '',
      items: [{ ...EMPTY_ITEM }],
    })
  }

  function updateItem(index, key, value) {
    setForm((current) => ({
      ...current,
      items: current.items.map((item, itemIndex) =>
        itemIndex === index ? { ...item, [key]: value } : item
      ),
    }))
  }

  function addItem() {
    setForm((current) => ({
      ...current,
      items: [...current.items, { ...EMPTY_ITEM }],
    }))
  }

  function removeItem(index) {
    setForm((current) => {
      if (current.items.length === 1) return current
      return {
        ...current,
        items: current.items.filter((_, itemIndex) => itemIndex !== index),
      }
    })
  }

  async function handleSubmit(event) {
    event.preventDefault()
    setSubmitting(true)
    setError('')
    setActionMessage('')

    const payload = {
      store_id: Number(form.store_id),
      raw_message: form.raw_message.trim() || null,
      items: form.items.map((item) => ({
        product_id: Number(item.product_id),
        quantity: Number(item.quantity),
      })),
    }

    try {
      await ordersService.createOrder(payload)
      setActionMessage('Pedido creado correctamente.')
      resetForm()
      await loadOrders()
    } catch (err) {
      if (err instanceof ApiError && err.data?.errors) {
        const firstError = Object.values(err.data.errors)[0]?.[0]
        setError(firstError ?? err.message)
      } else if (err instanceof ApiError && Array.isArray(err.data?.errors)) {
        setError(err.data.errors[0] ?? err.message)
      } else {
        setError(err instanceof ApiError ? err.message : 'No se pudo crear el pedido.')
      }
    } finally {
      setSubmitting(false)
    }
  }

  async function handleViewOrder(orderId) {
    setError('')
    try {
      const data = await ordersService.fetchOrder(orderId)
      setSelectedOrder(data.data)
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'No se pudo cargar el detalle del pedido.')
    }
  }

  async function handleStatusChange(orderId, status) {
    setUpdatingStatusId(orderId)
    setError('')
    setActionMessage('')
    try {
      await ordersService.updateOrderStatus(orderId, status)
      if (selectedOrder?.id === orderId) {
        const detail = await ordersService.fetchOrder(orderId)
        setSelectedOrder(detail.data)
      }
      await loadOrders()
      setActionMessage('Estado de pedido actualizado correctamente.')
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'No se pudo actualizar el estado del pedido.')
    } finally {
      setUpdatingStatusId(null)
    }
  }

  function toggleWeekday(value) {
    setSettings((current) => {
      const exists = current.delivery_weekdays.includes(value)
      const nextDays = exists
        ? current.delivery_weekdays.filter((day) => day !== value)
        : [...current.delivery_weekdays, value]

      return {
        ...current,
        delivery_weekdays: nextDays.sort((a, b) => a - b),
      }
    })
  }

  async function saveSettings() {
    if (settings.delivery_weekdays.length === 0) {
      setError('Selecciona al menos un día de entrega.')
      return
    }

    setSavingSettings(true)
    setError('')
    setActionMessage('')
    try {
      const updated = await supplierSettingsService.updateSupplierSettings(settings)
      setSettings({
        delivery_weekdays: updated.delivery_weekdays ?? [5],
        lead_time_days: Number(updated.lead_time_days ?? 2),
      })
      setActionMessage('Configuración de entrega guardada.')
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'No se pudo guardar la configuración de entrega.')
    } finally {
      setSavingSettings(false)
    }
  }

  return (
    <div className="products-page">
      <div className="page-header">
        <div>
          <h1>Pedidos</h1>
          <p className="muted">Crea pedidos manuales y consulta el historial de pedidos del proveedor.</p>
        </div>
      </div>

      {actionMessage && <div className="alert alert-success">{actionMessage}</div>}
      {error && <div className="alert alert-error">{error}</div>}

      <div className="card analytics-section">
        <h2>Configuración de entregas</h2>
        <p className="muted">
          Define tus días de entrega y cuántos días antes deben entrar los pedidos para salir en esa fecha.
        </p>
        <div className="orders-settings-grid">
          <div className="orders-weekday-picker">
            {WEEKDAY_OPTIONS.map((day) => (
              <label key={day.value} className="checkbox-row compact">
                <input
                  type="checkbox"
                  checked={settings.delivery_weekdays.includes(day.value)}
                  onChange={() => toggleWeekday(day.value)}
                />
                {day.label}
              </label>
            ))}
          </div>
          <label>
            Anticipación mínima (días)
            <input
              type="number"
              min="0"
              max="30"
              value={settings.lead_time_days}
              onChange={(event) =>
                setSettings((current) => ({
                  ...current,
                  lead_time_days: Number(event.target.value),
                }))
              }
            />
          </label>
        </div>
        <div className="form-actions">
          <button type="button" className="btn btn-primary" onClick={saveSettings} disabled={savingSettings}>
            {savingSettings ? 'Guardando…' : 'Guardar configuración'}
          </button>
        </div>
      </div>

      <form className="card form-card" onSubmit={handleSubmit}>
        <h2>Nuevo pedido</h2>
        <div className="form-grid">
          <label>
            Tienda
            <select
              value={form.store_id}
              onChange={(event) => setForm((current) => ({ ...current, store_id: event.target.value }))}
              required
              disabled={loadingDependencies}
            >
              <option value="" disabled>
                Selecciona una tienda
              </option>
              {stores.map((store) => (
                <option key={store.id} value={store.id}>
                  {store.name}
                </option>
              ))}
            </select>
          </label>

          <label>
            Mensaje original (opcional)
            <input
              value={form.raw_message}
              onChange={(event) => setForm((current) => ({ ...current, raw_message: event.target.value }))}
              placeholder="Pedido desde llamada o WhatsApp"
            />
          </label>
        </div>

        <div className="orders-items">
          {form.items.map((item, index) => (
            <div key={`item-${index}`} className="orders-item-row">
              <label>
                Producto
                <select
                  value={item.product_id}
                  onChange={(event) => updateItem(index, 'product_id', event.target.value)}
                  required
                  disabled={loadingDependencies}
                >
                  <option value="" disabled>
                    Selecciona producto
                  </option>
                  {products.map((product) => (
                    <option key={product.id} value={product.id}>
                      {product.name} (${Number(product.price).toFixed(2)})
                    </option>
                  ))}
                </select>
              </label>

              <label>
                Cantidad
                <input
                  type="number"
                  min="1"
                  step="1"
                  value={item.quantity}
                  onChange={(event) => updateItem(index, 'quantity', event.target.value)}
                  required
                />
              </label>

              <button
                type="button"
                className="btn btn-small btn-danger"
                onClick={() => removeItem(index)}
                disabled={form.items.length === 1}
              >
                Quitar
              </button>
            </div>
          ))}
        </div>

        <div className="form-actions">
          <button type="button" className="btn btn-ghost" onClick={addItem}>
            Agregar línea
          </button>
          <span className="muted">Total estimado: ${totalPreview.toFixed(2)}</span>
          <button type="submit" className="btn btn-primary" disabled={submitting || loadingDependencies}>
            {submitting ? 'Guardando…' : 'Crear pedido'}
          </button>
        </div>
      </form>

      {selectedOrder && (
        <div className="card analytics-section">
          <h2>Detalle pedido #{selectedOrder.id}</h2>
          <p className="muted">
            {selectedOrder.store?.name} · Estado: {selectedOrder.status} · Total: $
            {Number(selectedOrder.total ?? 0).toFixed(2)} · Entrega: {selectedOrder.delivery_date ?? '—'}
          </p>
          <ul className="analytics-insights-list">
            {(selectedOrder.items ?? []).map((item) => (
              <li key={item.id}>
                {item.product_name ?? `Producto ${item.product_id}`} · {item.quantity} unidades · $
                {Number(item.subtotal).toFixed(2)}
              </li>
            ))}
          </ul>
        </div>
      )}

      <div className="card table-card">
        <div className="page-header table-header-inline">
          <h2>Historial de pedidos</h2>
          <label className="checkbox-row compact">
            Estado
            <select value={statusFilter} onChange={(event) => setStatusFilter(event.target.value)}>
              {STATUS_OPTIONS.map((option) => (
                <option key={option.value || 'all'} value={option.value}>
                  {option.label}
                </option>
              ))}
            </select>
          </label>
        </div>
        {loading ? (
          <p className="muted table-empty">Cargando pedidos…</p>
        ) : orders.length === 0 ? (
          <p className="muted table-empty">No hay pedidos para mostrar.</p>
        ) : (
          <div className="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Tienda</th>
                  <th>Estado</th>
                  <th>Entrega</th>
                  <th>Total</th>
                  <th>Fecha</th>
                  <th>Acciones</th>
                </tr>
              </thead>
              <tbody>
                {orders.map((order) => (
                  <tr key={order.id}>
                    <td>#{order.id}</td>
                    <td>{order.store?.name ?? `Tienda ${order.store_id}`}</td>
                    <td>
                      <select
                        value={order.status}
                        onChange={(event) => handleStatusChange(order.id, event.target.value)}
                        disabled={updatingStatusId === order.id}
                      >
                        {STATUS_EDIT_OPTIONS.map((option) => (
                          <option key={option.value} value={option.value}>
                            {option.label}
                          </option>
                        ))}
                      </select>
                    </td>
                    <td>{order.delivery_date ?? '—'}</td>
                    <td>${Number(order.total).toFixed(2)}</td>
                    <td>{new Date(order.created_at).toLocaleDateString('es-SV')}</td>
                    <td className="table-actions">
                      <button
                        type="button"
                        className="btn btn-small btn-ghost"
                        onClick={() => handleViewOrder(order.id)}
                      >
                        Ver detalle
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  )
}

