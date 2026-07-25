export function Header() {
  return (
    <header className="bg-brand-600">
      <div className="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
        <a href="https://planningindex.co.uk" className="flex items-baseline gap-0.5">
          <span className="font-display text-lg font-bold tracking-tight text-white">
            PlanningIndex
          </span>
          <span className="font-display text-lg font-bold tracking-tight text-orange-500">
            .co.uk
          </span>
        </a>
        <div className="hidden items-center gap-2 sm:flex">
          <span className="text-sm text-brand-100">Need help?</span>
          <a
            href="https://planningindex.co.uk/contact"
            className="text-sm font-semibold text-white hover:text-orange-400"
          >
            Contact us
          </a>
        </div>
      </div>
    </header>
  );
}
