import { useState, useMemo } from 'react';
import { Search, MapPin, X, Check, ArrowRight, ArrowLeft } from 'lucide-react';
import { useCheckout } from '@/context/CheckoutContext';
import { councils, nations, PRICE_PER_COUNCIL } from '@/data/councils';
import { PriceSummary } from '@/components/PriceSummary';

const MIN_COUNCILS = 3;

export function CouncilSelection() {
  const { selectedCouncils, toggleCouncil, setStep, canProceedFromStep } = useCheckout();
  const [search, setSearch] = useState('');
  const [activeNation, setActiveNation] = useState<string>('all');

  const filteredCouncils = useMemo(() => {
    return councils.filter((c) => {
      const matchesSearch =
        search === '' ||
        c.name.toLowerCase().includes(search.toLowerCase()) ||
        c.region.toLowerCase().includes(search.toLowerCase());
      const matchesNation = activeNation === 'all' || c.nation === activeNation;
      return matchesSearch && matchesNation;
    });
  }, [search, activeNation]);

  const hasEnough = selectedCouncils.length >= MIN_COUNCILS;

  return (
    <div className="grid grid-cols-1 gap-8 lg:grid-cols-[1fr_340px]">
      <div>
        <div className="mb-6">
          <h1 className="font-display text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
            Select Your Councils
          </h1>
          <p className="mt-2 text-sm text-slate-500 sm:text-base">
            Choose the UK councils you need planning application access for. Each council is{' '}
            <span className="font-semibold text-slate-700">£{PRICE_PER_COUNCIL}/month</span>. Select at
            least {MIN_COUNCILS} to continue.
          </p>
        </div>

        <div className="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center">
          <div className="relative flex-1">
            <Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input
              type="text"
              placeholder="Search councils or regions..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="input-field pl-10"
              aria-label="Search councils"
            />
            {search && (
              <button
                onClick={() => setSearch('')}
                className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                aria-label="Clear search"
              >
                <X className="h-4 w-4" />
              </button>
            )}
          </div>
        </div>

        <div className="mb-5 flex flex-wrap gap-2">
          <button
            onClick={() => setActiveNation('all')}
            className={`rounded-lg px-3.5 py-1.5 text-sm font-medium transition-colors ${
              activeNation === 'all'
                ? 'bg-brand-600 text-white'
                : 'bg-white text-slate-600 ring-1 ring-inset ring-slate-200 hover:bg-slate-50'
            }`}
          >
            All Nations
          </button>
          {nations.map((n) => (
            <button
              key={n}
              onClick={() => setActiveNation(n)}
              className={`rounded-lg px-3.5 py-1.5 text-sm font-medium transition-colors ${
                activeNation === n
                  ? 'bg-brand-600 text-white'
                  : 'bg-white text-slate-600 ring-1 ring-inset ring-slate-200 hover:bg-slate-50'
              }`}
            >
              {n}
            </button>
          ))}
        </div>

        <div className="mb-4 flex items-center justify-between">
          <p className="text-sm text-slate-500">
            Showing <span className="font-semibold text-slate-700">{filteredCouncils.length}</span> councils
          </p>
          <div
            className={`badge ${
              hasEnough ? 'bg-success-50 text-success-700' : 'bg-slate-100 text-slate-500'
            }`}
          >
            {hasEnough ? (
              <Check className="h-3.5 w-3.5" />
            ) : (
              <MapPin className="h-3.5 w-3.5" />
            )}
            {selectedCouncils.length}/{MIN_COUNCILS}+ selected
          </div>
        </div>

        <div className="scrollbar-thin grid max-h-[480px] grid-cols-1 gap-2 overflow-y-auto pr-1 sm:grid-cols-2">
          {filteredCouncils.map((council) => {
            const isSelected = selectedCouncils.includes(council.name);
            return (
              <button
                key={council.name}
                onClick={() => toggleCouncil(council.name)}
                className={`group flex items-center justify-between rounded-xl border px-4 py-3 text-left transition-all duration-200 ${
                  isSelected
                    ? 'border-brand-500 bg-brand-50 ring-1 ring-brand-500'
                    : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50'
                }`}
              >
                <div className="min-w-0 flex-1">
                  <p
                    className={`truncate text-sm font-semibold ${
                      isSelected ? 'text-brand-700' : 'text-slate-700'
                    }`}
                  >
                    {council.name}
                  </p>
                  <p className="truncate text-xs text-slate-400">
                    {council.region} · {council.nation}
                  </p>
                </div>
                <div className="ml-3 flex shrink-0 items-center gap-2">
                  <span className={`text-sm font-semibold ${isSelected ? 'text-brand-600' : 'text-slate-400'}`}>
                    £{PRICE_PER_COUNCIL}
                  </span>
                  <div
                    className={`flex h-5 w-5 items-center justify-center rounded-md border-2 transition-colors ${
                      isSelected
                        ? 'border-brand-600 bg-brand-600 text-white'
                        : 'border-slate-300 bg-white group-hover:border-slate-400'
                    }`}
                  >
                    {isSelected && <Check className="h-3 w-3" />}
                  </div>
                </div>
              </button>
            );
          })}
          {filteredCouncils.length === 0 && (
            <div className="col-span-2 py-12 text-center">
              <MapPin className="mx-auto mb-3 h-8 w-8 text-slate-300" />
              <p className="text-sm text-slate-400">No councils match your search.</p>
            </div>
          )}
        </div>
      </div>

      <div>
        <PriceSummary />
      </div>

      <div className="flex items-center justify-between lg:col-span-2">
        <button
          onClick={() => {}}
          disabled
          className="btn-ghost invisible"
        >
          <ArrowLeft className="h-4 w-4" />
          Back
        </button>
        <div className="flex items-center gap-4">
          {!hasEnough && (
            <p className="hidden text-sm text-slate-400 sm:block">
              Select {MIN_COUNCILS - selectedCouncils.length} more to continue
            </p>
          )}
          <button
            onClick={() => setStep(2)}
            disabled={!canProceedFromStep(1)}
            className="btn-primary"
          >
            Continue to Templates
            <ArrowRight className="h-4 w-4" />
          </button>
        </div>
      </div>
    </div>
  );
}
