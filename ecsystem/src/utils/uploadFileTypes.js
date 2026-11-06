// MIME types browsers report for the extensions the parent upload API accepts. Listing them
// in `accept` next to the extensions lets mobile pickers (iOS Photos/Camera, Android
// Gallery/Camera) offer matching media instead of only a file browser.
const EXTENSION_MIME_TYPES = {
  jpg: ['image/jpeg'],
  jpeg: ['image/jpeg'],
  png: ['image/png'],
  gif: ['image/gif'],
  webp: ['image/webp'],
  mp4: ['video/mp4'],
  m4v: ['video/x-m4v'],
  mov: ['video/quicktime'],
  webm: ['video/webm'],
  ogg: ['video/ogg'],
  ogv: ['video/ogg'],
  '3gp': ['video/3gpp'],
  '3g2': ['video/3gpp2'],
  mp3: ['audio/mpeg'],
  wav: ['audio/wav', 'audio/x-wav'],
  m4a: ['audio/mp4', 'audio/x-m4a'],
  aac: ['audio/aac'],
  oga: ['audio/ogg'],
  pdf: ['application/pdf'],
  doc: ['application/msword'],
  docx: ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
  xls: ['application/vnd.ms-excel'],
  xlsx: ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
  ppt: ['application/vnd.ms-powerpoint'],
  pptx: ['application/vnd.openxmlformats-officedocument.presentationml.presentation'],
  odt: ['application/vnd.oasis.opendocument.text'],
  ods: ['application/vnd.oasis.opendocument.spreadsheet'],
  odp: ['application/vnd.oasis.opendocument.presentation'],
  rtf: ['application/rtf', 'text/rtf'],
  txt: ['text/plain'],
  csv: ['text/csv'],
}

const EXTENSION_PATTERN = /\.([a-z0-9]{1,5})$/i

export const fileExtension = name => {
  const match = EXTENSION_PATTERN.exec(String(name || '').trim())

  return match ? match[1].toLowerCase() : ''
}

export const acceptAttribute = extensions => [...new Set(
  extensions.flatMap(ext => [`.${ext}`, ...(EXTENSION_MIME_TYPES[ext] ?? [])]),
)].join(',')

export const extensionForMimeType = (mimeType, extensions) => {
  const type = String(mimeType || '').toLowerCase()
  if (!type)
    return ''

  return extensions.find(ext => (EXTENSION_MIME_TYPES[ext] ?? []).includes(type)) ?? ''
}

/**
 * Name sent to the API for `file`. Some Android pickers hand over names without an
 * extension (e.g. "1000012345"); the extension is then taken from the reported MIME type.
 * The server still checks the real content, so this only affects the filename.
 */
export const uploadFileName = (file, extensions) => {
  const name = String(file?.name || '').trim()
  if (fileExtension(name))
    return name

  const extension = extensionForMimeType(file?.type, extensions)

  return extension ? `${name || 'upload'}.${extension}` : name
}
