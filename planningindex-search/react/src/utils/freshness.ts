import { formatDate } from './format'

export function formatFreshness(dateReceived: string): string {
  if (!dateReceived) return ''
  const received = new Date(dateReceived)
  if (isNaN(received.getTime())) return dateReceived

  const now = new Date()
  const startOfToday = new Date(now.getFullYear(), now.getMonth(), now.getDate())
  const startOfReceived = new Date(received.getFullYear(), received.getMonth(), received.getDate())
  const dayDiff = Math.floor((startOfToday.getTime() - startOfReceived.getTime()) / (1000 * 60 * 60 * 24))

  if (dayDiff <= 0) return 'New today'
  if (dayDiff <= 7) return 'This week'
  if (dayDiff <= 30) return 'This month'
  return formatDate(dateReceived)
}
