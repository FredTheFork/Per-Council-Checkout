import type { PlanningApp, PlanningAppMeta, SearchFilters } from '../types'

const RECENCY_WEIGHT = 0.4
const VALUE_WEIGHT = 0.35
const TRADE_MATCH_WEIGHT = 0.25

const RECENCY_DECAY_DAYS = 30
const VALUE_MAX = 5000000

/**
 * Lead score combines three signals into a 0–100 ranking:
 *   1. Recency (40%) — apps received today score highest, decaying over ~30 days.
 *   2. Value (35%) — normalized estimated value against a rolling max.
 *   3. Trade match (25%) — boosts apps whose text contains active keyword filter terms.
 * Weights are exported as constants above so later stages can tune them.
 */
export function computeLeadScore(app: PlanningApp, filters: SearchFilters): number {
  const recencyScore = computeRecency(app.meta)
  const valueScore = computeValue(app.meta)
  const tradeScore = computeTradeMatch(app, filters)

  const total =
    recencyScore * RECENCY_WEIGHT +
    valueScore * VALUE_WEIGHT +
    tradeScore * TRADE_MATCH_WEIGHT

  return Math.round(Math.min(100, Math.max(0, total)))
}

function computeRecency(meta: PlanningAppMeta): number {
  if (!meta.date_received) return 0
  const received = new Date(meta.date_received)
  if (isNaN(received.getTime())) return 0
  const daysAgo = Math.max(0, (Date.now() - received.getTime()) / (1000 * 60 * 60 * 24))
  if (daysAgo >= RECENCY_DECAY_DAYS) return 0
  return 100 * (1 - daysAgo / RECENCY_DECAY_DAYS)
}

function computeValue(meta: PlanningAppMeta): number {
  const numeric = parseFloat(meta.est_value_numeric)
  if (isNaN(numeric) || numeric <= 0) return 0
  return Math.min(100, (numeric / VALUE_MAX) * 100)
}

function computeTradeMatch(app: PlanningApp, filters: SearchFilters): number {
  if (!filters.search) return 0
  const keywords = filters.search.toLowerCase().split(/\s+/).filter((k) => k.length >= 2)
  if (keywords.length === 0) return 0

  const haystack = `${app.title?.rendered ?? ''} ${app.content?.rendered ?? ''} ${app.meta?.address ?? ''}`.toLowerCase()
  const matched = keywords.filter((k) => haystack.includes(k))
  return (matched.length / keywords.length) * 100
}
