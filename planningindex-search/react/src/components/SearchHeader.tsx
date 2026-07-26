import SearchBar from './SearchBar'
import QuickFilterChips from './QuickFilterChips'

export default function SearchHeader() {
  return (
    <div className="sticky top-0 z-30 border-b border-slate-200 bg-white/90 shadow-sm backdrop-blur-md">
      <div className="mx-auto max-w-7xl px-4 py-4 sm:px-6 lg:px-8">
        <SearchBar />
        <div className="mt-3">
          <QuickFilterChips />
        </div>
      </div>
    </div>
  )
}
