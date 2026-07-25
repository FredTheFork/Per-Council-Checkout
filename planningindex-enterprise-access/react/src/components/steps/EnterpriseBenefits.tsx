import { ArrowRight, Globe, Users, ChartBar as BarChart3, Shield, Zap, Check } from 'lucide-react';
import { useCheckout } from '@/context/CheckoutContext';
import { getEnterprisePrice } from '@/lib/api';
import { PriceSummary } from '@/components/PriceSummary';

export function EnterpriseBenefits() {
  const { setStep, canProceedFromStep } = useCheckout();
  const price = getEnterprisePrice();

  return (
    <div className="grid grid-cols-1 gap-8 lg:grid-cols-[1fr_340px]">
      <div>
        <div className="mb-6">
          <p className="mb-1 text-sm font-semibold uppercase tracking-wide text-brand-600">
            Step 1 of 4
          </p>
          <h1 className="font-display text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
            Enterprise Access
          </h1>
          <p className="mt-2 text-sm text-slate-500 sm:text-base">
            Unlimited access to all UK councils with team collaboration and enterprise-grade reporting.
          </p>
        </div>

        <div className="card mb-6 overflow-hidden">
          <div className="bg-gradient-to-br from-brand-600 to-brand-800 px-6 py-8 text-white">
            <div className="flex items-center gap-3">
              <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-white/15">
                <Globe className="h-6 w-6" />
              </div>
              <div>
                <h2 className="font-display text-xl font-bold">All UK Councils</h2>
                <p className="text-sm text-brand-100">Unlimited nationwide coverage</p>
              </div>
            </div>
            <div className="mt-6 flex items-baseline gap-2">
              <span className="font-display text-4xl font-bold">£{price.toFixed(2)}</span>
              <span className="text-sm text-brand-100">/month</span>
            </div>
            <p className="mt-1 text-sm text-brand-100">Flat rate — no per-council fees</p>
          </div>
        </div>

        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
          <BenefitCard
            icon={<Globe className="h-5 w-5" />}
            title="All UK Councils"
            description="Access every UK planning authority — no regional limits or restrictions."
            accent="brand"
          />
          <BenefitCard
            icon={<Users className="h-5 w-5" />}
            title="Team Collaboration"
            description="Share access with your team. Manage seats and invite members from your dashboard."
            accent="success"
          />
          <BenefitCard
            icon={<BarChart3 className="h-5 w-5" />}
            title="Enterprise Reporting"
            description="Advanced analytics and reporting tools for tracking applications across all regions."
            accent="accent"
          />
        </div>

        <div className="card mt-6 p-5">
          <h3 className="mb-3 font-display text-sm font-bold uppercase tracking-wide text-slate-500">
            What's Included
          </h3>
          <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
            {[
              'Unlimited API access to all UK councils',
              'Nationwide planning application data',
              'Team management with configurable seats',
              'Priority support with dedicated account manager',
              'Custom PDF templates for all document types',
              'Advanced search and filtering across regions',
              'Bulk export and reporting capabilities',
              'Webhook integrations for automated workflows',
            ].map((feature) => (
              <div key={feature} className="flex items-center gap-2 text-sm">
                <Check className="h-4 w-4 shrink-0 text-success-500" />
                <span className="text-slate-600">{feature}</span>
              </div>
            ))}
          </div>
        </div>

        <div className="mt-6 flex items-center justify-between">
          <div className="flex items-center gap-2 text-sm text-slate-400">
            <Shield className="h-4 w-4" />
            <span>Enterprise-grade security &amp; SLA</span>
          </div>
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

      <div>
        <PriceSummary />
      </div>
    </div>
  );
}

function BenefitCard({
  icon,
  title,
  description,
  accent,
}: {
  icon: React.ReactNode;
  title: string;
  description: string;
  accent: string;
}) {
  const accentMap: Record<string, { bg: string; text: string }> = {
    brand: { bg: 'bg-brand-50', text: 'text-brand-600' },
    success: { bg: 'bg-success-50', text: 'text-success-600' },
    accent: { bg: 'bg-accent-50', text: 'text-accent-600' },
  };
  const a = accentMap[accent] || accentMap.brand;

  return (
    <div className="card p-5">
      <div className={`mb-3 flex h-10 w-10 items-center justify-center rounded-xl ${a.bg} ${a.text}`}>
        {icon}
      </div>
      <h3 className="font-display text-sm font-bold text-slate-900">{title}</h3>
      <p className="mt-1 text-xs leading-relaxed text-slate-500">{description}</p>
    </div>
  );
}
