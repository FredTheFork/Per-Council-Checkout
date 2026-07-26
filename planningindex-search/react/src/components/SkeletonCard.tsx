export default function SkeletonCard() {
  return (
    <div className="card flex flex-col overflow-hidden p-5">
      {/* Council + dot */}
      <div className="flex items-center gap-2">
        <div className="h-3 w-24 rounded shimmer-bg" />
        <div className="h-2.5 w-2.5 rounded-full shimmer-bg" />
      </div>

      {/* Address */}
      <div className="mt-2 space-y-1.5">
        <div className="h-4 w-full rounded shimmer-bg" />
        <div className="h-4 w-3/4 rounded shimmer-bg" />
      </div>

      {/* Badges */}
      <div className="mt-3 flex items-center gap-2">
        <div className="h-6 w-20 rounded-full shimmer-bg" />
        <div className="h-6 w-16 rounded-full shimmer-bg" />
      </div>

      {/* Meta row */}
      <div className="mt-3 flex items-center gap-4">
        <div className="h-3 w-20 rounded shimmer-bg" />
        <div className="h-3 w-16 rounded shimmer-bg" />
      </div>

      {/* Description */}
      <div className="mt-3 space-y-1.5">
        <div className="h-3 w-full rounded shimmer-bg" />
        <div className="h-3 w-full rounded shimmer-bg" />
        <div className="h-3 w-2/3 rounded shimmer-bg" />
      </div>

      {/* Action row */}
      <div className="mt-4 flex items-center gap-2 border-t border-slate-100 pt-3">
        <div className="h-7 w-16 rounded-lg shimmer-bg" />
        <div className="h-7 w-16 rounded-lg shimmer-bg" />
        <div className="ml-auto h-7 w-20 rounded-lg shimmer-bg" />
      </div>
    </div>
  )
}
