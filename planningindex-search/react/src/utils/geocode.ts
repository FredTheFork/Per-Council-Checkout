import { extractPostcode } from './format'

export type LngLat = [number, number]

const CACHE_KEY = 'pi-geocode-cache'
const BATCH_SIZE = 15

let cache: Record<string, LngLat> | null = null

function normalizePostcode(pc: string): string {
  return pc.replace(/\s+/g, '').toUpperCase()
}

function loadCache(): Record<string, LngLat> {
  if (cache) return cache
  try {
    const raw = localStorage.getItem(CACHE_KEY)
    cache = raw ? (JSON.parse(raw) as Record<string, LngLat>) : {}
  } catch {
    cache = {}
  }
  return cache!
}

function persistCache(): void {
  try {
    localStorage.setItem(CACHE_KEY, JSON.stringify(cache))
  } catch {
    // ignore quota / private mode
  }
}

export async function geocodePostcode(
  postcode: string,
  token: string,
  signal?: AbortSignal,
): Promise<LngLat | null> {
  if (!token) return null
  const key = normalizePostcode(postcode)
  const c = loadCache()
  if (c[key]) return c[key]

  const url = `https://api.mapbox.com/geocoding/v5/mapbox.places/${encodeURIComponent(
    postcode,
  )}.json?access_token=${token}&country=GB&types=postcode&limit=1`

  try {
    const res = await fetch(url, { signal })
    if (!res.ok) return null
    const data = await res.json()
    const center = data?.features?.[0]?.center as [number, number] | undefined
    if (!center) return null
    c[key] = center
    persistCache()
    return center
  } catch {
    return null
  }
}

export async function geocodeBatch(
  postcodes: string[],
  token: string,
  batchSize = BATCH_SIZE,
  onProgress?: (done: number, total: number) => void,
  signal?: AbortSignal,
): Promise<Map<string, LngLat>> {
  const out = new Map<string, LngLat>()
  const c = loadCache()

  const unique = Array.from(new Set(postcodes.map(normalizePostcode)))
  const toFetch = unique.filter((p) => !c[p])

  // Seed cached results
  for (const p of unique) {
    if (c[p]) {
      out.set(p, c[p])
    }
  }

  let done = out.size
  const total = unique.length
  onProgress?.(done, total)

  for (let i = 0; i < toFetch.length; i += batchSize) {
    if (signal?.aborted) break
    const batch = toFetch.slice(i, i + batchSize)
    await Promise.all(
      batch.map(async (p) => {
        if (signal?.aborted) return
        const coords = await geocodePostcode(p, token, signal)
        if (coords) {
          out.set(p, coords)
          done += 1
        } else {
          done += 1
        }
      }),
    )
    onProgress?.(done, total)
  }

  return out
}

export { extractPostcode }
