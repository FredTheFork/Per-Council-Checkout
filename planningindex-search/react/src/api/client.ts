import { config } from '../config'
import type { ApiError } from '../types'

export interface ApiResponse<T> {
  data: T
  headers: Headers
}

function toApiError(res: Response, endpoint: string, fallback: string): ApiError {
  return {
    status: res.status,
    message: fallback,
    endpoint,
  }
}

export async function apiRequest<T>(
  path: string,
  options: RequestInit = {},
): Promise<ApiResponse<T>> {
  const restRoot = config.getRestRoot().replace(/\/$/, '')
  const url = path.startsWith('http') ? path : `${restRoot}${path.startsWith('/') ? '' : '/'}${path}`

  const headers: Record<string, string> = {
    'X-WP-Nonce': config.getNonce(),
    ...((options.headers as Record<string, string>) ?? {}),
  }

  if (options.body && !headers['Content-Type']) {
    headers['Content-Type'] = 'application/json'
  }

  const res = await fetch(url, {
    ...options,
    headers,
    credentials: 'same-origin',
  })

  let parsed: unknown = null
  const text = await res.text()
  if (text) {
    try {
      parsed = JSON.parse(text)
    } catch {
      parsed = text
    }
  }

  if (!res.ok) {
    const message =
      typeof parsed === 'object' && parsed !== null && 'message' in parsed
        ? String((parsed as { message: unknown }).message)
        : `Request failed (${res.status})`
    throw toApiError(res, url, message)
  }

  return { data: parsed as T, headers: res.headers }
}

export function apiGet<T>(
  path: string,
  params?: Record<string, unknown>,
  signal?: AbortSignal,
): Promise<ApiResponse<T>> {
  const searchParams = buildQueryParams(params)
  const fullPath = searchParams ? `${path}?${searchParams}` : path
  return apiRequest<T>(fullPath, { method: 'GET', signal })
}

export function apiPost<T>(
  path: string,
  body?: unknown,
  signal?: AbortSignal,
): Promise<ApiResponse<T>> {
  return apiRequest<T>(path, {
    method: 'POST',
    body: body !== undefined ? JSON.stringify(body) : undefined,
    signal,
  })
}

export function buildQueryParams(params?: Record<string, unknown>): string {
  if (!params) return ''
  const parts: string[] = []
  for (const [key, value] of Object.entries(params)) {
    if (value === undefined || value === null || value === '') continue
    if (Array.isArray(value)) {
      if (value.length === 0) continue
      parts.push(`${encodeURIComponent(key)}=${encodeURIComponent(value.join(','))}`)
    } else {
      parts.push(`${encodeURIComponent(key)}=${encodeURIComponent(String(value))}`)
    }
  }
  return parts.join('&')
}

export { toApiError }
