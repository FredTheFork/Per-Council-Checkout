import { createContext, useContext, useState, useCallback, type ReactNode } from 'react';
import type { CheckoutStep, BusinessInfo, AccountInfo } from '@/types';
import { PRICE_PER_COUNCIL } from '@/data/councils';
import { getTemplateById } from '@/data/templates';

interface CheckoutContextValue {
  step: CheckoutStep;
  selectedCouncils: string[];
  selectedTemplateId: string | null;
  businessInfo: BusinessInfo;
  accountInfo: AccountInfo | null;
  monthlyCost: number;
  totalDueToday: number;
  setStep: (step: CheckoutStep) => void;
  toggleCouncil: (name: string) => void;
  clearCouncils: () => void;
  setSelectedTemplateId: (id: string | null) => void;
  setBusinessInfo: (info: BusinessInfo) => void;
  setAccountInfo: (info: AccountInfo | null) => void;
  canProceedFromStep: (step: CheckoutStep) => boolean;
}

const defaultBusinessInfo: BusinessInfo = {
  companyName: '',
  businessEmail: '',
  businessPhone: '',
  businessAddress: '',
};

const CheckoutContext = createContext<CheckoutContextValue | null>(null);

export function CheckoutProvider({ children }: { children: ReactNode }) {
  const [step, setStep] = useState<CheckoutStep>(1);
  const [selectedCouncils, setSelectedCouncils] = useState<string[]>([]);
  const [selectedTemplateId, setSelectedTemplateId] = useState<string | null>(null);
  const [businessInfo, setBusinessInfo] = useState<BusinessInfo>(defaultBusinessInfo);
  const [accountInfo, setAccountInfo] = useState<AccountInfo | null>(null);

  const toggleCouncil = useCallback((name: string) => {
    setSelectedCouncils((prev) =>
      prev.includes(name) ? prev.filter((c) => c !== name) : [...prev, name]
    );
  }, []);

  const clearCouncils = useCallback(() => setSelectedCouncils([]), []);

  const monthlyCost = selectedCouncils.length * PRICE_PER_COUNCIL;
  const totalDueToday = monthlyCost;

  const canProceedFromStep = useCallback(
    (s: CheckoutStep): boolean => {
      if (s === 1) return selectedCouncils.length >= 3;
      if (s === 2) return selectedTemplateId !== null;
      if (s === 3) return accountInfo !== null;
      if (s === 4) return true;
      return false;
    },
    [selectedCouncils, selectedTemplateId, accountInfo]
  );

  const value: CheckoutContextValue = {
    step,
    selectedCouncils,
    selectedTemplateId,
    businessInfo,
    accountInfo,
    monthlyCost,
    totalDueToday,
    setStep,
    toggleCouncil,
    clearCouncils,
    setSelectedTemplateId,
    setBusinessInfo,
    setAccountInfo,
    canProceedFromStep,
  };

  return <CheckoutContext.Provider value={value}>{children}</CheckoutContext.Provider>;
}

export function useCheckout() {
  const ctx = useContext(CheckoutContext);
  if (!ctx) throw new Error('useCheckout must be used within CheckoutProvider');
  return ctx;
}

export { getTemplateById };
