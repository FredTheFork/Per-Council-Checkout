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
  trialDays: number;
  councils: Council[];
  nations: readonly string[];
  templates: PdfTemplate[];
  loading: boolean;
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

const MIN_SELECTION = 1;
const MAX_SELECTION = 5;
const TRIAL_DAYS = 14;

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

        if (templatesRes.userCurrentTemplate) {
          setSelectedTemplateId(templatesRes.userCurrentTemplate);
        }

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

        if (isUserLoggedIn()) {
          setAccountInfo({
            username: '',
            email: '',
            fullName: '',
            password: '',
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
    setSelectedCouncils((prev) => {
      if (prev.includes(name)) {
        return prev.filter((c) => c !== name);
      }
      // Enforce max selection for the trial
      if (prev.length >= MAX_SELECTION) {
        return prev;
      }
      return [...prev, name];
    });
  }, []);

  const clearCouncils = useCallback(() => setSelectedCouncils([]), []);

  // Trial is always £0
  const monthlyCost = 0;
  const totalDueToday = 0;

  const canProceedFromStep = useCallback(
    (s: CheckoutStep): boolean => {
      if (s === 1) return selectedCouncils.length >= MIN_SELECTION && selectedCouncils.length <= MAX_SELECTION;
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
    trialDays: TRIAL_DAYS,
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
