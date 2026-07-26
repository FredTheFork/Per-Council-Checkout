import type { PlanningApp } from '../types'
import { titleCaseAddress, getEstPrice, formatDate } from './format'

const CSV_COLUMNS = [
  'Council',
  'Address',
  'Reference',
  'Date Received',
  'Estimated Value',
  'Status',
  'Info URL',
] as const

function escapeCsv(value: string): string {
  if (value === '') return ''
  if (/[",\n\r]/.test(value)) {
    return `"${value.replace(/"/g, '""')}"`
  }
  return value
}

function getAppRow(app: PlanningApp): string[] {
  const council = app._authority_name ?? ''
  const address = titleCaseAddress(app.meta?.address || app.title?.rendered || '')
  const reference = app.meta?.council_reference ?? ''
  const dateReceived = formatDate(app.meta?.date_received ?? '')
  const estValue = getEstPrice(app.meta) ?? ''
  const status = app.meta?.status ?? ''
  const infoUrl = app.meta?.info_url ?? ''
  return [council, address, reference, dateReceived, estValue, status, infoUrl]
}

export function buildAppsCsv(apps: PlanningApp[]): string {
  const rows: string[] = []
  rows.push(CSV_COLUMNS.join(','))
  for (const app of apps) {
    const values = getAppRow(app).map(escapeCsv)
    rows.push(values.join(','))
  }
  return rows.join('\r\n')
}

export function exportAppsToCsv(apps: PlanningApp[], filename?: string): void {
  if (apps.length === 0) return
  const csv = buildAppsCsv(apps)
  const bom = '\uFEFF'
  const blob = new Blob([bom + csv], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const date = new Date().toISOString().slice(0, 10)
  const downloadName = filename ?? `planning-apps-${date}.csv`
  const link = document.createElement('a')
  link.href = url
  link.download = downloadName
  link.style.display = 'none'
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  setTimeout(() => URL.revokeObjectURL(url), 1000)
}
