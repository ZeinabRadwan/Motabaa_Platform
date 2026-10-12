import i18n from '@/plugins/i18n/index.js'
import { isEmpty, isEmptyArray, isNullOrUndefined } from './index'


// 👉 Required Validator
export const requiredValidator = value => {
  if (isNullOrUndefined(value) || isEmptyArray(value) || value === false)
    return i18n.global.t('This field is required')
  
  return !!String(value).trim().length || i18n.global.t('This field is required')
}

export const requiredValidatorIf = (value, noCheck) => {
  if(noCheck == true){
    return true;
  }
  if (isNullOrUndefined(value) || isEmptyArray(value) || value === false)
    return i18n.global.t('This field is required')
  
  return !!String(value).trim().length || i18n.global.t('This field is required')
}

const emailFormatRegex = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/

// 👉 Email Validator
export const emailValidator = value => {
  if (isEmpty(value))
    return true
  if (Array.isArray(value))
    return value.every(val => emailFormatRegex.test(String(val))) || i18n.global.t('validtion.email')
  
  return emailFormatRegex.test(String(value)) || i18n.global.t('validtion.email')
}

/** Login identifier required (email or phone). */
export const loginIdentifierRequiredValidator = value => {
  if (isNullOrUndefined(value) || isEmptyArray(value))
    return i18n.global.t('auth.login.identifier_required')

  return !!String(value).trim().length || i18n.global.t('auth.login.identifier_required')
}

/** Login password required. */
export const loginPasswordRequiredValidator = value => {
  if (isNullOrUndefined(value) || isEmptyArray(value))
    return i18n.global.t('auth.login.password_required')

  return !!String(value).length || i18n.global.t('auth.login.password_required')
}

/** Login field: valid email or phone (with optional +, spaces, local or international digits). */
export const loginEmailOrPhoneValidator = value => {
  if (isEmpty(value))
    return true

  const trimmed = String(value).trim()
  if (emailFormatRegex.test(trimmed))
    return true

  if (!/^[\d+\s().-]+$/.test(trimmed))
    return i18n.global.t('auth.login.invalid_identifier')

  const digits = trimmed.replace(/\D/g, '')
  if (digits.length >= 9 && digits.length <= 15)
    return true

  return i18n.global.t('auth.login.invalid_identifier')
}

// 👉 Password Validator
export const passwordValidator = password => {
  const regExp = /(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[!@#$%&*()]).{8,}/
  const validPassword = regExp.test(password)
  
  return (
    // eslint-disable-next-line operator-linebreak
    validPassword || i18n.global.t('validtion.password'))
}

export const passwordValidatorIf = (password, noCheck = false) => {
  if(noCheck == true){
    return true;
  }
  const regExp = /(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[!@#$%&*()]).{8,}/
  const validPassword = regExp.test(password)
  
  return (
    // eslint-disable-next-line operator-linebreak
    validPassword || i18n.global.t('validtion.password'))
}

// 👉 Confirm Password Validator
export const confirmedValidator = (value, target) => value === target || i18n.global.t('validtion.passwordConfirm')

// 👉 Between Validator
export const betweenValidator = (value, min, max) => {
  const valueAsNumber = String(value).length

  if(Number(min) <= valueAsNumber && Number(max) >= valueAsNumber)
    return true;
  
  return (Number(min) == Number(max)) ? i18n.global.t('validtion.numberEqual', { length: max }) : i18n.global.t('validtion.numberBetween', { min: min, max: max })
}

// 👉 Minimum Validator
export const minimumValidator = (value, min) => {
  if(Number(min) <= value)
    return true;
  
  return i18n.global.t('validtion.minimum_number', { min: min })
}

// 👉 Integer Validator
export const integerValidator = value => {
  if (isEmpty(value))
    return true
  if (Array.isArray(value))
    return value.every(val => /^-?[0-9]+$/.test(String(val))) || i18n.global.t('validtion.integer')
  
  return /^-?[0-9]+$/.test(String(value)) || i18n.global.t('validtion.integer')
}

// 👉 Regex Validator
export const regexValidator = (value, regex) => {
  if (isEmpty(value))
    return true
  let regeX = regex
  if (typeof regeX === 'string')
    regeX = new RegExp(regeX)
  if (Array.isArray(value))
    return value.every(val => regexValidator(val, regeX))
  
  return regeX.test(String(value)) || 'The Regex field format is invalid'
}

// 👉 Alpha Validator
export const alphaValidator = value => {
  if (isEmpty(value))
    return true
  
  return /^[A-Z]*$/i.test(String(value)) || 'The Alpha field may only contain alphabetic characters'
}

// 👉 URL Validator
export const urlValidator = value => {
  if (isEmpty(value))
    return true
  const re = /^(http[s]?:\/\/){0,1}(www\.){0,1}[a-zA-Z0-9\.\-]+\.[a-zA-Z]{2,5}[\.]{0,1}/
  
  return re.test(String(value)) || 'URL is invalid'
}

// 👉 Length Validator
export const lengthValidator = (value, length) => {
  if (isEmpty(value))
    return true

    return String(value).length >= length || i18n.global.t('validtion.minCharacter', { length: length })
}

// 👉 Alpha-dash Validator
export const alphaDashValidator = value => {
  if (isEmpty(value))
    return true
  const valueAsString = String(value)
  
  return /^[0-9A-Z_-]*$/i.test(valueAsString) || 'All Character are not valid'
}

export const dateBetweenValidator = (value, start, end, message) => {
  if(start > value || end < value)
    return message
  
  return true
}
