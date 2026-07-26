import { useEffect, useState, useRef } from 'react'
import { useSearchContext } from '../context/SearchContext'
import type { PlanningApp, UserApp } from '../types'

export interface AppDetailResult {
  app: PlanningApp | null
  loading: boolean
  error: string | null
  retry: () => void
}

export function useAppDetail(appId: number | null): AppDetailResult {
  const {
    rawApps,
    mapApps,
    fetchAppById,
    fetchSavedApps,
    fetchRecentApps,
  } = useSearchContext()

  const [app, setApp] = useState<PlanningApp | null>(null)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [retryCount, setRetryCount] = useState(0)
  const savedCacheRef = useRef<UserApp[] | null>(null)
  const recentCacheRef = useRef<UserApp[] | null>(null)

  useEffect(() => {
    if (appId === null) {
      setApp(null)
      setLoading(false)
      setError(null)
      return
    }

    // 1. Check loaded search results
    const fromRaw = rawApps.find((a) => a.id === appId)
    if (fromRaw) {
      setApp(fromRaw)
      setLoading(false)
      setError(null)
      return
    }

    // 2. Check map apps
    const fromMap = mapApps.find((a) => a.id === appId)
    if (fromMap) {
      setApp(fromMap)
      setLoading(false)
      setError(null)
      return
    }

    // 3-5. Async fallback resolution
    let cancelled = false
    const abort = new AbortController()
    setLoading(true)
    setError(null)

    async function resolve() {
      try {
        // 3. Check saved apps
        if (!savedCacheRef.current) {
          try {
            savedCacheRef.current = await fetchSavedApps()
          } catch { savedCacheRef.current = [] }
        }
        if (cancelled) return
        const fromSaved = savedCacheRef.current?.find((a) => a.id === appId)
        if (fromSaved) {
          setApp(fromSaved)
          setLoading(false)
          return
        }

        // 4. Check recent apps
        if (!recentCacheRef.current) {
          try {
            recentCacheRef.current = await fetchRecentApps()
          } catch { recentCacheRef.current = [] }
        }
        if (cancelled) return
        const fromRecent = recentCacheRef.current?.find((a) => a.id === appId)
        if (fromRecent) {
          setApp(fromRecent)
          setLoading(false)
          return
        }

        // 5. Fallback: fetch from API
        const fetched = await fetchAppById(appId!, abort.signal)
        if (cancelled) return
        setApp(fetched)
        setLoading(false)
      } catch {
        if (cancelled) return
        setError("Couldn't load this application. It may have been removed.")
        setLoading(false)
      }
    }

    void resolve()

    return () => {
      cancelled = true
      abort.abort()
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [appId, retryCount, rawApps, mapApps])

  const retry = () => {
    savedCacheRef.current = null
    recentCacheRef.current = null
    setRetryCount((c) => c + 1)
  }

  return { app, loading, error, retry }
}
