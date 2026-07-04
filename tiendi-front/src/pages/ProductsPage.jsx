import { useCallback, useEffect, useState } from 'react'
import ProductForm from '../components/ProductForm'
import { ApiError } from '../services/api'
import * as productsService from '../services/products'

export default function ProductsPage() {
  const [products, setProducts] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [showInactive, setShowInactive] = useState(false)
  const [editingProduct, setEditingProduct] = useState(null)
  const [showForm, setShowForm] = useState(false)
  const [submitting, setSubmitting] = useState(false)
  const [actionMessage, setActionMessage] = useState('')

  const loadProducts = useCallback(async (includeInactive = showInactive) => {
    setLoading(true)
    setError('')

    try {
      const data = await productsService.fetchProducts(
        includeInactive ? {} : { active: true },
      )
      setProducts(data.data ?? [])
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'No se pudieron cargar los productos.')
    } finally {
      setLoading(false)
    }
  }, [showInactive])

  useEffect(() => {
    let active = true

    productsService
      .fetchProducts(showInactive ? {} : { active: true })
      .then((data) => {
        if (!active) return
        setProducts(data.data ?? [])
        setError('')
      })
      .catch((err) => {
        if (!active) return
        setError(err instanceof ApiError ? err.message : 'No se pudieron cargar los productos.')
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

  function openCreateForm() {
    setEditingProduct(null)
    setShowForm(true)
    setActionMessage('')
  }

  function openEditForm(product) {
    setEditingProduct(product)
    setShowForm(true)
    setActionMessage('')
  }

  function closeForm() {
    setShowForm(false)
    setEditingProduct(null)
  }

  async function handleSubmit(payload) {
    setSubmitting(true)
    setError('')

    try {
      if (editingProduct) {
        await productsService.updateProduct(editingProduct.id, payload)
        setActionMessage('Producto actualizado correctamente.')
      } else {
        await productsService.createProduct(payload)
        setActionMessage('Producto creado correctamente.')
      }

      closeForm()
      await loadProducts()
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'No se pudo guardar el producto.')
    } finally {
      setSubmitting(false)
    }
  }

  async function handleDeactivate(product) {
    const confirmed = window.confirm(`¿Desactivar "${product.name}"?`)
    if (!confirmed) return

    setError('')

    try {
      await productsService.deactivateProduct(product.id)
      setActionMessage(`"${product.name}" fue desactivado.`)
      await loadProducts()
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'No se pudo desactivar el producto.')
    }
  }

  return (
    <div className="products-page">
      <div className="page-header">
        <div>
          <h1>Productos</h1>
          <p className="muted">Administra el catálogo que verán las tiendas al pedir.</p>
        </div>

        <div className="page-header-actions">
          <label className="checkbox-row compact">
            <input
              type="checkbox"
              checked={showInactive}
              onChange={(event) => setShowInactive(event.target.checked)}
            />
            Mostrar inactivos
          </label>
          <button type="button" className="btn btn-primary" onClick={openCreateForm}>
            Nuevo producto
          </button>
        </div>
      </div>

      {actionMessage && <div className="alert alert-success">{actionMessage}</div>}
      {error && <div className="alert alert-error">{error}</div>}

      {showForm && (
        <ProductForm
          key={editingProduct?.id ?? 'new'}
          product={editingProduct}
          onSubmit={handleSubmit}
          onCancel={closeForm}
          submitting={submitting}
        />
      )}

      <div className="card table-card">
        {loading ? (
          <p className="muted table-empty">Cargando productos…</p>
        ) : products.length === 0 ? (
          <p className="muted table-empty">No hay productos para mostrar.</p>
        ) : (
          <div className="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Nombre</th>
                  <th>Categoría</th>
                  <th>Unidad</th>
                  <th>Precio</th>
                  <th>Estado</th>
                  <th>Acciones</th>
                </tr>
              </thead>
              <tbody>
                {products.map((product) => (
                  <tr key={product.id}>
                    <td>{product.name}</td>
                    <td>{product.category || '—'}</td>
                    <td>{product.unit}</td>
                    <td>${Number(product.price).toFixed(2)}</td>
                    <td>
                      <span className={`badge ${product.active ? 'badge-success' : 'badge-muted'}`}>
                        {product.active ? 'Activo' : 'Inactivo'}
                      </span>
                    </td>
                    <td className="table-actions">
                      <button
                        type="button"
                        className="btn btn-small btn-ghost"
                        onClick={() => openEditForm(product)}
                      >
                        Editar
                      </button>
                      {product.active && (
                        <button
                          type="button"
                          className="btn btn-small btn-danger"
                          onClick={() => handleDeactivate(product)}
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
