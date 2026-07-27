import { useEffect, useState, useRef, useCallback } from 'react'
import { Search, User, Loader as Loader2, Briefcase } from 'lucide-react'
import type { PlanningIndexSearchConfig } from './types'
import { ToastProvider } from './components/ToastProvider'
import SearchHeader from './components/SearchHeader'
import ResultsArea from './components/ResultsArea'
import AppDetailPanel from './components/AppDetailPanel'
import MyAppsSidebar from './components/MyAppsSidebar'
import SaveSearchModal from './components/SaveSearchModal'
import BatchActionBar from './components/BatchActionBar'
import { SearchProvider, useSearchContext } from './context/SearchContext'
import { useGlobalKeyboard } from './hooks/useGlobalKeyboard'

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
        <div className="flex flex-col items-center gap-3">
          <Loader2 className="h-6 w-6 animate-spin text-slate-400" />
          <p className="text-sm font-medium text-slate-500">Loading…</p>
        </div>
      </div>
    )
  }

  return (
    <ToastProvider>
      <SearchProvider>
        <AppContent config={config} />
      </SearchProvider>
    </ToastProvider>
  )
}

function AppContent({ config: appConfig }: { config: PlanningIndexSearchConfig | null }) {
  const { workspaceIds, openMyApps } = useSearchContext()
  const workspaceCount = workspaceIds.size
  const searchInputRef = useRef<HTMLInputElement | null>(null)

  const focusSearch = useCallback(() => {
    searchInputRef.current?.focus()
    searchInputRef.current?.select()
  }, [])

  useGlobalKeyboard({ onFocusSearch: focusSearch })

  return (
    <div className="min-h-screen bg-slate-50">
      {/* Header */}
      <header className="border-b border-slate-200 bg-white">
        <div className="mx-auto flex max-w-7xl items-center justify-between px-4 py-3 sm:px-6 lg:px-8">
          <div className="flex items-center gap-2.5">
            <div className="flex h-8 w-8 items-center justify-center rounded bg-brand-700">
              <Search className="h-4 w-4 text-white" strokeWidth={2} />
            </div>
            <h1 className="text-base font-semibold text-slate-900">
              Planning Index
            </h1>
          </div>
          {appConfig?.isLoggedIn && (
            <div className="flex items-center gap-2">
              {workspaceCount > 0 && (
                <button
                  type="button"
                  onClick={() => openMyApps('workspace')}
                  aria-label={`Open workspace, ${workspaceCount} leads in pipeline`}
                  className="inline-flex items-center gap-2 rounded-md px-3 py-1.5 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-100"
                >
                  <Briefcase className="h-4 w-4" />
                  <span className="hidden sm:inline">Workspace</span>
                  <span className="inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded bg-slate-200 px-1.5 text-xs font-semibold text-slate-700">
                    {workspaceCount}
                  </span>
                </button>
              )}
              <div className="flex items-center gap-2 rounded-md bg-slate-100 px-3 py-1.5">
                <User className="h-4 w-4 text-slate-500" />
                <span className="text-sm font-medium text-slate-700">
                  User #{appConfig.userId}
                </span>
                {appConfig.isAdmin && (
                  <span className="badge bg-brand-700 text-white">Admin</span>
                )}
              </div>
            </div>
          )}
        </div>
      </header>

      {/* Sticky search header */}
      <SearchHeader searchInputRef={searchInputRef} />

      {/* Main content */}
      <main className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <ResultsArea />
      </main>

      {/* Detail panel overlay */}
      <AppDetailPanel />

      {/* My Apps sidebar overlay */}
      <MyAppsSidebar />

      {/* Save search modal */}
      <SaveSearchModal />

      {/* Batch action bar overlay */}
      <BatchActionBar />
    </div>
  )
}
