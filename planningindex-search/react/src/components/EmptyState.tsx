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
    <div className="mx-auto max-w-md py-20 text-center">
      <div className="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-full bg-slate-100">
        <SearchX className="h-8 w-8 text-slate-400" />
      </div>
      <h3 className="text-lg font-semibold text-slate-700">No applications found</h3>
      <p className="mt-2 text-sm text-slate-500">
        Try adjusting your search or filters to find what you're looking for.
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
