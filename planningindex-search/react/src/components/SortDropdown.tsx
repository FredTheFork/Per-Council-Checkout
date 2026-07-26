import { ArrowDownWideNarrow } from 'lucide-react'
import type { SortOption } from '../types'
import { useSearchContext } from '../context/SearchContext'

const SORT_OPTIONS: { value: SortOption; label: string }[] = [
  { value: 'date_desc', label: 'Newest first' },
  { value: 'date_asc', label: 'Oldest first' },
  { value: 'alpha_asc', label: 'A–Z by address' },
  { value: 'alpha_desc', label: 'Z–A by address' },
  { value: 'value_desc', label: 'Highest value first' },
  { value: 'lead_score_desc', label: 'Lead score (hotness)' },
]

export default function SortDropdown() {
  const { sort, setSort } = useSearchContext()

  return (
    <div className="relative inline-flex items-center">
      <ArrowDownWideNarrow className="pointer-events-none absolute left-3 h-4 w-4 text-slate-400" />
      <select
        value={sort}
        onChange={(e) => setSort(e.target.value as SortOption)}
        className="appearance-none rounded-xl border-0 bg-white py-2 pl-9 pr-8 text-sm font-medium text-slate-700 ring-1 ring-inset ring-slate-300 transition-shadow focus:ring-2 focus:ring-inset focus:ring-brand-500"
        aria-label="Sort results"
      >
        {SORT_OPTIONS.map((opt) => (
          <option key={opt.value} value={opt.value}>
            {opt.label}
          </option>
        ))}
      </select>
      <svg
        className="pointer-events-none absolute right-2.5 h-4 w-4 text-slate-400"
        fill="none"
        stroke="currentColor"
        viewBox="0 0 24 24"
      >
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
      </svg>
    </div>
  )
}
