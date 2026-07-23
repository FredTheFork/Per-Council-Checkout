import { useState, useCallback, useEffect } from 'react';
import { CheckoutProvider, useCheckout } from '@/context/CheckoutContext';
import { Header } from '@/components/Header';
import { StepIndicator } from '@/components/StepIndicator';
import { RegionSelection } from '@/components/steps/RegionSelection';
import { TemplateSelection } from '@/components/steps/TemplateSelection';
import { AccountCreation } from '@/components/steps/AccountCreation';
import { Confirmation } from '@/components/steps/Confirmation';
import { isLoggedIn as isUserLoggedIn } from '@/lib/api';
import type { CheckoutStep } from '@/types';

function CheckoutFlow() {
  const { step, setStep, canProceedFromStep } = useCheckout();
  const [maxReachedStep, setMaxReachedStep] = useState<CheckoutStep>(1);
  const loggedIn = isUserLoggedIn();

  useEffect(() => {
    if (loggedIn && step === 3) {
      setStep(4);
    }
  }, [loggedIn, step, setStep]);

  const handleStepChange = useCallback(
    (newStep: CheckoutStep) => {
      setStep(newStep);
      setMaxReachedStep((prev) => Math.max(prev, newStep) as CheckoutStep);
    },
    [setStep]
  );

  const handleStepClick = useCallback(
    (target: CheckoutStep) => {
      if (target <= maxReachedStep) {
        setStep(target);
      }
    },
    [maxReachedStep, setStep]
  );

  return (
    <div className="min-h-screen bg-slate-50">
      <Header />

      <main className="mx-auto max-w-6xl px-4 py-8 sm:px-6 sm:py-12">
        <div className="mb-10">
          <StepIndicator
            currentStep={step}
            onStepClick={handleStepClick}
            maxReachedStep={maxReachedStep}
          />
        </div>

        <div className="transition-opacity duration-200">
          {step === 1 && <RegionSelection />}
          {step === 2 && <TemplateSelection />}
          {step === 3 && !loggedIn && <AccountCreation />}
          {step === 4 && <Confirmation />}
        </div>
      </main>

      <footer className="border-t border-slate-200/60 bg-white">
        <div className="mx-auto max-w-6xl px-6 py-6">
          <div className="flex flex-col items-center justify-between gap-2 sm:flex-row">
            <p className="text-xs text-slate-400">
              © {new Date().getFullYear()} Planning Index. All rights reserved.
            </p>
            <div className="flex items-center gap-4 text-xs text-slate-400">
              <a href="#" className="hover:text-slate-600">Terms</a>
              <a href="#" className="hover:text-slate-600">Privacy</a>
              <a href="#" className="hover:text-slate-600">Cookies</a>
            </div>
          </div>
        </div>
      </footer>
    </div>
  );
}

function App() {
  return (
    <CheckoutProvider>
      <CheckoutFlow />
    </CheckoutProvider>
  );
}

export default App;
