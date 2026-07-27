import { SearchX, RotateCcw } from 'lucide-react'
import { useSearchContext } from '../context/SearchContext'
import { advancedFilterCount } from '../utils/advancedFilters'

export default function EmptyState() {
  const { filters, clearFilters, runSearch } = useSearchContext()
  const hasFilters = advancedFilterCount(filters) > 0 || !!filters.search

  const handleClearAll = () => {
    clearFilters()
    runSearch()
  }

  return (
    <div className="mx-auto max-w-md py-24 text-center">
      <SearchX className="mx-auto h-10 w-10 text-slate-300" strokeWidth={1.5} />
      <h3 className="mt-4 text-base font-semibold text-slate-700">
        No applications found
      </h3>
      <p className="mt-1.5 text-sm text-slate-500">
        {hasFilters
          ? 'Try adjusting your search or filters to find what you need.'
          : 'There are no planning applications to display.'}
      </p>
      {hasFilters && (
        <button
          type="button"
          onClick={handleClearAll}
          className="btn-secondary mt-6"
        >
          <RotateCcw className="h-4 w-4" />
          Clear all filters
        </button>
      )}
    </div>
  )
}
