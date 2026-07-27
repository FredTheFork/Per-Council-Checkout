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
        <Loader2 className="h-8 w-8 animate-spin text-brand-600" />
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
          {appConfig?.isLoggedIn && (
            <div className="flex items-center gap-2">
              {workspaceCount > 0 && (
                <button
                  type="button"
                  onClick={() => openMyApps('workspace')}
                  aria-label={`Open workspace, ${workspaceCount} leads in pipeline`}
                  className="inline-flex items-center gap-2 rounded-lg bg-brand-700 px-3 py-1.5 text-sm font-medium text-white transition-colors hover:bg-brand-800 active:scale-95"
                >
                  <Briefcase className="h-4 w-4" />
                  <span className="hidden sm:inline">Workspace</span>
                  <span className="inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-accent-500 px-1.5 text-xs font-semibold text-white">
                    {workspaceCount}
                  </span>
                </button>
              )}
              <div className="flex items-center gap-2 rounded-lg bg-brand-700 px-3 py-1.5">
                <User className="h-4 w-4 text-brand-200" />
                <span className="text-sm font-medium text-white">
                  User #{appConfig.userId}
                </span>
                {appConfig.isAdmin && (
                  <span className="badge bg-accent-500 text-white">Admin</span>
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
