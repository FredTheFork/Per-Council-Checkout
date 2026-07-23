/**
 * Hidden form builder for the hybrid PMPro checkout POST.
 *
 * Instead of sending card data through the REST API, the React app builds
 * a traditional hidden <form> and submits it as a full-page navigation
 * to the PMPro checkout URL. PMPro's native Stripe gateway then collects
 * card data on its own checkout page, preserving PCI compliance and
 * firing all existing Stripe hooks.
 */

import type { AccountInfo, BusinessInfo } from '@/types';
import { getInjectedConfig } from '@/lib/api';

export interface CheckoutFormData {
  councils: string[];
  calculatedPrice: string;
  templateId: string;
  businessInfo: BusinessInfo;
  accountInfo: AccountInfo | null;
  isLoggedIn: boolean;
}

/**
 * Build and submit a hidden form to the PMPro checkout page.
 *
 * The form includes all session data as hidden inputs plus the PMPro
 * checkout nonce, then triggers a full-page navigation.
 */
export function submitCheckoutForm(data: CheckoutFormData): void {
  const config = getInjectedConfig();
  const levelId = config.levelId;
  const gateway = config.gateway || 'stripe';
  const nonce = config.checkoutNonce || '';

  // Build the PMPro checkout URL with query params
  const baseUrl = config.checkoutUrl || '';
  const checkoutUrl = appendQueryArgs(baseUrl, {
    level: String(levelId),
    pi_complete: '1',
    gateway,
  });

  const form = document.createElement('form');
  form.method = 'POST';
  form.action = checkoutUrl;
  form.style.display = 'none';

  // PMPro control fields
  addHiddenInput(form, 'pmpro_level', String(levelId));
  addHiddenInput(form, 'level', String(levelId));
  addHiddenInput(form, 'submit-checkout', '1');
  addHiddenInput(form, 'confirm', '1');
  addHiddenInput(form, 'checkjavascript', '1');
  addHiddenInput(form, 'gateway', gateway);

  if (nonce) {
    addHiddenInput(form, 'pmpro_checkout_nonce', nonce);
  }

  // Council selection (one input per council)
  data.councils.forEach((council) => {
    addHiddenInput(form, 'pmpc_councils[]', council);
  });

  // Calculated price
  addHiddenInput(form, 'pmpc_calculated_price', data.calculatedPrice);

  // Template
  addHiddenInput(form, 'pmpc_default_template', data.templateId || 'standard-planning');

  // Business info
  addHiddenInput(form, 'pmpc_company_name', data.businessInfo.companyName || '');
  addHiddenInput(form, 'pmpc_business_email', data.businessInfo.businessEmail || '');
  addHiddenInput(form, 'pmpc_business_phone', data.businessInfo.businessPhone || '');
  addHiddenInput(form, 'pmpc_company_address', data.businessInfo.businessAddress || '');

  // Account credentials — only for new (not-logged-in) users
  if (!data.isLoggedIn && data.accountInfo) {
    addHiddenInput(form, 'username', data.accountInfo.username || '');
    addHiddenInput(form, 'password', data.accountInfo.password || '');
    addHiddenInput(form, 'password2', data.accountInfo.password || '');
    addHiddenInput(form, 'bemail', data.accountInfo.email || '');
    addHiddenInput(form, 'bconfirmemail', data.accountInfo.email || '');
  }

  // Append to DOM and submit
  document.body.appendChild(form);
  form.submit();
}

/**
 * Append query parameters to a base URL.
 */
function appendQueryArgs(baseUrl: string, args: Record<string, string>): string {
  if (!baseUrl) return '';
  const url = new URL(baseUrl, window.location.origin);
  Object.entries(args).forEach(([key, value]) => {
    url.searchParams.set(key, value);
  });
  return url.toString();
}

/**
 * Add a hidden input to a form.
 */
function addHiddenInput(form: HTMLFormElement, name: string, value: string): void {
  const input = document.createElement('input');
  input.type = 'hidden';
  input.name = name;
  input.value = value;
  form.appendChild(input);
}
