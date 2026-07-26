import type { QuickFilterDef, QuickFilterId, SearchFilters } from './types'

export const QUICK_FILTERS: QuickFilterDef[] = [
  { id: 'this_week', label: 'This Week', type: 'date', days: 7 },
  { id: 'this_month', label: 'This Month', type: 'date', days: 30 },
  { id: 'extensions', label: 'Extensions', type: 'keyword', keyword: 'extension' },
  { id: 'new_builds', label: 'New Builds', type: 'keyword', keyword: 'new build' },
  { id: 'windows_doors', label: 'Windows & Doors', type: 'keyword', keyword: 'window door' },
  { id: 'roofing', label: 'Roofing', type: 'keyword', keyword: 'roof' },
  { id: 'garage_outbuilding', label: 'Garage / Outbuilding', type: 'keyword', keyword: 'garage outbuilding' },
  { id: 'high_value', label: 'High Value', type: 'toggle' },
  { id: 'construction', label: 'Construction Jobs', type: 'toggle' },
]

export function computeDateRange(days: number): { date_from: string; date_to: string } {
  const today = new Date()
  const start = new Date()
  start.setDate(today.getDate() - days)
  return {
    date_from: start.toISOString().slice(0, 10),
    date_to: today.toISOString().slice(0, 10),
  }
}

export function quickFilterToFilters(def: QuickFilterDef): Partial<SearchFilters> {
  switch (def.type) {
    case 'keyword':
      return { search: def.keyword }
    case 'date':
      return def.days ? computeDateRange(def.days) : {}
    case 'toggle':
      return def.id === 'high_value'
        ? { highValueOnly: true }
        : { constructionOnly: true }
  }
}

export function isToggleFilter(id: QuickFilterId): boolean {
  return id === 'high_value' || id === 'construction'
}
