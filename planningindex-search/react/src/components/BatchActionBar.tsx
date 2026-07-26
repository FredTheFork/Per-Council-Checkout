import { useState, useCallback, useEffect, useRef } from 'react'
import { Bookmark, Plus, Download, X, Loader as Loader2 } from 'lucide-react'
import { useSearchContext } from '../context/SearchContext'
import { useToast } from './ToastProvider'
import { exportAppsToCsv } from '../utils/csvExport'
import { usePrefersReducedMotion } from '../hooks/usePrefersReducedMotion'
import type { PlanningApp } from '../types'

type BatchOp = 'idle' | 'saving' | 'adding' | 'exporting'

export default function BatchActionBar() {
  const {
    selectedIds,
    rawApps,
    savedIds,
    workspaceIds,
    saveApp,
    addToWorkspace,
    clearSelection,
  } = useSearchContext()
  const { showToast } = useToast()
  const prefersReducedMotion = usePrefersReducedMotion()

  const [op, setOp] = useState<BatchOp>('idle')
  const [progress, setProgress] = useState({ done: 0, total: 0 })
  const [visible, setVisible] = useState(false)
  const prevSelectedRef = useRef(0)

  useEffect(() => {
    const hasSelection = selectedIds.size > 0
    if (hasSelection) {
      setVisible(true)
    } else if (prevSelectedRef.current > 0) {
      // Allow slide-down animation before unmount
      setVisible(false)
    }
    prevSelectedRef.current = selectedIds.size
  }, [selectedIds.size])

  const selectedApps = rawApps.filter((a) => selectedIds.has(a.id))
  const count = selectedIds.size

  const handleSaveAll = useCallback(async () => {
    if (op !== 'idle') return
    const toSave = selectedApps.filter((a) => !savedIds.has(a.id))
    if (toSave.length === 0) {
      showToast('All selected apps are already saved', { type: 'info' })
      return
    }
    setOp('saving')
    setProgress({ done: 0, total: toSave.length })
    let succeeded = 0
    let failed = 0
    for (let i = 0; i < toSave.length; i++) {
      try {
        await saveApp(toSave[i].id)
        succeeded++
      } catch {
        failed++
      }
      setProgress({ done: i + 1, total: toSave.length })
    }
    setOp('idle')
    if (failed === 0) {
      showToast(`${succeeded} application${succeeded !== 1 ? 's' : ''} saved`)
      clearSelection()
    } else {
      showToast(`${succeeded} saved, ${failed} failed`, { type: 'error' })
    }
  }, [op, selectedApps, savedIds, saveApp, showToast, clearSelection])

  const handleAddAllToWorkspace = useCallback(async () => {
    if (op !== 'idle') return
    const toAdd = selectedApps.filter((a) => !workspaceIds.has(a.id))
    if (toAdd.length === 0) {
      showToast('All selected apps are already in workspace', { type: 'info' })
      return
    }
    setOp('adding')
    setProgress({ done: 0, total: toAdd.length })
    let succeeded = 0
    let failed = 0
    for (let i = 0; i < toAdd.length; i++) {
      try {
        await addToWorkspace(toAdd[i].id)
        succeeded++
      } catch {
        failed++
      }
      setProgress({ done: i + 1, total: toAdd.length })
    }
    setOp('idle')
    if (failed === 0) {
      showToast(`${succeeded} application${succeeded !== 1 ? 's' : ''} added to workspace`)
      clearSelection()
    } else {
      showToast(`${succeeded} added, ${failed} failed`, { type: 'error' })
    }
  }, [op, selectedApps, workspaceIds, addToWorkspace, showToast, clearSelection])

  const handleExport = useCallback(() => {
    if (op !== 'idle') return
    if (selectedApps.length === 0) return
    setOp('exporting')
    try {
      exportAppsToCsv(selectedApps as PlanningApp[])
      showToast(`Exported ${selectedApps.length} application${selectedApps.length !== 1 ? 's' : ''} to CSV`)
    } catch {
      showToast('Could not export to CSV', { type: 'error' })
    } finally {
      setOp('idle')
    }
  }, [op, selectedApps, showToast])

  if (count === 0 && !visible) return null

  const busy = op !== 'idle'
  const progressLabel = busy && progress.total > 0
    ? `${progress.done} of ${progress.total}…`
    : ''

  return (
    <div
      className={`fixed bottom-6 left-1/2 z-40 -translate-x-1/2 transition-all duration-300 ease-out ${
        visible && count > 0
          ? 'translate-y-0 opacity-100'
          : 'translate-y-4 opacity-0 pointer-events-none'
      }`}
      style={prefersReducedMotion ? { transition: 'none' } : undefined}
    >
      <div
        role="toolbar"
        aria-label="Batch actions for selected applications"
        className="flex items-center gap-2 rounded-2xl bg-white px-3 py-2.5 shadow-elevated ring-1 ring-slate-200"
      >
        <span className="flex-shrink-0 pl-1 pr-2 text-sm font-semibold text-slate-700">
          {count} selected
        </span>

        <div className="h-6 w-px bg-slate-200" />

        <button
          type="button"
          onClick={handleSaveAll}
          disabled={busy}
          aria-label={`Save all ${count} selected applications`}
          className="inline-flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-100 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {op === 'saving' ? (
            <Loader2 className="h-4 w-4 animate-spin" />
          ) : (
            <Bookmark className="h-4 w-4" />
          )}
          <span className="hidden sm:inline">
            {op === 'saving' ? progressLabel : `Save all (${count})`}
          </span>
        </button>

        <button
          type="button"
          onClick={handleAddAllToWorkspace}
          disabled={busy}
          aria-label={`Add all ${count} selected applications to workspace`}
          className="inline-flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-100 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {op === 'adding' ? (
            <Loader2 className="h-4 w-4 animate-spin" />
          ) : (
            <Plus className="h-4 w-4" />
          )}
          <span className="hidden sm:inline">
            {op === 'adding' ? progressLabel : `Add to workspace (${count})`}
          </span>
        </button>

        <button
          type="button"
          onClick={handleExport}
          disabled={busy}
          aria-label={`Export ${count} selected applications to CSV`}
          className="inline-flex items-center gap-1.5 rounded-xl bg-brand-600 px-3 py-1.5 text-sm font-semibold text-white transition-colors hover:bg-brand-700 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {op === 'exporting' ? (
            <Loader2 className="h-4 w-4 animate-spin" />
          ) : (
            <Download className="h-4 w-4" />
          )}
          <span className="hidden sm:inline">
            {op === 'exporting' ? 'Exporting…' : `Export (${count}) to CSV`}
          </span>
        </button>

        <div className="h-6 w-px bg-slate-200" />

        <button
          type="button"
          onClick={clearSelection}
          disabled={busy}
          aria-label="Clear selection"
          className="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600 disabled:opacity-50"
        >
          <X className="h-4 w-4" />
        </button>
      </div>
    </div>
  )
}
