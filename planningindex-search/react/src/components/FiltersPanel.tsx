import { useEffect, useRef, useState } from 'react'
import { RotateCcw, Bookmark, X } from 'lucide-react'
import { useSearchContext } from '../context/SearchContext'
import { advancedFilterCount } from '../utils/advancedFilters'
import { config } from '../config'
import AuthorityMultiSelect from './AuthorityMultiSelect'

interface ToggleSwitchProps {
  checked: boolean
  onChange: () => void
  label: string
  description?: string
}

function ToggleSwitch({ checked, onChange, label, description }: ToggleSwitchProps) {
  return (
    <div className="flex items-start justify-between gap-3 py-2">
      <div className="min-w-0">
        <p className="text-sm font-medium text-slate-700">{label}</p>
        {description && <p className="mt-0.5 text-xs text-slate-500">{description}</p>}
      </div>
      <button
        type="button"
        role="switch"
        aria-checked={checked}
        aria-label={label}
        onClick={onChange}
        className={`relative mt-0.5 inline-flex h-6 w-11 flex-shrink-0 items-center rounded-full transition-colors duration-200 focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2 ${
          checked ? 'bg-brand-500' : 'bg-slate-300'
        }`}
      >
        <span
          className={`inline-block h-5 w-5 transform rounded-full bg-white shadow-sm transition-transform duration-200 ${
            checked ? 'translate-x-5' : 'translate-x-0.5'
          }`}
        />
      </button>
    </div>
  )
}

interface FieldProps {
  label: string
  children: React.ReactNode
}

function Field({ label, children }: FieldProps) {
  return (
    <div>
      <label className="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">
        {label}
      </label>
      {children}
    </div>
  )
}

interface FiltersPanelProps {
  open: boolean
  onClose?: () => void
}

export default function FiltersPanel({ open, onClose }: FiltersPanelProps) {
  const {
    filters,
    setFilters,
    runSearch,
    clearFilters,
    allowedAuthorities,
    categories,
    toggleHighValue,
    toggleConstruction,
    toggleHideSaved,
    toggleHideViewed,
    toggleHideWorkspace,
    setValueRange,
    rawApps,
    savedIds,
    recentIds,
    workspaceIds,
    openSaveSearchModal,
  } = useSearchContext()

  const [minValue, setMinValue] = useState('')
  const [maxValue, setMaxValue] = useState('')
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null)

  useEffect(() => {
    setMinValue(filters.estValueMin != null ? String(filters.estValueMin) : '')
    setMaxValue(filters.estValueMax != null ? String(filters.estValueMax) : '')
  }, [filters.estValueMin, filters.estValueMax])

  useEffect(() => {
    return () => {
      if (debounceRef.current) clearTimeout(debounceRef.current)
    }
  }, [])

  const commitValueRange = (min: string, max: string) => {
    const minNum = min.trim() === '' ? undefined : Number(min)
    const maxNum = max.trim() === '' ? undefined : Number(max)
    setValueRange(
      minNum != null && !isNaN(minNum) ? minNum : undefined,
      maxNum != null && !isNaN(maxNum) ? maxNum : undefined,
    )
  }

  const handleValueChange = (which: 'min' | 'max', value: string) => {
    if (which === 'min') setMinValue(value)
    else setMaxValue(value)
    if (debounceRef.current) clearTimeout(debounceRef.current)
    debounceRef.current = setTimeout(() => {
      commitValueRange(which === 'min' ? value : minValue, which === 'max' ? value : maxValue)
    }, 500)
  }

  const handleAuthorityChange = (ids: number[]) => {
    setFilters({ authority: ids.length ? ids : undefined })
    void runSearch()
  }

  const handleCategoryChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
    const v = e.target.value
    setFilters({ app_category: v ? Number(v) : undefined })
    void runSearch()
  }

  const handleDateChange = (which: 'date_from' | 'date_to', value: string) => {
    setFilters({ [which]: value || undefined })
    void runSearch()
  }

  const handleClearAll = () => {
    setMinValue('')
    setMaxValue('')
    clearFilters()
  }

  const handleDone = () => {
    onClose?.()
  }

  const excludeAvailable =
    savedIds.size > 0 || recentIds.size > 0 || workspaceIds.size > 0

  const filterContent = (
    <>
      <div className="grid grid-cols-1 gap-x-6 gap-y-5 sm:grid-cols-2 lg:grid-cols-3">
        {/* Location */}
        <div className="space-y-3 sm:col-span-2 lg:col-span-1">
          <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Location</p>
          <Field label="Authority">
            <AuthorityMultiSelect
              authorities={allowedAuthorities}
              selected={filters.authority ?? []}
              onChange={handleAuthorityChange}
            />
          </Field>
          {categories.length > 0 && (
            <Field label="Category">
              <select
                value={filters.app_category != null ? String(filters.app_category) : ''}
                onChange={handleCategoryChange}
                className="input-field"
                aria-label="Category"
              >
                <option value="">All categories</option>
                {categories.map((c) => (
                  <option key={c.id} value={String(c.id)}>
                    {c.name}
                  </option>
                ))}
              </select>
            </Field>
          )}
        </div>

        {/* Date */}
        <div className="space-y-3">
          <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Date</p>
          <div className="grid grid-cols-2 gap-3">
            <Field label="From">
              <input
                type="date"
                value={filters.date_from ?? ''}
                onChange={(e) => handleDateChange('date_from', e.target.value)}
                className="input-field"
                aria-label="Date from"
              />
            </Field>
            <Field label="To">
              <input
                type="date"
                value={filters.date_to ?? ''}
                onChange={(e) => handleDateChange('date_to', e.target.value)}
                className="input-field"
                aria-label="Date to"
              />
            </Field>
          </div>
        </div>

        {/* Value */}
        <div className="space-y-3">
          <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Estimated value</p>
          <div className="grid grid-cols-2 gap-3">
            <Field label="Min (£)">
              <input
                type="number"
                inputMode="numeric"
                min={0}
                value={minValue}
                onChange={(e) => handleValueChange('min', e.target.value)}
                placeholder="0"
                className="input-field"
                aria-label="Minimum estimated value"
              />
            </Field>
            <Field label="Max (£)">
              <input
                type="number"
                inputMode="numeric"
                min={0}
                value={maxValue}
                onChange={(e) => handleValueChange('max', e.target.value)}
                placeholder="Any"
                className="input-field"
                aria-label="Maximum estimated value"
              />
            </Field>
          </div>
          <p className="text-xs text-slate-400">Filter by estimated project value</p>
        </div>

        {/* Lead quality */}
        <div className="space-y-1 sm:col-span-2 lg:col-span-1">
          <p className="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-500">Lead quality</p>
          <ToggleSwitch
            checked={!!filters.highValueOnly}
            onChange={toggleHighValue}
            label="High value only"
            description="Only show applications flagged as high value"
          />
          <ToggleSwitch
            checked={!!filters.constructionOnly}
            onChange={toggleConstruction}
            label="Construction jobs only"
            description="Only show applications flagged as construction jobs"
          />
        </div>

        {/* Exclude already actioned */}
        <div className="space-y-1 sm:col-span-2 lg:col-span-2">
          <p className="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-500">
            Exclude already actioned
          </p>
          <div className="grid grid-cols-1 gap-x-6 sm:grid-cols-3">
            <ToggleSwitch
              checked={!!filters.hideSaved}
              onChange={toggleHideSaved}
              label="Hide saved"
            />
            <ToggleSwitch
              checked={!!filters.hideViewed}
              onChange={toggleHideViewed}
              label="Hide viewed"
            />
            <ToggleSwitch
              checked={!!filters.hideWorkspace}
              onChange={toggleHideWorkspace}
              label="Hide in workspace"
            />
          </div>
          <p className="mt-1 text-xs text-slate-400">
            {excludeAvailable
              ? 'Only show applications you haven\u2019t already saved, viewed, or added to your workspace.'
              : 'Sign in to track saved, viewed, and workspace applications.'}
          </p>
        </div>
      </div>

      <div className="mt-5 flex items-center justify-between border-t border-slate-100 pt-4">
        {config.isLoggedIn() && advancedFilterCount(filters) > 0 ? (
          <button
            type="button"
            onClick={openSaveSearchModal}
            className="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium text-brand-600 transition-colors hover:bg-brand-50"
          >
            <Bookmark className="h-3.5 w-3.5" />
            Save search
          </button>
        ) : (
          <span />
        )}
        <button
          type="button"
          onClick={handleClearAll}
          className="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-100 hover:text-slate-900"
        >
          <RotateCcw className="h-3.5 w-3.5" />
          Clear all
        </button>
      </div>
    </>
  )

  return (
    <>
      {/* Desktop: inline expanding panel */}
      <div
        id="advanced-filters-panel"
        className={`hidden overflow-hidden transition-all duration-300 ease-in-out sm:block ${
          open ? 'max-h-[40rem] opacity-100' : 'max-h-0 opacity-0'
        }`}
        aria-hidden={!open}
      >
        <div className="mt-3 rounded-2xl bg-white p-5 shadow-card ring-1 ring-slate-200/60">
          {filterContent}
        </div>
      </div>

      {/* Mobile: bottom sheet */}
      {open && (
        <div className="fixed inset-0 z-50 sm:hidden">
          {/* Backdrop */}
          <button
            type="button"
            aria-label="Close filters"
            onClick={handleDone}
            className="absolute inset-0 bg-slate-900/40 backdrop-blur-sm"
          />
          {/* Sheet */}
          <div
            className="absolute bottom-0 left-0 right-0 max-h-[85vh] overflow-y-auto rounded-t-2xl bg-white shadow-2xl"
            style={{ animation: 'piSheetUp var(--pi-transition-base) var(--pi-easing)' }}
          >
            {/* Drag handle */}
            <div className="flex justify-center pt-3 pb-1">
              <div className="h-1.5 w-12 rounded-full bg-slate-300" />
            </div>
            {/* Header */}
            <div className="flex items-center justify-between px-5 pb-3">
              <h2 className="text-base font-bold text-slate-900">Filters</h2>
              <button
                type="button"
                onClick={handleDone}
                aria-label="Close filters"
                className="flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-600"
              >
                <X className="h-5 w-5" />
              </button>
            </div>
            {/* Content */}
            <div className="px-5 pb-6">
              {filterContent}
              <button
                type="button"
                onClick={handleDone}
                className="btn-primary mt-5 w-full"
              >
                Show results
              </button>
            </div>
          </div>
          <style>{`@keyframes piSheetUp{from{transform:translateY(100%)}to{transform:translateY(0)}}`}</style>
        </div>
      )}
    </>
  )
}
