import { useEffect, useState, useCallback, useRef } from 'react'
import { X, Bookmark, Clock, BookmarkCheck, RotateCcw, TriangleAlert as AlertTriangle } from 'lucide-react'
import { useSearchContext } from '../context/SearchContext'
import { usePrefersReducedMotion } from '../hooks/usePrefersReducedMotion'
import { useFocusTrap } from '../hooks/useFocusTrap'
import SavedAppCard from './SavedAppCard'
import RecentAppCard from './RecentAppCard'
import MyAppsSkeleton from './MyAppsSkeleton'

type TabId = 'saved' | 'recent'

export default function MyAppsSidebar() {
  const {
    isMyAppsOpen,
    closeMyApps,
    savedApps,
    recentApps,
    loadingMyApps,
    savedIds,
    unsaveApp,
    saveApp,
    refreshMyApps,
    openDetailPanel,
    selectedAppId,
  } = useSearchContext()

  const [visible, setVisible] = useState(false)
  const [activeTab, setActiveTab] = useState<TabId>('saved')
  const [removingId, setRemovingId] = useState<number | null>(null)
  const [savingId, setSavingId] = useState<number | null>(null)
  const [error, setError] = useState<string | null>(null)
  const panelRef = useRef<HTMLDivElement | null>(null)
  const prevFocusRef = useRef<HTMLElement | null>(null)
  const prefersReducedMotion = usePrefersReducedMotion()
  useFocusTrap(isMyAppsOpen, panelRef)

  // Slide-in animation + body scroll lock
  useEffect(() => {
    if (isMyAppsOpen) {
      prevFocusRef.current = document.activeElement as HTMLElement | null
      setError(null)
      requestAnimationFrame(() => setVisible(true))
      document.body.style.overflow = 'hidden'
      panelRef.current?.focus()
    } else {
      setVisible(false)
      // Only restore scroll if the detail panel isn't also open
      if (selectedAppId === null) {
        document.body.style.overflow = ''
      }
      prevFocusRef.current?.focus()
    }
    return () => {
      if (selectedAppId === null) {
        document.body.style.overflow = ''
      }
    }
  }, [isMyAppsOpen, selectedAppId])

  // Escape to close
  useEffect(() => {
    if (!isMyAppsOpen) return
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') closeMyApps()
    }
    document.addEventListener('keydown', onKey)
    return () => document.removeEventListener('keydown', onKey)
  }, [isMyAppsOpen, closeMyApps])

  const handleRemove = useCallback(
    async (id: number) => {
      if (removingId !== null) return
      setRemovingId(id)
      try {
        await unsaveApp(id)
        // Wait for slide-up animation before refreshing
        setTimeout(() => {
          setRemovingId(null)
          void refreshMyApps()
        }, prefersReducedMotion ? 0 : 300)
      } catch {
        setRemovingId(null)
        setError('Could not remove this app. Please try again.')
      }
    },
    [removingId, unsaveApp, refreshMyApps, prefersReducedMotion],
  )

  const handleSaveFromRecent = useCallback(
    async (id: number) => {
      if (savingId !== null) return
      setSavingId(id)
      try {
        await saveApp(id)
        setTimeout(() => {
          setSavingId(null)
          void refreshMyApps()
        }, prefersReducedMotion ? 0 : 300)
      } catch {
        setSavingId(null)
        setError('Could not save this app. Please try again.')
      }
    },
    [savingId, saveApp, refreshMyApps, prefersReducedMotion],
  )

  const handleViewDetails = useCallback(
    (id: number) => {
      openDetailPanel(id)
    },
    [openDetailPanel],
  )

  const handleRetry = useCallback(() => {
    setError(null)
    void refreshMyApps()
  }, [refreshMyApps])

  if (!isMyAppsOpen) return null

  // Filter out already-saved apps from the recent list
  const recentFiltered = recentApps.filter((a) => !savedIds.has(a.id))

  return (
    <div
      className="fixed inset-0 z-50 flex justify-end"
      role="dialog"
      aria-modal="true"
      aria-label="My Apps"
    >
      {/* Backdrop */}
      <button
        type="button"
        aria-label="Close My Apps"
        onClick={closeMyApps}
        className={`absolute inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity duration-300 ${
          visible ? 'opacity-100' : 'opacity-0'
        }`}
      />

      {/* Panel */}
      <div
        ref={panelRef}
        tabIndex={-1}
        onClick={(e) => e.stopPropagation()}
        className={`relative flex h-full w-full max-w-md flex-col bg-white shadow-2xl transition-transform duration-300 ease-out focus:outline-none ${
          visible ? 'translate-x-0' : 'translate-x-full'
        }`}
      >
        {/* Sticky header */}
        <div className="flex items-center justify-between border-b border-slate-200 px-5 py-4">
          <div className="flex items-center gap-2">
            <BookmarkCheck className="h-5 w-5 text-brand-600" />
            <h2 className="font-display text-sm font-bold uppercase tracking-wide text-slate-500">
              My Apps
            </h2>
          </div>
          <button
            type="button"
            onClick={closeMyApps}
            aria-label="Close"
            className="flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600"
          >
            <X className="h-5 w-5" />
          </button>
        </div>

        {/* Tabs */}
        <div className="flex gap-1 border-b border-slate-200 bg-slate-50 p-2">
          <TabButton
            active={activeTab === 'saved'}
            onClick={() => setActiveTab('saved')}
            count={savedApps.length}
            icon={<Bookmark className="h-4 w-4" />}
            label="Saved"
            controlsId="myapps-saved-panel"
          />
          <TabButton
            active={activeTab === 'recent'}
            onClick={() => setActiveTab('recent')}
            count={recentFiltered.length}
            icon={<Clock className="h-4 w-4" />}
            label="Recently Viewed"
            controlsId="myapps-recent-panel"
          />
        </div>

        {/* Content */}
        <div className="flex-1 overflow-y-auto scrollbar-thin">
          {error && (
            <div className="m-4 rounded-xl border border-error-200 bg-error-50 p-4 text-center">
              <div className="mx-auto mb-2 flex h-10 w-10 items-center justify-center rounded-full bg-error-100">
                <AlertTriangle className="h-5 w-5 text-error-600" />
              </div>
              <p className="text-sm font-semibold text-error-700">{error}</p>
              <button
                type="button"
                onClick={handleRetry}
                className="btn-secondary mt-3 inline-flex items-center gap-1.5"
              >
                <RotateCcw className="h-3.5 w-3.5" />
                Retry
              </button>
            </div>
          )}

          {!error && loadingMyApps && <MyAppsSkeleton />}

          {!error && !loadingMyApps && activeTab === 'saved' && (
            <div
              id="myapps-saved-panel"
              role="tabpanel"
              aria-labelledby="myapps-saved-tab"
            >
              {savedApps.length === 0 ? (
                <EmptyState
                  icon={<Bookmark className="h-8 w-8 text-slate-300" />}
                  title="No saved apps yet"
                  message="Save apps by clicking the bookmark icon on any planning application."
                />
              ) : (
                <div className="space-y-3 p-4">
                  {savedApps.map((app) => (
                    <SavedAppCard
                      key={app.id}
                      app={app}
                      removing={removingId === app.id}
                      onRemove={handleRemove}
                      onViewDetails={handleViewDetails}
                    />
                  ))}
                </div>
              )}
            </div>
          )}

          {!error && !loadingMyApps && activeTab === 'recent' && (
            <div
              id="myapps-recent-panel"
              role="tabpanel"
              aria-labelledby="myapps-recent-tab"
            >
              {recentFiltered.length === 0 ? (
                <EmptyState
                  icon={<Clock className="h-8 w-8 text-slate-300" />}
                  title="No recently viewed apps"
                  message="Applications you open will appear here automatically."
                />
              ) : (
                <div className="space-y-3 p-4">
                  {recentFiltered.map((app) => (
                    <RecentAppCard
                      key={app.id}
                      app={app}
                      saving={savingId === app.id}
                      onSave={handleSaveFromRecent}
                      onViewDetails={handleViewDetails}
                    />
                  ))}
                </div>
              )}
            </div>
          )}
        </div>
      </div>
    </div>
  )
}

function TabButton({
  active,
  onClick,
  count,
  icon,
  label,
  controlsId,
}: {
  active: boolean
  onClick: () => void
  count: number
  icon: React.ReactNode
  label: string
  controlsId: string
}) {
  return (
    <button
      type="button"
      id={`myapps-${controlsId.split('-')[1]}-tab`}
      role="tab"
      aria-selected={active}
      aria-controls={controlsId}
      onClick={onClick}
      className={`inline-flex flex-1 items-center justify-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition-colors ${
        active
          ? 'bg-white text-brand-700 shadow-soft'
          : 'text-slate-500 hover:bg-slate-100 hover:text-slate-700'
      }`}
    >
      {icon}
      <span>{label}</span>
      <span
        className={`inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded-full px-1.5 text-xs font-semibold ${
          active ? 'bg-brand-600 text-white' : 'bg-slate-200 text-slate-600'
        }`}
      >
        {count}
      </span>
    </button>
  )
}

function EmptyState({
  icon,
  title,
  message,
}: {
  icon: React.ReactNode
  title: string
  message: string
}) {
  return (
    <div className="flex flex-col items-center justify-center gap-3 px-6 py-20 text-center">
      <div className="flex h-16 w-16 items-center justify-center rounded-full bg-slate-100">
        {icon}
      </div>
      <h3 className="text-base font-semibold text-slate-700">{title}</h3>
      <p className="max-w-xs text-sm text-slate-500">{message}</p>
    </div>
  )
}
