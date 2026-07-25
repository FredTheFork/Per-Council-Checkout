import { Check, ArrowRight, ArrowLeft } from 'lucide-react';
import { useCheckout } from '@/context/CheckoutContext';
import { isLoggedIn as isUserLoggedIn } from '@/lib/api';
import { PriceSummary } from '@/components/PriceSummary';
import { PdfPreview, TemplateThumbnails } from '@/components/PdfPreview';
import { getTemplateById } from '@/data/templates';

export function TemplateSelection() {
  const { selectedTemplateId, setSelectedTemplateId, setStep, canProceedFromStep, templates } = useCheckout();
  const selectedTemplate = getTemplateById(selectedTemplateId);

  return (
    <div className="grid grid-cols-1 gap-8 lg:grid-cols-[1fr_340px]">
      <div>
        <div className="mb-6">
          <h1 className="font-display text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
            Choose Your PDF Template
          </h1>
          <p className="mt-2 text-sm text-slate-500 sm:text-base">
            Select a template for your planning application documents. All templates are included with
            your subscription at no extra cost. Click a template card below to preview it.
          </p>
        </div>

        <div className="mb-6">
          <PdfPreview template={selectedTemplate} />
        </div>

        <div>
          <h2 className="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">
            All Templates ({templates.length})
          </h2>
          <TemplateThumbnails
            templates={templates}
            selectedId={selectedTemplateId}
            onSelect={setSelectedTemplateId}
          />
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
        <div className="flex items-center gap-4">
          {!selectedTemplateId && (
            <p className="hidden text-sm text-slate-400 sm:block">
              Select a template to continue
            </p>
          )}
          <button
            onClick={() => setStep(isUserLoggedIn() ? 4 : 3)}
            disabled={!canProceedFromStep(2)}
            className="btn-primary"
          >
            {isUserLoggedIn() ? 'Continue to Confirm' : 'Continue to Account'}
            <ArrowRight className="h-4 w-4" />
          </button>
        </div>
      </div>
    </div>
  );
}
