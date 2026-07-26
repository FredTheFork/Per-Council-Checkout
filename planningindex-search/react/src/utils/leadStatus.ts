import type { LeadStatus } from '../types'

export interface LeadStatusMeta {
  value: LeadStatus
  label: string
  badgeBg: string
  badgeText: string
  dot: string
  pillActive: string
  pillIdle: string
}

export const LEAD_STATUS_ORDER: LeadStatus[] = [
  'possible',
  'contacted',
  'quoted',
  'won',
  'lost',
]

export const LEAD_STATUS_META: Record<LeadStatus, LeadStatusMeta> = {
  possible: {
    value: 'possible',
    label: 'Possible',
    badgeBg: 'bg-slate-100',
    badgeText: 'text-slate-700',
    dot: 'bg-slate-400',
    pillActive: 'bg-slate-700 text-white',
    pillIdle: 'bg-slate-100 text-slate-600 hover:bg-slate-200',
  },
  contacted: {
    value: 'contacted',
    label: 'Contacted',
    badgeBg: 'bg-brand-100',
    badgeText: 'text-brand-700',
    dot: 'bg-brand-500',
    pillActive: 'bg-brand-600 text-white',
    pillIdle: 'bg-brand-50 text-brand-700 hover:bg-brand-100',
  },
  quoted: {
    value: 'quoted',
    label: 'Quoted',
    badgeBg: 'bg-accent-100',
    badgeText: 'text-accent-700',
    dot: 'bg-accent-500',
    pillActive: 'bg-accent-500 text-white',
    pillIdle: 'bg-accent-50 text-accent-700 hover:bg-accent-100',
  },
  won: {
    value: 'won',
    label: 'Won',
    badgeBg: 'bg-success-100',
    badgeText: 'text-success-700',
    dot: 'bg-success-500',
    pillActive: 'bg-success-600 text-white',
    pillIdle: 'bg-success-50 text-success-700 hover:bg-success-100',
  },
  lost: {
    value: 'lost',
    label: 'Lost',
    badgeBg: 'bg-error-100',
    badgeText: 'text-error-700',
    dot: 'bg-error-500',
    pillActive: 'bg-error-600 text-white',
    pillIdle: 'bg-error-50 text-error-700 hover:bg-error-100',
  },
}
