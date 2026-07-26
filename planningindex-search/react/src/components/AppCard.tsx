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
} from 'lucide-react'
import type { PlanningApp } from '../types'
import {
  titleCaseAddress,
  getEstPrice,
  isHighValue,
  formatDate,
} from '../utils/format'
import { formatFreshness } from '../utils/freshness'
import { getLeadTier, LEAD_TIER_COLORS } from '../utils/leadScoreHelpers'

interface AppCardProps {
  app: PlanningApp
  saved: boolean
  inWorkspace: boolean
  selected: boolean
  onToggleSave: (id: number) => void
  onAddToWorkspace: (id: number) => void
  onToggleSelect: (id: number) => void
  onViewDetails: (app: PlanningApp) => void
}

function stripHtml(html: string): string {
  if (!html) return ''
  const tmp = document.createElement('div')
  tmp.innerHTML = html
  return tmp.textContent || tmp.innerText || ''
}

function truncate(s: string, max: number): string {
  if (s.length <= max) return s
  return s.slice(0, max).trimEnd() + '…'
}

export default function AppCard({
  app,
  saved,
  inWorkspace,
  selected,
  onToggleSave,
  onAddToWorkspace,
  onToggleSelect,
  onViewDetails,
}: AppCardProps) {
  const [savePending, setSavePending] = useState(false)
  const [wsPending, setWsPending] = useState(false)

  const score = app.score ?? 0
  const tier = getLeadTier(score)
  const tierColor = LEAD_TIER_COLORS[tier]

  const address = titleCaseAddress(app.meta?.address || app.title?.rendered || 'No address')
  const councilName = app._authority_name || 'Unknown council'
  const estPrice = getEstPrice(app.meta)
  const highValue = isHighValue(app.meta)
  const freshness = formatFreshness(app.meta?.date_received || '')
  const dateReceived = formatDate(app.meta?.date_received || '')
  const councilRef = app.meta?.council_reference || ''
  const description = truncate(stripHtml(app.content?.rendered || ''), 150)

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
    <div
      className={`card group relative flex flex-col overflow-hidden transition-all duration-200 hover:-translate-y-0.5 hover:shadow-elevated ${
        selected ? 'ring-2 ring-brand-500' : ''
      }`}
    >
      {/* High-value ribbon */}
      {highValue && (
        <div className="absolute right-0 top-0 z-10">
          <div className="bg-accent-500 px-3 py-1 text-xs font-bold text-white shadow-soft">
            High Value
          </div>
        </div>
      )}

      {/* Checkbox */}
      <div className="absolute left-3 top-3 z-10">
        <label className={`flex cursor-pointer items-center transition-opacity ${
          selected ? 'opacity-100' : 'opacity-0 group-hover:opacity-100'
        }`}>
          <input
            type="checkbox"
            checked={selected}
            onChange={() => onToggleSelect(app.id)}
            className="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500"
            aria-label={`Select ${address}`}
          />
        </label>
      </div>

      <div className="flex flex-1 flex-col p-5">
        {/* Council + lead score */}
        <div className="flex items-center gap-2">
          <span className="text-xs font-semibold uppercase tracking-wide text-accent-600 line-clamp-1">
            {councilName}
          </span>
          <span
            className={`inline-flex h-2.5 w-2.5 flex-shrink-0 rounded-full ${tierColor.dot}`}
            title={tierColor.label}
            aria-label={tierColor.label}
          />
        </div>

        {/* Address (title) */}
        <h3 className="mt-2 line-clamp-2 text-base font-semibold text-slate-900">
          {address}
        </h3>

        {/* Value badge + freshness chip */}
        <div className="mt-3 flex flex-wrap items-center gap-2">
          {estPrice && (
            <span className="inline-flex items-center gap-1 rounded-full bg-brand-600 px-3 py-1 text-sm font-bold text-white">
              <PoundSterling className="h-3.5 w-3.5" />
              {estPrice}
            </span>
          )}
          {freshness && (
            <span className={`inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ${freshnessChipColor}`}>
              {freshness}
            </span>
          )}
        </div>

        {/* Meta row */}
        <div className="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500">
          {dateReceived && (
            <span className="inline-flex items-center gap-1">
              <Calendar className="h-3.5 w-3.5" />
              {dateReceived}
            </span>
          )}
          {councilRef && (
            <span className="inline-flex items-center gap-1">
              <Hash className="h-3.5 w-3.5" />
              {councilRef}
            </span>
          )}
        </div>

        {/* Description */}
        {description && (
          <p className="mt-3 line-clamp-3 text-sm leading-relaxed text-slate-600">
            {description}
          </p>
        )}

        {/* Spacer */}
        <div className="flex-1" />

        {/* Action row */}
        <div className="mt-4 flex items-center gap-2 border-t border-slate-100 pt-3">
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
            <span className="hidden sm:inline">{saved ? 'Saved' : 'Save'}</span>
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
                <span className="hidden sm:inline text-success-600">Added</span>
              </>
            ) : (
              <>
                <Plus className="h-4 w-4" />
                <span className="hidden sm:inline">Add</span>
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
            <span className="hidden sm:inline">Details</span>
          </button>
        </div>
      </div>
    </div>
  )
}
