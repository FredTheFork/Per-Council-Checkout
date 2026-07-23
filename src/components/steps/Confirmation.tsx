import { useState, useEffect } from 'react';
import {
  ArrowLeft,
  ArrowRight,
  Building,
  Mail,
  Phone,
  MapPin,
  Check,
  Loader2,
  Lock,
  User,
  FileText,
  MapPinned,
} from 'lucide-react';
import { useCheckout } from '@/context/CheckoutContext';
import { getTemplateById } from '@/data/templates';
import { api, isLoggedIn as isUserLoggedIn, getLoggedInUserEmail } from '@/lib/api';
import { PriceSummary } from '@/components/PriceSummary';

export function Confirmation() {
  const {
    selectedCouncils,
    selectedTemplateId,
    businessInfo,
    setBusinessInfo,
    accountInfo,
    monthlyCost,
    totalDueToday,
    setStep,
  } = useCheckout();

  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState(false);

  useEffect(() => {
    if (accountInfo) {
      if (isUserLoggedIn()) {
        api.getProfile().then((profile) => {
          if (profile) {
            setBusinessInfo({
              companyName: profile.companyName || '',
              businessEmail: profile.businessEmail || '',
              businessPhone: profile.businessPhone || '',
              businessAddress: profile.businessAddress || '',
            });
          }
        }).catch(() => {
          // Profile fetch failed — leave defaults
        });
      } else if (accountInfo.email) {
        setBusinessInfo({
          ...businessInfo,
          businessEmail: accountInfo.email || '',
        });
      }
    }
  }, [accountInfo, setBusinessInfo]);

  const template = getTemplateById(selectedTemplateId);

  const handleComplete = async () => {
    setError(null);
    setLoading(true);

    try {
      if (isUserLoggedIn()) {
        await api.updateProfile(businessInfo);
      }

      await api.saveSession(1, { councils: selectedCouncils });
      await api.saveSession(2, {
        template: selectedTemplateId || 'standard-planning',
        business: {
          pmpc_company_name: businessInfo.companyName,
          pmpc_business_email: businessInfo.businessEmail,
          pmpc_business_phone: businessInfo.businessPhone,
          pmpc_company_address: businessInfo.businessAddress,
        },
      });

      const result = await api.submitCheckout();

      if (result.redirectUrl) {
        window.location.href = result.redirectUrl;
        return;
      }

      setSuccess(true);
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Failed to complete subscription';
      setError(message);
    } finally {
      setLoading(false);
    }
  };

  if (success) {
    return (
      <div className="mx-auto max-w-lg text-center">
        <div className="mb-6 flex h-20 w-20 items-center justify-center rounded-full bg-success-100 mx-auto">
          <Check className="h-10 w-10 text-success-600" />
        </div>
        <h1 className="font-display text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
          Subscription Prepared
        </h1>
        <p className="mt-3 text-sm text-slate-500 sm:text-base">
          Your subscription has been set up and is ready for payment. You'll be redirected to our
          secure payment provider to complete your purchase.
        </p>
        <div className="card mt-6 p-5">
          <div className="flex items-center justify-between text-sm">
            <span className="text-slate-500">Monthly Cost</span>
            <span className="font-bold text-slate-900">£{monthlyCost.toFixed(2)}/month</span>
          </div>
          <div className="mt-2 flex items-center justify-between text-sm">
            <span className="text-slate-500">Total Due Today</span>
            <span className="font-bold text-brand-600">£{totalDueToday.toFixed(2)}</span>
          </div>
        </div>
        <p className="mt-4 text-xs text-slate-400">
          Stripe payment integration will be connected in the next phase.
        </p>
      </div>
    );
  }

  return (
    <div className="grid grid-cols-1 gap-8 lg:grid-cols-[1fr_340px]">
      <div>
        <div className="mb-6">
          <p className="mb-1 text-sm font-semibold uppercase tracking-wide text-brand-600">
            Step 4 of 4
          </p>
          <h1 className="font-display text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
            Review & Confirm
          </h1>
          <p className="mt-2 text-sm text-slate-500 sm:text-base">
            Review your selections and add optional business information before completing your
            subscription.
          </p>
        </div>

        <div className="card mb-6 p-5">
          <div className="mb-4 flex items-center justify-between border-b border-slate-100 pb-4">
            <div>
              <p className="text-sm text-slate-500">Monthly Cost</p>
              <p className="font-display text-2xl font-bold text-slate-900">
                £{monthlyCost.toFixed(2)}
                <span className="text-base font-medium text-slate-400">/month</span>
              </p>
            </div>
            <div className="text-right">
              <p className="text-sm text-slate-500">Total Due Today</p>
              <p className="font-display text-2xl font-bold text-brand-600">
                £{totalDueToday.toFixed(2)}
              </p>
            </div>
          </div>

          <div className="space-y-3">
            <ReviewItem icon={<MapPinned className="h-4 w-4" />} label="Councils">
              <p className="text-sm font-medium text-slate-700">
                {selectedCouncils.length} council{selectedCouncils.length !== 1 ? 's' : ''} selected
              </p>
              <button
                onClick={() => setStep(1)}
                className="text-xs font-semibold text-brand-600 hover:text-brand-700"
              >
                Edit selection
              </button>
            </ReviewItem>

            <ReviewItem icon={<FileText className="h-4 w-4" />} label="Template">
              <p className="text-sm font-medium text-slate-700">
                {template ? template.name : 'No template selected'}
              </p>
              <button
                onClick={() => setStep(2)}
                className="text-xs font-semibold text-brand-600 hover:text-brand-700"
              >
                Change template
              </button>
            </ReviewItem>

            <ReviewItem icon={<User className="h-4 w-4" />} label="Account">
              <p className="text-sm font-medium text-slate-700">
                {accountInfo?.fullName || 'Not set'} · {accountInfo?.email || ''}
              </p>
              <button
                onClick={() => setStep(3)}
                className="text-xs font-semibold text-brand-600 hover:text-brand-700"
              >
                Edit account
              </button>
            </ReviewItem>
          </div>
        </div>

        <div className="card p-5">
          <div className="mb-4">
            <div className="flex items-center gap-2">
              <Building className="h-4 w-4 text-slate-400" />
              <h2 className="font-display text-base font-bold text-slate-900">
                Business Information
              </h2>
              <span className="badge bg-slate-100 text-slate-500">Optional</span>
            </div>
            <p className="mt-1.5 text-sm text-slate-500">
              This information will appear on your proposal letters. You can update it anytime from
              your account settings.
            </p>
          </div>

          <div className="space-y-4">
            <div>
              <label className="mb-1.5 block text-sm font-semibold text-slate-700">
                Company Name
              </label>
              <div className="relative">
                <Building className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input
                  type="text"
                  value={businessInfo.companyName}
                  onChange={(e) =>
                    setBusinessInfo({ ...businessInfo, companyName: e.target.value })
                  }
                  className="input-field pl-10"
                  placeholder="Your company name"
                />
              </div>
            </div>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <div>
                <label className="mb-1.5 block text-sm font-semibold text-slate-700">
                  Business Email
                </label>
                <div className="relative">
                  <Mail className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                  <input
                    type="email"
                    value={businessInfo.businessEmail}
                    onChange={(e) =>
                      setBusinessInfo({ ...businessInfo, businessEmail: e.target.value })
                    }
                    className="input-field pl-10"
                    placeholder="business@example.com"
                  />
                </div>
              </div>

              <div>
                <label className="mb-1.5 block text-sm font-semibold text-slate-700">
                  Business Phone
                </label>
                <div className="relative">
                  <Phone className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                  <input
                    type="tel"
                    value={businessInfo.businessPhone}
                    onChange={(e) =>
                      setBusinessInfo({ ...businessInfo, businessPhone: e.target.value })
                    }
                    className="input-field pl-10"
                    placeholder="020 1234 5678"
                  />
                </div>
              </div>
            </div>

            <div>
              <label className="mb-1.5 block text-sm font-semibold text-slate-700">
                Business Address
              </label>
              <div className="relative">
                <MapPin className="pointer-events-none absolute left-3.5 top-4 h-4 w-4 text-slate-400" />
                <textarea
                  value={businessInfo.businessAddress}
                  onChange={(e) =>
                    setBusinessInfo({ ...businessInfo, businessAddress: e.target.value })
                  }
                  className="input-field min-h-[80px] resize-y pl-10 pt-3"
                  placeholder="Street address, city, postcode"
                  rows={3}
                />
              </div>
            </div>
          </div>
        </div>

        {error && (
          <div className="mt-4 rounded-lg bg-error-50 px-4 py-3 text-sm text-error-700 ring-1 ring-inset ring-error-200">
            {error}
          </div>
        )}

        <div className="mt-6 flex items-center justify-between">
          <button onClick={() => setStep(3)} className="btn-ghost">
            <ArrowLeft className="h-4 w-4" />
            Back to Account
          </button>
          <button onClick={handleComplete} disabled={loading} className="btn-primary">
            {loading ? (
              <>
                <Loader2 className="h-4 w-4 animate-spin" />
                Processing...
              </>
            ) : (
              <>
                <Lock className="h-4 w-4" />
                Complete Subscription
                <ArrowRight className="h-4 w-4" />
              </>
            )}
          </button>
        </div>
      </div>

      <div>
        <PriceSummary />
      </div>
    </div>
  );
}

function ReviewItem({
  icon,
  label,
  children,
}: {
  icon: React.ReactNode;
  label: string;
  children: React.ReactNode;
}) {
  return (
    <div className="flex items-start justify-between gap-3 rounded-xl bg-slate-50/50 px-4 py-3">
      <div className="flex items-start gap-3">
        <div className="mt-0.5 flex h-8 w-8 items-center justify-center rounded-lg bg-white text-slate-500 ring-1 ring-slate-200">
          {icon}
        </div>
        <div>
          <p className="text-xs font-semibold uppercase tracking-wide text-slate-400">{label}</p>
          {children}
        </div>
      </div>
    </div>
  );
}
