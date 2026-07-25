/**
 * Redirect helper for the trial PMPro checkout flow.
 *
 * The React wizard collects all selections (councils, template, business
 * info, account credentials). On the final step, the React app calls
 * the REST /checkout endpoint which saves the session and returns a
 * redirect URL to the PMPro checkout page. The browser navigates there
 * via window.location.href, and PMPro processes the checkout at £0
 * (no Stripe redirect — the trial is free for 14 days).
 *
 * This form-submit helper is kept as a fallback for cases where the
 * REST API is unavailable — it POSTs the trial data directly to PMPro.
 */

import type { AccountInfo, BusinessInfo } from '@/types';
import { getInjectedConfig } from '@/lib/api';

export interface TrialCheckoutFormData {
  councils: string[];
  templateId: string;
  businessInfo: BusinessInfo;
  accountInfo: AccountInfo | null;
  isLoggedIn: boolean;
}

/**
 * Submit a hidden form POST to the PMPro checkout URL for the trial.
 *
 * This POSTs all the trial data (councils, template, business info,
 * account credentials) directly to PMPro's checkout endpoint at £0.
 */
export function submitTrialCheckoutForm(data: TrialCheckoutFormData): void {
  const config = getInjectedConfig();
  const levelId = config.levelId;

  const baseUrl = config.checkoutUrl || window.location.href;

  const checkoutUrl = appendQueryArgs(baseUrl, {
    level: String(levelId),
    pmpro_level: String(levelId),
    pi_complete: '1',
  });

  const form = document.createElement('form');
  form.method = 'POST';
  form.action = checkoutUrl;
  form.style.display = 'none';

  addHiddenField(form, 'level', String(levelId));
  addHiddenField(form, 'pmpro_level', String(levelId));
  addHiddenField(form, 'checkjavascript', '1');

  // Trial price is always £0
  addHiddenField(form, 'pmpc_calculated_price', '0.00');
  addHiddenField(form, 'pmpc_default_template', data.templateId);

  // Councils as array fields
  for (const council of data.councils) {
    addHiddenField(form, 'pmpc_councils[]', council);
  }

  // Business info
  addHiddenField(form, 'pmpc_company_name', data.businessInfo.companyName || '');
  addHiddenField(form, 'pmpc_business_email', data.businessInfo.businessEmail || '');
  addHiddenField(form, 'pmpc_business_phone', data.businessInfo.businessPhone || '');
  addHiddenField(form, 'pmpc_company_address', data.businessInfo.businessAddress || '');

  // Account credentials for logged-out users (PMPro creates the account)
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
