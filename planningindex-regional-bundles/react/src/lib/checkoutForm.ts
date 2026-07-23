import type { AccountInfo, BusinessInfo } from '@/types';
import { getInjectedConfig } from '@/lib/api';

export interface CheckoutFormData {
  region: string;
  councils: string[];
  calculatedPrice: string;
  templateId: string;
  businessInfo: BusinessInfo;
  accountInfo: AccountInfo | null;
  isLoggedIn: boolean;
}

export function submitCheckoutForm(data: CheckoutFormData): void {
  const config = getInjectedConfig();
  const levelId = config.levelId;
  const gateway = config.gateway || 'stripe';

  const baseUrl = config.checkoutUrl || window.location.href;

  const checkoutUrl = appendQueryArgs(baseUrl, {
    level: String(levelId),
    pmpro_level: String(levelId),
    pirb_complete: '1',
    gateway,
  });

  const form = document.createElement('form');
  form.method = 'POST';
  form.action = checkoutUrl;
  form.style.display = 'none';

  addHiddenField(form, 'level', String(levelId));
  addHiddenField(form, 'pmpro_level', String(levelId));
  addHiddenField(form, 'gateway', gateway);
  addHiddenField(form, 'checkjavascript', '1');

  addHiddenField(form, 'pmpc_calculated_price', data.calculatedPrice);
  addHiddenField(form, 'pmpc_default_template', data.templateId);

  for (const council of data.councils) {
    addHiddenField(form, 'pmpc_councils[]', council);
  }

  addHiddenField(form, 'pmpc_company_name', data.businessInfo.companyName || '');
  addHiddenField(form, 'pmpc_business_email', data.businessInfo.businessEmail || '');
  addHiddenField(form, 'pmpc_business_phone', data.businessInfo.businessPhone || '');
  addHiddenField(form, 'pmpc_company_address', data.businessInfo.businessAddress || '');

  if (!data.isLoggedIn && data.accountInfo) {
    addHiddenField(form, 'username', data.accountInfo.username || '');
    addHiddenField(form, 'password', data.accountInfo.password || '');
    addHiddenField(form, 'password2', data.accountInfo.password || '');
    addHiddenField(form, 'bemail', data.accountInfo.email || '');
    addHiddenField(form, 'bconfirmemail', data.accountInfo.email || '');
  }

  document.body.appendChild(form);
  form.submit();
}

function addHiddenField(form: HTMLFormElement, name: string, value: string): void {
  const input = document.createElement('input');
  input.type = 'hidden';
  input.name = name;
  input.value = value;
  form.appendChild(input);
}

function appendQueryArgs(baseUrl: string, args: Record<string, string>): string {
  if (!baseUrl) return '';
  const url = new URL(baseUrl, window.location.origin);
  Object.entries(args).forEach(([key, value]) => {
    url.searchParams.set(key, value);
  });
  return url.toString();
}
