export type LeadTier = 'hot' | 'warm' | 'cold'

export function getLeadTier(score: number): LeadTier {
  if (score >= 67) return 'hot'
  if (score >= 34) return 'warm'
  return 'cold'
}

export const LEAD_TIER_COLORS: Record<LeadTier, { dot: string; label: string; text: string; bg: string; border: string }> = {
  hot: {
    dot: 'bg-accent-500',
    label: 'High priority',
    text: 'text-accent-700',
    bg: 'bg-accent-50',
    border: 'border-accent-200',
  },
  warm: {
    dot: 'bg-slate-400',
    label: 'Medium priority',
    text: 'text-slate-600',
    bg: 'bg-slate-100',
    border: 'border-slate-300',
  },
  cold: {
    dot: 'bg-slate-300',
    label: 'Low priority',
    text: 'text-slate-500',
    bg: 'bg-slate-50',
    border: 'border-slate-200',
  },
}
