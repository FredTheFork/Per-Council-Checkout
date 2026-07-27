import { useEffect, useState, useRef } from 'react'
import { Search as SearchIcon, X, Loader as Loader2 } from 'lucide-react'
import { useSearchContext } from '../context/SearchContext'

interface SearchBarProps {
  inputRef?: React.RefObject<HTMLInputElement | null>
}

export default function SearchBar({ inputRef }: SearchBarProps) {
  const { filters, setFilters, runSearch, loading } = useSearchContext()
  const [inputValue, setInputValue] = useState(filters.search ?? '')
  const internalRef = useRef<HTMLInputElement | null>(null)
  const ref = inputRef ?? internalRef
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null)

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
          className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
          strokeWidth={2}
        />
        <input
          ref={ref}
          type="text"
          value={inputValue}
          onChange={handleChange}
          onKeyDown={handleKeyDown}
          placeholder="Search by keyword, address, or reference"
          aria-label="Search planning applications"
          className="h-11 w-full rounded-lg border-0 bg-white py-2.5 pl-10 pr-10 text-sm text-slate-900 ring-1 ring-inset ring-slate-300 transition-shadow duration-200 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-accent-500"
        />
        {inputValue && (
          <button
            type="button"
            onClick={handleClear}
            aria-label="Clear search"
            className="absolute right-2.5 top-1/2 flex h-6 w-6 -translate-y-1/2 items-center justify-center rounded text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600"
          >
            <X className="h-4 w-4" />
          </button>
        )}
      </div>
      <button
        type="button"
        onClick={() => triggerSearch(inputValue)}
        disabled={loading}
        aria-label="Search"
        className="inline-flex h-11 items-center gap-2 rounded-lg bg-brand-700 px-5 text-sm font-semibold text-white shadow-sm transition-colors duration-200 hover:bg-brand-800 focus-visible:ring-accent-500 disabled:cursor-not-allowed disabled:opacity-60"
      >
        {loading ? (
          <Loader2 className="h-4 w-4 animate-spin" />
        ) : (
          <SearchIcon className="h-4 w-4" strokeWidth={2} />
        )}
        <span className="hidden sm:inline">Search</span>
      </button>
    </div>
  )
}
