/**
 * API client for the Planning Index checkout.
 *
 * In production (WordPress), all calls go through the WP REST API at
 * planningindex/v1, using the nonce injected by the plugin as
 * window.PlanningIndexCheckout.nonce.
 *
 * In the local dev/preview environment (no WordPress), the client falls
 * back to the hardcoded data so the UI is fully interactive.
 */

import type { Council, PdfTemplate, BusinessInfo, AccountInfo } from '@/types';
import { councils as fallbackCouncils, nations as fallbackNations, regions as fallbackRegions, PRICE_PER_COUNCIL } from '@/data/councils';
import { templates as fallbackTemplates } from '@/data/templates';

/** Shape of the injected config object. */
interface PicConfig {
  apiBase: string;
  nonce: string;
  checkoutUrl: string;
  checkoutNonce: string;
  ajaxUrl: string;
  isLoggedIn: boolean;
  userId: number;
  userName: string;
  userEmail: string;
  userCurrentTemplate: string;
  unitPrice: number;
  minSelection: number;
  levelId: number;
  gateway: string;
  requireBilling: boolean;
  strings: Record<string, string>;
}

const DEV_CONFIG: PicConfig = {
  apiBase: '',
  nonce: 'dev',
  checkoutUrl: '',
  checkoutNonce: 'dev',
  ajaxUrl: '',
  isLoggedIn: false,
  userId: 0,
  userName: '',
  userEmail: '',
  userCurrentTemplate: 'standard-planning',
  unitPrice: PRICE_PER_COUNCIL,
  minSelection: 3,
  levelId: 0,
  gateway: 'stripe',
  requireBilling: true,
  strings: {},
};

/** Read the config injected by the PHP plugin, or fall back to dev config. */
function getConfig(): PicConfig {
  const cfg = (window as unknown as { PlanningIndexCheckout?: PicConfig }).PlanningIndexCheckout;
  if (!cfg || !cfg.apiBase || !cfg.nonce) {
    console.warn('[PIC] No valid window.PlanningIndexCheckout config found, using dev fallback. window.PlanningIndexCheckout =', cfg);
    // Even in dev mode, try to extract the level ID from the URL so the
    // hidden form POST targets the right membership level.
    const params = new URLSearchParams(window.location.search);
    const urlLevel = parseInt(params.get('level') || params.get('pmpro_level') || '0', 10);
    if (urlLevel > 0) {
      return { ...DEV_CONFIG, levelId: urlLevel };
    }
    return DEV_CONFIG;
  }
  console.log('[PIC] Config loaded from window.PlanningIndexCheckout:', { apiBase: cfg.apiBase, isLoggedIn: cfg.isLoggedIn, userId: cfg.userId, levelId: cfg.levelId, gateway: cfg.gateway });
  return cfg;
}

/** Check whether we're in dev/preview mode (no WordPress backend). */
function isDevMode(): boolean {
  const cfg = getConfig();
  return cfg.apiBase === '';
}

/** Wrapper around fetch that adds the WP REST nonce and JSON headers. */
async function request<T>(
  path: string,
  options: { method?: string; body?: unknown } = {}
): Promise<T> {
  const cfg = getConfig();
  const url = `${cfg.apiBase}${path}`;

  const headers: Record<string, string> = {
    'X-WP-Nonce': cfg.nonce,
  };

  let body: string | undefined;
  if (options.body !== undefined) {
    headers['Content-Type'] = 'application/json';
    body = JSON.stringify(options.body);
  }

  const res = await fetch(url, {
    method: options.method ?? 'GET',
    headers,
    body,
    credentials: 'same-origin',
  });

  if (!res.ok) {
    let message = `Request failed (${res.status})`;
    try {
      const err = await res.json();
      console.error('[PIC] API error response:', err);
      if (err.message) message = err.message;
      else if (err.data && err.data.message) message = err.data.message;
    } catch {
      // response wasn't JSON
      console.error('[PIC] Non-JSON error response, status:', res.status);
    }
    throw new Error(message);
  }

  const json = await res.json() as T;
  // Check for success: false in the response body (server may return 200
  // but with success: false and a message field)
  if (json && typeof json === 'object' && 'success' in json && (json as { success: boolean }).success === false) {
    const msg = (json as { message?: string }).message || 'Request failed';
    console.error('[PIC] API returned success: false:', json);
    throw new Error(msg);
  }
  return json;
}

// --- Response types ---

interface CouncilsResponse {
  councils: Council[];
  nations: string[];
  regions: string[];
}

interface TemplatesResponse {
  templates: PdfTemplate[];
  userCurrentTemplate: string | null;
}

interface CheckUserResponse {
  valid: boolean;
  errors: { username?: string; email?: string };
}

interface SessionData {
  councils?: string[];
  price?: number;
  template?: string;
  business?: Record<string, string>;
  username?: string;
  password?: string;
  email?: string;
}

interface SessionResponse {
  data: SessionData;
}

interface ProfileResponse {
  id: string;
  username: string;
  fullName: string;
  email: string;
  companyName: string;
  businessEmail: string;
  businessPhone: string;
  businessAddress: string;
  website: string;
  vatNumber: string;
  selectedCouncils: string[];
  selectedTemplateId: string | null;
  monthlyCost: number;
  totalDueToday: number;
}

interface LoginResponse {
  success: boolean;
  message?: string;
  id?: string;
  username?: string;
  fullName?: string;
  email?: string;
  companyName?: string;
  businessEmail?: string;
  businessPhone?: string;
  businessAddress?: string;
  selectedCouncils?: string[];
  selectedTemplateId?: string | null;
}

interface CheckoutResponse {
  success: boolean;
  message?: string;
  orderCode?: string;
  orderDate?: string;
  planName?: string;
  councilCount?: number;
  monthlyCost?: number;
  totalDueToday?: number;
  redirectUrl?: string;
}

interface StripeSessionResponse {
  success: boolean;
  message?: string;
  stripeUrl?: string;
  sessionId?: string;
}

interface ConfigResponse {
  unitPrice: number;
  minSelection: number;
  totalSteps: number;
  checkoutUrl: string;
  ajaxUrl: string;
  gateway: string;
  levelId: number;
  requireBilling: boolean;
  isLoggedIn: boolean;
}

interface VerifyPriceResponse {
  success: boolean;
  councilCount: number;
  monthlyCost: number;
  totalDueToday: number;
  unitPrice: number;
}

// --- API surface ---

export const api = {
  /** GET /councils — full council list with nation/region grouping. */
  async getCouncils(): Promise<CouncilsResponse> {
    if (isDevMode()) {
      return {
        councils: fallbackCouncils,
        nations: [...fallbackNations],
        regions: fallbackRegions,
      };
    }
    return request<CouncilsResponse>('/councils');
  },

  /** GET /templates — available templates plus user's saved template. */
  async getTemplates(): Promise<TemplatesResponse> {
    if (isDevMode()) {
      return {
        templates: fallbackTemplates,
        userCurrentTemplate: null,
      };
    }
    return request<TemplatesResponse>('/templates');
  },

  /** POST /check-user — validate username/email availability. */
  async checkUser(username: string, email: string): Promise<CheckUserResponse> {
    if (isDevMode()) {
      const errors: { username?: string; email?: string } = {};
      if (username === 'taken') errors.username = 'This username is already taken.';
      if (email === 'taken@example.com') errors.email = 'This email is already registered.';
      return { valid: Object.keys(errors).length === 0, errors };
    }
    return request<CheckUserResponse>('/check-user', {
      method: 'POST',
      body: { username, email },
    });
  },

  /** GET /session — retrieve saved checkout session data. */
  async getSession(): Promise<SessionResponse> {
    if (isDevMode()) {
      return { data: {} };
    }
    return request<SessionResponse>('/session');
  },

  /** POST /session — save checkout step data. */
  async saveSession(
    step: number,
    payload: {
      councils?: string[];
      template?: string;
      business?: Record<string, string>;
      username?: string;
      password?: string;
      email?: string;
    }
  ): Promise<{ success: boolean; step: number }> {
    if (isDevMode()) {
      return { success: true, step: step + 1 };
    }
    return request('/session', { method: 'POST', body: { step, ...payload } });
  },

  /** DELETE /session — clear session after successful checkout. */
  async clearSession(): Promise<{ success: boolean }> {
    if (isDevMode()) {
      return { success: true };
    }
    return request('/session', { method: 'DELETE' });
  },

  /** GET /profile — logged-in user's profile data. */
  async getProfile(): Promise<ProfileResponse> {
    if (isDevMode()) {
      throw new Error('Not available in dev mode');
    }
    return request<ProfileResponse>('/profile');
  },

  /** POST /profile — update business info on the user's profile. */
  async updateProfile(info: BusinessInfo): Promise<{ success: boolean }> {
    if (isDevMode()) {
      return { success: true };
    }
    return request('/profile', {
      method: 'POST',
      body: {
        companyName: info.companyName,
        businessEmail: info.businessEmail,
        businessPhone: info.businessPhone,
        businessAddress: info.businessAddress,
      },
    });
  },

  /** POST /login — authenticate against WordPress and return profile. */
  async login(identifier: string, password: string): Promise<LoginResponse> {
    if (isDevMode()) {
      if (identifier === 'demo' && password === 'password') {
        return {
          success: true,
          id: '1',
          username: 'demo',
          fullName: 'Demo User',
          email: 'demo@example.com',
          companyName: '',
          businessEmail: 'demo@example.com',
          businessPhone: '',
          businessAddress: '',
          selectedCouncils: [],
          selectedTemplateId: null,
        };
      }
      return { success: false, message: 'Invalid login details. Use demo/password for dev mode.' };
    }
    return request<LoginResponse>('/login', {
      method: 'POST',
      body: { login: identifier, password },
    });
  },

  /** POST /checkout — process the subscription checkout. */
  async submitCheckout(): Promise<CheckoutResponse> {
    if (isDevMode()) {
      return {
        success: true,
        orderCode: 'PIC-DEV' + Math.random().toString(36).substring(2, 8).toUpperCase(),
        orderDate: new Date().toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' }),
        planName: 'Planning Index Subscription',
        councilCount: 0,
        monthlyCost: 0,
        totalDueToday: 0,
      };
    }
    return request<CheckoutResponse>('/checkout', { method: 'POST' });
  },

  /**
   * POST /stripe-session — create a Stripe Checkout Session and get the
   * Stripe-hosted URL. The React app redirects the browser there so the
   * user goes straight to checkout.stripe.com, bypassing PMPro's checkout
   * page entirely.
   */
  async createStripeSession(payload: {
    councils: string[];
    templateId: string;
    businessInfo: BusinessInfo;
    accountInfo: AccountInfo | null;
  }): Promise<StripeSessionResponse> {
    if (isDevMode()) {
      return { success: true, stripeUrl: '', sessionId: 'dev' };
    }
    return request<StripeSessionResponse>('/stripe-session', {
      method: 'POST',
      body: payload,
    });
  },

  /** GET /checkout/verify-price — server-sourced price confirmation. */
  async verifyPrice(localCouncilCount: number = 0): Promise<VerifyPriceResponse> {
    if (isDevMode()) {
      return {
        success: localCouncilCount > 0,
        councilCount: localCouncilCount,
        monthlyCost: localCouncilCount * PRICE_PER_COUNCIL,
        totalDueToday: localCouncilCount * PRICE_PER_COUNCIL,
        unitPrice: PRICE_PER_COUNCIL,
      };
    }
    return request<VerifyPriceResponse>('/checkout/verify-price');
  },

  /** GET /config — runtime configuration. */
  async getConfig(): Promise<ConfigResponse> {
    if (isDevMode()) {
      return {
        unitPrice: PRICE_PER_COUNCIL,
        minSelection: 3,
        totalSteps: 4,
        checkoutUrl: '',
        ajaxUrl: '',
        gateway: 'stripe',
        levelId: 0,
        requireBilling: true,
        isLoggedIn: false,
      };
    }
    return request<ConfigResponse>('/config');
  },
};

/** Convenience: get the injected config without making a network call. */
export function getInjectedConfig(): PicConfig {
  return getConfig();
}

/** Convenience: check if the user is logged in from the injected config. */
export function isLoggedIn(): boolean {
  return getConfig().isLoggedIn;
}

/** Convenience: get the logged-in user's display name from the injected config. */
export function getLoggedInUserName(): string {
  return getConfig().userName;
}

/** Convenience: get the logged-in user's email from the injected config. */
export function getLoggedInUserEmail(): string {
  return getConfig().userEmail;
}

/** Type re-export for consumers. */
export type { AccountInfo, BusinessInfo, Council, PdfTemplate };
