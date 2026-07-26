import { useEffect, useState } from 'react'
import { Search, MapPin, Shield, User, KeyRound, Building2, Loader as Loader2 } from 'lucide-react'
import type { PlanningIndexSearchConfig } from './types'

export default function App() {
  const [config, setConfig] = useState<PlanningIndexSearchConfig | null>(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    const cfg = window.PlanningIndexSearch
    if (cfg) {
      setConfig(cfg)
    }
    const timer = setTimeout(() => setLoading(false), 800)
    return () => clearTimeout(timer)
  }, [])

  if (loading) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-slate-50">
        <Loader2 className="h-8 w-8 animate-spin text-brand-600" />
      </div>
    )
  }

  return (
    <div className="min-h-screen bg-slate-50">
      {/* Header */}
      <header className="border-b border-slate-200 bg-brand-600">
        <div className="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
          <div className="flex items-center gap-3">
            <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-accent-500">
              <Search className="h-5 w-5 text-white" strokeWidth={2.5} />
            </div>
            <div>
              <h1 className="font-display text-lg font-bold text-white">
                Planning Index Search
              </h1>
              <p className="text-xs text-brand-200">Renovation in progress</p>
            </div>
          </div>
          {config?.isLoggedIn && (
            <div className="flex items-center gap-2 rounded-lg bg-brand-700 px-3 py-1.5">
              <User className="h-4 w-4 text-brand-200" />
              <span className="text-sm font-medium text-white">
                User #{config.userId}
              </span>
              {config.isAdmin && (
                <span className="badge bg-accent-500 text-white">Admin</span>
              )}
            </div>
          )}
        </div>
      </header>

      {/* Main content */}
      <main className="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
        <div className="card mx-auto max-w-2xl p-8">
          <div className="mb-6 flex items-center gap-3">
            <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-accent-100">
              <Search className="h-6 w-6 text-accent-600" strokeWidth={2} />
            </div>
            <div>
              <h2 className="font-display text-xl font-bold text-brand-600">
                Search Renovation in Progress
              </h2>
              <p className="text-sm text-slate-500">
                Stage 1: Plugin Foundation &amp; Build Pipeline
              </p>
            </div>
          </div>

          <p className="mb-8 text-sm leading-relaxed text-slate-600">
            The new React-based search interface is being built. This placeholder
            confirms that the plugin foundation, build pipeline, and config
            injection are all working correctly. The full search UI will replace
            this placeholder in the next stage.
          </p>

          {/* Config debug panel */}
          <div className="rounded-xl bg-slate-50 p-5 ring-1 ring-slate-200">
            <h3 className="mb-4 flex items-center gap-2 text-sm font-semibold text-brand-600">
              <Shield className="h-4 w-4" />
              Configuration Pipeline Verified
            </h3>
            <dl className="space-y-3 text-sm">
              <ConfigRow
                icon={<Search className="h-4 w-4 text-slate-400" />}
                label="REST API Base"
                value={config?.restBase ?? 'Not found'}
                mono
              />
              <ConfigRow
                icon={<KeyRound className="h-4 w-4 text-slate-400" />}
                label="WP REST Nonce"
                value={config?.nonce ? `${config.nonce.substring(0, 12)}…` : 'Missing'}
                mono
              />
              <ConfigRow
                icon={<MapPin className="h-4 w-4 text-slate-400" />}
                label="Mapbox Token"
                value={config?.mapboxToken ? `${config.mapboxToken.substring(0, 8)}…` : 'Not configured'}
                mono
              />
              <ConfigRow
                icon={<User className="h-4 w-4 text-slate-400" />}
                label="Logged In"
                value={config?.isLoggedIn ? 'Yes' : 'No'}
              />
              <ConfigRow
                icon={<User className="h-4 w-4 text-slate-400" />}
                label="User ID"
                value={String(config?.userId ?? 0)}
                mono
              />
              <ConfigRow
                icon={<Shield className="h-4 w-4 text-slate-400" />}
                label="Admin"
                value={config?.isAdmin ? 'Yes' : 'No'}
              />
              <ConfigRow
                icon={<Building2 className="h-4 w-4 text-slate-400" />}
                label="Allowed Authorities"
                value={
                  config?.allowedAuthorities?.length
                    ? `${config.allowedAuthorities.length} councils`
                    : 'All (admin or unscoped)'
                }
              />
            </dl>
          </div>

          <div className="mt-6 flex items-center gap-2 text-xs text-slate-400">
            <span className="h-2 w-2 rounded-full bg-success-500" />
            Plugin v{config?.version ?? '1.0.0'} — React + Vite + TypeScript + Tailwind
          </div>
        </div>
      </main>
    </div>
  )
}

function ConfigRow({
  icon,
  label,
  value,
  mono = false,
}: {
  icon: React.ReactNode
  label: string
  value: string
  mono?: boolean
}) {
  return (
    <div className="flex items-center justify-between gap-4">
      <dt className="flex items-center gap-2 text-slate-500">
        {icon}
        {label}
      </dt>
      <dd className={mono ? 'font-mono text-xs text-slate-700' : 'text-slate-700'}>
        {value}
      </dd>
    </div>
  )
}
