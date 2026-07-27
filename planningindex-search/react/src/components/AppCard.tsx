import { useState, useCallback } from 'react'
import {
  Bookmark,
  BookmarkCheck,
  Plus,
  Check,
  Eye,
  Calendar,
  Hash,
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
  index?: number
  getTabIndex?: (index: number) => number | undefined
  setItemRef?: (index: number) => (el: HTMLElement | null) => void
  onKeyDown?: (e: React.KeyboardEvent, index: number) => void
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
  index = 0,
  getTabIndex,
  setItemRef,
  onKeyDown,
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

  const handleKeyDown = useCallback((e: React.KeyboardEvent) => {
    if (e.key === 'Enter') {
      e.preventDefault()
      onViewDetails(app)
    } else if (e.key === ' ') {
      e.preventDefault()
      onToggleSelect(app.id)
    } else if (onKeyDown) {
      onKeyDown(e, index)
    }
  }, [onViewDetails, onToggleSelect, app, onKeyDown, index])

  const ariaLabel = [
    councilName,
    address,
    estPrice ? `estimated ${estPrice}` : '',
    dateReceived ? `received ${dateReceived}` : '',
    `${tierColor.label}, score ${score}`,
  ].filter(Boolean).join(', ')

  return (
    <div
      ref={setItemRef?.(index)}
      tabIndex={getTabIndex?.(index) ?? -1}
      onKeyDown={handleKeyDown}
      role="article"
      aria-label={ariaLabel}
      className={`card group relative flex flex-col overflow-hidden transition-shadow duration-200 hover:shadow-elevated focus-visible:ring-2 focus-visible:ring-accent-500 focus-visible:ring-offset-2 ${
        selected ? 'ring-2 ring-accent-500' : ''
      }`}
    >
      {/* Selection indicator bar */}
      {selected && (
        <div className="absolute left-0 top-0 h-full w-1 bg-accent-500" />
      )}

      {/* Checkbox */}
      <div className="absolute left-3 top-3 z-10">
        <label className={`flex cursor-pointer items-center transition-opacity ${
          selected ? 'opacity-100' : 'opacity-0 group-hover:opacity-100 focus-within:opacity-100'
        }`}>
          <input
            type="checkbox"
            checked={selected}
            onChange={() => onToggleSelect(app.id)}
            className="h-4 w-4 rounded border-slate-300 text-accent-600 focus:ring-accent-500"
            aria-label={`Select ${address}`}
          />
        </label>
      </div>

      <div className="flex flex-1 flex-col p-5">
        {/* Council + lead score */}
        <div className="flex items-center gap-2">
          <span className="text-xs font-medium uppercase tracking-wide text-slate-500 line-clamp-1">
            {councilName}
          </span>
          <span
            className={`inline-flex h-2 w-2 flex-shrink-0 rounded-full ${tierColor.dot}`}
            title={`${tierColor.label}, score ${score}`}
            aria-label={`${tierColor.label}, score ${score}`}
          />
        </div>

        {/* Address (title) */}
        <h3 className="mt-1.5 line-clamp-2 text-base font-semibold text-slate-900">
          {address}
        </h3>

        {/* Value + freshness + high-value tag */}
        <div className="mt-2.5 flex flex-wrap items-center gap-2">
          {estPrice && (
            <span className={`inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-sm font-semibold ${
              highValue ? 'bg-brand-700 text-white' : 'bg-slate-100 text-slate-700'
            }`}>
              {estPrice}
            </span>
          )}
          {highValue && !estPrice && (
            <span className="badge bg-brand-700 text-white">High Value</span>
          )}
          {freshness && (
            <span className={`badge ${
              freshness === 'New today'
                ? 'bg-accent-50 text-accent-700'
                : 'bg-slate-100 text-slate-600'
            }`}>
              {freshness}
            </span>
          )}
        </div>

        {/* Meta row */}
        <div className="mt-2.5 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500">
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
          <p className="mt-2.5 line-clamp-3 text-sm leading-relaxed text-slate-600">
            {description}
          </p>
        )}

        {/* Spacer */}
        <div className="flex-1" />

        {/* Action row */}
        <div className="mt-4 flex items-center gap-1 border-t border-slate-100 pt-3">
          <button
            type="button"
            onClick={handleSave}
            disabled={savePending}
            className="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1.5 text-xs font-medium text-slate-600 transition-colors hover:bg-slate-100 disabled:opacity-50"
            aria-label={saved ? `Unsave ${address}` : `Save ${address}`}
            title={saved ? 'Saved' : 'Save'}
          >
            {saved ? (
              <BookmarkCheck className="h-4 w-4 text-accent-600" />
            ) : (
              <Bookmark className="h-4 w-4" />
            )}
            <span className="hidden sm:inline">{saved ? 'Saved' : 'Save'}</span>
          </button>

          <button
            type="button"
            onClick={handleAddToWorkspace}
            disabled={wsPending || inWorkspace}
            className="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1.5 text-xs font-medium text-slate-600 transition-colors hover:bg-slate-100 disabled:opacity-50"
            aria-label={inWorkspace ? `${address} already in workspace` : `Add ${address} to workspace`}
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
            className="ml-auto inline-flex items-center gap-1.5 rounded-md px-2.5 py-1.5 text-xs font-medium text-brand-700 transition-colors hover:bg-brand-50"
            aria-label={`View details for ${address}`}
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
