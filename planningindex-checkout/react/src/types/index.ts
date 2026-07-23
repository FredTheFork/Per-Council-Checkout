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

export interface Profile {
  id: string;
  username: string;
  full_name: string;
  email: string;
  company_name: string | null;
  business_email: string | null;
  business_phone: string | null;
  business_address: string | null;
}

export interface Subscription {
  id: string;
  user_id: string;
  selected_councils: string[];
  selected_template_id: string | null;
  monthly_cost: number;
  total_due_today: number;
  status: string;
}
