import { useEffect, useState } from 'react'

export function useStickyOffset(
  targetRef: React.RefObject<HTMLElement>,
): { offset: number; isStuck: boolean } {
  const [offset, setOffset] = useState(0)
  const [isStuck, setIsStuck] = useState(false)

  useEffect(() => {
    const target = targetRef.current
    if (!target) return

    const updateOffset = () => {
      const rect = target.getBoundingClientRect()
      setOffset(rect.bottom)
    }

    updateOffset()
    const ro = new ResizeObserver(updateOffset)
    ro.observe(target)

    const onScroll = () => {
      const rect = target.getBoundingClientRect()
      setIsStuck(rect.bottom < 0)
    }
    window.addEventListener('scroll', onScroll, { passive: true })
    onScroll()

    return () => {
      ro.disconnect()
      window.removeEventListener('scroll', onScroll)
    }
  }, [targetRef])

  return { offset, isStuck }
}
