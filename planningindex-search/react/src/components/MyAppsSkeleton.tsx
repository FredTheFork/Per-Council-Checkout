export default function MyAppsSkeleton({ count = 4 }: { count?: number }) {
  return (
    <div className="space-y-3 p-4">
      {Array.from({ length: count }).map((_, i) => (
        <div
          key={i}
          className="rounded-xl bg-white p-3 ring-1 ring-slate-200/60"
        >
          <div className="flex items-center gap-2">
            <div className="h-3 w-20 rounded shimmer-bg" />
            <div className="ml-auto h-3 w-3 rounded-full shimmer-bg" />
          </div>
          <div className="mt-2 h-4 w-3/4 rounded shimmer-bg" />
          <div className="mt-2.5 flex items-center gap-2">
            <div className="h-5 w-16 rounded-full shimmer-bg" />
            <div className="h-3 w-20 rounded shimmer-bg" />
          </div>
        </div>
      ))}
    </div>
  )
}
