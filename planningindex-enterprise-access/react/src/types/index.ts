export type CheckoutStep = 1 | 2 | 3 | 4;

export type TeamSeats = 1 | 3 | 5;

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
  selectedTemplateId: string | null;
  teamSeats: TeamSeats;
  businessInfo: BusinessInfo;
  accountInfo: AccountInfo | null;
}
