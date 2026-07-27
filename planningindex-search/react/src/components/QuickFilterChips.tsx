import { Check, X } from 'lucide-react'
import { useSearchContext } from '../context/SearchContext'
import { QUICK_FILTERS, quickFilterToFilters, isToggleFilter } from '../quickFilters'
import type { QuickFilterDef, QuickFilterId } from '../types'

export default function QuickFilterChips() {
  const {
    filters,
    activeQuickFilter,
    setQuickFilter,
    setFilters,
    toggleHighValue,
    toggleConstruction,
    runSearch,
    clearFilters,
    rawApps,
  } = useSearchContext()

  const handleChipClick = (def: QuickFilterDef) => {
    if (isToggleFilter(def.id)) {
      if (def.id === 'high_value') {
        toggleHighValue()
      } else {
        toggleConstruction()
      }
      if (rawApps.length === 0) {
        void runSearch()
      }
      return
    }

    if (activeQuickFilter === def.id) {
      setQuickFilter(null)
      const cleared =
        def.type === 'keyword'
          ? { search: undefined }
          : { date_from: undefined, date_to: undefined }
      setFilters(cleared)
      void runSearch()
    } else {
      setQuickFilter(def.id)
      setFilters(quickFilterToFilters(def))
      void runSearch()
    }
  }

  const handleClearAll = () => {
    clearFilters()
  }

  const hasActiveFilter =
    activeQuickFilter !== null ||
    !!filters.highValueOnly ||
    !!filters.constructionOnly ||
    !!filters.search

  const isChipActive = (id: QuickFilterId): boolean => {
    if (isToggleFilter(id)) {
      return id === 'high_value' ? !!filters.highValueOnly : !!filters.constructionOnly
    }
    return activeQuickFilter === id
  }

  return (
    <div className="flex items-center gap-3">
      <div className="no-scrollbar flex flex-1 items-center gap-1.5 overflow-x-auto py-0.5">
        {QUICK_FILTERS.map((def) => {
          const active = isChipActive(def.id)
          const isToggle = isToggleFilter(def.id)
          return (
            <button
              key={def.id}
              type="button"
              onClick={() => handleChipClick(def)}
              aria-label={def.label}
              aria-pressed={active}
              className={[
                'inline-flex flex-shrink-0 items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium transition-colors duration-150',
                active
                  ? 'bg-brand-700 text-white'
                  : 'bg-white text-slate-600 ring-1 ring-inset ring-slate-200 hover:bg-slate-50 hover:text-slate-900',
              ].join(' ')}
            >
              {isToggle && active && <Check className="h-3.5 w-3.5" strokeWidth={2.5} />}
              {def.label}
            </button>
          )
        })}
      </div>
      {hasActiveFilter && (
        <button
          type="button"
          onClick={handleClearAll}
          className="inline-flex flex-shrink-0 items-center gap-1 rounded-md px-2.5 py-1.5 text-sm font-medium text-slate-500 transition-colors hover:text-slate-900"
        >
          <X className="h-3.5 w-3.5" />
          <span className="hidden sm:inline">Clear</span>
        </button>
      )}
    </div>
  )
}
