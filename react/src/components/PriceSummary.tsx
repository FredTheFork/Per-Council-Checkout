import { useCheckout } from '@/context/CheckoutContext';
import { getTemplateById } from '@/data/templates';
import { PRICE_PER_COUNCIL } from '@/data/councils';
import { MapPin, FileText, Check } from 'lucide-react';

export function PriceSummary() {
  const { selectedCouncils, selectedTemplateId, monthlyCost, totalDueToday } = useCheckout();
  const template = getTemplateById(selectedTemplateId);

  return (
    <div className="card sticky top-6 overflow-hidden">
      <div className="border-b border-slate-200/60 bg-slate-50/50 px-5 py-4">
        <h3 className="font-display text-sm font-bold uppercase tracking-wide text-slate-500">
          Order Summary
        </h3>
      </div>

      <div className="px-5 py-4">
        <div className="mb-4">
          <div className="mb-2 flex items-center gap-2 text-sm font-semibold text-slate-700">
            <MapPin className="h-4 w-4 text-brand-600" />
            <span>
              Councils <span className="text-slate-400">({selectedCouncils.length})</span>
            </span>
          </div>
          {selectedCouncils.length === 0 ? (
            <p className="text-sm text-slate-400">No councils selected yet</p>
          ) : (
            <div className="scrollbar-thin max-h-32 space-y-1 overflow-y-auto pr-1">
              {selectedCouncils.map((c) => (
                <div key={c} className="flex items-center justify-between text-sm">
                  <span className="truncate text-slate-600">{c}</span>
                  <span className="ml-2 shrink-0 font-medium text-slate-500">£{PRICE_PER_COUNCIL}</span>
                </div>
              ))}
            </div>
          )}
          {selectedCouncils.length > 0 && (
            <div className="mt-2 border-t border-slate-100 pt-2 text-sm">
              <div className="flex justify-between">
                <span className="text-slate-500">Council subtotal</span>
                <span className="font-medium text-slate-700">
                  £{(selectedCouncils.length * PRICE_PER_COUNCIL).toFixed(2)}
                </span>
              </div>
            </div>
          )}
        </div>

        <div className="mb-4 border-t border-slate-100 pt-4">
          <div className="mb-2 flex items-center gap-2 text-sm font-semibold text-slate-700">
            <FileText className="h-4 w-4 text-brand-600" />
            <span>Template</span>
          </div>
          {template ? (
            <div className="flex items-start gap-2 text-sm">
              <Check className="mt-0.5 h-4 w-4 shrink-0 text-success-500" />
              <div>
                <p className="font-medium text-slate-700">{template.name}</p>
                <p className="text-xs text-slate-400">
                  {template.included ? 'Included' : `+£${template.price.toFixed(2)}`}
                </p>
              </div>
            </div>
          ) : (
            <p className="text-sm text-slate-400">No template selected yet</p>
          )}
        </div>
      </div>

      <div className="border-t border-slate-200/60 bg-slate-50/50 px-5 py-4">
        <div className="mb-1 flex items-center justify-between">
          <span className="text-sm font-medium text-slate-600">Monthly Cost</span>
          <span className="text-lg font-bold text-slate-900">£{monthlyCost.toFixed(2)}</span>
        </div>
        <div className="flex items-center justify-between">
          <span className="text-sm font-medium text-slate-600">Total Due Today</span>
          <span className="text-lg font-bold text-brand-600">£{totalDueToday.toFixed(2)}</span>
        </div>
        <p className="mt-3 text-xs text-slate-400">
          Billed monthly. Cancel anytime from your account settings.
        </p>
      </div>
    </div>
  );
}
