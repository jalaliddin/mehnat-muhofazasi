/**
 * Bo'sh kataklarni birinchi raqamdan boshlab ketma-ket to'ldiradi: "MM-0451" → "MM-0452", ...
 * Raqamdagi oxirgi son oshiriladi, prefiks/suffiks va boshidagi nollar saqlanadi.
 * To'ldirilgan qiymatlar o'zgarmaydi (oldindan raqamlangan blankalar uchun).
 *
 * @param {string} first
 * @param {string[]} values
 * @returns {string[]}
 */
export function fillSequential(first, values) {
  const match = String(first).trim().match(/^(.*?)(\d+)(\D*)$/)
  if (!match) {
    throw new Error("Birinchi raqamda son bo'lishi kerak")
  }

  const [, prefix, digits, suffix] = match
  let next = Number(digits)

  return values.map(value => {
    if (String(value ?? '').trim() !== '') return value
    const number = `${prefix}${String(next).padStart(digits.length, '0')}${suffix}`
    next += 1
    return number
  })
}
