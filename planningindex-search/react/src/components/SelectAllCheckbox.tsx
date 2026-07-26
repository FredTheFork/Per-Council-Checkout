import { useEffect, useRef } from 'react'
import { useSearchContext } from '../context/SearchContext'
import type { PlanningApp } from '../types'

export default function SelectAllCheckbox({ apps }: { apps: PlanningApp[] }) {
  const { selectedIds, selectAll, clearSelection } = useSearchContext()
  const checkboxRef = useRef<HTMLInputElement>(null)

  const allSelected = apps.length > 0 && apps.every((a) => selectedIds.has(a.id))
  const someSelected = apps.some((a) => selectedIds.has(a.id)) && !allSelected

  useEffect(() => {
    if (checkboxRef.current) {
      checkboxRef.current.indeterminate = someSelected
    }
  }, [someSelected])

  const label = allSelected
    ? `All selected (${apps.length})`
    : someSelected
      ? `${selectedIds.size} of ${apps.length} selected`
      : 'Select all'

  return (
    <label
      className="inline-flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 text-sm text-slate-600 transition-colors hover:bg-slate-100"
      title="Selects all loaded applications"
    >
      <input
        ref={checkboxRef}
        type="checkbox"
        checked={allSelected}
        onChange={() => {
          if (allSelected) {
            clearSelection()
          } else {
            selectAll(apps.map((a) => a.id))
          }
        }}
        className="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500"
        aria-label={label}
      />
      <span className="hidden whitespace-nowrap md:inline">{label}</span>
    </label>
  )
}
