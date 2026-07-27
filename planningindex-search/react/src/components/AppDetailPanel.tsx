import { useEffect, useState, useCallback, useRef } from 'react'
import { X, Bookmark, BookmarkCheck, Plus, Check, ExternalLink, Copy, Calendar, Hash, PoundSterling, FileText, CircleDot, Gavel, Sparkles, HardHat, Loader as Loader2, TriangleAlert as AlertTriangle, RotateCcw, ClipboardList, StickyNote } from 'lucide-react'
import { useSearchContext } from '../context/SearchContext'
import { useAppDetail } from '../hooks/useAppDetail'
import {
  titleCaseAddress,
  getEstPrice,
  isHighValue,
  isConstructionJob,
  formatDate,
} from '../utils/format'
import { formatFreshness } from '../utils/freshness'
import { getLeadTier, LEAD_TIER_COLORS } from '../utils/leadScoreHelpers'
import { LEAD_STATUS_META, LEAD_STATUS_ORDER } from '../utils/leadStatus'
import type { LeadStatus } from '../types'

export default function AppDetailPanel() {
  const {
    selectedAppId,
    closeDetailPanel,
    savedIds,
    workspaceIds,
    saveApp,
    unsaveApp,
    addToWorkspace,
  } = useSearchContext()

  const { app, loading, error, retry } = useAppDetail(selectedAppId)
  const [savePending, setSavePending] = useState(false)
  const [wsPending, setWsPending] = useState(false)
  const [copied, setCopied] = useState(false)
  const [visible, setVisible] = useState(false)
  const panelRef = useRef<HTMLDivElement | null>(null)
  const prevFocusRef = useRef<HTMLElement | null>(null)

  // Slide-in animation + body scroll lock
  useEffect(() => {
    if (selectedAppId !== null) {
      prevFocusRef.current = document.activeElement as HTMLElement | null
      requestAnimationFrame(() => setVisible(true))
      document.body.style.overflow = 'hidden'
      // Focus the panel
      panelRef.current?.focus()
    } else {
      setVisible(false)
      document.body.style.overflow = ''
      prevFocusRef.current?.focus()
    }
    return () => {
      document.body.style.overflow = ''
    }
  }, [selectedAppId])

  // Escape to close
  useEffect(() => {
    if (selectedAppId === null) return
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') closeDetailPanel()
    }
    document.addEventListener('keydown', onKey)
    return () => document.removeEventListener('keydown', onKey)
  }, [selectedAppId, closeDetailPanel])

  const handleSave = useCallback(() => {
    if (!app || savePending) return
    setSavePending(true)
    const action = savedIds.has(app.id) ? unsaveApp(app.id) : saveApp(app.id)
    Promise.resolve(action).finally(() => setSavePending(false))
  }, [app, savePending, savedIds, saveApp, unsaveApp])

  const handleAddToWorkspace = useCallback(() => {
    if (!app || wsPending || workspaceIds.has(app.id)) return
    setWsPending(true)
    Promise.resolve(addToWorkspace(app.id)).finally(() => setWsPending(false))
  }, [app, wsPending, workspaceIds, addToWorkspace])

  const handleCopyRef = useCallback(() => {
    if (!app?.meta?.council_reference) return
    navigator.clipboard.writeText(app.meta.council_reference).then(() => {
      setCopied(true)
      setTimeout(() => setCopied(false), 2000)
    }).catch(() => {
      // Fallback for older browsers
      const textarea = document.createElement('textarea')
      textarea.value = app.meta.council_reference
      document.body.appendChild(textarea)
      textarea.select()
      try { document.execCommand('copy') } catch { /* noop */ }
      document.body.removeChild(textarea)
      setCopied(true)
      setTimeout(() => setCopied(false), 2000)
    })
  }, [app])

  if (selectedAppId === null) return null

  const saved = app ? savedIds.has(app.id) : false
  const inWorkspace = app ? workspaceIds.has(app.id) : false

  return (
    <div
      className="fixed inset-0 z-50 flex justify-end"
      role="dialog"
      aria-modal="true"
      aria-label="Application details"
    >
      {/* Backdrop */}
      <button
        type="button"
        aria-label="Close detail panel"
        onClick={closeDetailPanel}
        className={`absolute inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity duration-300 ${
          visible ? 'opacity-100' : 'opacity-0'
        }`}
      />

      {/* Panel */}
      <div
        ref={panelRef}
        tabIndex={-1}
        onClick={(e) => e.stopPropagation()}
        className={`relative flex h-full w-full flex-col bg-white shadow-2xl transition-transform duration-300 ease-out focus:outline-none sm:max-w-md ${
          visible ? 'translate-x-0' : 'translate-x-full'
        }`}
      >
        {/* Sticky header */}
        <div className="flex items-center justify-between border-b border-slate-200 px-5 py-4">
          <h2 className="font-display text-sm font-bold uppercase tracking-wide text-slate-500">
            Application Details
          </h2>
          <button
            type="button"
            onClick={closeDetailPanel}
            aria-label="Close"
            className="flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600"
          >
            <X className="h-5 w-5" />
          </button>
        </div>

        {/* Content */}
        <div className="flex-1 overflow-y-auto">
          {loading && (
            <div className="flex flex-col items-center justify-center gap-3 py-20">
              <Loader2 className="h-8 w-8 animate-spin text-brand-600" />
              <p className="text-sm font-medium text-slate-600">Loading application…</p>
            </div>
          )}

          {error && !loading && (
            <div className="mx-5 mt-6 rounded-xl border border-error-200 bg-error-50 p-6 text-center">
              <div className="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-error-100">
                <AlertTriangle className="h-6 w-6 text-error-600" />
              </div>
              <p className="text-sm font-semibold text-error-700">Couldn't load this application</p>
              <p className="mt-1 text-xs text-error-600">{error}</p>
              <button
                type="button"
                onClick={retry}
                className="btn-secondary mt-4 inline-flex items-center gap-1.5"
              >
                <RotateCcw className="h-3.5 w-3.5" />
                Retry
              </button>
            </div>
          )}

          {app && !loading && !error && (
            <DetailContent app={app} saved={saved} inWorkspace={inWorkspace} />
          )}
        </div>

        {/* Sticky action bar */}
        {app && !loading && !error && (
          <div className="border-t border-slate-200 bg-white/95 px-5 py-3 backdrop-blur-md">
            <div className="flex flex-wrap items-center gap-2">
              <button
                type="button"
                onClick={handleSave}
                disabled={savePending}
                className="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-100 disabled:opacity-50"
                aria-label={saved ? 'Unsave application' : 'Save application'}
              >
                {saved ? (
                  <BookmarkCheck className="h-4 w-4 text-brand-600" />
                ) : (
                  <Bookmark className="h-4 w-4" />
                )}
                {saved ? 'Saved' : 'Save'}
              </button>

              <button
                type="button"
                onClick={handleAddToWorkspace}
                disabled={wsPending || inWorkspace}
                className="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-100 disabled:opacity-50"
                aria-label={inWorkspace ? 'In workspace' : 'Add to workspace'}
              >
                {inWorkspace ? (
                  <>
                    <Check className="h-4 w-4 text-success-600" />
                    <span className="text-success-600">Added</span>
                  </>
                ) : (
                  <>
                    <Plus className="h-4 w-4" />
                    Add
                  </>
                )}
              </button>

              <div className="ml-auto flex items-center gap-2">
                {app.meta?.council_reference && (
                  <button
                    type="button"
                    onClick={handleCopyRef}
                    className="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-100"
                    aria-label="Copy council reference"
                    title="Copy reference to clipboard"
                  >
                    {copied ? (
                      <>
                        <Check className="h-4 w-4 text-success-600" />
                        <span className="text-success-600">Copied!</span>
                      </>
                    ) : (
                      <>
                        <Copy className="h-4 w-4" />
                        <span className="hidden sm:inline">Copy Ref</span>
                      </>
                    )}
                  </button>
                )}

                {app.meta?.info_url && (
                  <button
                    type="button"
                    onClick={() => window.open(app.meta!.info_url, '_blank', 'noopener,noreferrer')}
                    className="inline-flex items-center gap-1.5 rounded-lg bg-brand-600 px-3 py-2 text-sm font-medium text-white transition-colors hover:bg-brand-700"
                    aria-label="View on council website"
                  >
                    <ExternalLink className="h-4 w-4" />
                    <span className="hidden sm:inline">Council Site</span>
                  </button>
                )}
              </div>
            </div>
          </div>
        )}
      </div>
    </div>
  )
}

function DetailContent({
  app,
  saved,
  inWorkspace,
}: {
  app: NonNullable<ReturnType<typeof useAppDetail>['app']>
  saved: boolean
  inWorkspace: boolean
}) {
  const address = titleCaseAddress(app.meta?.address || app.title?.rendered || 'No address')
  const councilName = app._authority_name || 'Unknown council'
  const estPrice = getEstPrice(app.meta)
  const highValue = isHighValue(app.meta)
  const construction = isConstructionJob(app.meta)
  const freshness = formatFreshness(app.meta?.date_received || '')
  const dateReceived = formatDate(app.meta?.date_received || '')
  const councilRef = app.meta?.council_reference || ''
  const status = app.meta?.status || ''
  const decision = app.meta?.decision || ''
  const aiBadge = app.meta?.ai_badge || ''
  const score = app.score ?? 0
  const tier = getLeadTier(score)
  const tierColor = LEAD_TIER_COLORS[tier]

  const freshnessChipColor = freshness === 'New today'
    ? 'bg-success-100 text-success-700'
    : freshness === 'This week'
      ? 'bg-accent-100 text-accent-700'
      : 'bg-slate-100 text-slate-600'

  const description = app.content?.rendered || ''

  return (
    <div className="flex flex-col">
      {/* Hero section */}
      <div className="px-5 pt-5">
        <span className="text-[11px] font-semibold uppercase tracking-[0.12em] text-accent-600">
          {councilName}
        </span>
        <h1 className="mt-1.5 font-display text-xl font-semibold leading-snug tracking-tight text-slate-900">
          {address}
        </h1>

        {/* Badges */}
        <div className="mt-3 flex flex-wrap items-center gap-2">
          {/* Lead score badge */}
          <span
            className={`inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold ${tierColor.bg} ${tierColor.text}`}
          >
            <span className={`h-2 w-2 rounded-full ${tierColor.dot}`} />
            {tierColor.label} · {score}
          </span>

          {/* Value badge */}
          {estPrice && (
            <span className="inline-flex items-center gap-1 rounded-full bg-brand-600 px-3 py-1 text-xs font-bold text-white">
              <PoundSterling className="h-3 w-3" />
              {estPrice}
            </span>
          )}

          {/* High value tag */}
          {highValue && (
            <span className="inline-flex items-center rounded-full bg-accent-500 px-3 py-1 text-xs font-bold text-white">
              High Value
            </span>
          )}

          {/* Freshness badge */}
          {freshness && (
            <span className={`inline-flex items-center rounded-full px-3 py-1 text-xs font-medium ${freshnessChipColor}`}>
              {freshness}
            </span>
          )}
        </div>
      </div>

      {/* Metadata grid */}
      <div className="px-5 py-5">
        <dl className="space-y-3">
          {councilRef && (
            <MetaRow icon={<Hash className="h-4 w-4" />} label="Council Reference" value={councilRef} />
          )}
          {dateReceived && (
            <MetaRow icon={<Calendar className="h-4 w-4" />} label="Date Received" value={dateReceived} />
          )}
          {status && (
            <MetaRow icon={<CircleDot className="h-4 w-4" />} label="Status" value={status} />
          )}
          {decision && (
            <MetaRow icon={<Gavel className="h-4 w-4" />} label="Decision" value={decision} />
          )}
          {estPrice && (
            <MetaRow
              icon={<PoundSterling className="h-4 w-4" />}
              label="Estimated Value"
              value={estPrice}
            />
          )}
          {aiBadge && (
            <MetaRow
              icon={<Sparkles className="h-4 w-4" />}
              label="AI Category"
              value={aiBadge}
            />
          )}
          {construction && (
            <MetaRow
              icon={<HardHat className="h-4 w-4" />}
              label="Construction Job"
              value="Yes"
            />
          )}
        </dl>
      </div>

      {/* Lead Pipeline section */}
      <LeadPipelineSection appId={app.id} inWorkspace={inWorkspace} />

      {/* Description */}
      <div className="border-t border-slate-100 px-5 py-5">
        <h3 className="mb-3 flex items-center gap-2 text-sm font-semibold text-slate-700">
          <FileText className="h-4 w-4 text-slate-400" />
          Description
        </h3>
        {description ? (
          <div
            className="prose prose-sm max-w-none font-body text-[13px] leading-relaxed text-slate-600 [&_a]:text-brand-600 [&_a]:underline [&_p]:mb-2 [&_p]:leading-relaxed"
            dangerouslySetInnerHTML={{ __html: description }}
          />
        ) : (
          <p className="text-sm text-slate-400">No description available</p>
        )}
      </div>
    </div>
  )
}

function LeadPipelineSection({
  appId,
  inWorkspace,
}: {
  appId: number
  inWorkspace: boolean
}) {
  const { getPipelineEntry, setLeadStatus, setLeadNotes, addToWorkspace } = useSearchContext()
  const entry = getPipelineEntry(appId)
  const [notes, setNotes] = useState(entry?.notes ?? '')
  const [notesDirty, setNotesDirty] = useState(false)
  const [notesSaved, setNotesSaved] = useState(false)
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null)
  const firstRun = useRef(true)

  useEffect(() => {
    if (firstRun.current) {
      firstRun.current = false
      return
    }
    setNotes(entry?.notes ?? '')
    setNotesDirty(false)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [appId])

  useEffect(() => {
    if (!notesDirty) return
    if (debounceRef.current) clearTimeout(debounceRef.current)
    debounceRef.current = setTimeout(() => {
      setLeadNotes(appId, notes)
      setNotesDirty(false)
      setNotesSaved(true)
      setTimeout(() => setNotesSaved(false), 2000)
    }, 800)
    return () => {
      if (debounceRef.current) clearTimeout(debounceRef.current)
    }
  }, [notes, notesDirty, appId, setLeadNotes])

  const handleBlur = () => {
    if (!notesDirty) return
    if (debounceRef.current) clearTimeout(debounceRef.current)
    setLeadNotes(appId, notes)
    setNotesDirty(false)
    setNotesSaved(true)
    setTimeout(() => setNotesSaved(false), 2000)
  }

  const handleStatusChange = (status: LeadStatus) => {
    setLeadStatus(appId, status)
  }

  if (!inWorkspace) {
    return (
      <div className="border-t border-slate-100 px-5 py-5">
        <div className="rounded-xl bg-slate-50 p-4 text-center">
          <ClipboardList className="mx-auto mb-2 h-8 w-8 text-slate-300" />
          <p className="text-sm font-medium text-slate-600">
            Add to workspace to track this lead's status and notes.
          </p>
          <button
            type="button"
            onClick={() => addToWorkspace(appId)}
            className="btn-primary mt-3"
          >
            <Plus className="h-4 w-4" />
            Add to workspace
          </button>
        </div>
      </div>
    )
  }

  return (
    <div className="border-t border-slate-100 px-5 py-5">
      <h3 className="mb-3 flex items-center gap-2 text-sm font-semibold text-slate-700">
        <ClipboardList className="h-4 w-4 text-slate-400" />
        Lead Pipeline
      </h3>

      {/* Status pills */}
      <div className="flex flex-wrap gap-2">
        {LEAD_STATUS_ORDER.map((status) => {
          const meta = LEAD_STATUS_META[status]
          const active = entry?.status === status
          return (
            <button
              key={status}
              type="button"
              aria-pressed={active}
              aria-label={`Set status to ${meta.label}`}
              onClick={() => handleStatusChange(status)}
              className={`inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition-colors ${
                active ? meta.pillActive : meta.pillIdle
              }`}
            >
              <span className={`h-2 w-2 rounded-full ${meta.dot}`} />
              {meta.label}
            </button>
          )
        })}
      </div>

      {/* Notes */}
      <div className="mt-4">
        <div className="mb-1.5 flex items-center justify-between">
          <label
            htmlFor="lead-notes"
            className="flex items-center gap-1.5 text-xs font-medium uppercase tracking-wide text-slate-400"
          >
            <StickyNote className="h-3.5 w-3.5" />
            Notes
          </label>
          {notesSaved && (
            <span className="text-xs font-medium text-success-600">Saved</span>
          )}
        </div>
        <textarea
          id="lead-notes"
          value={notes}
          onChange={(e) => {
            setNotes(e.target.value)
            setNotesDirty(true)
          }}
          onBlur={handleBlur}
          placeholder="e.g. Called, left voicemail. Quoted £45k."
          rows={3}
          className="w-full resize-y rounded-xl border-0 bg-white px-3 py-2.5 text-sm text-slate-900 ring-1 ring-inset ring-slate-300 transition-shadow duration-200 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-brand-500"
        />
      </div>
    </div>
  )
}

function MetaRow({
  icon,
  label,
  value,
}: {
  icon: React.ReactNode
  label: string
  value: string
}) {
  return (
    <div className="flex items-start gap-3">
      <div className="mt-0.5 flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-400">
        {icon}
      </div>
      <div className="min-w-0 flex-1">
        <dt className="text-xs font-medium uppercase tracking-wide text-slate-400">{label}</dt>
        <dd className="mt-0.5 text-sm font-semibold text-slate-800">{value}</dd>
      </div>
    </div>
  )
}
