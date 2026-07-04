import { useState } from 'react'
import { ApiError } from '../services/api'
import { sendChatbotMessage } from '../services/chatbot'

export default function ChatbotTestPage() {
  const [phoneNumber, setPhoneNumber] = useState('')
  const [message, setMessage] = useState('')
  const [sending, setSending] = useState(false)
  const [history, setHistory] = useState([])

  async function handleSubmit(event) {
    event.preventDefault()
    if (!phoneNumber.trim() || !message.trim()) return

    const outbound = message.trim()
    setHistory((current) => [...current, { role: 'user', text: outbound }])
    setMessage('')
    setSending(true)

    try {
      const response = await sendChatbotMessage({
        phone_number: phoneNumber,
        message: outbound,
      })

      const orders = response?.orders?.data ?? []
      const orderSummary =
        orders.length > 0
          ? orders
              .map((order) => `Order ID: ${order.id} | Total: $${order.total} | Estado: ${order.status}`)
              .join('\n')
          : `Order ID: ${response?.order?.id ?? '-'} | Total: $${response?.order?.total ?? '0.00'}`

      const summary = `${response.reply}\n\n${orderSummary}`

      setHistory((current) => [
        ...current,
        { role: 'assistant', text: summary },
      ])
    } catch (error) {
      let text = 'No se pudo procesar el pedido.'
      if (error instanceof ApiError) {
        if (Array.isArray(error.data?.errors) && error.data.errors.length) {
          text = `${error.data.message ?? 'Pedido inválido.'}\n- ${error.data.errors.join('\n- ')}`
        } else {
          text = error.data?.message ?? error.message
        }
      }
      setHistory((current) => [...current, { role: 'assistant', text }])
    } finally {
      setSending(false)
    }
  }

  return (
    <div className="chatbot-page">
      <div className="card chatbot-card">
        <h1>Chatbot de Pedidos (Prueba)</h1>
        <p className="muted">
          Simula el flujo WhatsApp + OpenAI sin login.
        </p>

        <form className="chatbot-form" onSubmit={handleSubmit}>
          <label>
            Número de tienda (El Salvador)
            <div className="phone-input">
              <span className="phone-prefix">+503</span>
              <input
                value={phoneNumber}
                onChange={(event) => setPhoneNumber(event.target.value)}
                placeholder="7123-4567"
                inputMode="numeric"
                required
              />
            </div>
          </label>

          <label>
            Mensaje
            <textarea
              value={message}
              onChange={(event) => setMessage(event.target.value)}
              placeholder="quiero 2 cajas de coca y 3 paquetes de galletas"
              rows={3}
              required
            />
          </label>

          <button className="btn btn-primary" type="submit" disabled={sending}>
            {sending ? 'Procesando…' : 'Enviar al chatbot'}
          </button>
        </form>
      </div>

      <div className="card chat-history-card">
        <h2>Conversación</h2>
        {history.length === 0 ? (
          <p className="muted">Aún no hay mensajes.</p>
        ) : (
          <div className="chat-history">
            {history.map((entry, index) => (
              <div
                key={`${entry.role}-${index}`}
                className={`chat-bubble ${entry.role === 'user' ? 'bubble-user' : 'bubble-assistant'}`}
              >
                <strong>{entry.role === 'user' ? 'Tienda' : 'Bot'}</strong>
                <pre>{entry.text}</pre>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  )
}

