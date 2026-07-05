# Integracion WhatsApp + n8n + Tiendi Backend

Esta guia documenta la integracion recomendada para que n8n reciba mensajes de WhatsApp y delegue la creacion de pedidos al backend Laravel.

## 1) Arquitectura recomendada

1. Cliente escribe por WhatsApp.
2. Meta envia evento a **n8n** (WhatsApp Trigger).
3. n8n normaliza datos y llama webhook de Laravel:
   - `POST /api/webhooks/n8n/whatsapp-inbound`
4. Laravel interpreta el mensaje, crea/actualiza pedidos y responde JSON con `reply`.
5. n8n envia `reply` de vuelta al cliente por WhatsApp.

> Nota: El endpoint `POST /api/chatbot/test-order` se mantiene para pruebas internas.  
> Para produccion con n8n usar `POST /api/webhooks/n8n/whatsapp-inbound`.

---

## 2) Endpoint nuevo en Laravel

### URL

`POST https://TU_BACKEND/api/webhooks/n8n/whatsapp-inbound`

### Headers

- `Content-Type: application/json`
- `X-N8N-Secret: <valor N8N_WEBHOOK_SECRET>` (opcional pero recomendado)

Si `N8N_WEBHOOK_SECRET` esta configurado en `.env`, el header es obligatorio.

### Request body

```json
{
  "phone_number": "50371234567",
  "message": "quiero 2 cajas de coca",
  "message_id": "wamid.HBgL...",
  "session_id": "50371234567",
  "source": "n8n-whatsapp"
}
```

Campos:

- `phone_number` (**required**): numero del remitente (solo numeros, con o sin `+503`).
- `message` (**required**): texto del mensaje.
- `message_id` (opcional): id unico del mensaje WhatsApp para idempotencia.
- `session_id` (opcional): trazabilidad.
- `source` (opcional): marca del canal.

### Response (exito)

```json
{
  "message": "Pedido creado correctamente.",
  "reply": "Pedido recibido correctamente....",
  "order": { "...": "..." },
  "orders": [{ "...": "..." }],
  "duplicate": false,
  "meta": {
    "message_id": "wamid.HBgL...",
    "session_id": "50371234567",
    "source": "n8n-whatsapp"
  }
}
```

### Response (mensaje duplicado)

Si llega el mismo `message_id` nuevamente, Laravel devuelve la respuesta cacheada con:

```json
{
  "...": "...",
  "duplicate": true
}
```

---

## 3) Configuracion de entorno en Laravel

Agregar en `.env`:

```env
OPENAI_API_KEY=...
OPENAI_MODEL=gpt-4.1-mini

N8N_WEBHOOK_SECRET=pon_aqui_un_secreto_largo
N8N_BASE_URL=https://hardcode275.app.n8n.cloud
N8N_AI_AGENT_PATH=/webhook/ai-agent
```

---

## 4) Implementacion en n8n (workflow de tu equipo)

Con base en su instancia:

- n8n cloud: `https://hardcode275.app.n8n.cloud`
- workflow: `hX9EyHHicMrfjoCJ`

### Nodo A: WhatsApp Trigger (ya lo tienen)

Entrada esperada desde Meta:
- numero remitente (`from`)
- texto mensaje
- id mensaje (`message.id`)

### Nodo B: Set / Function (mapeo)

Construir payload para Laravel:

```json
{
  "phone_number": "={{$json.from}}",
  "message": "={{$json.messages[0].text.body}}",
  "message_id": "={{$json.messages[0].id}}",
  "session_id": "={{$json.from}}",
  "source": "n8n-whatsapp"
}
```

> Ajustar paths segun la estructura exacta del payload del trigger.

### Nodo C: HTTP Request -> Laravel

- Method: `POST`
- URL: `https://TU_BACKEND/api/webhooks/n8n/whatsapp-inbound`
- Headers:
  - `Content-Type: application/json`
  - `X-N8N-Secret: <mismo valor que N8N_WEBHOOK_SECRET>`
- Body: JSON del Nodo B

### Nodo D: Send Message (WhatsApp)

Enviar al mismo numero:
- destino: `from`
- texto: `={{$json.reply}}`

Recomendado fallback si falta `reply`:
- `"Recibimos tu mensaje. En breve te confirmamos el pedido."`

---

## 5) Checklist de produccion

- [ ] `OPENAI_API_KEY` valida en Laravel.
- [ ] `N8N_WEBHOOK_SECRET` configurado en Laravel.
- [ ] n8n envia header `X-N8N-Secret` correcto.
- [ ] Workflow n8n publicado y activo.
- [ ] HTTPS valido en backend.
- [ ] Reintentos n8n configurados (3 intentos con backoff).
- [ ] Logging habilitado para errores 4xx/5xx.

---

## 6) Prueba rapida (cURL)

```bash
curl -X POST "https://TU_BACKEND/api/webhooks/n8n/whatsapp-inbound" \
  -H "Content-Type: application/json" \
  -H "X-N8N-Secret: TU_SECRETO" \
  -d '{
    "phone_number": "50371234567",
    "message": "quiero 1 caja de coca",
    "message_id": "test-123",
    "session_id": "50371234567",
    "source": "manual-test"
  }'
```

---

## 7) Convencion sugerida para errores en n8n

- Si Laravel responde `404`: tienda no registrada -> responder mensaje de onboarding.
- Si Laravel responde `422`: mensaje ambiguo/invalid -> pedir aclaracion al cliente.
- Si Laravel responde `502/500`: responder mensaje temporal y reintentar.

