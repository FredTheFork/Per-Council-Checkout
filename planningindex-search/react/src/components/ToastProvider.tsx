import {
  createContext,
  useCallback,
  useContext,
  useRef,
  useState,
  type ReactNode,
} from 'react'
import { Check, CircleAlert as AlertCircle, Info, X } from 'lucide-react'

type ToastType = 'success' | 'error' | 'info'

interface ToastItem {
  id: number
  message: string
  type: ToastType
}

interface ToastContextValue {
  showToast: (message: string, opts?: { type?: ToastType }) => void
}

const ToastContext = createContext<ToastContextValue | null>(null)

const TOAST_DURATION = 4000

const TYPE_STYLES: Record<ToastType, { bg: string; icon: typeof Check; iconColor: string }> = {
  success: { bg: 'bg-success-600', icon: Check, iconColor: 'text-white' },
  error: { bg: 'bg-error-600', icon: AlertCircle, iconColor: 'text-white' },
  info: { bg: 'bg-brand-600', icon: Info, iconColor: 'text-white' },
}

export function ToastProvider({ children }: { children: ReactNode }) {
  const [toasts, setToasts] = useState<ToastItem[]>([])
  const idCounter = useRef(0)
  const timers = useRef<Map<number, ReturnType<typeof setTimeout>>>(new Map())

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
      setToasts((prev) => [...prev, { id, message, type }])
      const timer = setTimeout(() => dismiss(id), TOAST_DURATION)
      timers.current.set(id, timer)
    },
    [dismiss],
  )

  const pauseTimer = useCallback((id: number) => {
    const timer = timers.current.get(id)
    if (timer) {
      clearTimeout(timer)
      timers.current.delete(id)
    }
  }, [])

  const resumeTimer = useCallback(
    (id: number) => {
      const timer = setTimeout(() => dismiss(id), TOAST_DURATION)
      timers.current.set(id, timer)
    },
    [dismiss],
  )

  return (
    <ToastContext.Provider value={{ showToast }}>
      {children}
      <div
        role="status"
        aria-live="polite"
        className="pointer-events-none fixed bottom-6 left-1/2 z-[10000] flex -translate-x-1/2 flex-col items-center gap-2 sm:left-auto sm:right-6 sm:translate-x-0"
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
              className={`pointer-events-auto flex items-center gap-2.5 rounded-xl ${style.bg} px-4 py-3 text-sm font-medium text-white shadow-elevated transition-all duration-300 ease-out`}
              style={{ animation: 'toastSlideIn 0.3s ease-out' }}
            >
              <Icon className={`h-4 w-4 flex-shrink-0 ${style.iconColor}`} strokeWidth={2.5} />
              <span className="max-w-xs truncate">{toast.message}</span>
              <button
                type="button"
                onClick={() => dismiss(toast.id)}
                aria-label="Dismiss notification"
                className="ml-1 flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full text-white/70 transition-colors hover:bg-white/20 hover:text-white"
              >
                <X className="h-3.5 w-3.5" />
              </button>
            </div>
          )
        })}
      </div>
      <style>{`@keyframes toastSlideIn{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}`}</style>
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
