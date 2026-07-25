import { Building2 } from 'lucide-react';

export function Header() {
  return (
    <header className="border-b border-slate-200/80 bg-white/80 backdrop-blur-sm">
      <div className="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
        <div className="flex items-center gap-2.5">
          <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-brand-600 text-white shadow-sm">
            <Building2 className="h-5 w-5" />
          </div>
          <div className="flex flex-col leading-none">
            <span className="font-display text-lg font-bold tracking-tight text-slate-900">
              Planning Index
            </span>
            <span className="text-[11px] font-medium tracking-wide text-slate-400">
              planningindex.co.uk
            </span>
          </div>
        </div>
        <div className="hidden items-center gap-2 sm:flex">
          <span className="text-sm text-slate-500">Need help?</span>
          <a
            href="mailto:support@planningindex.co.uk"
            className="text-sm font-semibold text-brand-600 hover:text-brand-700"
          >
            Contact support
          </a>
        </div>
      </div>
    </header>
  );
}
