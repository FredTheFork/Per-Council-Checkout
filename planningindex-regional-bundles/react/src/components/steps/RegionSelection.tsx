import { useState, useMemo } from 'react';
import { Search, MapPin, X, Check, ArrowRight, ArrowLeft, Layers } from 'lucide-react';
import { useCheckout } from '@/context/CheckoutContext';
import { PriceSummary } from '@/components/PriceSummary';

export function RegionSelection() {
  const { selectedRegion, selectRegion, setStep, canProceedFromStep, regions, loading } = useCheckout();
  const [search, setSearch] = useState('');

  const filteredRegions = useMemo(() => {
    if (!search) return regions;
    const q = search.toLowerCase();
    return regions.filter((r) => {
      if (r.name.toLowerCase().includes(q)) return true;
      return r.councils.some((c) => c.toLowerCase().includes(q));
    });
  }, [search, regions]);

  const hasSelection = selectedRegion !== null;

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
          <h1 className="font-display text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
            Select Your Regional Bundle
          </h1>
          <p className="mt-2 text-sm text-slate-500 sm:text-base">
            Choose a regional bundle to get access to all councils in that region at a flat monthly
            price. Each bundle includes every council listed below.
          </p>
        </div>

        <div className="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center">
          <div className="relative flex-1">
            <Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input
              type="text"
              placeholder="Search regions or councils..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="input-field pl-10"
              aria-label="Search regions"
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

        <div className="mb-4 flex items-center justify-between">
          <p className="text-sm text-slate-500">
            Showing <span className="font-semibold text-slate-700">{filteredRegions.length}</span> bundles
          </p>
          <div
            className={`badge ${
              hasSelection ? 'bg-success-50 text-success-700' : 'bg-slate-100 text-slate-500'
            }`}
          >
            {hasSelection ? (
              <Check className="h-3.5 w-3.5" />
            ) : (
              <Layers className="h-3.5 w-3.5" />
            )}
            {hasSelection ? '1 selected' : 'None selected'}
          </div>
        </div>

        <div className="scrollbar-thin grid max-h-[560px] grid-cols-1 gap-3 overflow-y-auto pr-1">
          {filteredRegions.map((region) => {
            const isSelected = selectedRegion === region.id;
            return (
              <button
                key={region.id}
                onClick={() => selectRegion(region.id)}
                className={`group flex flex-col rounded-2xl border-2 p-4 text-left transition-all duration-200 ${
                  isSelected
                    ? 'border-brand-500 bg-brand-50 ring-1 ring-brand-500'
                    : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50'
                }`}
              >
                <div className="flex items-start justify-between gap-3">
                  <div className="min-w-0 flex-1">
                    <div className="flex items-center gap-2">
                      <MapPin className={`h-4 w-4 shrink-0 ${isSelected ? 'text-brand-600' : 'text-slate-400'}`} />
                      <p
                        className={`truncate text-sm font-bold ${
                          isSelected ? 'text-brand-700' : 'text-slate-700'
                        }`}
                      >
                        {region.name}
                      </p>
                    </div>
                    <p className="mt-0.5 text-xs text-slate-400">
                      {region.councils.length} councils included
                    </p>
                  </div>
                  <div className="flex shrink-0 items-center gap-3">
                    <div className="text-right">
                      <p className={`text-sm font-bold ${isSelected ? 'text-brand-600' : 'text-slate-700'}`}>
                        £{region.price}
                      </p>
                      <p className="text-[11px] text-slate-400">/month</p>
                    </div>
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
                </div>

                <div className="mt-3 flex flex-wrap gap-1.5">
                  {region.councils.slice(0, 6).map((council) => (
                    <span
                      key={council}
                      className={`rounded-md px-2 py-0.5 text-[11px] font-medium ${
                        isSelected
                          ? 'bg-brand-100 text-brand-700'
                          : 'bg-slate-100 text-slate-500'
                      }`}
                    >
                      {council}
                    </span>
                  ))}
                  {region.councils.length > 6 && (
                    <span className="rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-400">
                      +{region.councils.length - 6} more
                    </span>
                  )}
                </div>
              </button>
            );
          })}
          {filteredRegions.length === 0 && (
            <div className="py-12 text-center">
              <MapPin className="mx-auto mb-3 h-8 w-8 text-slate-300" />
              <p className="text-sm text-slate-400">No regions match your search.</p>
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
          {!hasSelection && (
            <p className="hidden text-sm text-slate-400 sm:block">
              Select a bundle to continue
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
