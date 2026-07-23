export type CheckoutStep = 1 | 2 | 3 | 4;

export interface Region {
  id: string;
  name: string;
  price: number;
  councils: string[];
}

export interface PdfTemplate {
  id: string;
  name: string;
  description: string;
  category: string;
  included: boolean;
  price: number;
  accent: string;
}

export interface BusinessInfo {
  companyName: string;
  businessEmail: string;
  businessPhone: string;
  businessAddress: string;
}

export interface AccountInfo {
  username: string;
  email: string;
  fullName: string;
  password?: string;
}

export interface CheckoutState {
  step: CheckoutStep;
  selectedRegion: string | null;
  selectedCouncils: string[];
  selectedTemplateId: string | null;
  businessInfo: BusinessInfo;
  accountInfo: AccountInfo | null;
}
