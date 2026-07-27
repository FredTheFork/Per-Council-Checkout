import { useCallback, useEffect, useRef, useState } from 'react'

interface RovingFocusOptions {
  itemCount: number
  enabled?: boolean
}

export function useRovingFocus({ itemCount, enabled = true }: RovingFocusOptions) {
  const [activeIndex, setActiveIndex] = useState(0)
  const containerRef = useRef<HTMLDivElement | null>(null)
  const itemRefs = useRef<Map<number, HTMLElement>>(new Map())

  const setItemRef = useCallback((index: number) => (el: HTMLElement | null) => {
    if (el) {
      itemRefs.current.set(index, el)
    } else {
      itemRefs.current.delete(index)
    }
  }, [])

  const focusItem = useCallback((index: number) => {
    const clamped = Math.max(0, Math.min(index, itemCount - 1))
    setActiveIndex(clamped)
    const el = itemRefs.current.get(clamped)
    if (el) {
      el.focus()
    }
  }, [itemCount])

  const handleKeyDown = useCallback((e: React.KeyboardEvent, index: number) => {
    if (!enabled) return

    if (e.key === 'ArrowDown') {
      e.preventDefault()
      const next = index + 1
      if (next < itemCount) {
        focusItem(next)
      }
    } else if (e.key === 'ArrowUp') {
      e.preventDefault()
      const prev = index - 1
      if (prev >= 0) {
        focusItem(prev)
      }
    }
  }, [enabled, itemCount, focusItem])

  // Reset active index when item count changes drastically
  useEffect(() => {
    if (activeIndex >= itemCount) {
      setActiveIndex(0)
    }
  }, [itemCount, activeIndex])

  const getTabIndex = useCallback((index: number) => {
    if (!enabled) return undefined
    return index === activeIndex ? 0 : -1
  }, [enabled, activeIndex])

  return {
    containerRef,
    setItemRef,
    handleKeyDown,
    getTabIndex,
    focusItem,
    activeIndex,
  }
}
