import { useEffect, useState } from 'react'
import { Search, User, Loader as Loader2, SlidersHorizontal, LogIn } from 'lucide-react'
import type { PlanningIndexSearchConfig } from './types'
import { useSearchContext } from './context/SearchContext'
import SearchHeader from './components/SearchHeader'

export default function App() {
  const [config, setConfig] = useState<PlanningIndexSearchConfig | null>(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    const cfg = window.PlanningIndexSearch
    if (cfg) {
      setConfig(cfg)
    }
    const timer = setTimeout(() => setLoading(false), 800)
    return () => clearTimeout(timer)
  }, [])

  if (loading) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-slate-50">
        <Loader2 className="h-8 w-8 animate-spin text-brand-600" />
      </div>
    )
  }

  return (
    <div className="min-h-screen bg-slate-50">
      {/* Header */}
      <header className="border-b border-slate-200 bg-brand-600">
        <div className="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
          <div className="flex items-center gap-3">
            <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-accent-500">
              <Search className="h-5 w-5 text-white" strokeWidth={2.5} />
            </div>
            <div>
              <h1 className="font-display text-lg font-bold text-white">
                Planning Index Search
              </h1>
              <p className="text-xs text-brand-200">Renovation in progress</p>
            </div>
          </div>
          {config?.isLoggedIn && (
            <div className="flex items-center gap-2 rounded-lg bg-brand-700 px-3 py-1.5">
              <User className="h-4 w-4 text-brand-200" />
              <span className="text-sm font-medium text-white">
                User #{config.userId}
              </span>
              {config.isAdmin && (
                <span className="badge bg-accent-500 text-white">Admin</span>
              )}
            </div>
          )}
        </div>
      </header>

      {/* Sticky search header */}
      <SearchHeader />

      {/* Main content */}
      <main className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <ResultsPlaceholder />
      </main>
    </div>
  )
}

function ResultsPlaceholder() {
  const { apps, total, loading, error, filters, activeQuickFilter } = useSearchContext()

  if (loading) {
    return (
      <div className="flex items-center justify-center py-16 text-slate-500">
        <Loader2 className="mr-2 h-5 w-5 animate-spin" />
        <span className="text-sm font-medium">Searching…</span>
      </div>
    )
  }

  if (error) {
    return (
      <div className="mx-auto max-w-md rounded-xl border border-error-200 bg-error-50 p-6 text-center">
        <p className="text-sm font-medium text-error-700">
          Something went wrong loading results.
        </p>
        <p className="mt-1 text-xs text-error-600">{error.message}</p>
      </div>
    )
  }

  if (apps.length === 0) {
    return (
      <div className="mx-auto max-w-md py-16 text-center">
        <div className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-slate-100">
          <SlidersHorizontal className="h-6 w-6 text-slate-400" />
        </div>
        <h3 className="text-base font-semibold text-slate-700">No applications found</h3>
        <p className="mt-1 text-sm text-slate-500">
          Try adjusting your search or filters.
        </p>
      </div>
    )
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <p className="text-sm text-slate-600">
          <span className="font-semibold text-slate-900">{total}</span> application{total !== 1 ? 's' : ''}
          {filters.search && (
            <>
              {' '}for <span className="font-medium text-slate-900">"{filters.search}"</span>
            </>
          )}
          {activeQuickFilter && (
            <span className="ml-2 inline-flex items-center rounded-full bg-accent-100 px-2.5 py-0.5 text-xs font-medium text-accent-700">
              {activeQuickFilter.replace('_', ' ')}
            </span>
          )}
          {filters.highValueOnly && (
            <span className="ml-2 inline-flex items-center rounded-full bg-success-100 px-2.5 py-0.5 text-xs font-medium text-success-700">
              High Value
            </span>
          )}
          {filters.constructionOnly && (
            <span className="ml-2 inline-flex items-center rounded-full bg-brand-100 px-2.5 py-0.5 text-xs font-medium text-brand-700">
              Construction
            </span>
          )}
        </p>
        <span className="text-xs text-slate-400">Results grid coming in Stage 5</span>
      </div>
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {apps.slice(0, 9).map((app) => (
          <div
            key={app.id}
            className="card p-5"
          >
            <p className="text-xs font-semibold uppercase tracking-wide text-accent-600">
              {app._authority_name || 'Unknown council'}
            </p>
            <h4 className="mt-2 line-clamp-2 text-sm font-semibold text-slate-900">
              {app.title.rendered}
            </h4>
            <p className="mt-1 line-clamp-1 text-xs text-slate-500">
              {app.meta.address || 'No address'}
            </p>
          </div>
        ))}
      </div>
    </div>
  )
}
