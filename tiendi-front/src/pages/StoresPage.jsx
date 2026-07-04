import { useEffect, useState } from 'react'
import StoreForm from '../components/StoreForm'
import { ApiError } from '../services/api'
import * as storesService from '../services/stores'
import { formatDisplayPhone } from '../utils/phone'

export default function StoresPage() {
  const [stores, setStores] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [showInactive, setShowInactive] = useState(false)
  const [editingStore, setEditingStore] = useState(null)
  const [showForm, setShowForm] = useState(false)
  const [submitting, setSubmitting] = useState(false)
  const [actionMessage, setActionMessage] = useState('')

  useEffect(() => {
    let active = true

    storesService
      .fetchStores(showInactive ? {} : { active: true })
      .then((data) => {
        if (!active) return
        setStores(data.data ?? [])
        setError('')
      })
      .catch((err) => {
        if (!active) return
        setError(err instanceof ApiError ? err.message : 'No se pudieron cargar las tiendas.')
      })
      .finally(() => {
        if (active) {
          setLoading(false)
        }
      })

    return () => {
      active = false
    }
  }, [showInactive])

  async function loadStores() {
    setLoading(true)
    setError('')

    try {
      const data = await storesService.fetchStores(showInactive ? {} : { active: true })
      setStores(data.data ?? [])
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'No se pudieron cargar las tiendas.')
    } finally {
      setLoading(false)
    }
  }

  function openCreateForm() {
    setEditingStore(null)
    setShowForm(true)
    setActionMessage('')
  }

  function openEditForm(store) {
    setEditingStore(store)
    setShowForm(true)
    setActionMessage('')
  }

  function closeForm() {
    setShowForm(false)
    setEditingStore(null)
  }

  async function handleSubmit(payload) {
    setSubmitting(true)
    setError('')

    try {
      if (editingStore) {
        await storesService.updateStore(editingStore.id, payload)
        setActionMessage('Tienda actualizada correctamente.')
      } else {
        await storesService.createStore(payload)
        setActionMessage('Tienda registrada correctamente.')
      }

      closeForm()
      await loadStores()
    } catch (err) {
      if (err instanceof ApiError && err.data?.errors) {
        const firstError = Object.values(err.data.errors)[0]?.[0]
        setError(firstError ?? err.message)
      } else {
        setError(err instanceof ApiError ? err.message : 'No se pudo guardar la tienda.')
      }
    } finally {
      setSubmitting(false)
    }
  }

  async function handleDeactivate(store) {
    const confirmed = window.confirm(`¿Desactivar "${store.name}"?`)
    if (!confirmed) return

    setError('')

    try {
      await storesService.deactivateStore(store.id)
      setActionMessage(`"${store.name}" fue desactivada.`)
      await loadStores()
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'No se pudo desactivar la tienda.')
    }
  }

  return (
    <div className="products-page">
      <div className="page-header">
        <div>
          <h1>Tiendas</h1>
          <p className="muted">
            Registra las tiendas autorizadas para hacer pedidos por WhatsApp.
          </p>
        </div>

        <div className="page-header-actions">
          <label className="checkbox-row compact">
            <input
              type="checkbox"
              checked={showInactive}
              onChange={(event) => setShowInactive(event.target.checked)}
            />
            Mostrar inactivas
          </label>
          {!showForm && (
            <button type="button" className="btn btn-primary" onClick={openCreateForm}>
              Nueva tienda
            </button>
          )}
        </div>
      </div>

      {actionMessage && <div className="alert alert-success">{actionMessage}</div>}
      {error && <div className="alert alert-error">{error}</div>}

      {showForm && (
        <StoreForm
          key={editingStore?.id ?? 'new'}
          store={editingStore}
          onSubmit={handleSubmit}
          onCancel={closeForm}
          submitting={submitting}
        />
      )}

      <div className="card table-card">
        {loading ? (
          <p className="muted table-empty">Cargando tiendas…</p>
        ) : stores.length === 0 ? (
          <p className="muted table-empty">Aún no hay tiendas registradas.</p>
        ) : (
          <div className="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Nombre</th>
                  <th>WhatsApp</th>
                  <th>Estado</th>
                  <th>Registrada</th>
                  <th>Acciones</th>
                </tr>
              </thead>
              <tbody>
                {stores.map((store) => (
                  <tr key={store.id}>
                    <td>{store.name}</td>
                    <td>{store.phone_display ?? formatDisplayPhone(store.phone_number)}</td>
                    <td>
                      <span className={`badge ${store.active ? 'badge-success' : 'badge-muted'}`}>
                        {store.active ? 'Activa' : 'Inactiva'}
                      </span>
                    </td>
                    <td>{new Date(store.created_at).toLocaleDateString('es-SV')}</td>
                    <td className="table-actions">
                      <button
                        type="button"
                        className="btn btn-small btn-ghost"
                        onClick={() => openEditForm(store)}
                      >
                        Editar
                      </button>
                      {store.active && (
                        <button
                          type="button"
                          className="btn btn-small btn-danger"
                          onClick={() => handleDeactivate(store)}
                        >
                          Desactivar
                        </button>
                      )}
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
