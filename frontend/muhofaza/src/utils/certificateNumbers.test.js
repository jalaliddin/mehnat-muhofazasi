import { test } from 'node:test'
import assert from 'node:assert/strict'
import { fillSequential } from './certificateNumbers.js'

test('increments keeping prefix and zero padding', () => {
  assert.deepEqual(fillSequential('MM-0451', ['', '', '']), ['MM-0451', 'MM-0452', 'MM-0453'])
})

test('skips filled values and keeps them', () => {
  assert.deepEqual(fillSequential('0451', ['', '0377', '']), ['0451', '0377', '0452'])
})

test('treats whitespace-only values as empty', () => {
  assert.deepEqual(fillSequential('7', ['  ', '']), ['7', '8'])
})

test('increments the last number and grows width on overflow', () => {
  assert.deepEqual(fillSequential('2026/99', ['', '']), ['2026/99', '2026/100'])
})

test('keeps a non-digit suffix', () => {
  assert.deepEqual(fillSequential('MM-0451-A', ['', '']), ['MM-0451-A', 'MM-0452-A'])
})

test('throws without digits', () => {
  assert.throws(() => fillSequential('ABC', ['']), /Birinchi raqamda son bo'lishi kerak/)
})
