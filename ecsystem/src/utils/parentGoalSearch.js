const ARABIC_DIACRITICS = /[\u064B-\u065F\u0670\u06D6-\u06ED]/g
const TATWEEL = /\u0640/g
const ARABIC_INDIC_DIGITS = /[\u0660-\u0669\u06F0-\u06F9]/g

const LETTER_VARIANTS = {
  'أ': 'ا',
  'إ': 'ا',
  'آ': 'ا',
  'ٱ': 'ا',
  'ى': 'ي',
  'ئ': 'ي',
  'ؤ': 'و',
  'ة': 'ه',
}

const LETTER_VARIANT_PATTERN = new RegExp(`[${Object.keys(LETTER_VARIANTS).join('')}]`, 'g')

/**
 * Lowercases and folds the spelling differences parents commonly type differently
 * (Arabic diacritics, tatweel, hamza/alef forms, taa marbuta, alef maqsura, Arabic digits).
 */
export const normalizeSearchText = value => String(value ?? '')
  .toLocaleLowerCase()
  .normalize('NFKC')
  .replace(ARABIC_DIACRITICS, '')
  .replace(TATWEEL, '')
  .replace(LETTER_VARIANT_PATTERN, letter => LETTER_VARIANTS[letter])
  .replace(ARABIC_INDIC_DIGITS, digit => String(digit.charCodeAt(0) & 0xF))

export const searchWords = query => [...new Set(
  normalizeSearchText(query)
    .split(/[\s,،؛;:.!?؟()[\]{}"'«»\-_/\\]+/u)
    .filter(Boolean),
)]

/** Texts of the goal title cell as parents see it (title plus translated standard/generalization). */
export const goalTitleTexts = (goal, t) => [
  goal?.title,
  goal?.standard ? t(goal.standard) : null,
  goal?.generalization ? t(goal.generalization) : null,
]

/**
 * Keeps the items whose text contains at least one of the words typed in `query`.
 * An empty query keeps every item.
 *
 * @template T
 * @param {T[]} items
 * @param {string} query
 * @param {(item: T) => Array<string|null|undefined>} textsOf Visible texts of one item.
 * @returns {T[]}
 */
export const filterByAnyWord = (items, query, textsOf) => {
  const words = searchWords(query)
  if (!words.length)
    return items

  return items.filter(item => {
    const text = normalizeSearchText(textsOf(item).filter(Boolean).join(' '))

    return words.some(word => text.includes(word))
  })
}
