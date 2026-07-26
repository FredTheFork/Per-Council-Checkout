import { useSearchContext } from '../context/SearchContext'
import type { SearchContextValue } from '../context/SearchContext'
import type { SearchFilters, SortOption, ViewMode } from '../types'

export interface UseSearchState extends SearchContextValue {}

export function useSearchState(): UseSearchState {
  const ctx = useSearchContext()
  return ctx
}

export type { SearchFilters, SortOption, ViewMode }
