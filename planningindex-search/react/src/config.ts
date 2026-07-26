import type { PlanningIndexSearchConfig } from './types'

let cachedConfig: PlanningIndexSearchConfig | null = null

function getConfig(): PlanningIndexSearchConfig {
  if (cachedConfig) return cachedConfig

  const raw = window.PlanningIndexSearch
  if (!raw) {
    throw new Error(
      '[PlanningIndexSearch] Config not found on window.PlanningIndexSearch. ' +
        'Ensure the plugin is enqueued on this page.',
    )
  }

  cachedConfig = raw
  return cachedConfig
}

function getRestBase(): string {
  return getConfig().restBase
}

function getRestRoot(): string {
  return getConfig().restRoot
}

function getNonce(): string {
  return getConfig().nonce
}

function getMapboxToken(): string {
  return getConfig().mapboxToken ?? ''
}

function getUserId(): number {
  return getConfig().userId ?? 0
}

function isLoggedIn(): boolean {
  return getConfig().isLoggedIn ?? false
}

function isAdmin(): boolean {
  return getConfig().isAdmin ?? false
}

function getAllowedAuthorities(): number[] {
  return getConfig().allowedAuthorities ?? []
}

function getPluginVersion(): string {
  return getConfig().version ?? '1.0.0'
}

function getPluginUrl(): string {
  return getConfig().pluginUrl ?? ''
}

function isAuthorityAllowed(termId: number): boolean {
  if (isAdmin()) return true
  const allowed = getAllowedAuthorities()
  if (allowed.length === 0) return false
  return allowed.includes(termId)
}

export const config = Object.freeze({
  get: getConfig,
  getRestBase,
  getRestRoot,
  getNonce,
  getMapboxToken,
  getUserId,
  isLoggedIn,
  isAdmin,
  getAllowedAuthorities,
  getPluginVersion,
  getPluginUrl,
  isAuthorityAllowed,
})

export type Config = typeof config
