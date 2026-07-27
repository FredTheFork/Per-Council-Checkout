import {
  createContext,
  useCallback,
  useContext,
  useRef,
  useState,
  type ReactNode,
} from 'react'
import { Check, AlertCircle, Info, X } from 'lucide-react'
import { usePrefersReducedMotion } from '../hooks/usePrefersReducedMotion'

type ToastType = 'success' | 'error' | 'info'

interface ToastItem {
  id: number
  message: string
  type: ToastType
  remaining: number
  paused: boolean
}

interface ToastContextValue {
  showToast: (message: string, opts?: { type?: ToastType }) => void
}

const ToastContext = createContext<ToastContextValue | null>(null)

const TOAST_DURATION = 2500
const MAX_VISIBLE = 3

const TYPE_STYLES: Record<ToastType, { bg: string; icon: typeof Check; iconColor: string }> = {
  success: { bg: 'bg-success-600', icon: Check, iconColor: 'text-white' },
  error: { bg: 'bg-error-600', icon: AlertCircle, iconColor: 'text-white' },
  info: { bg: 'bg-brand-600', icon: Info, iconColor: 'text-white' },
}

export function ToastProvider({ children }: { children: ReactNode }) {
  const [toasts, setToasts] = useState<ToastItem[]>([])
  const idCounter = useRef(0)
  const timers = useRef<Map<number, ReturnType<typeof setTimeout>>>(new Map())
  const prefersReducedMotion = usePrefersReducedMotion()

  const dismiss = useCallback((id: number) => {
    setToasts((prev) => prev.filter((t) => t.id !== id))
    const timer = timers.current.get(id)
    if (timer) {
      clearTimeout(timer)
      timers.current.delete(id)
    }
  }, [])

  const showToast = useCallback(
    (message: string, opts?: { type?: ToastType }) => {
      const id = ++idCounter.current
      const type: ToastType = opts?.type ?? 'success'
      const item: ToastItem = { id, message, type, remaining: TOAST_DURATION, paused: false }

      setToasts((prev) => {
        const next = [...prev, item]
        // Cap at MAX_VISIBLE — dismiss oldest
        if (next.length > MAX_VISIBLE) {
          const oldest = next[0]
          const oldTimer = timers.current.get(oldest.id)
          if (oldTimer) {
            clearTimeout(oldTimer)
            timers.current.delete(oldest.id)
          }
          return next.slice(next.length - MAX_VISIBLE)
        }
        return next
      })

      const duration = prefersReducedMotion ? 0 : TOAST_DURATION
      const timer = setTimeout(() => dismiss(id), duration)
      timers.current.set(id, timer)
    },
    [dismiss, prefersReducedMotion],
  )

  const pauseTimer = useCallback((id: number) => {
    const timer = timers.current.get(id)
    if (timer) {
      clearTimeout(timer)
      timers.current.delete(id)
    }
    setToasts((prev) => prev.map((t) => (t.id === id ? { ...t, paused: true } : t)))
  }, [])

  const resumeTimer = useCallback(
    (id: number) => {
      const timer = setTimeout(() => dismiss(id), TOAST_DURATION)
      timers.current.set(id, timer)
      setToasts((prev) => prev.map((t) => (t.id === id ? { ...t, paused: false } : t)))
    },
    [dismiss],
  )

  return (
    <ToastContext.Provider value={{ showToast }}>
      {children}
      <div
        role="status"
        aria-live="polite"
        className="pointer-events-none fixed bottom-6 right-6 z-[10000] flex flex-col items-end gap-2"
      >
        {toasts.map((toast) => {
          const style = TYPE_STYLES[toast.type]
          const Icon = style.icon
          return (
            <div
              key={toast.id}
              aria-atomic="true"
              onMouseEnter={() => pauseTimer(toast.id)}
              onMouseLeave={() => resumeTimer(toast.id)}
              className={`pointer-events-auto relative flex w-80 max-w-[calc(100vw-3rem)] items-center gap-2.5 overflow-hidden rounded-xl ${style.bg} px-4 py-3 text-sm font-medium text-white shadow-elevated`}
              style={{
                animation: prefersReducedMotion
                  ? undefined
                  : 'piToastSlideIn var(--pi-transition-base) var(--pi-easing)',
              }}
            >
              <Icon className={`h-4 w-4 flex-shrink-0 ${style.iconColor}`} strokeWidth={2.5} />
              <span className="flex-1 truncate">{toast.message}</span>
              <button
                type="button"
                onClick={() => dismiss(toast.id)}
                aria-label="Dismiss notification"
                className="flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full text-white/70 transition-colors hover:bg-white/20 hover:text-white"
              >
                <X className="h-3.5 w-3.5" />
              </button>
              {/* Progress bar */}
              {!toast.paused && (
                <div className="absolute bottom-0 left-0 h-0.5 w-full bg-white/30">
                  <div
                    className="pi-toast-progress h-full origin-left bg-white/60"
                    style={{
                      animationDuration: `${TOAST_DURATION}ms`,
                    }}
                  />
                </div>
              )}
            </div>
          )
        })}
      </div>
      <style>{`@keyframes piToastSlideIn{from{opacity:0;transform:translateX(100%)}to{opacity:1;transform:translateX(0)}}`}</style>
    </ToastContext.Provider>
  )
}

export function useToast(): ToastContextValue {
  const ctx = useContext(ToastContext)
  if (!ctx) {
    throw new Error('useToast must be used within a ToastProvider')
  }
  return ctx
}
