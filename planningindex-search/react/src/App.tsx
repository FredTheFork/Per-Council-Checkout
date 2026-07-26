import { useEffect, useState } from 'react'
import { Search, User, Loader as Loader2 } from 'lucide-react'
import type { PlanningIndexSearchConfig } from './types'
import SearchHeader from './components/SearchHeader'
import ResultsArea from './components/ResultsArea'

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
        <ResultsArea />
      </main>
    </div>
  )
}
