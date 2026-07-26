import type { PlanningAppMeta } from '../types'

export const HIGH_VALUE_THRESHOLD = 500000

const UK_POSTCODE_REGEX = /\b([A-Z]{1,2}\d[A-Z\d]?\s*\d[A-Z]{2})\b/gi

export function extractPostcode(address: string): string | null {
  if (!address) return null
  const matches = address.match(UK_POSTCODE_REGEX)
  if (!matches || matches.length === 0) return null
  return matches[matches.length - 1].toUpperCase().replace(/\s+/g, ' ').trim()
}

const ADDRESS_ABBREVIATIONS = new Set([
  'road', 'street', 'lane', 'avenue', 'drive', 'close', 'way', 'place',
  'square', 'court', 'terrace', 'crescent', 'hill', 'view', 'park',
  'gardens', 'mews', 'walk', 'grove', 'rise', 'vale', 'end',
])

export function titleCaseAddress(s: string): string {
  if (!s) return ''
  return s
    .toLowerCase()
    .split(/\s+/)
    .map((word) => {
      if (ADDRESS_ABBREVIATIONS.has(word)) {
        return word.charAt(0).toUpperCase() + word.slice(1)
      }
      if (/^\d/.test(word)) return word
      return word.charAt(0).toUpperCase() + word.slice(1)
    })
    .join(' ')
}

export function getEstPrice(meta: PlanningAppMeta): string | null {
  const numeric = parseFloat(meta.est_value_numeric)
  if (!isNaN(numeric) && numeric > 0) {
    return formatCurrency(numeric)
  }
  if (meta.est_value && meta.est_value.trim()) {
    return meta.est_value.trim()
  }
  return null
}

export function isHighValue(meta: PlanningAppMeta): boolean {
  const numeric = parseFloat(meta.est_value_numeric)
  return !isNaN(numeric) && numeric >= HIGH_VALUE_THRESHOLD
}

export function isConstructionJob(meta: PlanningAppMeta): boolean {
  return meta.is_construction_job === '1'
}

export function formatCurrency(n: number): string {
  if (n >= 1000000) {
    return `£${(n / 1000000).toFixed(n % 1000000 === 0 ? 0 : 1)}m`
  }
  return `£${Math.round(n).toLocaleString('en-GB')}`
}

export function formatDate(iso: string): string {
  if (!iso) return ''
  const d = new Date(iso)
  if (isNaN(d.getTime())) return iso
  return d.toLocaleDateString('en-GB', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  })
}
