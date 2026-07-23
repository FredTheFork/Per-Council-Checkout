/**
 * Redirect helper for the hybrid PMPro checkout flow.
 *
 * The React wizard collects all selections (councils, template, business
 * info, account credentials). On the final step we submit a hidden form
 * directly to the PMPro checkout URL via a POST request, exactly like the
 * legacy PHP checkout did. This populates $_REQUEST so PMPro's hooks
 * (registration_checks, checkout_level_price, Stripe filters) see the
 * correct price and councils, and PMPro renders its own Stripe checkout.
 *
 * The PHP session is also saved as a backup via the REST /session endpoint,
 * but the form POST is the primary transport — it does not depend on
 * session cookie sharing between the REST API and the main site.
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
 * Submit a hidden form POST to the PMPro checkout URL.
 *
 * This mirrors the legacy PHP checkout form: it POSTs all the per-council
 * data (councils, price, template, business info, account credentials)
 * directly to PMPro's checkout endpoint so the server-side hooks can
 * intercept and override the price before Stripe takes over.
 */
export function submitCheckoutForm(data: CheckoutFormData): void {
  const config = getInjectedConfig();
  const levelId = config.levelId;
  const gateway = config.gateway || 'stripe';

  // Use the injected checkoutUrl if available. Fall back to the current
  // page URL — the React wizard runs ON the PMPro checkout page, so
  // posting back to the same URL with the per-council data in $_POST
  // is sufficient to trigger PMPro's hooks and render the Stripe form.
  const baseUrl = config.checkoutUrl || window.location.href;

  const checkoutUrl = appendQueryArgs(baseUrl, {
    level: String(levelId),
    pi_complete: '1',
    gateway,
  });

  // Build a hidden form and submit it via POST so PMPro receives all
  // the data in $_POST / $_REQUEST, exactly like the legacy checkout.
  const form = document.createElement('form');
  form.method = 'POST';
  form.action = checkoutUrl;
  form.style.display = 'none';

  // Core PMPro fields — NOTE: we do NOT send submit-checkout here.
  // We want PMPro to render its checkout form (with card fields) so the
  // user can enter payment details. PMPro's hooks will read the per-council
  // data from $_POST/$_REQUEST and set the correct price. When the user
  // submits PMPro's own form, submit-checkout is sent by PMPro's form.
  addHiddenField(form, 'level', String(levelId));
  addHiddenField(form, 'pmpro_level', String(levelId));
  addHiddenField(form, 'gateway', gateway);
  addHiddenField(form, 'checkjavascript', '1');

  // Per-council data
  addHiddenField(form, 'pmpc_calculated_price', data.calculatedPrice);
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
