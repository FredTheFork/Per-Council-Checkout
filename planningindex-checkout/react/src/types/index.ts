export type CheckoutStep = 1 | 2 | 3 | 4;

export interface Council {
  name: string;
  region: string;
  nation: 'England' | 'Scotland' | 'Wales' | 'Northern Ireland';
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
}

export interface CheckoutState {
  step: CheckoutStep;
  selectedCouncils: string[];
  selectedTemplateId: string | null;
  businessInfo: BusinessInfo;
  accountInfo: AccountInfo | null;
}
