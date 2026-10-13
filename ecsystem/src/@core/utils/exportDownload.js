import axios from '@axios'

function parseFilenameFromContentDisposition(disposition, fallback) {
  if (! disposition)
    return fallback

  const utf8Match = disposition.match(/filename\*=UTF-8''([^;\n]+)/i)
  if (utf8Match?.[1]) {
    try {
      return decodeURIComponent(utf8Match[1].trim())
    }
    catch {
      // fall through
    }
  }

  const match = disposition.match(/filename=\"?([^\";\n]+)\"?/i)
  if (match?.[1])
    return decodeURIComponent(match[1].trim())

  return fallback
}

function ensurePdfExtension(fileName, fallback = 'export.pdf') {
  const base = (fileName || fallback).trim() || fallback

  return base.toLowerCase().endsWith('.pdf') ? base : `${base.replace(/\.[^.]+$/, '')}.pdf`
}

/**
 * Trigger browser download for a Blob (PDF, Excel, etc.).
 */
export function downloadBlobFile(blob, fileName) {
  const blobUrl = window.URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = blobUrl
  link.download = fileName
  document.body.appendChild(link)
  link.click()
  link.remove()
  window.URL.revokeObjectURL(blobUrl)
}

/**
 * Validate and download a PDF from an axios response (responseType: blob).
 */
export async function downloadPdfFromAxiosResponse(response, fallbackFileName = 'export.pdf') {
  const blob = response?.data
  if (!(blob instanceof Blob))
    throw new Error('Invalid export response')

  const contentType = String(response.headers?.['content-type'] || blob.type || '').toLowerCase()

  if (contentType.includes('json') || contentType.includes('text/html')) {
    const text = await blob.text()
    let message = text
    try {
      const parsed = JSON.parse(text)
      message = parsed?.errors?.error?.[0]
        || parsed?.errors?.parent_ids?.[0]
        || parsed?.message
        || text
    }
    catch {
      // keep raw text
    }
    throw new Error(message || 'Export failed')
  }

  const signature = await blob.slice(0, 5).text()
  if (! signature.startsWith('%PDF-')) {
    const text = await blob.slice(0, 500).text()
    throw new Error(text || 'Export did not return a PDF file')
  }

  const fileName = ensurePdfExtension(
    parseFilenameFromContentDisposition(response.headers?.['content-disposition'], fallbackFileName),
    fallbackFileName,
  )

  downloadBlobFile(blob, fileName)
}

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

  if (contentType.includes('application/pdf')) {
    await downloadPdfFromAxiosResponse(response, 'export.pdf')

    return
  }

  let fileName = 'export.xlsx'
  fileName = parseFilenameFromContentDisposition(response.headers['content-disposition'], fileName)
  if (! fileName.toLowerCase().endsWith('.xlsx') && ! fileName.toLowerCase().endsWith('.xls'))
    fileName = `${fileName.replace(/\.[^.]+$/, '')}.xlsx`

  downloadBlobFile(response.data, fileName)
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
