const COUNTRY_CODE = '503'

export function toLocalPhoneInput(storedPhone) {
  if (!storedPhone) return ''

  const digits = String(storedPhone).replace(/\D/g, '')

  if (digits.startsWith(COUNTRY_CODE)) {
    return digits.slice(3)
  }

  return digits
}

export function formatLocalPhone(value) {
  const digits = String(value).replace(/\D/g, '').slice(0, 8)

  if (digits.length <= 4) {
    return digits
  }

  return `${digits.slice(0, 4)}-${digits.slice(4)}`
}

export function buildPhonePayload(localValue) {
  const digits = String(localValue).replace(/\D/g, '')

  return {
    phone_number: digits,
  }
}

export function formatDisplayPhone(storedPhone) {
  const local = toLocalPhoneInput(storedPhone)

  if (local.length !== 8) {
    return storedPhone ?? ''
  }

  return `+${COUNTRY_CODE} ${formatLocalPhone(local)}`
}
