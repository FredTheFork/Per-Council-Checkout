import { useState } from 'react'
import { SlidersHorizontal } from 'lucide-react'
import SearchBar from './SearchBar'
import QuickFilterChips from './QuickFilterChips'
import FiltersPanel from './FiltersPanel'
import { useSearchContext } from '../context/SearchContext'
import { advancedFilterCount } from '../utils/advancedFilters'

export default function SearchHeader() {
  const { filters } = useSearchContext()
  const [panelOpen, setPanelOpen] = useState(false)
  const count = advancedFilterCount(filters)

  return (
    <div className="sticky top-0 z-30 border-b border-slate-200 bg-white/90 shadow-sm backdrop-blur-md">
      <div className="mx-auto max-w-7xl px-4 py-4 sm:px-6 lg:px-8">
        <SearchBar />
        <div className="mt-3">
          <QuickFilterChips />
        </div>
        <div className="mt-3 flex items-center justify-end">
          <button
            type="button"
            onClick={() => setPanelOpen((v) => !v)}
            aria-expanded={panelOpen}
            aria-controls="advanced-filters-panel"
            className={`inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-medium transition-colors ${
              panelOpen
                ? 'bg-slate-900 text-white'
                : 'bg-white text-slate-700 ring-1 ring-inset ring-slate-300 hover:bg-slate-50'
            }`}
          >
            <SlidersHorizontal className="h-4 w-4" />
            <span>Filters</span>
            {count > 0 && (
              <span className="inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-accent-500 px-1.5 text-xs font-semibold text-white">
                {count}
              </span>
            )}
          </button>
        </div>
        <FiltersPanel open={panelOpen} />
      </div>
    </div>
  )
}
