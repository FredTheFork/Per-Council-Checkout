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
  PdfTemplate,
  TeamSeats,
} from '@/types';
import { getTemplateById as getTemplateByIdStatic, templates as fallbackTemplates } from '@/data/templates';
import { api, isLoggedIn as isUserLoggedIn, getEnterprisePrice } from '@/lib/api';

interface CheckoutContextValue {
  step: CheckoutStep;
  selectedTemplateId: string | null;
  teamSeats: TeamSeats;
  businessInfo: BusinessInfo;
  accountInfo: AccountInfo | null;
  monthlyCost: number;
  totalDueToday: number;
  templates: PdfTemplate[];
  loading: boolean;
  setStep: (step: CheckoutStep) => void;
  setSelectedTemplateId: (id: string | null) => void;
  setTeamSeats: (seats: TeamSeats) => void;
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
  const [selectedTemplateId, setSelectedTemplateId] = useState<string | null>(null);
  const [teamSeats, setTeamSeats] = useState<TeamSeats>(1);
  const [businessInfo, setBusinessInfo] = useState<BusinessInfo>(defaultBusinessInfo);
  const [accountInfo, setAccountInfo] = useState<AccountInfo | null>(null);

  const [templates, setTemplates] = useState<PdfTemplate[]>(fallbackTemplates);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let mounted = true;

    async function init() {
      try {
        const [templatesRes, session] = await Promise.all([
          api.getTemplates(),
          api.getSession(),
        ]);

        if (!mounted) return;

        setTemplates(templatesRes.templates);

        if (templatesRes.userCurrentTemplate) {
          setSelectedTemplateId(templatesRes.userCurrentTemplate);
        }

        const data = session.data;
        if (data.template) {
          setSelectedTemplateId(data.template);
        }
        if (data.team_seats) {
          setTeamSeats(data.team_seats as TeamSeats);
        }
        if (data.business) {
          setBusinessInfo({
            companyName: data.business.pmpe_company_name || '',
            businessEmail: data.business.pmpe_business_email || '',
            businessPhone: data.business.pmpe_business_phone || '',
            businessAddress: data.business.pmpe_company_address || '',
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
        // Fallback data is already set as default state
      } finally {
        if (mounted) setLoading(false);
      }
    }

    init();

    return () => {
      mounted = false;
    };
  }, []);

  const monthlyCost = getEnterprisePrice();
  const totalDueToday = monthlyCost;

  const canProceedFromStep = useCallback(
    (s: CheckoutStep): boolean => {
      if (s === 1) return true; // Enterprise benefits — always proceed
      if (s === 2) return selectedTemplateId !== null;
      if (s === 3) return accountInfo !== null;
      if (s === 4) return true;
      return false;
    },
    [selectedTemplateId, accountInfo]
  );

  const value: CheckoutContextValue = {
    step,
    selectedTemplateId,
    teamSeats,
    businessInfo,
    accountInfo,
    monthlyCost,
    totalDueToday,
    templates,
    loading,
    setStep,
    setSelectedTemplateId,
    setTeamSeats,
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
