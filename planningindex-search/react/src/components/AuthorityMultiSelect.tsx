import { useEffect, useMemo, useRef, useState } from 'react'
import { Check, ChevronDown, Search, X } from 'lucide-react'
import type { Authority } from '../types'

interface AuthorityMultiSelectProps {
  authorities: Authority[]
  selected: number[]
  onChange: (ids: number[]) => void
}

export default function AuthorityMultiSelect({
  authorities,
  selected,
  onChange,
}: AuthorityMultiSelectProps) {
  const [open, setOpen] = useState(false)
  const [query, setQuery] = useState('')
  const containerRef = useRef<HTMLDivElement>(null)

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

  const filtered = useMemo(() => {
    const q = query.trim().toLowerCase()
    if (!q) return authorities
    return authorities.filter((a) => a.name && a.name.toLowerCase().includes(q))
  }, [authorities, query])

  const selectedSet = useMemo(() => new Set(selected), [selected])

  const label = (() => {
    if (selected.length === 0 || selected.length === authorities.length) return 'All authorities'
    if (selected.length === 1) {
      const a = authorities.find((x) => x.id === selected[0])
      return a?.name || '1 authority selected'
    }
    return `${selected.length} authorities selected`
  })()

  const toggle = (id: number) => {
    onChange(selectedSet.has(id) ? selected.filter((x) => x !== id) : [...selected, id])
  }

  const selectAll = () => onChange(authorities.map((a) => a.id))
  const clearAll = () => onChange([])

  return (
    <div ref={containerRef} className="relative">
      <button
        type="button"
        onClick={() => setOpen((v) => !v)}
        aria-expanded={open}
        aria-haspopup="listbox"
        className="input-field flex items-center justify-between text-left"
      >
        <span className="truncate">{label}</span>
        <ChevronDown
          className={`ml-2 h-4 w-4 flex-shrink-0 text-slate-400 transition-transform ${open ? 'rotate-180' : ''}`}
        />
      </button>

      {open && (
        <div className="absolute left-0 right-0 z-40 mt-1 rounded-xl border border-slate-200 bg-white shadow-lg">
          <div className="border-b border-slate-100 p-2">
            <div className="relative">
              <Search className="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
              <input
                type="text"
                value={query}
                onChange={(e) => setQuery(e.target.value)}
                placeholder="Search authorities…"
                aria-label="Search authorities"
                className="w-full rounded-lg border-0 bg-slate-50 py-2 pl-8 pr-8 text-sm text-slate-900 ring-1 ring-inset ring-slate-200 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-brand-500"
                autoFocus
              />
              {query && (
                <button
                  type="button"
                  onClick={() => setQuery('')}
                  aria-label="Clear search"
                  className="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                >
                  <X className="h-3.5 w-3.5" />
                </button>
              )}
            </div>
            <div className="mt-2 flex items-center justify-between px-1">
              <button
                type="button"
                onClick={selectAll}
                className="text-xs font-medium text-brand-600 hover:text-brand-700"
              >
                Select All
              </button>
              <button
                type="button"
                onClick={clearAll}
                className="text-xs font-medium text-slate-500 hover:text-slate-700"
              >
                Clear All
              </button>
            </div>
          </div>
          <div className="max-h-56 overflow-y-auto scrollbar-thin p-1">
            {filtered.length === 0 ? (
              <p className="px-3 py-6 text-center text-xs text-slate-400">No authorities found</p>
            ) : (
              filtered.map((a) => {
                const checked = selectedSet.has(a.id)
                return (
                  <label
                    key={a.id}
                    className="flex cursor-pointer items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm text-slate-700 hover:bg-slate-50"
                  >
                    <span
                      className={`flex h-4 w-4 flex-shrink-0 items-center justify-center rounded border transition-colors ${
                        checked
                          ? 'border-brand-500 bg-brand-500 text-white'
                          : 'border-slate-300 bg-white'
                      }`}
                    >
                      {checked && <Check className="h-3 w-3" strokeWidth={3} />}
                    </span>
                    <input
                      type="checkbox"
                      checked={checked}
                      onChange={() => toggle(a.id)}
                      className="sr-only"
                    />
                    <span className="truncate">{a.name || `Authority #${a.id}`}</span>
                  </label>
                )
              })
            )}
          </div>
          <div className="border-t border-slate-100 px-3 py-1.5 text-xs text-slate-500">
            {selected.length} selected
          </div>
        </div>
      )}
    </div>
  )
}
