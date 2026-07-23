import { useState, useCallback, type ChangeEvent } from 'react';
import {
  ArrowRight,
  ArrowLeft,
  Eye,
  EyeOff,
  X,
  Check,
  Loader2,
  User,
  Lock,
  Mail,
} from 'lucide-react';
import { useCheckout } from '@/context/CheckoutContext';
import { api, isLoggedIn as isUserLoggedIn, getLoggedInUserName, getLoggedInUserEmail } from '@/lib/api';
import { PriceSummary } from '@/components/PriceSummary';

type Mode = 'signup' | 'login';

interface PasswordRules {
  length: boolean;
  uppercase: boolean;
  number: boolean;
  symbol: boolean;
}

function validatePassword(password: string): PasswordRules {
  return {
    length: password.length >= 8,
    uppercase: /[A-Z]/.test(password),
    number: /[0-9]/.test(password),
    symbol: /[^A-Za-z0-9]/.test(password),
  };
}

function validateEmail(email: string): boolean {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

export function AccountCreation() {
  const { setStep, setAccountInfo, canProceedFromStep } = useCheckout();
  const [mode, setMode] = useState<Mode>('signup');

  // Signup state
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [email, setEmail] = useState('');
  const [confirmEmail, setConfirmEmail] = useState('');
  const [fullName, setFullName] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // Login state
  const [loginIdentifier, setLoginIdentifier] = useState('');
  const [loginPassword, setLoginPassword] = useState('');

  const passwordRules = validatePassword(password);
  const passwordsMatch = password !== '' && password === confirmPassword;
  const emailsMatch = email !== '' && email === confirmEmail;
  const emailValid = validateEmail(email);
  const usernameValid = username.length >= 3;
  const fullNameValid = fullName.trim().length >= 2;

  const allRulesMet = Object.values(passwordRules).every(Boolean);
  const canSubmit =
    usernameValid &&
    allRulesMet &&
    passwordsMatch &&
    emailValid &&
    emailsMatch &&
    fullNameValid;

  const handleSignup = async () => {
    setError(null);
    setLoading(true);

    try {
      const result = await api.checkUser(username, email);
      if (!result.valid) {
        const msgs = Object.values(result.errors).filter(Boolean);
        throw new Error(msgs.length > 0 ? msgs.join(' ') : 'Validation failed');
      }

      await api.saveSession(3, { username, password, email });

      setAccountInfo({ username, email, fullName });
      setStep(4);
    } catch (err) {
      const message = err instanceof Error ? err.message : 'An error occurred during sign up';
      setError(message);
    } finally {
      setLoading(false);
    }
  };

  const handleLogin = async () => {
    setError(null);
    setLoading(true);

    try {
      const result = await api.login(loginIdentifier, loginPassword);

      setAccountInfo({
        username: result.username || loginIdentifier,
        email: result.email || '',
        fullName: result.fullName || '',
      });
      setStep(4);
    } catch (err) {
      const message = err instanceof Error ? err.message : 'An error occurred during login';
      setError(message);
    } finally {
      setLoading(false);
    }
  };

  const handleFieldChange = useCallback(
    (setter: (v: string) => void) => (e: ChangeEvent<HTMLInputElement>) => {
      setter(e.target.value);
      setError(null);
    },
    []
  );

  if (mode === 'login') {
    return (
      <div className="grid grid-cols-1 gap-8 lg:grid-cols-[1fr_340px]">
        <div className="mx-auto w-full max-w-md">
          <div className="mb-6">
            <h1 className="font-display text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
              Welcome Back
            </h1>
            <p className="mt-2 text-sm text-slate-500 sm:text-base">
              Log in to your account to continue with your subscription.
            </p>
          </div>

          <div className="card p-6">
            {error && (
              <div className="mb-4 rounded-lg bg-error-50 px-4 py-3 text-sm text-error-700 ring-1 ring-inset ring-error-200">
                {error}
              </div>
            )}

            <div className="space-y-4">
              <div>
                <label className="mb-1.5 block text-sm font-semibold text-slate-700">
                  Username or Email
                </label>
                <div className="relative">
                  <User className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                  <input
                    type="text"
                    value={loginIdentifier}
                    onChange={handleFieldChange(setLoginIdentifier)}
                    className="input-field pl-10"
                    placeholder="your.username or you@example.com"
                    autoComplete="username"
                  />
                </div>
              </div>

              <div>
                <label className="mb-1.5 block text-sm font-semibold text-slate-700">Password</label>
                <div className="relative">
                  <Lock className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                  <input
                    type={showPassword ? 'text' : 'password'}
                    value={loginPassword}
                    onChange={handleFieldChange(setLoginPassword)}
                    className="input-field px-10"
                    placeholder="Enter your password"
                    autoComplete="current-password"
                  />
                  <button
                    type="button"
                    onClick={() => setShowPassword(!showPassword)}
                    className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                    aria-label={showPassword ? 'Hide password' : 'Show password'}
                  >
                    {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                  </button>
                </div>
              </div>
            </div>

            <button
              onClick={handleLogin}
              disabled={loading || !loginIdentifier || !loginPassword}
              className="btn-primary mt-6 w-full"
            >
              {loading ? (
                <>
                  <Loader2 className="h-4 w-4 animate-spin" />
                  Logging in...
                </>
              ) : (
                <>
                  Log in & Continue
                  <ArrowRight className="h-4 w-4" />
                </>
              )}
            </button>

            <p className="mt-4 text-center text-sm text-slate-500">
              Don't have an account?{' '}
              <button
                onClick={() => {
                  setMode('signup');
                  setError(null);
                }}
                className="font-semibold text-brand-600 hover:text-brand-700"
              >
                Create one here
              </button>
            </p>
          </div>
        </div>

        <div>
          <PriceSummary />
        </div>

        <div className="flex items-center justify-between lg:col-span-2">
          <button onClick={() => setStep(2)} className="btn-ghost">
            <ArrowLeft className="h-4 w-4" />
            Back to Templates
          </button>
        </div>
      </div>
    );
  }

  return (
    <div className="grid grid-cols-1 gap-8 lg:grid-cols-[1fr_340px]">
      <div className="mx-auto w-full max-w-lg">
        <div className="mb-6">
          <p className="mb-1 text-sm font-semibold uppercase tracking-wide text-brand-600">
            Step 3 of 4
          </p>
          <h1 className="font-display text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
            Create Your Account
          </h1>
          <p className="mt-2 text-sm text-slate-500 sm:text-base">
            Set up your account to access your planning applications dashboard and manage your
            subscription.
          </p>
        </div>

        <div className="card p-6">
          {error && (
            <div className="mb-4 rounded-lg bg-error-50 px-4 py-3 text-sm text-error-700 ring-1 ring-inset ring-error-200">
              {error}
            </div>
          )}

          <div className="space-y-5">
            <div>
              <label className="mb-1.5 block text-sm font-semibold text-slate-700">
                Username <span className="text-error-500">*</span>
              </label>
              <div className="relative">
                <User className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input
                  type="text"
                  value={username}
                  onChange={handleFieldChange(setUsername)}
                  className={`input-field pl-10 ${
                    username && !usernameValid ? 'ring-error-300 focus:ring-error-500' : ''
                  }`}
                  placeholder="Choose a username (3+ characters)"
                  autoComplete="username"
                />
              </div>
              {username && !usernameValid && (
                <p className="mt-1 text-xs text-error-500">Username must be at least 3 characters</p>
              )}
            </div>

            <div>
              <label className="mb-1.5 block text-sm font-semibold text-slate-700">
                Password <span className="text-error-500">*</span>
              </label>
              <div className="relative">
                <Lock className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input
                  type={showPassword ? 'text' : 'password'}
                  value={password}
                  onChange={handleFieldChange(setPassword)}
                  className={`input-field px-10 ${
                    password && !allRulesMet ? 'ring-error-300 focus:ring-error-500' : ''
                  }`}
                  placeholder="Create a password"
                  autoComplete="new-password"
                />
                <button
                  type="button"
                  onClick={() => setShowPassword(!showPassword)}
                  className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                  aria-label={showPassword ? 'Hide password' : 'Show password'}
                >
                  {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                </button>
              </div>

              <div className="mt-2 grid grid-cols-2 gap-1.5">
                <PasswordRule label="8+ characters" met={passwordRules.length} />
                <PasswordRule label="Uppercase letter" met={passwordRules.uppercase} />
                <PasswordRule label="Number" met={passwordRules.number} />
                <PasswordRule label="Symbol" met={passwordRules.symbol} />
              </div>
            </div>

            <div>
              <label className="mb-1.5 block text-sm font-semibold text-slate-700">
                Confirm Password <span className="text-error-500">*</span>
              </label>
              <div className="relative">
                <Lock className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input
                  type={showPassword ? 'text' : 'password'}
                  value={confirmPassword}
                  onChange={handleFieldChange(setConfirmPassword)}
                  className={`input-field pl-10 ${
                    confirmPassword && !passwordsMatch ? 'ring-error-300 focus:ring-error-500' : ''
                  }`}
                  placeholder="Re-enter your password"
                  autoComplete="new-password"
                />
              </div>
              {confirmPassword && !passwordsMatch && (
                <p className="mt-1 text-xs text-error-500">Passwords do not match</p>
              )}
            </div>

            <div>
              <label className="mb-1.5 block text-sm font-semibold text-slate-700">
                Email Address <span className="text-error-500">*</span>
              </label>
              <div className="relative">
                <Mail className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input
                  type="email"
                  value={email}
                  onChange={handleFieldChange(setEmail)}
                  className={`input-field pl-10 ${
                    email && !emailValid ? 'ring-error-300 focus:ring-error-500' : ''
                  }`}
                  placeholder="you@example.com"
                  autoComplete="email"
                />
              </div>
              {email && !emailValid && (
                <p className="mt-1 text-xs text-error-500">Please enter a valid email address</p>
              )}
            </div>

            <div>
              <label className="mb-1.5 block text-sm font-semibold text-slate-700">
                Confirm Email <span className="text-error-500">*</span>
              </label>
              <div className="relative">
                <Mail className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input
                  type="email"
                  value={confirmEmail}
                  onChange={handleFieldChange(setConfirmEmail)}
                  className={`input-field pl-10 ${
                    confirmEmail && !emailsMatch ? 'ring-error-300 focus:ring-error-500' : ''
                  }`}
                  placeholder="Re-enter your email"
                  autoComplete="email"
                />
              </div>
              {confirmEmail && !emailsMatch && (
                <p className="mt-1 text-xs text-error-500">Emails do not match</p>
              )}
            </div>

            <div>
              <label className="mb-1.5 block text-sm font-semibold text-slate-700">Full Name</label>
              <div className="relative">
                <User className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input
                  type="text"
                  value={fullName}
                  onChange={handleFieldChange(setFullName)}
                  className="input-field pl-10"
                  placeholder="Your full name"
                  autoComplete="name"
                />
              </div>
            </div>
          </div>

          <button
            onClick={handleSignup}
            disabled={loading || !canSubmit}
            className="btn-primary mt-6 w-full"
          >
            {loading ? (
              <>
                <Loader2 className="h-4 w-4 animate-spin" />
                Creating account...
              </>
            ) : (
              <>
                Continue to Payment
                <ArrowRight className="h-4 w-4" />
              </>
            )}
          </button>

          <p className="mt-4 text-center text-sm text-slate-500">
            Already have an account?{' '}
            <button
              onClick={() => {
                setMode('login');
                setError(null);
              }}
              className="font-semibold text-brand-600 hover:text-brand-700"
            >
              Log in here
            </button>
          </p>
        </div>
      </div>

      <div>
        <PriceSummary />
      </div>

      <div className="flex items-center justify-between lg:col-span-2">
        <button onClick={() => setStep(2)} className="btn-ghost">
          <ArrowLeft className="h-4 w-4" />
          Back to Templates
        </button>
      </div>
    </div>
  );
}

function PasswordRule({ label, met }: { label: string; met: boolean }) {
  return (
    <div className="flex items-center gap-1.5">
      <div
        className={`flex h-4 w-4 items-center justify-center rounded-full ${
          met ? 'bg-success-500 text-white' : 'bg-slate-200 text-slate-400'
        }`}
      >
        {met ? <Check className="h-2.5 w-2.5" /> : <X className="h-2.5 w-2.5" />}
      </div>
      <span className={`text-xs ${met ? 'text-slate-600' : 'text-slate-400'}`}>{label}</span>
    </div>
  );
}
