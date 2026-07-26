import { useEffect, useRef, useState, useCallback } from 'react'
import { createRoot } from 'react-dom/client'
import mapboxgl from 'mapbox-gl'
import MapboxDraw from '@mapbox/mapbox-gl-draw'
import { MapPin, Loader as Loader2, Flame, PenTool, X, TriangleAlert as AlertTriangle, MapPinOff } from 'lucide-react'
import { useSearchContext } from '../context/SearchContext'
import { config } from '../config'
import type { PlanningApp } from '../types'
import {
  extractPostcode,
  getEstPrice,
} from '../utils/format'
import { geocodeBatch, type LngLat } from '../utils/geocode'
import { getValueBand, VALUE_BAND_COLORS, VALUE_BAND_LABELS, type ValueBand } from '../utils/mapColors'
import { pointInPolygon } from '../utils/pointInPolygon'
import MapPopup from './MapPopup'

interface GeoJSONFeature {
  type: 'Feature'
  geometry: { type: 'Point'; coordinates: [number, number] }
  properties: {
    postId: string
    marker_color: string
    value_band: ValueBand
    council: string
    address: string
    est_price: string
    est_value_numeric: number
    date_received: string
    council_reference: string
    saved: boolean
    in_workspace: boolean
    app_id: number
  }
}

const MARKER_LAYERS = [
  'cluster-glow',
  'cluster-ring',
  'clusters',
  'cluster-count',
  'unclustered-glow',
  'unclustered-ring',
  'unclustered-point',
  'unclustered-dot',
] as const

export default function MapView() {
  const {
    mapApps,
    loadingMap,
    savedIds,
    workspaceIds,
    saveApp,
    unsaveApp,
    addToWorkspace,
    openDetailPanel,
  } = useSearchContext()

  const token = config.getMapboxToken()

  const mapContainerRef = useRef<HTMLDivElement | null>(null)
  const mapInstanceRef = useRef<mapboxgl.Map | null>(null)
  const drawRef = useRef<MapboxDraw | null>(null)
  const popupRef = useRef<mapboxgl.Popup | null>(null)
  const popupRootRef = useRef<ReturnType<typeof createRoot> | null>(null)
  const hasFittedRef = useRef(false)

  const [mapReady, setMapReady] = useState(false)
  const [mapError, setMapError] = useState<string | null>(null)
  const [geocodingProgress, setGeocodingProgress] = useState<{ done: number; total: number } | null>(null)
  const [showHeatmap, setShowHeatmap] = useState(false)
  const [drawMode, setDrawMode] = useState(false)
  const [drawnPolygon, setDrawnPolygon] = useState<number[][] | null>(null)
  const [popupApp, setPopupApp] = useState<PlanningApp | null>(null)
  const [popupCoords, setPopupCoords] = useState<[number, number] | null>(null)

  // ── Map initialization ──────────────────────────────────────────────
  useEffect(() => {
    if (!token || !mapContainerRef.current || mapInstanceRef.current) return

    mapboxgl.accessToken = token

    let map: mapboxgl.Map
    try {
      map = new mapboxgl.Map({
        container: mapContainerRef.current,
        style: 'mapbox://styles/mapbox/light-v11',
        center: [-2.5, 54.0],
        zoom: 5.5,
        antialias: true,
      })
      mapInstanceRef.current = map
    } catch (err) {
      setMapError(err instanceof Error ? err.message : 'Failed to load map')
      return
    }

    map.addControl(new mapboxgl.NavigationControl({ showCompass: false }), 'top-right')

    map.on('load', () => {
      map.addSource('planning-apps', {
        type: 'geojson',
        data: { type: 'FeatureCollection', features: [] },
        cluster: true,
        clusterMaxZoom: 14,
        clusterRadius: 60,
      })

      // Cluster glow halo
      map.addLayer({
        id: 'cluster-glow',
        type: 'circle',
        source: 'planning-apps',
        filter: ['has', 'point_count'],
        paint: {
          'circle-color': '#1b2534',
          'circle-radius': ['step', ['get', 'point_count'], 30, 10, 38, 50, 48],
          'circle-opacity': 0.08,
          'circle-blur': 0.7,
        },
      })

      // Cluster outer ring
      map.addLayer({
        id: 'cluster-ring',
        type: 'circle',
        source: 'planning-apps',
        filter: ['has', 'point_count'],
        paint: {
          'circle-color': 'transparent',
          'circle-radius': ['step', ['get', 'point_count'], 24, 10, 30, 50, 38],
          'circle-stroke-width': 2,
          'circle-stroke-color': 'rgba(27, 37, 52, 0.2)',
        },
      })

      // Main cluster circle
      map.addLayer({
        id: 'clusters',
        type: 'circle',
        source: 'planning-apps',
        filter: ['has', 'point_count'],
        paint: {
          'circle-color': ['step', ['get', 'point_count'], '#1b2534', 10, '#2d3a4d', 50, '#f97316'],
          'circle-radius': ['step', ['get', 'point_count'], 18, 10, 24, 50, 32],
          'circle-stroke-width': 3,
          'circle-stroke-color': '#ffffff',
        },
      })

      // Cluster count
      map.addLayer({
        id: 'cluster-count',
        type: 'symbol',
        source: 'planning-apps',
        filter: ['has', 'point_count'],
        layout: {
          'text-field': ['get', 'point_count_abbreviated'],
          'text-size': ['step', ['get', 'point_count'], 13, 10, 14, 50, 16],
        },
        paint: {
          'text-color': '#ffffff',
        },
      })

      // Unclustered glow
      map.addLayer({
        id: 'unclustered-glow',
        type: 'circle',
        source: 'planning-apps',
        filter: ['!', ['has', 'point_count']],
        paint: {
          'circle-color': '#f97316',
          'circle-radius': 20,
          'circle-opacity': 0.1,
          'circle-blur': 0.9,
        },
      })

      // Unclustered ring
      map.addLayer({
        id: 'unclustered-ring',
        type: 'circle',
        source: 'planning-apps',
        filter: ['!', ['has', 'point_count']],
        paint: {
          'circle-color': 'transparent',
          'circle-radius': 13,
          'circle-stroke-width': 1.5,
          'circle-stroke-opacity': 0.25,
          'circle-stroke-color': ['get', 'marker_color'],
        },
      })

      // Main marker dot (color by value band)
      map.addLayer({
        id: 'unclustered-point',
        type: 'circle',
        source: 'planning-apps',
        filter: ['!', ['has', 'point_count']],
        paint: {
          'circle-color': ['get', 'marker_color'],
          'circle-radius': 7,
          'circle-stroke-width': 2.5,
          'circle-stroke-color': '#ffffff',
        },
      })

      // Inner highlight
      map.addLayer({
        id: 'unclustered-dot',
        type: 'circle',
        source: 'planning-apps',
        filter: ['!', ['has', 'point_count']],
        paint: {
          'circle-color': '#ffffff',
          'circle-radius': 2.5,
          'circle-opacity': 0.85,
        },
      })

      // Heatmap (hidden by default)
      map.addLayer({
        id: 'heatmap-layer',
        type: 'heatmap',
        source: 'planning-apps',
        layout: { visibility: 'none' },
        paint: {
          'heatmap-weight': [
            'interpolate',
            ['linear'],
            ['get', 'est_value_numeric'],
            0, 0,
            100000, 1,
            500000, 3,
          ],
          'heatmap-color': [
            'interpolate',
            ['linear'],
            ['heatmap-density'],
            0, 'rgba(0, 0, 255, 0)',
            0.33, '#4f6a93',
            0.66, '#f97316',
            1, '#dc2626',
          ],
          'heatmap-radius': ['interpolate', ['linear'], ['zoom'], 0, 30, 9, 50],
          'heatmap-intensity': 1,
          'heatmap-opacity': 0.7,
        },
      })

      setMapReady(true)
    })

    map.on('error', (e) => {
      setMapError(e.error?.message || 'Map error')
    })

    // Cluster click → expand
    map.on('click', 'clusters', (e) => {
      const features = map.queryRenderedFeatures(e.point, { layers: ['clusters'] })
      const clusterId = features[0]?.properties?.cluster_id as number | undefined
      if (clusterId === undefined) return
      const source = map.getSource('planning-apps') as mapboxgl.GeoJSONSource
      source.getClusterExpansionZoom(clusterId, (err, zoom) => {
        if (err || zoom == null) return
        const geom = features[0].geometry as GeoJSON.Point
        const coords: [number, number] = geom.coordinates as [number, number]
        map.easeTo({ center: coords, zoom, duration: 500 })
      })
    })

    // Marker click → popup
    map.on('click', 'unclustered-point', (e) => {
      const features = map.queryRenderedFeatures(e.point, { layers: ['unclustered-point'] })
      const f = features[0]
      if (!f) return
      const geom = f.geometry as GeoJSON.Point
      const coords: [number, number] = geom.coordinates as [number, number]
      const postId = f.properties?.postId as string | undefined
      const app = mapAppsRef.current.find((a) => String(a.id) === postId) || null
      setPopupApp(app)
      setPopupCoords(coords)
    })

    // Cursor
    const setPointer = () => (map.getCanvas().style.cursor = 'pointer')
    const setDefault = () => (map.getCanvas().style.cursor = '')
    map.on('mouseenter', 'clusters', setPointer)
    map.on('mouseleave', 'clusters', setDefault)
    map.on('mouseenter', 'unclustered-point', setPointer)
    map.on('mouseleave', 'unclustered-point', setDefault)

    // Resize observer
    const resizeObserver = new ResizeObserver(() => map.resize())
    resizeObserver.observe(mapContainerRef.current)

    return () => {
      resizeObserver.disconnect()
      closePopup()
      if (drawRef.current) {
        try { map.removeControl(drawRef.current) } catch { /* noop */ }
        drawRef.current = null
      }
      map.remove()
      mapInstanceRef.current = null
      setMapReady(false)
      hasFittedRef.current = false
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [token])

  // Keep a ref to mapApps for use inside map event handlers
  const mapAppsRef = useRef<PlanningApp[]>(mapApps)
  mapAppsRef.current = mapApps

  // ── Geocode + data update ───────────────────────────────────────────
  useEffect(() => {
    if (!mapReady || !mapInstanceRef.current) return
    const map = mapInstanceRef.current

    let cancelled = false
    const abort = new AbortController()

    async function update() {
      if (mapApps.length === 0) {
        const source = map.getSource('planning-apps') as mapboxgl.GeoJSONSource | undefined
        if (source) source.setData({ type: 'FeatureCollection', features: [] })
        return
      }

      // Extract postcodes
      const postcodeToApps = new Map<string, PlanningApp[]>()
      for (const app of mapApps) {
        const pc = extractPostcode(app.meta?.address || '')
        if (!pc) continue
        const norm = pc.replace(/\s+/g, '').toUpperCase()
        if (!postcodeToApps.has(norm)) postcodeToApps.set(norm, [])
        postcodeToApps.get(norm)!.push(app)
      }

      if (postcodeToApps.size === 0) {
        const source = map.getSource('planning-apps') as mapboxgl.GeoJSONSource | undefined
        if (source) source.setData({ type: 'FeatureCollection', features: [] })
        return
      }

      const postcodes = Array.from(postcodeToApps.keys())
      setGeocodingProgress({ done: 0, total: postcodes.length })

      const coords = await geocodeBatch(
        postcodes,
        token,
        15,
        (done, total) => {
          if (!cancelled) setGeocodingProgress({ done, total })
        },
        abort.signal,
      )

      if (cancelled) return

      // Build features
      const features: GeoJSONFeature[] = []
      for (const [pc, apps] of postcodeToApps) {
        const c = coords.get(pc)
        if (!c) continue
        for (const app of apps) {
          const band = getValueBand(app.meta)
          features.push({
            type: 'Feature',
            geometry: { type: 'Point', coordinates: [c[0], c[1]] },
            properties: {
              postId: String(app.id),
              marker_color: VALUE_BAND_COLORS[band],
              value_band: band,
              council: app._authority_name || '',
              address: app.meta?.address || '',
              est_price: getEstPrice(app.meta) || '',
              est_value_numeric: parseFloat(app.meta?.est_value_numeric || '0') || 0,
              date_received: app.meta?.date_received || '',
              council_reference: app.meta?.council_reference || '',
              saved: savedIdsRef.current.has(app.id),
              in_workspace: workspaceIdsRef.current.has(app.id),
              app_id: app.id,
            },
          })
        }
      }

      if (cancelled) return

      // Apply drawn polygon filter
      let filteredFeatures = features
      if (drawnPolygonRef.current) {
        filteredFeatures = features.filter((f) =>
          pointInPolygon(f.geometry.coordinates[0], f.geometry.coordinates[1], drawnPolygonRef.current!),
        )
      }

      const source = map.getSource('planning-apps') as mapboxgl.GeoJSONSource | undefined
      if (source) {
        source.setData({ type: 'FeatureCollection', features: filteredFeatures })
      }

      // Fit bounds on first load
      if (!hasFittedRef.current && filteredFeatures.length > 0) {
        const bounds = new mapboxgl.LngLatBounds()
        for (const f of filteredFeatures) {
          bounds.extend(f.geometry.coordinates)
        }
        map.fitBounds(bounds, { padding: 60, maxZoom: 14, duration: 800 })
        hasFittedRef.current = true
      }

      setGeocodingProgress(null)
    }

    void update()

    return () => {
      cancelled = true
      abort.abort()
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [mapReady, mapApps, token])

  // Refs for latest saved/workspace state inside the effect
  const savedIdsRef = useRef(savedIds)
  const workspaceIdsRef = useRef(workspaceIds)
  savedIdsRef.current = savedIds
  workspaceIdsRef.current = workspaceIds
  const drawnPolygonRef = useRef<number[][] | null>(drawnPolygon)
  drawnPolygonRef.current = drawnPolygon

  // ── Heatmap toggle ──────────────────────────────────────────────────
  useEffect(() => {
    if (!mapReady || !mapInstanceRef.current) return
    const map = mapInstanceRef.current

    if (showHeatmap) {
      for (const layer of MARKER_LAYERS) {
        map.setLayoutProperty(layer, 'visibility', 'none')
      }
      map.setLayoutProperty('heatmap-layer', 'visibility', 'visible')
    } else {
      map.setLayoutProperty('heatmap-layer', 'visibility', 'none')
      for (const layer of MARKER_LAYERS) {
        map.setLayoutProperty(layer, 'visibility', 'visible')
      }
    }
  }, [showHeatmap, mapReady])

  // ── Popup rendering ─────────────────────────────────────────────────
  const closePopup = useCallback(() => {
    if (popupRef.current) {
      popupRef.current.remove()
      popupRef.current = null
    }
    if (popupRootRef.current) {
      try { popupRootRef.current.unmount() } catch { /* noop */ }
      popupRootRef.current = null
    }
    setPopupApp(null)
    setPopupCoords(null)
  }, [])

  useEffect(() => {
    if (!mapReady || !mapInstanceRef.current) return
    if (!popupApp || !popupCoords) {
      closePopup()
      return
    }

    const map = mapInstanceRef.current
    closePopup()

    const container = document.createElement('div')
    const popup = new mapboxgl.Popup({
      closeOnClick: true,
      maxWidth: '320px',
      offset: 12,
      anchor: 'bottom',
    })
      .setLngLat(popupCoords)
      .setDOMContent(container)
      .addTo(map)

    popup.on('close', () => {
      setPopupApp(null)
      setPopupCoords(null)
    })

    popupRef.current = popup
    popupRootRef.current = createRoot(container)
    popupRootRef.current.render(
      <MapPopup
        app={popupApp}
        saved={savedIds.has(popupApp.id)}
        inWorkspace={workspaceIds.has(popupApp.id)}
        onToggleSave={(id) => {
          if (savedIds.has(id)) unsaveApp(id)
          else saveApp(id)
        }}
        onAddToWorkspace={addToWorkspace}
        onViewDetails={(app) => {
          closePopup()
          openDetailPanel(app.id)
        }}
        onClose={() => closePopup()}
      />,
    )
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [popupApp, popupCoords, mapReady, savedIds, workspaceIds])

  // ── Draw mode ───────────────────────────────────────────────────────
  useEffect(() => {
    if (!mapReady || !mapInstanceRef.current) return
    const map = mapInstanceRef.current

    if (drawMode && !drawRef.current) {
      const draw = new MapboxDraw({
        displayControlsDefault: false,
        controls: { polygon: true, trash: true },
      })
      map.addControl(draw)
      drawRef.current = draw
      draw.changeMode('draw_polygon')
      map.getCanvas().style.cursor = 'crosshair'

      const onCreate = (e: { features: GeoJSON.Feature[] }) => {
        const f = e.features?.[0]
        if (!f) return
        const geom = f.geometry as GeoJSON.Polygon
        const coords = geom.coordinates as number[][][]
        const ring = coords[0]
        if (ring && ring.length >= 3) {
          setDrawnPolygon(ring)
        }
        setDrawMode(false)
      }
      ;(draw as unknown as { on: (ev: string, cb: (e: { features: GeoJSON.Feature[] }) => void) => void }).on('draw.create', onCreate)
    }

    if (!drawMode && drawRef.current) {
      map.getCanvas().style.cursor = ''
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [drawMode, mapReady])

  const handleClearArea = useCallback(() => {
    if (drawRef.current) {
      drawRef.current.deleteAll()
    }
    setDrawnPolygon(null)
    setDrawMode(false)
    const map = mapInstanceRef.current
    if (map) {
      map.getCanvas().style.cursor = ''
    }
  }, [])

  // ── Early returns ───────────────────────────────────────────────────
  if (!token) {
    return (
      <div role="alert" className="mx-auto max-w-md rounded-xl border border-slate-200 bg-white p-8 text-center shadow-soft">
        <div className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-slate-100">
          <MapPinOff className="h-7 w-7 text-slate-400" />
        </div>
        <h3 className="text-base font-semibold text-slate-700">Map not available</h3>
        <p className="mt-1 text-sm text-slate-500">
          A Mapbox access token is required to display the map view. Add one in the plugin settings.
        </p>
      </div>
    )
  }

  if (mapError) {
    return (
      <div role="alert" className="mx-auto max-w-md rounded-xl border border-error-200 bg-error-50 p-8 text-center">
        <div className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-error-100">
          <AlertTriangle className="h-7 w-7 text-error-600" />
        </div>
        <h3 className="text-base font-semibold text-error-700">Map failed to load</h3>
        <p className="mt-1 text-sm text-error-600">{mapError}</p>
      </div>
    )
  }

  if (loadingMap && mapApps.length === 0) {
    return (
      <div className="flex h-[600px] items-center justify-center rounded-xl bg-white shadow-soft ring-1 ring-slate-200/60">
        <div className="flex flex-col items-center gap-3">
          <Loader2 className="h-8 w-8 animate-spin text-brand-600" />
          <p className="text-sm font-medium text-slate-600">Loading all applications…</p>
        </div>
      </div>
    )
  }

  if (!loadingMap && mapApps.length === 0) {
    return (
      <div className="mx-auto max-w-md rounded-xl border border-slate-200 bg-white p-8 text-center shadow-soft">
        <div className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-slate-100">
          <MapPin className="h-7 w-7 text-slate-400" />
        </div>
        <h3 className="text-base font-semibold text-slate-700">No applications to display</h3>
        <p className="mt-1 text-sm text-slate-500">
          No planning applications match the current filters, or none have postcode data to map.
        </p>
      </div>
    )
  }

  return (
    <div className="relative h-[600px] overflow-hidden rounded-xl bg-white shadow-soft ring-1 ring-slate-200/60">
      <div ref={mapContainerRef} aria-label="Map of planning applications" className="h-full w-full" />

      {/* Overlay controls bar */}
      <div className="pointer-events-none absolute left-3 top-3 z-10 flex flex-wrap items-center gap-2">
        <div className="pointer-events-auto flex items-center gap-1.5 rounded-xl bg-white/90 p-1.5 shadow-soft backdrop-blur-md">
          <button
            type="button"
            onClick={() => setDrawMode((v) => !v)}
            aria-label="Draw area"
            title="Draw area"
            className={`inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium transition-colors ${
              drawMode
                ? 'bg-brand-600 text-white'
                : 'text-slate-600 hover:bg-slate-100'
            }`}
          >
            <PenTool className="h-3.5 w-3.5" />
            <span className="hidden sm:inline">Draw area</span>
          </button>

          {drawnPolygon && (
            <button
              type="button"
              onClick={handleClearArea}
              aria-label="Clear area"
              title="Clear area"
              className="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium text-slate-600 transition-colors hover:bg-slate-100"
            >
              <X className="h-3.5 w-3.5" />
              <span className="hidden sm:inline">Clear</span>
            </button>
          )}

          <div className="mx-0.5 h-5 w-px bg-slate-200" />

          <button
            type="button"
            onClick={() => setShowHeatmap((v) => !v)}
            aria-label="Toggle heatmap"
            title="Toggle heatmap"
            className={`inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium transition-colors ${
              showHeatmap
                ? 'bg-accent-500 text-white'
                : 'text-slate-600 hover:bg-slate-100'
            }`}
          >
            <Flame className="h-3.5 w-3.5" />
            <span className="hidden sm:inline">Heatmap</span>
          </button>
        </div>

        {drawnPolygon && (
          <span className="pointer-events-auto inline-flex items-center gap-1.5 rounded-full bg-brand-600 px-3 py-1.5 text-xs font-semibold text-white shadow-soft">
            <MapPin className="h-3.5 w-3.5" />
            Region filter active
          </span>
        )}
      </div>

      {/* Legend / progress */}
      <div className="absolute bottom-3 left-3 z-10">
        {geocodingProgress ? (
          <div className="rounded-xl bg-white/90 px-3 py-2 shadow-soft backdrop-blur-md">
            <div className="flex items-center gap-2">
              <Loader2 className="h-3.5 w-3.5 animate-spin text-brand-600" />
              <span className="text-xs font-medium text-slate-700">
                Geocoding {geocodingProgress.done} of {geocodingProgress.total} postcodes…
              </span>
            </div>
            <div className="mt-1.5 h-1 w-40 overflow-hidden rounded-full bg-slate-200">
              <div
                className="h-full rounded-full bg-brand-600 transition-all duration-300"
                style={{
                  width: `${geocodingProgress.total > 0 ? (geocodingProgress.done / geocodingProgress.total) * 100 : 0}%`,
                }}
              />
            </div>
          </div>
        ) : (
          <div
            role="img"
            aria-label="Map legend: value band colors"
            className="rounded-xl bg-white/90 px-3 py-2 shadow-soft backdrop-blur-md"
          >
            <p className="mb-1.5 text-xs font-semibold uppercase tracking-wide text-slate-500">
              {showHeatmap ? 'Density' : 'Value bands'}
            </p>
            {showHeatmap ? (
              <div className="flex items-center gap-2">
                <div className="h-2 w-24 rounded-full bg-gradient-to-r from-blue-500 via-accent-500 to-red-600" />
                <span className="text-xs text-slate-600">Low → High</span>
              </div>
            ) : (
              <ul className="space-y-1">
                {(Object.keys(VALUE_BAND_COLORS) as ValueBand[]).map((band) => (
                  <li key={band} className="flex items-center gap-2">
                    <span
                      className="h-2.5 w-2.5 rounded-full"
                      style={{ backgroundColor: VALUE_BAND_COLORS[band] }}
                    />
                    <span className="text-xs text-slate-600">{VALUE_BAND_LABELS[band]}</span>
                  </li>
                ))}
              </ul>
            )}
          </div>
        )}
      </div>
    </div>
  )
}
