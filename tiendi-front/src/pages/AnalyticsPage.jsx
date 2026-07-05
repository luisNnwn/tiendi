import { useEffect, useMemo, useState } from 'react'
import { ApiError } from '../services/api'
import * as analyticsService from '../services/analytics'

function monthRange(date) {
  const start = new Date(date.getFullYear(), date.getMonth(), 1)
  const end = new Date(date.getFullYear(), date.getMonth() + 1, 0)
  const toISO = (value) => value.toISOString().slice(0, 10)
  return { from: toISO(start), to: toISO(end) }
}

function formatGrowth(value) {
  if (value === null || value === undefined) return '0.00%'
  const number = Number(value)
  const sign = number > 0 ? '+' : ''
  return `${sign}${number.toFixed(2)}%`
}

function growthClass(value) {
  const number = Number(value ?? 0)
  if (number > 0) return 'analytics-growth-positive'
  if (number < 0) return 'analytics-growth-negative'
  return 'analytics-growth-neutral'
}

export default function AnalyticsPage() {
  const [selectedMonth, setSelectedMonth] = useState(() => {
    const now = new Date()
    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`
  })
  const [overview, setOverview] = useState(null)
  const [insights, setInsights] = useState([])
  const [loading, setLoading] = useState(true)
  const [loadingInsights, setLoadingInsights] = useState(false)
  const [error, setError] = useState('')

  const range = useMemo(() => {
    const [year, month] = selectedMonth.split('-').map(Number)
    return monthRange(new Date(year, month - 1, 1))
  }, [selectedMonth])

  async function loadOverview() {
    setLoading(true)
    setError('')
    try {
      const data = await analyticsService.fetchOverview(range)
      setOverview(data)
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'No se pudo cargar la analítica.')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    loadOverview()
  }, [selectedMonth])

  async function loadInsights() {
    setLoadingInsights(true)
    setError('')
    try {
      const data = await analyticsService.fetchInsights(range)
      setInsights(data.insights ?? [])
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'No se pudieron generar insights.')
    } finally {
      setLoadingInsights(false)
    }
  }

  const kpis = overview?.kpis ?? {}
  const unsoldStores = overview?.unsold_stores ?? []
  const topProducts = overview?.top_products ?? []
  const topStores = overview?.top_stores ?? []
  const salesByCategory = overview?.sales_by_category ?? []
  const monthlySales = overview?.monthly_sales ?? []
  const statusRatio = kpis.status_ratio ?? {}

  return (
    <div className="products-page">
      <div className="page-header">
        <div>
          <h1>Analítica</h1>
          <p className="muted">Resumen de ventas del proveedor para el periodo seleccionado.</p>
        </div>

        <div className="page-header-actions">
          <label>
            Mes
            <input
              type="month"
              value={selectedMonth}
              onChange={(event) => setSelectedMonth(event.target.value)}
            />
          </label>
          <button type="button" className="btn btn-ghost" onClick={loadOverview}>
            Recargar
          </button>
          <button type="button" className="btn btn-primary" onClick={loadInsights} disabled={loadingInsights}>
            {loadingInsights ? 'Generando…' : 'Generar insights IA'}
          </button>
        </div>
      </div>

      {error && <div className="alert alert-error">{error}</div>}

      {loading ? (
        <div className="card">
          <p className="muted">Cargando analítica…</p>
        </div>
      ) : (
        <>
          <div className="analytics-kpis">
            <article className="card analytics-kpi">
              <p className="muted">Ingresos</p>
              <h2>${kpis.revenue ?? '0.00'}</h2>
              <small className={growthClass(kpis.revenue_growth_pct)}>
                {formatGrowth(kpis.revenue_growth_pct)} vs periodo anterior
              </small>
            </article>
            <article className="card analytics-kpi">
              <p className="muted">Pedidos</p>
              <h2>{kpis.orders_count ?? 0}</h2>
              <small className={growthClass(kpis.orders_growth_pct)}>
                {formatGrowth(kpis.orders_growth_pct)} vs periodo anterior
              </small>
            </article>
            <article className="card analytics-kpi">
              <p className="muted">Ticket promedio</p>
              <h2>${kpis.avg_ticket ?? '0.00'}</h2>
              <small className={growthClass(kpis.avg_ticket_growth_pct)}>
                {formatGrowth(kpis.avg_ticket_growth_pct)} vs periodo anterior
              </small>
            </article>
            <article className="card analytics-kpi">
              <p className="muted">Clientes actuales</p>
              <h2>{kpis.clients_current ?? '0/0'}</h2>
              <small className={growthClass(kpis.clients_growth_pct)}>
                {formatGrowth(kpis.clients_growth_pct)} vs periodo anterior
              </small>
            </article>
            <article className="card analytics-kpi">
              <p className="muted">Cobertura catálogo</p>
              <h2>{Number(kpis.catalog_coverage_pct ?? 0).toFixed(2)}%</h2>
              <small className="muted">
                {kpis.products_sold ?? 0} de {kpis.products_active ?? 0} productos activos vendidos
              </small>
            </article>
            <article className="card analytics-kpi">
              <p className="muted">Tiendas sin venta</p>
              <h2>{kpis.unsold_stores_count ?? 0}</h2>
              <small className="muted">en el periodo seleccionado</small>
            </article>
          </div>

          <div className="card analytics-section">
            <h2>Tendencia últimos 6 meses</h2>
            {monthlySales.length === 0 ? (
              <p className="muted">Sin datos históricos para tendencia.</p>
            ) : (
              <div className="table-wrap">
                <table>
                  <thead>
                    <tr>
                      <th>Mes</th>
                      <th>Pedidos</th>
                      <th>Ventas</th>
                    </tr>
                  </thead>
                  <tbody>
                    {monthlySales.map((item) => (
                      <tr key={item.month}>
                        <td>{item.month}</td>
                        <td>{item.orders_count}</td>
                        <td>${item.sales}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>

          <div className="card analytics-section">
            <h2>Ratio de estados</h2>
            <div className="table-wrap">
              <table>
                <thead>
                  <tr>
                    <th>Estado</th>
                    <th>Cantidad</th>
                    <th>Porcentaje</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td>Pendiente</td>
                    <td>{statusRatio.pending?.count ?? 0}</td>
                    <td>{Number(statusRatio.pending?.pct ?? 0).toFixed(2)}%</td>
                  </tr>
                  <tr>
                    <td>Confirmado</td>
                    <td>{statusRatio.confirmed?.count ?? 0}</td>
                    <td>{Number(statusRatio.confirmed?.pct ?? 0).toFixed(2)}%</td>
                  </tr>
                  <tr>
                    <td>Entregado</td>
                    <td>{statusRatio.delivered?.count ?? 0}</td>
                    <td>{Number(statusRatio.delivered?.pct ?? 0).toFixed(2)}%</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div className="card analytics-section">
            <h2>Top productos</h2>
            {topProducts.length === 0 ? (
              <p className="muted">No hay ventas en el periodo.</p>
            ) : (
              <div className="table-wrap">
                <table>
                  <thead>
                    <tr>
                      <th>Producto</th>
                      <th>Cantidad</th>
                      <th>Venta</th>
                    </tr>
                  </thead>
                  <tbody>
                    {topProducts.map((product) => (
                      <tr key={product.id}>
                        <td>{product.name}</td>
                        <td>{product.quantity}</td>
                        <td>${product.sales}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>

          <div className="card analytics-section">
            <h2>Top tiendas por venta</h2>
            {topStores.length === 0 ? (
              <p className="muted">No hay ventas por tienda en este periodo.</p>
            ) : (
              <div className="table-wrap">
                <table>
                  <thead>
                    <tr>
                      <th>Tienda</th>
                      <th>Pedidos</th>
                      <th>Venta</th>
                    </tr>
                  </thead>
                  <tbody>
                    {topStores.map((store) => (
                      <tr key={store.id}>
                        <td>{store.name}</td>
                        <td>{store.orders_count}</td>
                        <td>${store.sales}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>

          <div className="card analytics-section">
            <h2>Mix de ventas por categoría</h2>
            {salesByCategory.length === 0 ? (
              <p className="muted">No hay ventas por categoría en el periodo.</p>
            ) : (
              <div className="table-wrap">
                <table>
                  <thead>
                    <tr>
                      <th>Categoría</th>
                      <th>Ventas</th>
                    </tr>
                  </thead>
                  <tbody>
                    {salesByCategory.map((category) => (
                      <tr key={category.category}>
                        <td>{category.category}</td>
                        <td>${category.sales}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>

          <div className="card analytics-section">
            <h2>Tiendas sin venta en el periodo</h2>
            {unsoldStores.length === 0 ? (
              <p className="muted">Todas las tiendas activas compraron en este periodo.</p>
            ) : (
              <ul className="analytics-unsold-list">
                {unsoldStores.map((store) => (
                  <li key={store.id}>
                    <strong>{store.name}</strong>
                    <span className="muted">{store.phone_display ?? store.phone_number}</span>
                  </li>
                ))}
              </ul>
            )}
          </div>

          <div className="card analytics-section">
            <h2>Insights IA</h2>
            {insights.length === 0 ? (
              <p className="muted">Genera insights para obtener recomendaciones.</p>
            ) : (
              <ul className="analytics-insights-list">
                {insights.map((item, index) => (
                  <li key={`${index}-${item}`}>{item}</li>
                ))}
              </ul>
            )}
          </div>
        </>
      )}
    </div>
  )
}

