import { useEffect, useState, useCallback, useRef } from 'react'
import { X, Bookmark } from 'lucide-react'
import { useSearchContext } from '../context/SearchContext'
import { useToast } from './ToastProvider'
import { useFocusTrap } from '../hooks/useFocusTrap'
import { usePrefersReducedMotion } from '../hooks/usePrefersReducedMotion'
import { MAX_SAVED_SEARCHES } from '../utils/savedSearchStorage'

export default function SaveSearchModal() {
  const {
    saveSearchModalOpen,
    closeSaveSearchModal,
    saveCurrentSearch,
    savedSearches,
  } = useSearchContext()

  const { showToast } = useToast()
  const [name, setName] = useState('')
  const [error, setError] = useState(false)
  const [visible, setVisible] = useState(false)
  const panelRef = useRef<HTMLDivElement | null>(null)
  const inputRef = useRef<HTMLInputElement | null>(null)
  const prevFocusRef = useRef<HTMLElement | null>(null)
  const prefersReducedMotion = usePrefersReducedMotion()
  useFocusTrap(saveSearchModalOpen, panelRef)

  // Slide-in animation + body scroll lock
  useEffect(() => {
    if (saveSearchModalOpen) {
      prevFocusRef.current = document.activeElement as HTMLElement | null
      setName('')
      setError(false)
      document.body.style.overflow = 'hidden'
      requestAnimationFrame(() => setVisible(true))
      // Focus the input after the modal appears
      setTimeout(() => inputRef.current?.focus(), prefersReducedMotion ? 0 : 100)
    } else {
      setVisible(false)
      document.body.style.overflow = ''
      prevFocusRef.current?.focus()
    }
    return () => {
      document.body.style.overflow = ''
    }
  }, [saveSearchModalOpen, prefersReducedMotion])

  // Escape to close
  useEffect(() => {
    if (!saveSearchModalOpen) return
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') closeSaveSearchModal()
    }
    document.addEventListener('keydown', onKey)
    return () => document.removeEventListener('keydown', onKey)
  }, [saveSearchModalOpen, closeSaveSearchModal])

  const handleSave = useCallback(() => {
    const trimmed = name.trim()
    if (!trimmed) {
      setError(true)
      return
    }

    if (savedSearches.length >= MAX_SAVED_SEARCHES) {
      showToast(`You can save up to ${MAX_SAVED_SEARCHES} searches. Delete one first.`, {
        type: 'error',
      })
      return
    }

    const success = saveCurrentSearch(trimmed)
    if (success) {
      const displayName = trimmed.length > 40 ? `${trimmed.slice(0, 37)}...` : trimmed
      showToast(`Search "${displayName}" saved`, { type: 'success' })
      setName('')
    }
  }, [name, savedSearches, saveCurrentSearch, showToast])

  const handleKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
    if (e.key === 'Enter') {
      e.preventDefault()
      handleSave()
    }
  }

  if (!saveSearchModalOpen) return null

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center p-4"
      role="dialog"
      aria-modal="true"
      aria-label="Save search"
    >
      {/* Backdrop */}
      <button
        type="button"
        aria-label="Close save search dialog"
        onClick={closeSaveSearchModal}
        className={`absolute inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity duration-300 ${
          visible ? 'opacity-100' : 'opacity-0'
        }`}
      />

      {/* Modal */}
      <div
        ref={panelRef}
        tabIndex={-1}
        onClick={(e) => e.stopPropagation()}
        className={`relative flex w-full max-w-md flex-col rounded-2xl bg-white shadow-2xl transition-all duration-200 ease-out focus:outline-none ${
          visible
            ? 'scale-100 opacity-100'
            : 'scale-95 opacity-0'
        }`}
        style={{
          transitionDuration: prefersReducedMotion ? '0ms' : '200ms',
        }}
      >
        {/* Header */}
        <div className="flex items-center justify-between border-b border-slate-200 px-5 py-4">
          <div className="flex items-center gap-2">
            <Bookmark className="h-4 w-4 text-brand-600" />
            <h2 className="font-display text-sm font-bold uppercase tracking-wide text-slate-500">
              Save this search
            </h2>
          </div>
          <button
            type="button"
            onClick={closeSaveSearchModal}
            aria-label="Close"
            className="flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600"
          >
            <X className="h-5 w-5" />
          </button>
        </div>

        {/* Body */}
        <div className="px-5 py-5">
          <label
            htmlFor="save-search-name"
            className="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500"
          >
            Search name
          </label>
          <input
            ref={inputRef}
            id="save-search-name"
            type="text"
            value={name}
            onChange={(e) => {
              setName(e.target.value)
              if (error) setError(false)
            }}
            onKeyDown={handleKeyDown}
            placeholder="e.g. Extensions in Manchester"
            className="input-field"
            aria-invalid={error}
            aria-describedby={error ? 'save-search-error' : undefined}
            maxLength={80}
          />
          {error && (
            <p
              id="save-search-error"
              className="mt-2 text-sm font-medium text-error-600"
            >
              Please enter a name
            </p>
          )}
          <p className="mt-2 text-xs text-slate-400">
            Save your current filters and sort so you can quickly return to this search later.
          </p>
        </div>

        {/* Footer */}
        <div className="flex items-center justify-end gap-3 border-t border-slate-200 px-5 py-4">
          <button
            type="button"
            onClick={closeSaveSearchModal}
            className="btn-secondary"
          >
            Cancel
          </button>
          <button
            type="button"
            onClick={handleSave}
            disabled={!name.trim()}
            className="btn-primary disabled:cursor-not-allowed disabled:opacity-50"
          >
            <Bookmark className="h-4 w-4" />
            Save search
          </button>
        </div>
      </div>
    </div>
  )
}
