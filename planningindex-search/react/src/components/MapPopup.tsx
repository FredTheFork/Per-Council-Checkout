import { useState, useCallback } from 'react'
import {
  Bookmark,
  BookmarkCheck,
  Plus,
  Check,
  Eye,
  Calendar,
  Hash,
  PoundSterling,
  X,
} from 'lucide-react'
import type { PlanningApp } from '../types'
import {
  titleCaseAddress,
  getEstPrice,
  isHighValue,
  formatDate,
} from '../utils/format'
import { formatFreshness } from '../utils/freshness'

interface MapPopupProps {
  app: PlanningApp
  saved: boolean
  inWorkspace: boolean
  onToggleSave: (id: number) => void
  onAddToWorkspace: (id: number) => void
  onViewDetails: (app: PlanningApp) => void
  onClose: () => void
}

export default function MapPopup({
  app,
  saved,
  inWorkspace,
  onToggleSave,
  onAddToWorkspace,
  onViewDetails,
  onClose,
}: MapPopupProps) {
  const [savePending, setSavePending] = useState(false)
  const [wsPending, setWsPending] = useState(false)

  const address = titleCaseAddress(app.meta?.address || app.title?.rendered || 'No address')
  const councilName = app._authority_name || 'Unknown council'
  const estPrice = getEstPrice(app.meta)
  const highValue = isHighValue(app.meta)
  const freshness = formatFreshness(app.meta?.date_received || '')
  const dateReceived = formatDate(app.meta?.date_received || '')
  const councilRef = app.meta?.council_reference || ''

  const handleSave = useCallback(() => {
    if (savePending) return
    setSavePending(true)
    Promise.resolve(onToggleSave(app.id)).finally(() => setSavePending(false))
  }, [savePending, onToggleSave, app.id])

  const handleAddToWorkspace = useCallback(() => {
    if (wsPending || inWorkspace) return
    setWsPending(true)
    Promise.resolve(onAddToWorkspace(app.id)).finally(() => setWsPending(false))
  }, [wsPending, inWorkspace, onAddToWorkspace, app.id])

  const freshnessChipColor = freshness === 'New today'
    ? 'bg-success-100 text-success-700'
    : freshness === 'This week'
      ? 'bg-accent-100 text-accent-700'
      : 'bg-slate-100 text-slate-600'

  return (
    <div className="w-[300px] max-w-[320px] rounded-xl bg-white shadow-elevated ring-1 ring-slate-200/60">
      {/* Header */}
      <div className="relative flex items-start justify-between p-4 pb-3">
        <div className="min-w-0 pr-8">
          <span className="text-xs font-semibold uppercase tracking-wide text-accent-600 line-clamp-1">
            {councilName}
          </span>
          <h3 className="mt-1 line-clamp-2 text-sm font-semibold text-slate-900">
            {address}
          </h3>
        </div>
        <button
          type="button"
          onClick={onClose}
          className="absolute right-2 top-2 flex h-7 w-7 items-center justify-center rounded-full text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600"
          aria-label="Close popup"
        >
          <X className="h-4 w-4" />
        </button>
      </div>

      {/* Badges */}
      <div className="flex flex-wrap items-center gap-2 px-4 pb-3">
        {estPrice && (
          <span className="inline-flex items-center gap-1 rounded-full bg-brand-600 px-2.5 py-1 text-xs font-bold text-white">
            <PoundSterling className="h-3 w-3" />
            {estPrice}
          </span>
        )}
        {highValue && (
          <span className="inline-flex items-center rounded-full bg-accent-500 px-2.5 py-1 text-xs font-bold text-white">
            High Value
          </span>
        )}
        {freshness && (
          <span className={`inline-flex items-center rounded-full px-2 py-1 text-xs font-medium ${freshnessChipColor}`}>
            {freshness}
          </span>
        )}
      </div>

      {/* Meta */}
      <div className="flex flex-wrap items-center gap-x-3 gap-y-1 px-4 pb-3 text-xs text-slate-500">
        {dateReceived && (
          <span className="inline-flex items-center gap-1">
            <Calendar className="h-3 w-3" />
            {dateReceived}
          </span>
        )}
        {councilRef && (
          <span className="inline-flex items-center gap-1">
            <Hash className="h-3 w-3" />
            {councilRef}
          </span>
        )}
      </div>

      {/* Actions */}
      <div className="flex items-center gap-2 border-t border-slate-100 p-3">
        <button
          type="button"
          onClick={handleSave}
          disabled={savePending}
          className="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium text-slate-600 transition-colors hover:bg-slate-100 disabled:opacity-50"
          aria-label={saved ? 'Unsave application' : 'Save application'}
          title={saved ? 'Saved' : 'Save'}
        >
          {saved ? (
            <BookmarkCheck className="h-4 w-4 text-brand-600" />
          ) : (
            <Bookmark className="h-4 w-4" />
          )}
          <span>{saved ? 'Saved' : 'Save'}</span>
        </button>

        <button
          type="button"
          onClick={handleAddToWorkspace}
          disabled={wsPending || inWorkspace}
          className="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium text-slate-600 transition-colors hover:bg-slate-100 disabled:opacity-50"
          aria-label={inWorkspace ? 'In workspace' : 'Add to workspace'}
          title={inWorkspace ? 'Added' : 'Add to workspace'}
        >
          {inWorkspace ? (
            <>
              <Check className="h-4 w-4 text-success-600" />
              <span className="text-success-600">Added</span>
            </>
          ) : (
            <>
              <Plus className="h-4 w-4" />
              <span>Add</span>
            </>
          )}
        </button>

        <button
          type="button"
          onClick={() => onViewDetails(app)}
          className="ml-auto inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium text-brand-600 transition-colors hover:bg-brand-50"
          aria-label="View details"
          title="Details"
        >
          <Eye className="h-4 w-4" />
          <span>Details</span>
        </button>
      </div>
    </div>
  )
}
