import { useEffect, useRef } from 'react'

export interface UseInfiniteScrollOptions {
  enabled: boolean
  hasMore: boolean
  loading: boolean
  onLoadMore: () => void
  rootMargin?: string
}

export function useInfiniteScroll({
  enabled,
  hasMore,
  loading,
  onLoadMore,
  rootMargin = '300px',
}: UseInfiniteScrollOptions): React.RefObject<HTMLDivElement> {
  const sentinelRef = useRef<HTMLDivElement>(null)
  const loadingRef = useRef(loading)
  loadingRef.current = loading

  useEffect(() => {
    if (!enabled || !hasMore) return
    const sentinel = sentinelRef.current
    if (!sentinel) return

    const observer = new IntersectionObserver(
      (entries) => {
        const entry = entries[0]
        if (entry?.isIntersecting && !loadingRef.current) {
          onLoadMore()
        }
      },
      { rootMargin },
    )

    observer.observe(sentinel)
    return () => observer.disconnect()
  }, [enabled, hasMore, onLoadMore, rootMargin])

  return sentinelRef
}
