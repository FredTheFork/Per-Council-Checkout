import { useEffect } from 'react'

interface GlobalKeyboardOptions {
  onFocusSearch?: () => void
}

function isTypingInField(): boolean {
  const el = document.activeElement
  if (!el) return false
  const tag = el.tagName.toLowerCase()
  return tag === 'input' || tag === 'textarea' || tag === 'select' || (el as HTMLElement).isContentEditable
}

export function useGlobalKeyboard({ onFocusSearch }: GlobalKeyboardOptions) {
  useEffect(() => {
    const onKeyDown = (e: KeyboardEvent) => {
      // '/' focuses search bar (only when not already in an input)
      if (e.key === '/' && !isTypingInField() && onFocusSearch) {
        e.preventDefault()
        onFocusSearch()
      }
    }

    document.addEventListener('keydown', onKeyDown)
    return () => document.removeEventListener('keydown', onKeyDown)
  }, [onFocusSearch])
}
