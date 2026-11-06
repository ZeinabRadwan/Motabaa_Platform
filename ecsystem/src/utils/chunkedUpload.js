// Cloudflare (LIVE proxy) returns 524 when one request takes longer than ~100 s, so large
// files go up as several small requests that each finish well inside that window.
export const DEFAULT_CHUNK_BYTES = 4 * 1024 * 1024

const RETRYABLE_STATUSES = new Set([408, 423, 425, 429, 500, 502, 503, 504, 520, 521, 522, 523, 524])

const NON_RETRYABLE_REASONS = new Set(['storage_failed', 'chunk_invalid', 'upload_expired', 'too_many_uploads'])

// Statuses on the last chunk that mean the server is (probably) still reassembling and
// storing the file: 423 from the finalize lock, 504/524 when the proxy gave up waiting.
const FINALIZING_STATUSES = new Set([423, 504, 524])

export const isRetryableUploadError = error => {
  if (error?.code === 'ERR_CANCELED')
    return false

  const response = error?.response
  if (!response)
    return true
  if (NON_RETRYABLE_REASONS.has(response.data?.reason))
    return false

  return RETRYABLE_STATUSES.has(response.status)
}

export const createUploadId = () => {
  if (globalThis.crypto?.randomUUID)
    return globalThis.crypto.randomUUID()

  const bytes = new Uint8Array(16)

  globalThis.crypto.getRandomValues(bytes)

  return Array.from(bytes, byte => byte.toString(16).padStart(2, '0')).join('')
}

const canceledError = () => Object.assign(new Error('canceled'), { code: 'ERR_CANCELED' })

const wait = (ms, signal) => new Promise((resolve, reject) => {
  if (signal?.aborted) {
    reject(canceledError())

    return
  }

  const timer = setTimeout(resolve, ms)

  signal?.addEventListener('abort', () => {
    clearTimeout(timer)
    reject(canceledError())
  }, { once: true })
})

/**
 * Resolves once the browser reports a connection again (or after `timeoutMs`), so a phone
 * that briefly loses signal does not burn its retries while offline.
 */
const waitForConnection = (signal, timeoutMs = 120000) => new Promise((resolve, reject) => {
  if (globalThis.navigator?.onLine !== false || !globalThis.addEventListener) {
    resolve()

    return
  }

  const done = () => {
    clearTimeout(timer)
    globalThis.removeEventListener('online', done)
    resolve()
  }

  const timer = setTimeout(done, timeoutMs)

  globalThis.addEventListener('online', done, { once: true })
  signal?.addEventListener('abort', () => {
    clearTimeout(timer)
    globalThis.removeEventListener('online', done)
    reject(canceledError())
  }, { once: true })
})

const isStillFinalizing = error => FINALIZING_STATUSES.has(error?.response?.status)
  && !NON_RETRYABLE_REASONS.has(error.response.data?.reason)

/**
 * How to react to a failed chunk request, or null to give up. Mutates the attempt counters.
 */
const retryPlan = (error, isLast, attempts, options) => {
  if (options.signal?.aborted || error?.code === 'ERR_CANCELED')
    return null

  if (isLast && isStillFinalizing(error) && attempts.finalizing < options.finalizeWaits) {
    attempts.finalizing++

    return { waiting: 'finalizing', delay: options.finalizeDelayMs, needsConnection: false }
  }
  if (attempts.failures >= options.retries || !isRetryableUploadError(error))
    return null

  attempts.failures++

  return {
    waiting: error?.response ? null : 'reconnecting',
    delay: Math.min(options.maxRetryDelayMs, options.retryDelayMs * 2 ** (attempts.failures - 1)),
    needsConnection: true,
  }
}

async function sendWithRetries(send, isLast, options) {
  const attempts = { failures: 0, finalizing: 0 }

  for (;;) {
    try {
      return await send()
    }
    catch (error) {
      const plan = retryPlan(error, isLast, attempts, options)
      if (!plan)
        throw error

      if (plan.waiting)
        options.onWaiting?.(plan.waiting)
      if (plan.needsConnection)
        await waitForConnection(options.signal)
      await wait(plan.delay, options.signal)
    }
  }
}

/**
 * Sends `file` in order, one chunk at a time, retrying transient failures per chunk.
 * Re-sending a chunk the server already has is safe, so a failed upload can continue from
 * `startIndex` with the same upload id instead of starting over.
 *
 * @param {object} options
 * @param {File} options.file
 * @param {number} options.chunkBytes
 * @param {(chunk: {index: number, totalChunks: number, blob: Blob, isLast: boolean}, onChunkProgress: (loaded: number) => void) => Promise<any>} options.sendChunk
 * @param {(loadedBytes: number) => void} [options.onProgress]
 * @param {(index: number) => void} [options.onChunkSent] Called after the server accepted chunk `index`.
 * @param {(state: 'reconnecting'|'finalizing') => void} [options.onWaiting]
 * @param {AbortSignal} [options.signal]
 * @param {number} [options.startIndex]
 * @param {number} [options.retries]
 * @param {number} [options.retryDelayMs]
 * @param {number} [options.maxRetryDelayMs]
 * @param {number} [options.finalizeWaits] Extra attempts for the last chunk while the server is still storing the file.
 * @param {number} [options.finalizeDelayMs]
 * @returns {Promise<any>} Response of the last chunk.
 */
export async function uploadInChunks({
  file,
  chunkBytes,
  sendChunk,
  onProgress,
  onChunkSent,
  onWaiting,
  signal,
  startIndex = 0,
  retries = 5,
  retryDelayMs = 2000,
  maxRetryDelayMs = 30000,
  finalizeWaits = 24,
  finalizeDelayMs = 5000,
}) {
  const totalChunks = Math.max(1, Math.ceil(file.size / chunkBytes))
  const firstIndex = Math.min(Math.max(0, Math.trunc(startIndex) || 0), totalChunks - 1)
  const retry = { onWaiting, signal, retries, retryDelayMs, maxRetryDelayMs, finalizeWaits, finalizeDelayMs }
  let response = null

  onProgress?.(firstIndex * chunkBytes)

  for (let index = firstIndex; index < totalChunks; index++) {
    const start = index * chunkBytes
    const blob = file.slice(start, Math.min(file.size, start + chunkBytes))
    const chunk = { index, totalChunks, blob, isLast: index === totalChunks - 1 }

    response = await sendWithRetries(
      () => sendChunk(chunk, loaded => onProgress?.(start + Math.min(loaded, blob.size))),
      chunk.isLast,
      retry,
    )

    onChunkSent?.(index)
    onProgress?.(start + blob.size)

    // A retried chunk of an upload the server already finished returns the stored result.
    if (response?.data?.data?.media)
      return response
  }

  return response
}
