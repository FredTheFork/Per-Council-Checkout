import { useEffect, useRef, useState } from 'react'
import { SlidersHorizontal, BookmarkCheck } from 'lucide-react'
import SearchBar from './SearchBar'
import QuickFilterChips from './QuickFilterChips'
import FiltersPanel from './FiltersPanel'
import { useSearchContext } from '../context/SearchContext'
import { advancedFilterCount } from '../utils/advancedFilters'
import { config } from '../config'

interface SearchHeaderProps {
  searchInputRef?: React.RefObject<HTMLInputElement | null>
}

export default function SearchHeader({ searchInputRef }: SearchHeaderProps) {
  const { filters, savedApps, isMyAppsOpen, openMyApps } = useSearchContext()
  const [panelOpen, setPanelOpen] = useState(false)
  const headerRef = useRef<HTMLDivElement>(null)
  const count = advancedFilterCount(filters)
  const savedCount = savedApps.length

  useEffect(() => {
    const header = headerRef.current
    if (!header) return
    const update = () => {
      const h = header.offsetHeight
      document.documentElement.style.setProperty('--pi-header-height', `${h}px`)
    }
    update()
    const ro = new ResizeObserver(update)
    ro.observe(header)
    return () => ro.disconnect()
  }, [])

  return (
    <div
      ref={headerRef}
      className="sticky top-0 z-30 border-b border-slate-200 bg-white"
    >
      <div className="mx-auto max-w-7xl px-4 py-3 sm:px-6 lg:px-8">
        <SearchBar inputRef={searchInputRef} />
        <div className="mt-2.5">
          <QuickFilterChips />
        </div>
        <div className="mt-2.5 flex items-center justify-end gap-2">
          <div className="flex items-center gap-2">
            {config.isLoggedIn() && (
              <button
                type="button"
                onClick={() => openMyApps()}
                aria-expanded={isMyAppsOpen}
                aria-pressed={isMyAppsOpen}
                aria-label={`Open My Apps, ${savedCount} saved`}
                className={`inline-flex items-center gap-2 rounded-md px-3 py-1.5 text-sm font-medium transition-colors ${
                  isMyAppsOpen
                    ? 'bg-brand-700 text-white'
                    : 'bg-white text-slate-600 ring-1 ring-inset ring-slate-200 hover:bg-slate-50 hover:text-slate-900'
                }`}
              >
                <BookmarkCheck className="h-4 w-4" />
                <span className="hidden sm:inline">My Apps</span>
                {savedCount > 0 && (
                  <span className="inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded bg-slate-200 px-1.5 text-xs font-semibold text-slate-700">
                    {savedCount}
                  </span>
                )}
              </button>
            )}
            <button
              type="button"
              onClick={() => setPanelOpen((v) => !v)}
              aria-expanded={panelOpen}
              aria-pressed={panelOpen}
              aria-controls="advanced-filters-panel"
              className={`inline-flex items-center gap-2 rounded-md px-3 py-1.5 text-sm font-medium transition-colors ${
                panelOpen
                  ? 'bg-slate-900 text-white'
                  : 'bg-white text-slate-600 ring-1 ring-inset ring-slate-200 hover:bg-slate-50 hover:text-slate-900'
              }`}
            >
              <SlidersHorizontal className="h-4 w-4" />
              <span>Filters</span>
              {count > 0 && (
                <span className="inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded bg-accent-100 px-1.5 text-xs font-semibold text-accent-700">
                  {count}
                </span>
              )}
            </button>
          </div>
        </div>
        <FiltersPanel open={panelOpen} onClose={() => setPanelOpen(false)} />
      </div>
    </div>
  )
}
