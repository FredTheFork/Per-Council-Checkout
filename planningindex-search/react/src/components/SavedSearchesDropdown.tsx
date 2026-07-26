import { useEffect, useMemo, useRef, useState, useCallback } from 'react'
import { ListChecks, Trash2, Search as SearchIcon } from 'lucide-react'
import { useSearchContext } from '../context/SearchContext'
import { usePrefersReducedMotion } from '../hooks/usePrefersReducedMotion'
import { summarizeFilters } from '../utils/filterSummary'
import type { SavedSearch } from '../types'

export default function SavedSearchesDropdown() {
  const {
    savedSearches,
    allowedAuthorities,
    loadingSavedSearchCounts,
    applySavedSearch,
    deleteSavedSearch,
  } = useSearchContext()

  const [open, setOpen] = useState(false)
  const [visible, setVisible] = useState(false)
  const containerRef = useRef<HTMLDivElement | null>(null)
  const prefersReducedMotion = usePrefersReducedMotion()

  // Animate in when opening
  useEffect(() => {
    if (open) {
      requestAnimationFrame(() => setVisible(true))
    } else {
      setVisible(false)
    }
  }, [open])

  // Click outside to close
  useEffect(() => {
    if (!open) return
    const onMouseDown = (e: MouseEvent) => {
      if (containerRef.current && !containerRef.current.contains(e.target as Node)) {
        setOpen(false)
      }
    }
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') setOpen(false)
    }
    document.addEventListener('mousedown', onMouseDown)
    document.addEventListener('keydown', onKey)
    return () => {
      document.removeEventListener('mousedown', onMouseDown)
      document.removeEventListener('keydown', onKey)
    }
  }, [open])

  const totalNewCount = useMemo(() => {
    return savedSearches.reduce((sum, s) => sum + (s.newCount ?? 0), 0)
  }, [savedSearches])

  const handleApply = useCallback(
    (search: SavedSearch) => {
      applySavedSearch(search)
      setOpen(false)
    },
    [applySavedSearch],
  )

  const handleDelete = useCallback(
    (e: React.MouseEvent, id: number) => {
      e.stopPropagation()
      deleteSavedSearch(id)
    },
    [deleteSavedSearch],
  )

  return (
    <div ref={containerRef} className="relative">
      <button
        type="button"
        onClick={() => setOpen((v) => !v)}
        aria-expanded={open}
        aria-haspopup="menu"
        aria-label={`Saved searches, ${totalNewCount} new leads`}
        className={`inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-medium transition-colors ${
          open
            ? 'bg-brand-600 text-white'
            : 'bg-white text-slate-700 ring-1 ring-inset ring-slate-300 hover:bg-slate-50'
        }`}
      >
        <ListChecks className="h-4 w-4" />
        <span className="hidden sm:inline">Saved</span>
        {totalNewCount > 0 && (
          <span className="inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-accent-500 px-1.5 text-xs font-semibold text-white">
            {totalNewCount}
          </span>
        )}
      </button>

      {open && (
        <div
          role="menu"
          className={`absolute right-0 z-40 mt-2 w-80 origin-top-right overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg transition-all duration-150 ease-out ${
            visible ? 'opacity-100' : 'translate-y-1 opacity-0'
          }`}
          style={{ transitionDuration: prefersReducedMotion ? '0ms' : '150ms' }}
        >
          {/* Header */}
          <div className="border-b border-slate-100 px-4 py-2.5">
            <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">
              Saved searches
            </p>
          </div>

          {/* List */}
          <div className="max-h-80 overflow-y-auto scrollbar-thin">
            {savedSearches.length === 0 ? (
              <div className="flex flex-col items-center justify-center gap-3 px-6 py-10 text-center">
                <div className="flex h-12 w-12 items-center justify-center rounded-full bg-slate-100">
                  <SearchIcon className="h-6 w-6 text-slate-400" />
                </div>
                <p className="text-sm font-semibold text-slate-600">No saved searches yet</p>
                <p className="max-w-[14rem] text-xs text-slate-400">
                  Set up your filters and click Saved to save this search.
                </p>
              </div>
            ) : (
              <ul className="py-1">
                {savedSearches.map((search) => (
                  <li key={search.id}>
                    <div
                      role="menuitem"
                      tabIndex={0}
                      onClick={() => handleApply(search)}
                      onKeyDown={(e) => {
                        if (e.key === 'Enter' || e.key === ' ') {
                          e.preventDefault()
                          handleApply(search)
                        }
                      }}
                      className="group flex cursor-pointer items-start gap-3 px-4 py-3 transition-colors hover:bg-slate-50"
                    >
                      <div className="min-w-0 flex-1">
                        <p className="truncate text-sm font-semibold text-slate-800">
                          {search.name}
                        </p>
                        <p className="mt-0.5 truncate text-xs text-slate-400">
                          {summarizeFilters(search.filters, allowedAuthorities)}
                        </p>
                      </div>

                      <div className="flex flex-shrink-0 items-center gap-2">
                        {/* New badge or loading placeholder */}
                        {loadingSavedSearchCounts && search.newCount === undefined ? (
                          <span className="h-5 w-10 rounded-full bg-slate-100 shimmer-bg" />
                        ) : search.newCount != null && search.newCount > 0 ? (
                          <span className="inline-flex items-center rounded-full bg-accent-100 px-2 py-0.5 text-xs font-bold text-accent-700">
                            +{search.newCount} new
                          </span>
                        ) : null}

                        <button
                          type="button"
                          onClick={(e) => handleDelete(e, search.id)}
                          aria-label={`Delete saved search "${search.name}"`}
                          className="flex h-7 w-7 items-center justify-center rounded-lg text-slate-300 transition-colors hover:bg-error-50 hover:text-error-600"
                        >
                          <Trash2 className="h-3.5 w-3.5" />
                        </button>
                      </div>
                    </div>
                  </li>
                ))}
              </ul>
            )}
          </div>
        </div>
      )}
    </div>
  )
}
