export type LeadTier = 'hot' | 'warm' | 'cold'

export function getLeadTier(score: number): LeadTier {
  if (score >= 67) return 'hot'
  if (score >= 34) return 'warm'
  return 'cold'
}

export const LEAD_TIER_COLORS: Record<LeadTier, { dot: string; label: string; text: string; bg: string }> = {
  hot: {
    dot: 'bg-success-500',
    label: 'Hot lead',
    text: 'text-success-700',
    bg: 'bg-success-50',
  },
  warm: {
    dot: 'bg-warning-500',
    label: 'Warm lead',
    text: 'text-warning-700',
    bg: 'bg-warning-50',
  },
  cold: {
    dot: 'bg-slate-400',
    label: 'Cold lead',
    text: 'text-slate-600',
    bg: 'bg-slate-100',
  },
}
