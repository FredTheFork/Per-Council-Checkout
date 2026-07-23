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
  Region,
  PdfTemplate,
} from '@/types';
import { getRegionById } from '@/data/regions';
import { getTemplateById as getTemplateByIdStatic, templates as fallbackTemplates } from '@/data/templates';
import { api, isLoggedIn as isUserLoggedIn } from '@/lib/api';

interface CheckoutContextValue {
  step: CheckoutStep;
  selectedRegion: string | null;
  selectedCouncils: string[];
  selectedTemplateId: string | null;
  businessInfo: BusinessInfo;
  accountInfo: AccountInfo | null;
  monthlyCost: number;
  totalDueToday: number;
  regions: Region[];
  templates: PdfTemplate[];
  loading: boolean;
  setStep: (step: CheckoutStep) => void;
  selectRegion: (id: string) => void;
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
  const [selectedRegion, setSelectedRegion] = useState<string | null>(null);
  const [selectedCouncils, setSelectedCouncils] = useState<string[]>([]);
  const [selectedTemplateId, setSelectedTemplateId] = useState<string | null>(null);
  const [businessInfo, setBusinessInfo] = useState<BusinessInfo>(defaultBusinessInfo);
  const [accountInfo, setAccountInfo] = useState<AccountInfo | null>(null);

  const [regions, setRegions] = useState<Region[]>([]);
  const [templates, setTemplates] = useState<PdfTemplate[]>(fallbackTemplates);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let mounted = true;

    async function init() {
      try {
        const [regionsRes, templatesRes] = await Promise.all([
          api.getRegions(),
          api.getTemplates(),
        ]);

        if (!mounted) return;

        setRegions(regionsRes.regions);
        setTemplates(templatesRes.templates);

        if (templatesRes.userCurrentTemplate) {
          setSelectedTemplateId(templatesRes.userCurrentTemplate);
        }

        const session = await api.getSession();
        if (!mounted) return;

        const data = session.data;
        if (data.region) {
          setSelectedRegion(data.region);
          const region = getRegionById(data.region);
          if (region) {
            setSelectedCouncils(region.councils);
          }
        } else if (data.councils && data.councils.length > 0) {
          setSelectedCouncils(data.councils);
        }
        if (data.template) {
          setSelectedTemplateId(data.template);
        }
        if (data.business) {
          setBusinessInfo({
            companyName: data.business.pirb_company_name || '',
            businessEmail: data.business.pirb_business_email || '',
            businessPhone: data.business.pirb_business_phone || '',
            businessAddress: data.business.pirb_company_address || '',
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

  const selectRegion = useCallback((id: string) => {
    setSelectedRegion(id);
    const region = getRegionById(id);
    if (region) {
      setSelectedCouncils(region.councils);
    }
  }, []);

  const monthlyCost = (() => {
    if (!selectedRegion) return 0;
    const region = getRegionById(selectedRegion);
    return region ? region.price : 0;
  })();
  const totalDueToday = monthlyCost;

  const canProceedFromStep = useCallback(
    (s: CheckoutStep): boolean => {
      if (s === 1) return selectedRegion !== null;
      if (s === 2) return selectedTemplateId !== null;
      if (s === 3) return accountInfo !== null;
      if (s === 4) return true;
      return false;
    },
    [selectedRegion, selectedTemplateId, accountInfo]
  );

  const value: CheckoutContextValue = {
    step,
    selectedRegion,
    selectedCouncils,
    selectedTemplateId,
    businessInfo,
    accountInfo,
    monthlyCost,
    totalDueToday,
    regions,
    templates,
    loading,
    setStep,
    selectRegion,
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
