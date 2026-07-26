import type { PlanningApp, SearchFilters, SortOption } from '../types'
import { computeLeadScore } from './leadScore'
import { titleCaseAddress } from './format'

function getDateValue(app: PlanningApp): number {
  const dateStr = app.meta?.date_received || ''
  if (!dateStr) return 0
  const t = new Date(dateStr).getTime()
  return isNaN(t) ? 0 : t
}

function getNumericValue(app: PlanningApp): number {
  const n = parseFloat(app.meta?.est_value_numeric ?? '')
  return isNaN(n) ? 0 : n
}

function getAddressLabel(app: PlanningApp): string {
  const raw = app.meta?.address || app.title?.rendered || ''
  return titleCaseAddress(raw).toLowerCase()
}

export function sortApps(
  apps: PlanningApp[],
  sort: SortOption,
  filters: SearchFilters,
): PlanningApp[] {
  const withScores = apps.map((app) => ({
    app,
    date: getDateValue(app),
    value: getNumericValue(app),
    address: getAddressLabel(app),
    score: app.score ?? computeLeadScore(app, filters),
  }))

  switch (sort) {
    case 'date_desc':
      withScores.sort((a, b) => b.date - a.date)
      break
    case 'date_asc':
      withScores.sort((a, b) => a.date - b.date)
      break
    case 'alpha_asc':
      withScores.sort((a, b) => a.address.localeCompare(b.address))
      break
    case 'alpha_desc':
      withScores.sort((a, b) => b.address.localeCompare(a.address))
      break
    case 'value_desc':
      withScores.sort((a, b) => b.value - a.value)
      break
    case 'lead_score_desc':
      withScores.sort((a, b) => b.score - a.score)
      break
  }

  return withScores.map(({ app, score }) => ({ ...app, score }))
}
