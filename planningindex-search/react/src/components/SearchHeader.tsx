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
      className="sticky top-0 z-30 border-b border-slate-200 bg-white/90 shadow-sm backdrop-blur-md"
    >
      <div className="mx-auto max-w-7xl px-4 py-4 sm:px-6 lg:px-8">
        <SearchBar inputRef={searchInputRef} />
        <div className="mt-3">
          <QuickFilterChips />
        </div>
        <div className="mt-3 flex items-center justify-end gap-2">
          <div className="flex items-center gap-2">
            {config.isLoggedIn() && (
              <button
                type="button"
                onClick={() => openMyApps()}
                aria-expanded={isMyAppsOpen}
                aria-pressed={isMyAppsOpen}
                aria-label={`Open My Apps, ${savedCount} saved`}
                className={`inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-medium transition-colors ${
                  isMyAppsOpen
                    ? 'bg-brand-600 text-white'
                    : 'bg-white text-slate-700 ring-1 ring-inset ring-slate-300 hover:bg-slate-50'
                }`}
              >
                <BookmarkCheck className="h-4 w-4" />
                <span className="hidden sm:inline">My Apps</span>
                {savedCount > 0 && (
                  <span className="inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-accent-500 px-1.5 text-xs font-semibold text-white">
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
        </div>
        <FiltersPanel open={panelOpen} onClose={() => setPanelOpen(false)} />
      </div>
    </div>
  )
}
