import type { Authority, SearchFilters } from '../types'

function formatValueRange(min: number | undefined, max: number | undefined): string | null {
  if (min != null && max != null) {
    return `£${formatNum(min)}–£${formatNum(max)}`
  }
  if (min != null) {
    return `£${formatNum(min)}+`
  }
  if (max != null) {
    return `Under £${formatNum(max)}`
  }
  return null
}

function formatNum(n: number): string {
  if (n >= 1000000) return `${(n / 1000000).toFixed(n % 1000000 === 0 ? 0 : 1)}m`
  if (n >= 1000) return `${Math.round(n / 1000)}k`
  return String(Math.round(n))
}

function formatDateLabel(dateStr: string): string {
  const d = new Date(dateStr)
  if (isNaN(d.getTime())) return dateStr
  return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })
}

export function summarizeFilters(
  filters: SearchFilters,
  authorities: Authority[],
): string {
  const parts: string[] = []

  // Keyword
  if (filters.search) {
    parts.push(`"${filters.search}"`)
  }

  // Authorities
  if (filters.authority && filters.authority.length > 0) {
    if (filters.authority.length === 1) {
      const a = authorities.find((x) => x.id === filters.authority![0])
      parts.push(a?.name || `Authority #${filters.authority[0]}`)
    } else {
      parts.push(`${filters.authority.length} authorities`)
    }
  }

  // Category
  if (filters.app_category != null) {
    parts.push('Category filter')
  }

  // Date range
  if (filters.date_from && filters.date_to) {
    parts.push(`${formatDateLabel(filters.date_from)}–${formatDateLabel(filters.date_to)}`)
  } else if (filters.date_from) {
    parts.push(`From ${formatDateLabel(filters.date_from)}`)
  } else if (filters.date_to) {
    parts.push(`Until ${formatDateLabel(filters.date_to)}`)
  }

  // Value range
  const valueStr = formatValueRange(filters.estValueMin, filters.estValueMax)
  if (valueStr) parts.push(valueStr)

  // High value
  if (filters.highValueOnly) parts.push('High value')

  // Construction
  if (filters.constructionOnly) parts.push('Construction')

  // Hide flags
  const hideParts: string[] = []
  if (filters.hideSaved) hideParts.push('saved')
  if (filters.hideViewed) hideParts.push('viewed')
  if (filters.hideWorkspace) hideParts.push('workspace')
  if (hideParts.length > 0) {
    parts.push(`Hide ${hideParts.join('/')}`)
  }

  return parts.length > 0 ? parts.join(' · ') : 'All applications'
}
