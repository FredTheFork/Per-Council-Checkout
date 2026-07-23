/**
 * API client for the Planning Index checkout.
 *
 * Replaces the Supabase client. All calls go through the WordPress REST API
 * at planningindex/v1, using the nonce injected by the plugin as
 * window.PlanningIndexCheckout.nonce.
 */

import type { Council, PdfTemplate, BusinessInfo, AccountInfo } from '@/types';

/** Shape of the injected config object. */
interface PicConfig {
  apiBase: string;
  nonce: string;
  checkoutUrl: string;
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

/** Read the config injected by the PHP plugin. */
function getConfig(): PicConfig {
  const cfg = (window as unknown as { PlanningIndexCheckout?: PicConfig }).PlanningIndexCheckout;
  if (!cfg || !cfg.apiBase || !cfg.nonce) {
    throw new Error('Planning Index checkout config not found. Ensure the plugin is active.');
  }
  return cfg;
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
      if (err.message) message = err.message;
    } catch {
      // response wasn't JSON
    }
    throw new Error(message);
  }

  return res.json() as Promise<T>;
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

// --- API surface ---

export const api = {
  /** GET /councils — full council list with nation/region grouping. */
  async getCouncils(): Promise<CouncilsResponse> {
    return request<CouncilsResponse>('/councils');
  },

  /** GET /templates — available templates plus user's saved template. */
  async getTemplates(): Promise<TemplatesResponse> {
    return request<TemplatesResponse>('/templates');
  },

  /** POST /check-user — validate username/email availability. */
  async checkUser(username: string, email: string): Promise<CheckUserResponse> {
    return request<CheckUserResponse>('/check-user', {
      method: 'POST',
      body: { username, email },
    });
  },

  /** GET /session — retrieve saved checkout session data. */
  async getSession(): Promise<SessionResponse> {
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
    return request('/session', { method: 'POST', body: { step, ...payload } });
  },

  /** DELETE /session — clear session after successful checkout. */
  async clearSession(): Promise<{ success: boolean }> {
    return request('/session', { method: 'DELETE' });
  },

  /** GET /profile — logged-in user's profile data. */
  async getProfile(): Promise<ProfileResponse> {
    return request<ProfileResponse>('/profile');
  },

  /** POST /profile — update business info on the user's profile. */
  async updateProfile(info: BusinessInfo): Promise<{ success: boolean }> {
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

  /** GET /config — runtime configuration. */
  async getConfig(): Promise<ConfigResponse> {
    return request<ConfigResponse>('/config');
  },
};

/** Convenience: get the injected config without making a network call. */
export function getInjectedConfig(): PicConfig {
  return getConfig();
}

/** Convenience: check if the user is logged in from the injected config. */
export function isLoggedIn(): boolean {
  try {
    return getConfig().isLoggedIn;
  } catch {
    return false;
  }
}

/** Convenience: get the logged-in user's display name from the injected config. */
export function getLoggedInUserName(): string {
  try {
    return getConfig().userName;
  } catch {
    return '';
  }
}

/** Convenience: get the logged-in user's email from the injected config. */
export function getLoggedInUserEmail(): string {
  try {
    return getConfig().userEmail;
  } catch {
    return '';
  }
}

/** Type re-export for consumers. */
export type { AccountInfo, BusinessInfo, Council, PdfTemplate };
