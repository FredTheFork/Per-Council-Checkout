import { LayoutGrid, List, Map } from 'lucide-react'
import type { ViewMode } from '../types'
import { useSearchContext } from '../context/SearchContext'

const VIEWS: { id: ViewMode; label: string; icon: typeof LayoutGrid }[] = [
  { id: 'grid', label: 'Grid', icon: LayoutGrid },
  { id: 'list', label: 'List', icon: List },
  { id: 'map', label: 'Map', icon: Map },
]

export default function ViewToggle() {
  const { view, switchView } = useSearchContext()

  return (
    <div
      className="inline-flex items-center gap-1 rounded-xl bg-slate-100 p-1"
      role="tablist"
      aria-label="View mode"
    >
      {VIEWS.map(({ id, label, icon: Icon }) => {
        const active = view === id
        return (
          <button
            key={id}
            type="button"
            role="tab"
            aria-selected={active}
            aria-pressed={active}
            onClick={() => switchView(id)}
            className={`inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium transition-all duration-200 active:scale-95 ${
              active
                ? 'bg-white text-brand-700 shadow-soft'
                : 'text-slate-500 hover:text-slate-700'
            }`}
          >
            <Icon className="h-4 w-4" />
            <span className="hidden sm:inline">{label}</span>
          </button>
        )
      })}
    </div>
  )
}
