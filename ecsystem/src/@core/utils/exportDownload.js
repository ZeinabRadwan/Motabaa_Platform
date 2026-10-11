import axios from '@axios'

/**
 * Normalize export download paths from API (legacy absolute URLs or /api/... paths).
 */
export function normalizeExportDownloadPath(url) {
  if (! url)
    return ''

  let path = String(url).trim()
  const apiBase = (import.meta.env.VITE_APP_API_URL || '').replace(/\/$/, '')

  if (path.startsWith('http://') || path.startsWith('https://')) {
    try {
      const parsed = new URL(path)
      path = `${parsed.pathname}${parsed.search}`

      if (apiBase) {
        const api = new URL(apiBase)
        if (parsed.hostname !== api.hostname) {
          return `${apiBase}${path.startsWith('/api') ? path.replace(/^\/api/, '') : path}`
        }
      }
    }
    catch {
      return path
    }
  }

  if (path.startsWith('/api/'))
    path = path.replace(/^\/api/, '')

  if (! path.startsWith('/'))
    path = `/${path}`

  if (apiBase && path.startsWith('/'))
    return `${apiBase}${path}`

  return path
}

/**
 * Download export file through the API client (auth + correct API host).
 */
export async function downloadExportFile(urlFromServer) {
  const apiBase = (import.meta.env.VITE_APP_API_URL || '').replace(/\/$/, '')
  let requestPath = String(urlFromServer || '').trim()

  if (requestPath.startsWith('http://') || requestPath.startsWith('https://')) {
    try {
      const parsed = new URL(requestPath)
      requestPath = `${parsed.pathname}${parsed.search}`
    }
    catch {
      throw new Error('Invalid export download URL')
    }
  }

  if (requestPath.startsWith('/api/'))
    requestPath = requestPath.replace(/^\/api/, '')

  if (! requestPath.startsWith('/'))
    requestPath = `/${requestPath}`

  const response = await axios.get(requestPath, { responseType: 'blob' })
  const contentType = String(response.headers['content-type'] || '')

  if (contentType.includes('application/json') || contentType.includes('text/json')) {
    const message = await response.data.text()
    throw new Error(message || 'Export download failed')
  }

  let fileName = 'export.xlsx'
  const disposition = response.headers['content-disposition']
  const match = disposition?.match(/filename=\"?([^\";]+)\"?/i)
  if (match?.[1])
    fileName = decodeURIComponent(match[1].trim())

  const blobUrl = window.URL.createObjectURL(response.data)
  const link = document.createElement('a')
  link.href = blobUrl
  link.download = fileName
  document.body.appendChild(link)
  link.click()
  link.remove()
  window.URL.revokeObjectURL(blobUrl)
}

/**
 * @deprecated Prefer downloadExportFile for reliable downloads.
 */
export function openExportDownload(url) {
  const target = normalizeExportDownloadPath(url)
  if (! target)
    return

  window.open(target, '_blank', 'noopener')
}
