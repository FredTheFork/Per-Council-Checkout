/**
 * Redirect helper for the hybrid PMPro checkout flow.
 *
 * The React wizard collects all selections (councils, template, business
 * info, account credentials) and persists them server-side via the REST
 * session endpoint. On the final step we redirect the browser to the
 * real PMPro checkout page via a normal GET navigation.
 *
 * PMPro then renders its own checkout form with a fresh nonce and its
 * own Stripe gateway, which avoids "Nonce security check failed"
 * errors. The PmproHooks::restore_session() method fires on
 * pmpro_checkout_preheader and merges the saved session data into
 * $_REQUEST so the price override, hidden fields, and billing
 * pre-population all work.
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
 * Redirect the browser to the PMPro checkout page.
 *
 * All wizard data is already saved in the PHP session via the REST
 * /session endpoint, so we only need to navigate to the checkout URL
 * with the level and pi_complete flag. PMPro renders its own form,
 * the session is restored by PmproHooks, and the user submits PMPro's
 * native form to reach Stripe.
 */
export function submitCheckoutForm(_data: CheckoutFormData): void {
  const config = getInjectedConfig();
  const levelId = config.levelId;
  const gateway = config.gateway || 'stripe';

  const baseUrl = config.checkoutUrl || '';
  const checkoutUrl = appendQueryArgs(baseUrl, {
    level: String(levelId),
    pi_complete: '1',
    gateway,
  });

  if (checkoutUrl) {
    window.location.href = checkoutUrl;
  }
}

function appendQueryArgs(baseUrl: string, args: Record<string, string>): string {
  if (!baseUrl) return '';
  const url = new URL(baseUrl, window.location.origin);
  Object.entries(args).forEach(([key, value]) => {
    url.searchParams.set(key, value);
  });
  return url.toString();
}
