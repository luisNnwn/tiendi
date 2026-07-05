import { NavLink, Outlet } from 'react-router-dom'
import { useAuth } from '../hooks/useAuth'

export default function Layout() {
  const { supplier, user, logout } = useAuth()

  async function handleLogout() {
    await logout()
  }

  return (
    <div className="app-shell">
      <header className="app-header">
        <div className="brand">
          <span className="brand-mark">T</span>
          <div>
            <strong>Tiendi</strong>
            <span className="brand-sub">Portal proveedor</span>
          </div>
        </div>

        <nav className="app-nav">
          <NavLink to="/productos" className={({ isActive }) => (isActive ? 'active' : '')}>
            Productos
          </NavLink>
          <NavLink to="/pedidos" className={({ isActive }) => (isActive ? 'active' : '')}>
            Pedidos
          </NavLink>
          <NavLink to="/tiendas" className={({ isActive }) => (isActive ? 'active' : '')}>
            Tiendas
          </NavLink>
          <NavLink to="/analitica" className={({ isActive }) => (isActive ? 'active' : '')}>
            Analítica
          </NavLink>
        </nav>

        <div className="header-actions">
          <div className="user-chip">
            <span>{supplier?.name ?? user?.name}</span>
            <small>{user?.email}</small>
          </div>
          <button type="button" className="btn btn-ghost" onClick={handleLogout}>
            Salir
          </button>
        </div>
      </header>

      <main className="app-main">
        <Outlet />
      </main>
    </div>
  )
}
