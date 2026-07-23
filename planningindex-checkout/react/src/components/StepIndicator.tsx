import { Check } from 'lucide-react';
import type { CheckoutStep } from '@/types';
import { isLoggedIn as isUserLoggedIn } from '@/lib/api';

const steps: { id: CheckoutStep; label: string; description: string }[] = [
  { id: 1, label: 'Councils', description: 'Select councils' },
  { id: 2, label: 'Template', description: 'Choose a template' },
  { id: 3, label: 'Account', description: 'Create your account' },
  { id: 4, label: 'Confirm', description: 'Review & pay' },
];

interface StepIndicatorProps {
  currentStep: CheckoutStep;
  onStepClick?: (step: CheckoutStep) => void;
  maxReachedStep: CheckoutStep;
}

export function StepIndicator({ currentStep, onStepClick, maxReachedStep }: StepIndicatorProps) {
  const loggedIn = isUserLoggedIn();

  return (
    <nav aria-label="Checkout progress" className="w-full">
      <div className="flex items-center justify-between">
        {steps.map((s, idx) => {
          // When logged in, step 3 (Account) is auto-completed — show it
          // with a checkmark and make it clickable to revisit, but never
          // the "current" step.
          const isComplete = loggedIn && s.id === 3 ? true : s.id < currentStep;
          const isCurrent = (loggedIn && s.id === 3) ? false : s.id === currentStep;
          const isAccessible = s.id <= maxReachedStep;

          return (
            <div key={s.id} className="flex flex-1 items-center">
              <button
                type="button"
                disabled={!isAccessible}
                onClick={() => isAccessible && onStepClick?.(s.id)}
                className={`group flex flex-col items-center gap-1.5 ${isAccessible ? 'cursor-pointer' : 'cursor-not-allowed'}`}
              >
                <div
                  className={`flex h-10 w-10 items-center justify-center rounded-full border-2 text-sm font-semibold transition-colors duration-200 ${
                    isComplete
                      ? 'border-brand-600 bg-brand-600 text-white'
                      : isCurrent
                        ? 'border-brand-600 bg-white text-brand-600 ring-4 ring-brand-100'
                        : 'border-slate-300 bg-white text-slate-400'
                  }`}
                >
                  {isComplete ? <Check className="h-5 w-5" /> : s.id}
                </div>
                <div className="flex flex-col items-center">
                  <span
                    className={`text-xs font-semibold ${
                      isCurrent ? 'text-slate-900' : isComplete ? 'text-slate-600' : 'text-slate-400'
                    }`}
                  >
                    {s.label}
                  </span>
                  <span className="hidden text-[11px] text-slate-400 sm:block">
                    {s.description}
                  </span>
                </div>
              </button>
              {idx < steps.length - 1 && (
                <div className="mx-2 mb-6 h-0.5 flex-1 rounded-full sm:mx-4">
                  <div
                    className={`h-full rounded-full transition-colors duration-300 ${
                      s.id < currentStep ? 'bg-brand-600' : 'bg-slate-200'
                    }`}
                  />
                </div>
              )}
            </div>
          );
        })}
      </div>
    </nav>
  );
}
