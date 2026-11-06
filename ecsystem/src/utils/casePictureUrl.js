/** Resolve case/beneficiary avatar URL from list or detail API shapes. */
export function casePictureUrl(picture) {
  if (!picture)
    return null
  if (typeof picture === 'string')
    return picture

  return picture.file_url ?? picture.url ?? null
}
