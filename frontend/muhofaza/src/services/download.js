import api from './api'

// Token Authorization header'da yuborilgani uchun oddiy <a href> ishlamaydi:
// faylni blob qilib olib, brauzerga saqlatamiz yoki yangi oynada ochamiz.

function filenameFrom(headers, fallback) {
  const disposition = headers['content-disposition'] || ''
  const utf8 = disposition.match(/filename\*=UTF-8''([^;]+)/i)
  if (utf8) return decodeURIComponent(utf8[1])
  const plain = disposition.match(/filename="?([^";]+)"?/i)
  return plain ? plain[1] : fallback
}

// Blob javobdagi xato matnini (JSON {message}) o'qib, oddiy Error qilib qaytaradi.
async function toError(e) {
  const data = e.response?.data
  if (data instanceof Blob) {
    try {
      const json = JSON.parse(await data.text())
      return new Error(json.message || 'Faylni olishda xatolik')
    } catch {
      // JSON bo'lmasa umumiy xabar
    }
  }
  return new Error(e.response?.data?.message || 'Faylni olishda xatolik')
}

export async function downloadFile(url, params = {}, fallbackName = 'fayl') {
  let res
  try {
    res = await api.get(url, { params, responseType: 'blob' })
  } catch (e) {
    throw await toError(e)
  }
  const href = URL.createObjectURL(res.data)
  const a = document.createElement('a')
  a.href = href
  a.download = filenameFrom(res.headers, fallbackName)
  document.body.appendChild(a)
  a.click()
  a.remove()
  setTimeout(() => URL.revokeObjectURL(href), 1000)
}

export async function openFile(url, params = {}) {
  // Popup bloklanmasligi uchun oynani so'rovdan oldin ochamiz.
  const win = window.open('', '_blank')
  try {
    const res = await api.get(url, { params, responseType: 'blob' })
    const href = URL.createObjectURL(res.data)
    if (win) win.location.href = href
    else window.location.href = href
    setTimeout(() => URL.revokeObjectURL(href), 60000)
  } catch (e) {
    win?.close()
    throw await toError(e)
  }
}
