import type { PlanningAppMeta } from '../types'
import { HIGH_VALUE_THRESHOLD } from './format'

export type ValueBand = 'low' | 'mid' | 'high'

const MID_THRESHOLD = 100000

export function getValueBand(meta: PlanningAppMeta): ValueBand {
  const numeric = parseFloat(meta.est_value_numeric)
  if (isNaN(numeric) || numeric <= 0) return 'low'
  if (numeric >= HIGH_VALUE_THRESHOLD) return 'high'
  if (numeric >= MID_THRESHOLD) return 'mid'
  return 'low'
}

export const VALUE_BAND_COLORS: Record<ValueBand, string> = {
  low: '#94a3b8',
  mid: '#1b2534',
  high: '#f97316',
}

export const VALUE_BAND_LABELS: Record<ValueBand, string> = {
  low: 'Under £100k',
  mid: '£100k–£500k',
  high: '£500k+',
}
