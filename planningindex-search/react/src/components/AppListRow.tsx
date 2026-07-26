import { useState, useCallback } from 'react'
import {
  Bookmark,
  BookmarkCheck,
  Plus,
  Check,
  Eye,
} from 'lucide-react'
import type { PlanningApp } from '../types'
import {
  titleCaseAddress,
  getEstPrice,
  isHighValue,
  formatDate,
} from '../utils/format'
import { getLeadTier, LEAD_TIER_COLORS } from '../utils/leadScoreHelpers'

interface AppListRowProps {
  app: PlanningApp
  saved: boolean
  inWorkspace: boolean
  selected: boolean
  onToggleSave: (id: number) => void
  onAddToWorkspace: (id: number) => void
  onToggleSelect: (id: number) => void
  onViewDetails: (app: PlanningApp) => void
}

export default function AppListRow({
  app,
  saved,
  inWorkspace,
  selected,
  onToggleSave,
  onAddToWorkspace,
  onToggleSelect,
  onViewDetails,
}: AppListRowProps) {
  const [savePending, setSavePending] = useState(false)
  const [wsPending, setWsPending] = useState(false)

  const score = app.score ?? 0
  const tier = getLeadTier(score)
  const tierColor = LEAD_TIER_COLORS[tier]

  const address = titleCaseAddress(app.meta?.address || app.title?.rendered || 'No address')
  const councilName = app._authority_name || 'Unknown'
  const estPrice = getEstPrice(app.meta)
  const highValue = isHighValue(app.meta)
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

  return (
    <div
      className={`group flex items-center gap-3 border-b border-slate-100 px-4 py-2.5 transition-colors hover:bg-slate-50 ${
        selected ? 'bg-brand-50' : ''
      } ${highValue ? 'border-l-4 border-l-accent-500' : ''}`}
    >
      {/* Checkbox */}
      <label className="flex flex-shrink-0 cursor-pointer items-center">
        <input
          type="checkbox"
          checked={selected}
          onChange={() => onToggleSelect(app.id)}
          className="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500"
          aria-label={`Select ${address}`}
        />
      </label>

      {/* Lead score dot */}
      <span
        className={`inline-flex h-2.5 w-2.5 flex-shrink-0 rounded-full ${tierColor.dot}`}
        title={tierColor.label}
        aria-label={tierColor.label}
      />

      {/* Council */}
      <span className="hidden w-28 flex-shrink-0 truncate text-xs font-medium text-slate-500 md:block">
        {councilName}
      </span>

      {/* Address */}
      <span
        className="flex-1 cursor-pointer truncate text-sm font-semibold text-slate-900"
        onClick={() => onViewDetails(app)}
        title={address}
      >
        {address}
      </span>

      {/* Value */}
      <span className="hidden w-24 flex-shrink-0 text-right text-sm font-semibold text-slate-700 sm:block">
        {estPrice ? (
          <span className={highValue ? 'text-accent-600' : ''}>{estPrice}</span>
        ) : (
          <span className="text-slate-300">—</span>
        )}
      </span>

      {/* Date */}
      <span className="hidden w-24 flex-shrink-0 text-xs text-slate-500 lg:block">
        {dateReceived}
      </span>

      {/* Lead score number */}
      <span className={`hidden w-10 flex-shrink-0 text-right text-xs font-bold ${tierColor.text} lg:block`}>
        {score}
      </span>

      {/* Reference */}
      <span className="hidden w-28 flex-shrink-0 truncate text-xs text-slate-400 xl:block">
        {councilRef}
      </span>

      {/* Actions */}
      <div className="flex flex-shrink-0 items-center gap-1">
        <button
          type="button"
          onClick={handleSave}
          disabled={savePending}
          className="rounded p-1.5 text-slate-400 transition-colors hover:bg-slate-200 hover:text-slate-600 disabled:opacity-50"
          aria-label={saved ? 'Unsave application' : 'Save application'}
          title={saved ? 'Saved' : 'Save'}
        >
          {saved ? (
            <BookmarkCheck className="h-4 w-4 text-brand-600" />
          ) : (
            <Bookmark className="h-4 w-4" />
          )}
        </button>

        <button
          type="button"
          onClick={handleAddToWorkspace}
          disabled={wsPending || inWorkspace}
          className="rounded p-1.5 text-slate-400 transition-colors hover:bg-slate-200 hover:text-slate-600 disabled:opacity-50"
          aria-label={inWorkspace ? 'In workspace' : 'Add to workspace'}
          title={inWorkspace ? 'Added' : 'Add to workspace'}
        >
          {inWorkspace ? (
            <Check className="h-4 w-4 text-success-600" />
          ) : (
            <Plus className="h-4 w-4" />
          )}
        </button>

        <button
          type="button"
          onClick={() => onViewDetails(app)}
          className="rounded p-1.5 text-slate-400 transition-colors hover:bg-slate-200 hover:text-brand-600"
          aria-label="View details"
          title="Details"
        >
          <Eye className="h-4 w-4" />
        </button>
      </div>
    </div>
  )
}
