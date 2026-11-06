// Mirrors App\Support\Hr\SaudiIdentity on the backend (which stays the source of truth).

const ARABIC_DIGITS = { '٠': '0', '١': '1', '٢': '2', '٣': '3', '٤': '4', '٥': '5', '٦': '6', '٧': '7', '٨': '8', '٩': '9' }

export const normalizeSaudiId = value => String(value ?? '')
  .replace(/[٠-٩]/g, digit => ARABIC_DIGITS[digit])
  .replace(/\s+/g, '')

export const hasValidSaudiIdChecksum = number => {
  if (!/^[12]\d{9}$/.test(number))
    return false

  let sum = 0
  for (let i = 0; i < 10; i++) {
    const digit = Number(number[i])
    if (i % 2 === 0) {
      const doubled = digit * 2

      sum += Math.floor(doubled / 10) + (doubled % 10)
    }
    else {
      sum += digit
    }
  }

  return sum % 10 === 0
}

/**
 * i18n key of the problem with the number, or null when valid. Saudi nationals hold a National ID (starts
 * with 1); other nationalities an Iqama (starts with 2).
 */
export const saudiIdError = (value, nationality) => {
  const number = normalizeSaudiId(value)
  if (!number)
    return null
  if (!hasValidSaudiIdChecksum(number))
    return 'hr.validation.id_number_invalid'
  if (nationality) {
    const expectedPrefix = String(nationality).toUpperCase() === 'SA' ? '1' : '2'
    if (number[0] !== expectedPrefix)
      return 'hr.validation.id_number_nationality_mismatch'
  }

  return null
}
