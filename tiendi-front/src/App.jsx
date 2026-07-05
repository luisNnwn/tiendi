import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom'
import Layout from './components/Layout'
import ProtectedRoute from './components/ProtectedRoute'
import { AuthProvider } from './context/AuthProvider'
import AnalyticsPage from './pages/AnalyticsPage'
import ChatbotTestPage from './pages/ChatbotTestPage'
import LoginPage from './pages/LoginPage'
import OrdersPage from './pages/OrdersPage'
import ProductsPage from './pages/ProductsPage'
import SignupPage from './pages/SignupPage'
import StoreSignupPage from './pages/StoreSignupPage'
import StoresPage from './pages/StoresPage'
import './App.css'

export default function App() {
  return (
    <AuthProvider>
      <BrowserRouter>
        <Routes>
          <Route path="/login" element={<LoginPage />} />
          <Route path="/signup" element={<SignupPage />} />
          <Route path="/registro-tienda" element={<StoreSignupPage />} />
          <Route path="/chatbot" element={<ChatbotTestPage />} />

          <Route element={<ProtectedRoute />}>
            <Route element={<Layout />}>
              <Route index element={<Navigate to="/productos" replace />} />
              <Route path="/productos" element={<ProductsPage />} />
              <Route path="/pedidos" element={<OrdersPage />} />
              <Route path="/tiendas" element={<StoresPage />} />
              <Route path="/analitica" element={<AnalyticsPage />} />
            </Route>
          </Route>

          <Route path="*" element={<Navigate to="/productos" replace />} />
        </Routes>
      </BrowserRouter>
    </AuthProvider>
  )
}
