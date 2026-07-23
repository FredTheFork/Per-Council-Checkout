import {
  createContext,
  useContext,
  useState,
  useCallback,
  useEffect,
  type ReactNode,
} from 'react';
import type {
  CheckoutStep,
  BusinessInfo,
  AccountInfo,
  Council,
  PdfTemplate,
} from '@/types';
import { PRICE_PER_COUNCIL } from '@/data/councils';
import { getTemplateById as getTemplateByIdStatic, templates as fallbackTemplates } from '@/data/templates';
import { api, isLoggedIn as isUserLoggedIn } from '@/lib/api';

interface CheckoutContextValue {
  step: CheckoutStep;
  selectedCouncils: string[];
  selectedTemplateId: string | null;
  businessInfo: BusinessInfo;
  accountInfo: AccountInfo | null;
  monthlyCost: number;
  totalDueToday: number;
  // Async-loaded data
  councils: Council[];
  nations: readonly string[];
  templates: PdfTemplate[];
  loading: boolean;
  // Actions
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

const defaultNations = ['England', 'Scotland', 'Wales', 'Northern Ireland'] as const;

const CheckoutContext = createContext<CheckoutContextValue | null>(null);

export function CheckoutProvider({ children }: { children: ReactNode }) {
  const [step, setStep] = useState<CheckoutStep>(1);
  const [selectedCouncils, setSelectedCouncils] = useState<string[]>([]);
  const [selectedTemplateId, setSelectedTemplateId] = useState<string | null>(null);
  const [businessInfo, setBusinessInfo] = useState<BusinessInfo>(defaultBusinessInfo);
  const [accountInfo, setAccountInfo] = useState<AccountInfo | null>(null);

  const [councils, setCouncils] = useState<Council[]>([]);
  const [nations, setNations] = useState<readonly string[]>(defaultNations);
  const [templates, setTemplates] = useState<PdfTemplate[]>(fallbackTemplates);
  const [loading, setLoading] = useState(true);

  // Initial load: fetch councils, templates, and restore session
  useEffect(() => {
    let mounted = true;

    async function init() {
      try {
        const [councilsRes, templatesRes] = await Promise.all([
          api.getCouncils(),
          api.getTemplates(),
        ]);

        if (!mounted) return;

        setCouncils(councilsRes.councils);
        if (councilsRes.nations.length > 0) {
          setNations(councilsRes.nations);
        }
        setTemplates(templatesRes.templates);

        // Pre-select user's current template if available
        if (templatesRes.userCurrentTemplate) {
          setSelectedTemplateId(templatesRes.userCurrentTemplate);
        }

        // Restore session if in progress
        const session = await api.getSession();
        if (!mounted) return;

        const data = session.data;
        if (data.councils && data.councils.length > 0) {
          setSelectedCouncils(data.councils);
        }
        if (data.template) {
          setSelectedTemplateId(data.template);
        }
        if (data.business) {
          setBusinessInfo({
            companyName: data.business.pmpc_company_name || '',
            businessEmail: data.business.pmpc_business_email || '',
            businessPhone: data.business.pmpc_business_phone || '',
            businessAddress: data.business.pmpc_company_address || '',
          });
        }

        // If user is already logged in via WordPress, pre-fill account info
        if (isUserLoggedIn()) {
          setAccountInfo({
            username: '',
            email: '',
            fullName: '',
          });
        }
      } catch {
        // In dev mode or if the API is unavailable, the fallback data
        // from the static imports is already set as default state.
      } finally {
        if (mounted) setLoading(false);
      }
    }

    init();

    return () => {
      mounted = false;
    };
  }, []);

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
    councils,
    nations,
    templates,
    loading,
    setStep,
    toggleCouncil,
    clearCouncils,
    setSelectedTemplateId,
    setBusinessInfo,
    setAccountInfo,
    canProceedFromStep,
  };

  return (
    <CheckoutContext.Provider value={value}>{children}</CheckoutContext.Provider>
  );
}

export function useCheckout() {
  const ctx = useContext(CheckoutContext);
  if (!ctx) throw new Error('useCheckout must be used within CheckoutProvider');
  return ctx;
}

export { getTemplateByIdStatic as getTemplateById };
