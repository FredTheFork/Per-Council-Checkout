/**
 * Ray-casting point-in-polygon test (even-odd rule).
 * polygon: array of [lng, lat] pairs forming a closed ring (first !== last).
 */
export function pointInPolygon(lng: number, lat: number, polygon: number[][]): boolean {
  if (!polygon || polygon.length < 3) return false

  let inside = false
  for (let i = 0, j = polygon.length - 1; i < polygon.length; j = i++) {
    const xi = polygon[i][0]
    const yi = polygon[i][1]
    const xj = polygon[j][0]
    const yj = polygon[j][1]

    const intersect =
      yi > lat !== yj > lat &&
      lng < ((xj - xi) * (lat - yi)) / (yj - yi) + xi
    if (intersect) inside = !inside
  }
  return inside
}
