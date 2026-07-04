import { apiRequest } from './api'

export async function sendChatbotMessage(payload) {
  return apiRequest('/chatbot/test-order', {
    method: 'POST',
    body: JSON.stringify(payload),
  })
}

