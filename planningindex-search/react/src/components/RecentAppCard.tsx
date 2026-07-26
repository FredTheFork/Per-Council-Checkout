import { Bookmark, Eye, Calendar, Hash, PoundSterling, Clock } from 'lucide-react'
import type { UserApp } from '../types'
import {
  titleCaseAddress,
  getEstPrice,
  formatDate,
} from '../utils/format'

interface RecentAppCardProps {
  app: UserApp
  saving: boolean
  onSave: (id: number) => void
  onViewDetails: (id: number) => void
}

export default function RecentAppCard({
  app,
  saving,
  onSave,
  onViewDetails,
}: RecentAppCardProps) {
  const address = titleCaseAddress(
    app.meta?.address || app.title?.rendered || 'No address',
  )
  const councilName = app._authority_name || 'Unknown council'
  const estPrice = getEstPrice(app.meta)
  const dateReceived = formatDate(app.meta?.date_received || '')
  const councilRef = app.meta?.council_reference || ''

  return (
    <div
      className={`rounded-xl bg-white p-3 ring-1 ring-slate-200/60 transition-all duration-300 ease-out ${
        saving
          ? '-translate-y-2 opacity-0'
          : 'translate-y-0 opacity-100 hover:bg-slate-50'
      }`}
    >
      <div className="flex items-start gap-2">
        <div className="min-w-0 flex-1">
          <span className="line-clamp-1 text-xs font-semibold uppercase tracking-wide text-accent-600">
            {councilName}
          </span>
          <h4 className="mt-0.5 line-clamp-1 text-sm font-semibold text-slate-900">
            {address}
          </h4>
        </div>
        <button
          type="button"
          onClick={() => onSave(app.id)}
          className="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-lg text-slate-400 transition-colors hover:bg-accent-50 hover:text-accent-600"
          aria-label={`Save ${councilName} — ${address}`}
          title="Save"
        >
          <Bookmark className="h-4 w-4" />
        </button>
      </div>

      <div className="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500">
        {estPrice && (
          <span className="inline-flex items-center gap-1 rounded-full bg-brand-600 px-2 py-0.5 text-xs font-bold text-white">
            <PoundSterling className="h-3 w-3" />
            {estPrice}
          </span>
        )}
        {dateReceived && (
          <span className="inline-flex items-center gap-1">
            <Calendar className="h-3 w-3" />
            {dateReceived}
          </span>
        )}
        {councilRef && (
          <span className="inline-flex items-center gap-1 line-clamp-1">
            <Hash className="h-3 w-3" />
            {councilRef}
          </span>
        )}
      </div>

      <div className="mt-2.5 flex items-center gap-2">
        <button
          type="button"
          onClick={() => onViewDetails(app.id)}
          className="inline-flex flex-1 items-center justify-center gap-1.5 rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-700 transition-colors hover:bg-brand-50 hover:text-brand-600"
          aria-label={`View details for ${address}`}
        >
          <Eye className="h-3.5 w-3.5" />
          View details
        </button>
        {app.timestamp && (
          <span
            className="inline-flex items-center gap-1 text-xs text-slate-400"
            title="Last viewed"
          >
            <Clock className="h-3 w-3" />
          </span>
        )}
      </div>
    </div>
  )
}
