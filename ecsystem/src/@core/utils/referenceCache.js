const TTL_MS = 3 * 60 * 1000
const LONG_TTL_MS = 5 * 60 * 1000

const GET_RULES = [
  { pattern: /^\/disabilities\/?$/, group: 'disabilities', ttl: TTL_MS },
  { pattern: /^\/services\/?$/, group: 'services', ttl: TTL_MS },
  { pattern: /^\/roles\/get\/all\/?$/, group: 'roles', ttl: TTL_MS },
  { pattern: /^\/centers\/packages\/?$/, group: 'packages', ttl: LONG_TTL_MS },
  { pattern: /^\/centers\/items\/?$/, group: 'centers', ttl: TTL_MS },
  { pattern: /^\/terms\/items\/?$/, group: 'terms', ttl: TTL_MS },
  { pattern: /^\/terms\/current\/?$/, group: 'terms', ttl: TTL_MS },
  { pattern: /^\/logs\/types\/?$/, group: 'logs', ttl: LONG_TTL_MS },
  { pattern: /^\/logs\/models\/?$/, group: 'logs', ttl: TTL_MS },
  { pattern: /^\/assessments\/evaluation-methods\/?$/, group: 'evaluation-methods', ttl: TTL_MS },
]

const MUTATION_INVALIDATIONS = [
  { pattern: /\/disabilities/, groups: ['disabilities'] },
  { pattern: /\/terms/, groups: ['terms'] },
  { pattern: /\/roles/, groups: ['roles'] },
  { pattern: /\/centers/, groups: ['centers', 'packages'] },
  { pattern: /\/services/, groups: ['services'] },
  { pattern: /\/assessments/, groups: ['evaluation-methods'] },
]

const entries = new Map()
const inflight = new Map()

const requestPath = config => {
  const raw = config.url || ''
  try {
    return new URL(raw, config.baseURL || 'http://local.invalid').pathname
  }
  catch (e) {
    return String(raw).split('?')[0]
  }
}

const matchRule = config => {
  if ((config.method || 'get').toLowerCase() !== 'get' || config.skipCache || config.cache === false)
    return null

  if (config.responseType && config.responseType !== 'json')
    return null

  const path = requestPath(config)

  return GET_RULES.find(rule => rule.pattern.test(path)) || null
}

const identityParts = config => {
  let userId = 'anon'
  try {
    userId = JSON.parse(localStorage.getItem('userData') || '{}').id || 'anon'
  }
  catch (e) {
    userId = 'anon'
  }

  const locale = config.headers?.['Accept-Language'] || ''
  const center = localStorage.getItem('center') || ''
  const query = String(config.url || '').split('?')[1] || ''
  const params = config.params ? JSON.stringify(config.params) : ''

  return `${userId}|${center}|${locale}|${query}|${params}`
}

export const referenceCacheKey = config => {
  const rule = matchRule(config)
  if (!rule)
    return null

  return `${rule.group}|${requestPath(config)}|${identityParts(config)}`
}

const cloneData = data => {
  if (data === undefined || data === null)
    return data

  try {
    return JSON.parse(JSON.stringify(data))
  }
  catch (e) {
    return data
  }
}

const readEntry = key => {
  const entry = entries.get(key)
  if (!entry)
    return null

  if (Date.now() > entry.expiresAt) {
    entries.delete(key)

    return null
  }

  return entry
}

export const getCachedReferenceResponse = config => {
  const key = referenceCacheKey(config)
  if (!key)
    return null

  const entry = readEntry(key)
  if (!entry)
    return null

  return {
    data: cloneData(entry.data),
    status: entry.status,
    statusText: 'OK',
    headers: entry.headers || {},
    config,
    request: { fromReferenceCache: true },
  }
}

export const getInflightReferenceRequest = config => {
  const key = referenceCacheKey(config)
  if (!key)
    return null

  return inflight.get(key) || null
}

export const trackInflightReferenceRequest = (config, promise) => {
  const key = referenceCacheKey(config)
  if (!key)
    return promise

  inflight.set(key, promise)

  return promise.finally(() => {
    if (inflight.get(key) === promise)
      inflight.delete(key)
  })
}

export const storeReferenceResponse = (config, response) => {
  const rule = matchRule(config)
  const key = referenceCacheKey(config)
  if (!rule || !key || !response || response.status < 200 || response.status >= 300)
    return

  entries.set(key, {
    group: rule.group,
    data: cloneData(response.data),
    status: response.status,
    headers: response.headers,
    expiresAt: Date.now() + rule.ttl,
  })
}

export const invalidateReferenceGroups = (...groups) => {
  const wanted = new Set(groups.filter(Boolean))
  if (!wanted.size)
    return

  for (const [key, entry] of entries.entries()) {
    if (wanted.has(entry.group))
      entries.delete(key)
  }
}

export const invalidateReferenceForRequest = config => {
  const method = (config.method || 'get').toLowerCase()
  if (method === 'get' || method === 'head' || method === 'options')
    return

  const path = requestPath(config)
  const groups = new Set()

  MUTATION_INVALIDATIONS.forEach(rule => {
    if (rule.pattern.test(path))
      rule.groups.forEach(group => groups.add(group))
  })

  invalidateReferenceGroups(...groups)
}

export const clearReferenceCache = () => {
  entries.clear()
  inflight.clear()
}

export const REFERENCE_CACHE_TTL_MS = TTL_MS
