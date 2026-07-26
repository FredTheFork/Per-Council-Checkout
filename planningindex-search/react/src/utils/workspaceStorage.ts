import type { LeadPipelineEntry, LeadStatus, PipelineMap } from '../types'

function storageKey(userId: number): string {
  return `pis_workspace_pipeline_${userId}`
}

function isValidEntry(raw: unknown): raw is LeadPipelineEntry {
  if (typeof raw !== 'object' || raw === null) return false
  const e = raw as Record<string, unknown>
  return (
    typeof e.appId === 'number' &&
    typeof e.status === 'string' &&
    ['possible', 'contacted', 'quoted', 'won', 'lost'].includes(e.status) &&
    typeof e.notes === 'string' &&
    typeof e.updatedAt === 'string'
  )
}

function safeParse(raw: string | null): PipelineMap | null {
  if (!raw) return null
  try {
    const parsed = JSON.parse(raw)
    if (typeof parsed !== 'object' || parsed === null || Array.isArray(parsed)) return null
    const map: PipelineMap = {}
    for (const [key, value] of Object.entries(parsed)) {
      const id = Number(key)
      if (!isNaN(id) && isValidEntry(value)) {
        map[id] = value as LeadPipelineEntry
      }
    }
    return map
  } catch {
    console.warn('[PlanningIndexSearch] Corrupt pipeline JSON, starting fresh')
    return null
  }
}

export function loadPipeline(userId: number): PipelineMap {
  if (userId <= 0) return {}
  const raw = localStorage.getItem(storageKey(userId))
  const parsed = safeParse(raw)
  return parsed ?? {}
}

export function persistPipeline(userId: number, pipeline: PipelineMap): void {
  if (userId <= 0) return
  try {
    localStorage.setItem(storageKey(userId), JSON.stringify(pipeline))
  } catch {
    console.warn('[PlanningIndexSearch] Could not persist pipeline')
  }
}

export function setPipelineEntry(
  userId: number,
  pipeline: PipelineMap,
  appId: number,
  status: LeadStatus,
): PipelineMap {
  const existing = pipeline[appId]
  const entry: LeadPipelineEntry = {
    appId,
    status,
    notes: existing?.notes ?? '',
    updatedAt: new Date().toISOString(),
  }
  const next = { ...pipeline, [appId]: entry }
  persistPipeline(userId, next)
  return next
}

export function setPipelineNotes(
  userId: number,
  pipeline: PipelineMap,
  appId: number,
  notes: string,
): PipelineMap {
  const existing = pipeline[appId]
  const entry: LeadPipelineEntry = {
    appId,
    status: existing?.status ?? 'possible',
    notes,
    updatedAt: new Date().toISOString(),
  }
  const next = { ...pipeline, [appId]: entry }
  persistPipeline(userId, next)
  return next
}

export function ensurePipelineEntry(
  userId: number,
  pipeline: PipelineMap,
  appId: number,
): PipelineMap {
  if (pipeline[appId]) return pipeline
  const entry: LeadPipelineEntry = {
    appId,
    status: 'possible',
    notes: '',
    updatedAt: new Date().toISOString(),
  }
  const next = { ...pipeline, [appId]: entry }
  persistPipeline(userId, next)
  return next
}

export function removePipelineEntry(
  userId: number,
  pipeline: PipelineMap,
  appId: number,
): PipelineMap {
  if (!pipeline[appId]) return pipeline
  const next = { ...pipeline }
  delete next[appId]
  persistPipeline(userId, next)
  return next
}
