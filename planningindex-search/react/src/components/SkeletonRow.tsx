export default function SkeletonRow() {
  return (
    <div className="flex items-center gap-3 border-b border-slate-100 px-4 py-2.5">
      {/* Checkbox */}
      <div className="h-4 w-4 flex-shrink-0 rounded shimmer-bg" />

      {/* Dot */}
      <div className="h-2.5 w-2.5 flex-shrink-0 rounded-full shimmer-bg" />

      {/* Council */}
      <div className="hidden h-3 w-28 flex-shrink-0 rounded shimmer-bg md:block" />

      {/* Address */}
      <div className="flex-1">
        <div className="h-4 w-full max-w-xs rounded shimmer-bg" />
      </div>

      {/* Value */}
      <div className="hidden h-4 w-24 flex-shrink-0 rounded shimmer-bg sm:block" />

      {/* Date */}
      <div className="hidden h-3 w-24 flex-shrink-0 rounded shimmer-bg lg:block" />

      {/* Score */}
      <div className="hidden h-3 w-10 flex-shrink-0 rounded shimmer-bg lg:block" />

      {/* Reference */}
      <div className="hidden h-3 w-28 flex-shrink-0 rounded shimmer-bg xl:block" />

      {/* Actions */}
      <div className="flex flex-shrink-0 items-center gap-1">
        <div className="h-7 w-7 rounded shimmer-bg" />
        <div className="h-7 w-7 rounded shimmer-bg" />
        <div className="h-7 w-7 rounded shimmer-bg" />
      </div>
    </div>
  )
}
