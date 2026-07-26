import { useEffect, useRef, useState } from 'react'
import { Search as SearchIcon, X, Loader as Loader2 } from 'lucide-react'
import { useSearchContext } from '../context/SearchContext'

export default function SearchBar() {
  const { filters, setFilters, runSearch, loading } = useSearchContext()
  const [inputValue, setInputValue] = useState(filters.search ?? '')
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null)

  // Keep input in sync when filters.search changes externally (e.g. keyword chip click)
  useEffect(() => {
    setInputValue(filters.search ?? '')
  }, [filters.search])

  useEffect(() => {
    return () => {
      if (debounceRef.current) clearTimeout(debounceRef.current)
    }
  }, [])

  const triggerSearch = (value: string) => {
    setFilters({ search: value || undefined })
    void runSearch()
  }

  const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const value = e.target.value
    setInputValue(value)

    if (debounceRef.current) clearTimeout(debounceRef.current)
    debounceRef.current = setTimeout(() => {
      triggerSearch(value)
    }, 350)
  }

  const handleKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
    if (e.key === 'Enter') {
      e.preventDefault()
      if (debounceRef.current) clearTimeout(debounceRef.current)
      triggerSearch(inputValue)
    }
  }

  const handleClear = () => {
    setInputValue('')
    if (debounceRef.current) clearTimeout(debounceRef.current)
    setFilters({ search: undefined })
    void runSearch()
  }

  return (
    <div className="relative flex items-center gap-2">
      <div className="relative flex-1">
        <SearchIcon
          className="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400"
          strokeWidth={2.25}
        />
        <input
          type="text"
          value={inputValue}
          onChange={handleChange}
          onKeyDown={handleKeyDown}
          placeholder="Search by keyword, address, or reference…"
          aria-label="Search planning applications"
          className="h-14 w-full rounded-full border-0 bg-white py-3 pl-12 pr-12 text-base text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 transition-shadow duration-200 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-brand-500"
        />
        {inputValue && (
          <button
            type="button"
            onClick={handleClear}
            aria-label="Clear search"
            className="absolute right-3 top-1/2 flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-full text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600"
          >
            <X className="h-5 w-5" />
          </button>
        )}
      </div>
      <button
        type="button"
        onClick={() => triggerSearch(inputValue)}
        disabled={loading}
        className="inline-flex h-14 items-center gap-2 rounded-full bg-accent-500 px-6 text-base font-semibold text-white shadow-sm transition-all duration-200 hover:bg-accent-600 focus-visible:ring-2 focus-visible:ring-accent-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
      >
        {loading ? (
          <Loader2 className="h-5 w-5 animate-spin" />
        ) : (
          <SearchIcon className="h-5 w-5" strokeWidth={2.5} />
        )}
        <span className="hidden sm:inline">Search</span>
      </button>
    </div>
  )
}
