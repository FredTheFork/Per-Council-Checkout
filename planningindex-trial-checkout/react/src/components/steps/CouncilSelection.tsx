import { useState, useMemo } from 'react';
import { Search, MapPin, X, Check, ArrowRight, ArrowLeft, Sparkles } from 'lucide-react';
import { useCheckout } from '@/context/CheckoutContext';
import { PriceSummary } from '@/components/PriceSummary';

const MIN_COUNCILS = 1;
const MAX_COUNCILS = 5;

export function CouncilSelection() {
  const { selectedCouncils, toggleCouncil, setStep, canProceedFromStep, councils, nations, loading } = useCheckout();
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
  }, [search, activeNation, councils]);

  const hasEnough = selectedCouncils.length >= MIN_COUNCILS;
  const atMax = selectedCouncils.length >= MAX_COUNCILS;

  if (loading) {
    return (
      <div className="flex items-center justify-center py-20">
        <div className="h-8 w-8 animate-spin rounded-full border-2 border-slate-200 border-t-brand-600" />
      </div>
    );
  }

  return (
    <div className="grid grid-cols-1 gap-8 lg:grid-cols-[1fr_340px]">
      <div>
        <div className="mb-6">
          <div className="mb-3 inline-flex items-center gap-2 rounded-full bg-success-50 px-3 py-1.5 ring-1 ring-inset ring-success-200">
            <Sparkles className="h-4 w-4 text-success-600" />
            <span className="text-sm font-semibold text-success-700">14-Day Free Trial</span>
          </div>
          <h1 className="font-display text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
            Start Your Free Trial
          </h1>
          <p className="mt-2 text-sm text-slate-500 sm:text-base">
            Choose up to <span className="font-semibold text-slate-700">{MAX_COUNCILS} councils</span> to try free
            for 14 days. No payment required — you'll only be charged{' '}
            <span className="font-semibold text-slate-700">£3/council/month</span> if you decide to subscribe after
            your trial ends.
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
            {selectedCouncils.length}/{MAX_COUNCILS} selected
          </div>
        </div>

        {atMax && (
          <div className="mb-4 rounded-lg bg-accent-50 px-4 py-3 text-sm text-accent-700 ring-1 ring-inset ring-accent-200">
            You've reached the maximum of {MAX_COUNCILS} councils for your free trial. Remove a council to select a different one.
          </div>
        )}

        <div className="scrollbar-thin grid max-h-[480px] grid-cols-1 gap-2 overflow-y-auto p-1 pl-0 sm:grid-cols-2">
          {filteredCouncils.map((council) => {
            const isSelected = selectedCouncils.includes(council.name);
            const isDisabled = !isSelected && atMax;
            return (
              <button
                key={`${council.name}-${council.region}-${council.nation}`}
                onClick={() => toggleCouncil(council.name)}
                disabled={isDisabled}
                className={`group flex items-center justify-between rounded-xl border px-4 py-3 text-left transition-all duration-200 ${
                  isSelected
                    ? 'border-brand-500 bg-brand-50 ring-1 ring-brand-500'
                    : isDisabled
                      ? 'border-slate-200 bg-slate-50 opacity-50 cursor-not-allowed'
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
                  <span className={`text-xs font-medium ${isSelected ? 'text-success-600' : 'text-slate-400'}`}>
                    {isSelected ? 'Free' : 'Free trial'}
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
              Select at least {MIN_COUNCILS} council to continue
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
