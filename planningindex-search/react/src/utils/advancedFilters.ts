import type { SearchFilters } from '../types'

export function advancedFilterCount(filters: SearchFilters): number {
  let count = 0
  if (filters.authority && filters.authority.length > 0) count += 1
  if (filters.app_category != null) count += 1
  if (filters.date_from) count += 1
  if (filters.date_to) count += 1
  if (filters.estValueMin != null) count += 1
  if (filters.estValueMax != null) count += 1
  if (filters.highValueOnly) count += 1
  if (filters.constructionOnly) count += 1
  if (filters.hideSaved) count += 1
  if (filters.hideViewed) count += 1
  if (filters.hideWorkspace) count += 1
  return count
}
