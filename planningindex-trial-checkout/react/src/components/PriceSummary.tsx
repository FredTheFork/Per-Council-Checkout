import { useCheckout } from '@/context/CheckoutContext';
import { getTemplateById } from '@/data/templates';
import { PRICE_PER_COUNCIL } from '@/data/councils';
import { MapPin, FileText, Check, Clock, Gift } from 'lucide-react';

export function PriceSummary() {
  const { selectedCouncils, selectedTemplateId, trialDays } = useCheckout();
  const template = getTemplateById(selectedTemplateId);

  return (
    <div className="card sticky top-6 overflow-hidden">
      <div className="border-b border-slate-200/60 bg-success-50/50 px-5 py-4">
        <div className="flex items-center gap-2">
          <Gift className="h-4 w-4 text-success-600" />
          <h3 className="font-display text-sm font-bold uppercase tracking-wide text-success-700">
            Free Trial Summary
          </h3>
        </div>
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
                  <span className="ml-2 shrink-0 font-medium text-success-600">Free</span>
                </div>
              ))}
            </div>
          )}
          {selectedCouncils.length > 0 && (
            <div className="mt-2 border-t border-slate-100 pt-2 text-sm">
              <div className="flex justify-between">
                <span className="text-slate-500">After trial ends</span>
                <span className="font-medium text-slate-700">
                  £{(selectedCouncils.length * PRICE_PER_COUNCIL).toFixed(2)}/month
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
                <p className="text-xs text-slate-400">Included free with trial</p>
              </div>
            </div>
          ) : (
            <p className="text-sm text-slate-400">No template selected yet</p>
          )}
        </div>
      </div>

      <div className="border-t border-slate-200/60 bg-success-50/50 px-5 py-4">
        <div className="mb-1 flex items-center justify-between">
          <span className="text-sm font-medium text-slate-600">Trial Duration</span>
          <span className="flex items-center gap-1.5 text-sm font-bold text-success-700">
            <Clock className="h-4 w-4" />
            {trialDays} days
          </span>
        </div>
        <div className="mb-1 flex items-center justify-between">
          <span className="text-sm font-medium text-slate-600">Total Due Today</span>
          <span className="text-lg font-bold text-success-600">£0.00</span>
        </div>
        <div className="flex items-center justify-between">
          <span className="text-sm font-medium text-slate-600">After Trial</span>
          <span className="text-sm font-semibold text-slate-700">
            £{(selectedCouncils.length * PRICE_PER_COUNCIL).toFixed(2)}/month
          </span>
        </div>
        <p className="mt-3 text-xs text-slate-400">
          No payment required now. You'll only be charged if you decide to subscribe after your {trialDays}-day trial ends.
        </p>
      </div>
    </div>
  );
}
