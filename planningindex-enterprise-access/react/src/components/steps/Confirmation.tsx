import { useState, useEffect } from 'react';
import { ArrowLeft, ArrowRight, Building, Mail, Phone, MapPin, Loader as Loader2, Lock, User, FileText, Globe, Users, Check, Shield, ShieldCheck } from 'lucide-react';
import { useCheckout } from '@/context/CheckoutContext';
import { getTemplateById } from '@/data/templates';
import { api, isLoggedIn as isUserLoggedIn, getEnterprisePrice } from '@/lib/api';
import { PriceSummary } from '@/components/PriceSummary';
import type { TeamSeats } from '@/types';

const seatOptions: { value: TeamSeats; label: string; description: string }[] = [
  { value: 1, label: '1 Seat', description: 'Solo access' },
  { value: 3, label: '3 Seats', description: 'Small team' },
  { value: 5, label: '5 Seats', description: 'Growing team' },
];

export function Confirmation() {
  const {
    selectedTemplateId,
    teamSeats,
    setTeamSeats,
    businessInfo,
    setBusinessInfo,
    accountInfo,
    monthlyCost,
    totalDueToday,
    setStep,
  } = useCheckout();

  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const template = getTemplateById(selectedTemplateId);
  const enterprisePrice = getEnterprisePrice();

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

  const handleComplete = async () => {
    setError(null);
    setLoading(true);

    if (!isUserLoggedIn() && !accountInfo?.email) {
      setError('Please go back and complete your account information before subscribing.');
      setLoading(false);
      return;
    }

    try {
      if (isUserLoggedIn()) {
        await api.updateProfile(businessInfo).catch(() => {});
      }

      await api.saveSession(2, {
        template: selectedTemplateId || 'standard-planning',
        business: {
          pmpe_company_name: businessInfo.companyName,
          pmpe_business_email: businessInfo.businessEmail,
          pmpe_business_phone: businessInfo.businessPhone,
          pmpe_company_address: businessInfo.businessAddress,
        },
        team_seats: teamSeats,
      }).catch(() => {});

      const result = await api.createStripeSession({
        templateId: selectedTemplateId || 'standard-planning',
        teamSeats,
        businessInfo,
        accountInfo,
      });

      if (!result.success || !result.stripeUrl) {
        throw new Error(result.message || 'Unable to start Stripe checkout.');
      }

      window.location.href = result.stripeUrl;
    } catch (err) {
      const message = err instanceof Error ? err.message : 'An error occurred during checkout.';
      setError(message);
      setLoading(false);
    }
  };

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
            Review your Enterprise subscription details and add optional business information before
            completing your purchase.
          </p>
        </div>

        <div className="card mb-6 p-5">
          <div className="mb-4 flex items-center justify-between border-b border-slate-100 pb-4">
            <div>
              <p className="text-sm text-slate-500">Monthly Cost</p>
              <p className="font-display text-2xl font-bold text-slate-900">
                £{(monthlyCost || enterprisePrice).toFixed(2)}
                <span className="text-base font-medium text-slate-400">/month</span>
              </p>
            </div>
            <div className="text-right">
              <p className="text-sm text-slate-500">Total Due Today</p>
              <p className="font-display text-2xl font-bold text-brand-600">
                £{(totalDueToday || enterprisePrice).toFixed(2)}
              </p>
            </div>
          </div>

          <div className="space-y-3">
            <ReviewItem icon={<Globe className="h-4 w-4" />} label="Coverage">
              <p className="text-sm font-medium text-slate-700">Enterprise Access</p>
              <p className="text-xs text-slate-400">All UK Councils included</p>
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
                {isUserLoggedIn()
                  ? (accountInfo?.fullName || accountInfo?.email || 'Logged in')
                  : `${accountInfo?.fullName || 'Not set'} · ${accountInfo?.email || ''}`}
              </p>
              {!isUserLoggedIn() && (
                <button
                  onClick={() => setStep(3)}
                  className="text-xs font-semibold text-brand-600 hover:text-brand-700"
                >
                  Edit account
                </button>
              )}
            </ReviewItem>
          </div>
        </div>

        <div className="card mb-6 p-5">
          <div className="mb-4">
            <div className="flex items-center gap-2">
              <Users className="h-4 w-4 text-slate-400" />
              <h2 className="font-display text-base font-bold text-slate-900">
                Team Seats
              </h2>
            </div>
            <p className="mt-1.5 text-sm text-slate-500">
              Choose how many team members can access your Enterprise subscription.
            </p>
          </div>

          <div className="grid grid-cols-3 gap-3">
            {seatOptions.map((option) => {
              const isSelected = teamSeats === option.value;
              return (
                <button
                  key={option.value}
                  type="button"
                  onClick={() => setTeamSeats(option.value)}
                  className={`flex flex-col items-center gap-1 rounded-xl border-2 px-3 py-4 transition-all duration-200 ${
                    isSelected
                      ? 'border-brand-600 bg-brand-50 ring-2 ring-brand-100'
                      : 'border-slate-200 hover:border-slate-300 hover:bg-slate-50'
                  }`}
                >
                  <div className={`flex h-8 w-8 items-center justify-center rounded-full ${
                    isSelected ? 'bg-brand-600 text-white' : 'bg-slate-100 text-slate-400'
                  }`}>
                    {isSelected ? <Check className="h-4 w-4" /> : <Users className="h-4 w-4" />}
                  </div>
                  <span className={`text-sm font-bold ${isSelected ? 'text-brand-700' : 'text-slate-700'}`}>
                    {option.label}
                  </span>
                  <span className="text-xs text-slate-400">{option.description}</span>
                </button>
              );
            })}
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

        <div className="mt-4 flex items-center gap-4 text-xs text-slate-400">
          <div className="flex items-center gap-1.5">
            <ShieldCheck className="h-4 w-4 text-success-500" />
            <span>30-day money-back guarantee</span>
          </div>
          <div className="flex items-center gap-1.5">
            <Lock className="h-3.5 w-3.5 text-slate-400" />
            <span>SSL encrypted checkout</span>
          </div>
          <div className="flex items-center gap-1.5">
            <Shield className="h-3.5 w-3.5 text-slate-400" />
            <span>Cancel anytime</span>
          </div>
        </div>

        {error && (
          <div className="mt-4 rounded-lg bg-error-50 px-4 py-3 text-sm text-error-700 ring-1 ring-inset ring-error-200">
            {error}
          </div>
        )}

        <div className="mt-6 flex items-center justify-between">
          <button onClick={() => setStep(isUserLoggedIn() ? 2 : 3)} className="btn-ghost">
            <ArrowLeft className="h-4 w-4" />
            Back to {isUserLoggedIn() ? 'Templates' : 'Account'}
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
                Complete Enterprise Subscription
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
