import { useState, useCallback, useEffect } from 'react';
import { CheckoutProvider, useCheckout } from '@/context/CheckoutContext';
import { Header } from '@/components/Header';
import { Footer } from '@/components/Footer';
import { StepIndicator } from '@/components/StepIndicator';
import { EnterpriseBenefits } from '@/components/steps/EnterpriseBenefits';
import { TemplateSelection } from '@/components/steps/TemplateSelection';
import { AccountCreation } from '@/components/steps/AccountCreation';
import { Confirmation } from '@/components/steps/Confirmation';
import { isLoggedIn as isUserLoggedIn } from '@/lib/api';
import type { CheckoutStep } from '@/types';

function CheckoutFlow() {
  const { step, setStep } = useCheckout();
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
          {step === 1 && <EnterpriseBenefits />}
          {step === 2 && <TemplateSelection />}
          {step === 3 && !loggedIn && <AccountCreation />}
          {step === 4 && <Confirmation />}
        </div>
      </main>

      <Footer />
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
