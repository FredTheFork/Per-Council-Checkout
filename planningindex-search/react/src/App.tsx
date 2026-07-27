import { useEffect, useState, useRef, useCallback } from 'react'
import { Loader as Loader2 } from 'lucide-react'
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
  const searchInputRef = useRef<HTMLInputElement | null>(null)

  const focusSearch = useCallback(() => {
    searchInputRef.current?.focus()
    searchInputRef.current?.select()
  }, [])

  useGlobalKeyboard({ onFocusSearch: focusSearch })

  return (
    <div className="min-h-screen bg-slate-50">
      {/* Sticky search header */}
      <SearchHeader searchInputRef={searchInputRef} />

      {/* Main content */}
      <main className="mx-auto max-w-7xl px-4 pt-4 sm:px-6 lg:px-8">
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
