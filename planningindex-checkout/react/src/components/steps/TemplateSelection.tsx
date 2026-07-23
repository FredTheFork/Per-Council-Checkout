import { Check, ArrowRight, ArrowLeft, FileText } from 'lucide-react';
import { useCheckout } from '@/context/CheckoutContext';
import { PriceSummary } from '@/components/PriceSummary';

const accentClasses: Record<string, { bg: string; text: string; ring: string; badge: string }> = {
  brand: { bg: 'bg-brand-50', text: 'text-brand-600', ring: 'ring-brand-500', badge: 'bg-brand-100 text-brand-700' },
  success: { bg: 'bg-success-50', text: 'text-success-600', ring: 'ring-success-500', badge: 'bg-success-100 text-success-700' },
  accent: { bg: 'bg-accent-50', text: 'text-accent-600', ring: 'ring-accent-500', badge: 'bg-accent-100 text-accent-700' },
  warning: { bg: 'bg-warning-50', text: 'text-warning-600', ring: 'ring-warning-500', badge: 'bg-warning-100 text-warning-700' },
};

export function TemplateSelection() {
  const { selectedTemplateId, setSelectedTemplateId, setStep, canProceedFromStep, templates } = useCheckout();

  return (
    <div className="grid grid-cols-1 gap-8 lg:grid-cols-[1fr_340px]">
      <div>
        <div className="mb-6">
          <h1 className="font-display text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
            Choose Your PDF Template
          </h1>
          <p className="mt-2 text-sm text-slate-500 sm:text-base">
            Select a template for your planning application documents. All templates are included with
            your subscription at no extra cost.
          </p>
        </div>

        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          {templates.map((template) => {
            const isSelected = selectedTemplateId === template.id;
            const accent = accentClasses[template.accent] || accentClasses.brand;

            return (
              <button
                key={template.id}
                onClick={() => setSelectedTemplateId(template.id)}
                className={`group relative flex flex-col overflow-hidden rounded-2xl border-2 bg-white text-left transition-all duration-200 ${
                  isSelected
                    ? `border-transparent ring-2 ${accent.ring}`
                    : 'border-slate-200 hover:border-slate-300 hover:shadow-card'
                }`}
              >
                <div className={`relative flex h-40 items-center justify-center ${accent.bg}`}>
                  <div className="flex flex-col items-center gap-2">
                    <FileText className={`h-12 w-12 ${accent.text} opacity-80`} />
                    <span className={`text-xs font-semibold uppercase tracking-wide ${accent.text}`}>
                      {template.category}
                    </span>
                  </div>
                  {isSelected && (
                    <div className="absolute right-3 top-3 flex h-7 w-7 items-center justify-center rounded-full bg-brand-600 text-white shadow-sm">
                      <Check className="h-4 w-4" />
                    </div>
                  )}
                  <div className={`absolute left-3 top-3 badge ${accent.badge}`}>
                    {template.included ? 'Included' : `+£${template.price.toFixed(2)}`}
                  </div>
                </div>

                <div className="flex flex-1 flex-col p-4">
                  <h3 className="font-display text-base font-bold text-slate-900">
                    {template.name}
                  </h3>
                  <p className="mt-1.5 text-sm leading-relaxed text-slate-500">
                    {template.description}
                  </p>
                </div>
              </button>
            );
          })}
        </div>
      </div>

      <div>
        <PriceSummary />
      </div>

      <div className="flex items-center justify-between lg:col-span-2">
        <button onClick={() => setStep(1)} className="btn-ghost">
          <ArrowLeft className="h-4 w-4" />
          Back to Councils
        </button>
        <button
          onClick={() => setStep(userLoggedIn ? 4 : 3)}
          disabled={!canProceedFromStep(2)}
          className="btn-primary"
        >
          {userLoggedIn ? 'Continue to Confirmation' : 'Continue to Account'}
          <ArrowRight className="h-4 w-4" />
        </button>
      </div>
    </div>
  );
}
