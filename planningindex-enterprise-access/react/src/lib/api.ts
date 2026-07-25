import type { PdfTemplate, BusinessInfo, AccountInfo, TeamSeats } from '@/types';
import { templates as fallbackTemplates } from '@/data/templates';

interface PieConfig {
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
  levelId: number;
  gateway: string;
  requireBilling: boolean;
  enterprisePrice: number;
  totalSteps: number;
  strings: Record<string, string>;
}

const DEV_CONFIG: PieConfig = {
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
  levelId: 60,
  gateway: 'stripe',
  requireBilling: true,
  enterprisePrice: 199,
  totalSteps: 4,
  strings: {},
};

function getConfig(): PieConfig {
  const cfg = (window as unknown as { PlanningIndexEnterpriseAccess?: PieConfig }).PlanningIndexEnterpriseAccess;
  if (!cfg || !cfg.apiBase || !cfg.nonce) {
    console.warn('[PIE] No valid window.PlanningIndexEnterpriseAccess config found, using dev fallback.');
    const params = new URLSearchParams(window.location.search);
    const urlLevel = parseInt(params.get('level') || params.get('pmpro_level') || '0', 10);
    if (urlLevel > 0) {
      return { ...DEV_CONFIG, levelId: urlLevel };
    }
    return DEV_CONFIG;
  }
  return cfg;
}

function isDevMode(): boolean {
  if (typeof window !== 'undefined') {
    const host = window.location.hostname || '';
    if (host === 'localhost' || host === '127.0.0.1' || host === '0.0.0.0') {
      const cfg = (window as unknown as { PlanningIndexEnterpriseAccess?: PieConfig }).PlanningIndexEnterpriseAccess;
      if (!cfg || !cfg.apiBase || !cfg.nonce) {
        return true;
      }
    }
  }
  return false;
}

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
      else if (err.data && err.data.message) message = err.data.message;
    } catch {
      // response wasn't JSON
    }
    throw new Error(message);
  }

  const json = await res.json() as T;
  if (json && typeof json === 'object' && 'success' in json && (json as { success: boolean }).success === false) {
    const msg = (json as { message?: string }).message || 'Request failed';
    throw new Error(msg);
  }
  return json;
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
  template?: string;
  business?: Record<string, string>;
  team_seats?: number;
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
  selectedTemplateId: string | null;
  monthlyCost: number;
  totalDueToday: number;
  teamSeats: number;
  isTeamOwner: boolean;
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
  selectedTemplateId?: string | null;
  teamSeats?: number;
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
  levelId: number;
  gateway: string;
  requireBilling: boolean;
  checkoutUrl: string;
  ajaxUrl: string;
  totalSteps: number;
  isLoggedIn: boolean;
  enterprisePrice: number;
}

interface VerifyPriceResponse {
  success: boolean;
  councilCount: number;
  monthlyCost: number;
  totalDueToday: number;
}

export const api = {
  async getTemplates(): Promise<TemplatesResponse> {
    if (isDevMode()) {
      return {
        templates: fallbackTemplates,
        userCurrentTemplate: null,
      };
    }
    return request<TemplatesResponse>('/templates');
  },

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

  async getSession(): Promise<SessionResponse> {
    if (isDevMode()) {
      return { data: {} };
    }
    return request<SessionResponse>('/session');
  },

  async saveSession(
    step: number,
    payload: {
      template?: string;
      business?: Record<string, string>;
      team_seats?: number;
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

  async clearSession(): Promise<{ success: boolean }> {
    if (isDevMode()) {
      return { success: true };
    }
    return request('/session', { method: 'DELETE' });
  },

  async getProfile(): Promise<ProfileResponse> {
    if (isDevMode()) {
      throw new Error('Not available in dev mode');
    }
    return request<ProfileResponse>('/profile');
  },

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
          selectedTemplateId: null,
          teamSeats: 1,
        };
      }
      return { success: false, message: 'Invalid login details. Use demo/password for dev mode.' };
    }
    return request<LoginResponse>('/login', {
      method: 'POST',
      body: { login: identifier, password },
    });
  },

  async submitCheckout(): Promise<CheckoutResponse> {
    if (isDevMode()) {
      return {
        success: true,
        orderCode: 'PIE-DEV' + Math.random().toString(36).substring(2, 8).toUpperCase(),
        orderDate: new Date().toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' }),
        planName: 'Planning Index Enterprise Access',
        councilCount: 380,
        monthlyCost: 199,
        totalDueToday: 199,
      };
    }
    return request<CheckoutResponse>('/checkout', { method: 'POST' });
  },

  async createStripeSession(payload: {
    templateId: string;
    teamSeats: TeamSeats;
    businessInfo: BusinessInfo;
    accountInfo: AccountInfo | null;
  }): Promise<StripeSessionResponse> {
    if (isDevMode()) {
      return { success: true, stripeUrl: '', sessionId: 'dev' };
    }
    const cfg = (window as unknown as { PlanningIndexEnterpriseAccess?: PieConfig }).PlanningIndexEnterpriseAccess;
    if (!cfg || !cfg.apiBase || !cfg.nonce) {
      throw new Error('Checkout configuration failed to load. Please refresh the page and try again, or contact support if the problem persists.');
    }
    return request<StripeSessionResponse>('/stripe-session', {
      method: 'POST',
      body: payload,
    });
  },

  async verifyPrice(): Promise<VerifyPriceResponse> {
    if (isDevMode()) {
      return {
        success: true,
        councilCount: 380,
        monthlyCost: 199,
        totalDueToday: 199,
      };
    }
    return request<VerifyPriceResponse>('/checkout/verify-price');
  },

  async getConfig(): Promise<ConfigResponse> {
    if (isDevMode()) {
      return {
        levelId: 60,
        gateway: 'stripe',
        requireBilling: true,
        checkoutUrl: '',
        ajaxUrl: '',
        totalSteps: 4,
        isLoggedIn: false,
        enterprisePrice: 199,
      };
    }
    return request<ConfigResponse>('/config');
  },
};

export function getInjectedConfig(): PieConfig {
  return getConfig();
}

export function isLoggedIn(): boolean {
  return getConfig().isLoggedIn;
}

export function getLoggedInUserName(): string {
  return getConfig().userName;
}

export function getLoggedInUserEmail(): string {
  return getConfig().userEmail;
}

export function getEnterprisePrice(): number {
  return getConfig().enterprisePrice;
}

export type { AccountInfo, BusinessInfo, PdfTemplate, TeamSeats };
