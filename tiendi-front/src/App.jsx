import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom'
import Layout from './components/Layout'
import ProtectedRoute from './components/ProtectedRoute'
import { AuthProvider } from './context/AuthProvider'
import ChatbotTestPage from './pages/ChatbotTestPage'
import LoginPage from './pages/LoginPage'
import ProductsPage from './pages/ProductsPage'
import StoresPage from './pages/StoresPage'
import './App.css'

export default function App() {
  return (
    <AuthProvider>
      <BrowserRouter>
        <Routes>
          <Route path="/login" element={<LoginPage />} />
          <Route path="/chatbot" element={<ChatbotTestPage />} />

          <Route element={<ProtectedRoute />}>
            <Route element={<Layout />}>
              <Route index element={<Navigate to="/productos" replace />} />
              <Route path="/productos" element={<ProductsPage />} />
              <Route path="/tiendas" element={<StoresPage />} />
            </Route>
          </Route>

          <Route path="*" element={<Navigate to="/productos" replace />} />
        </Routes>
      </BrowserRouter>
    </AuthProvider>
  )
}
