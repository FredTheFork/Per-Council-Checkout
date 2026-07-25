export function Footer() {
  return (
    <footer className="pi-hp-footer bg-brand-600 text-white">
      <div className="pi-hp-footer-inner mx-auto max-w-6xl px-6 py-12">
        <div className="grid grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-4">
          <div className="pi-hp-footer-brand">
            <h3 className="font-display text-xl font-bold text-white">PlanningIndex</h3>
            <p className="mt-2 text-sm text-brand-100">
              The UK's most affordable planning application platform for construction professionals.
            </p>
            <div className="pi-hp-footer-social mt-4 flex items-center gap-3">
              <a
                href="mailto:hello@planningindex.co.uk"
                aria-label="Email"
                className="flex h-9 w-9 items-center justify-center rounded-lg bg-brand-500 text-white transition-colors hover:bg-brand-400"
              >
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                  <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
                  <polyline points="22,6 12,13 2,6" />
                </svg>
              </a>
              <a
                href="tel:01702680801"
                aria-label="Phone"
                className="flex h-9 w-9 items-center justify-center rounded-lg bg-brand-500 text-white transition-colors hover:bg-brand-400"
              >
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                  <path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 16.92z" />
                </svg>
              </a>
            </div>
          </div>

          <div className="pi-hp-footer-links grid grid-cols-1 gap-8 sm:grid-cols-3 lg:col-span-3">
            <div className="pi-hp-footer-col">
              <h4 className="font-display text-sm font-bold uppercase tracking-wide text-white">Platform</h4>
              <div className="mt-3 flex flex-col gap-2">
                <a href="https://planningindex.co.uk/membership-levels/" className="text-sm text-brand-100 hover:text-white">Pricing</a>
                <a href="https://planningindex.co.uk/about/" className="text-sm text-brand-100 hover:text-white">About</a>
                <a href="https://planningindex.co.uk/features/" className="text-sm text-brand-100 hover:text-white">Features</a>
                <a href="https://planningindex.co.uk/blog/" className="text-sm text-brand-100 hover:text-white">Blog</a>
                <a href="https://planningindex.co.uk/help-centre/" className="text-sm text-brand-100 hover:text-white">Help Centre</a>
              </div>
            </div>
            <div className="pi-hp-footer-col">
              <h4 className="font-display text-sm font-bold uppercase tracking-wide text-white">Account</h4>
              <div className="mt-3 flex flex-col gap-2">
                <a href="https://planningindex.co.uk/login/" className="text-sm text-brand-100 hover:text-white">Sign In</a>
                <a href="https://planningindex.co.uk/membership-checkout/?pmpro_level=63" className="text-sm text-brand-100 hover:text-white">Free Trial</a>
                <a href="https://planningindex.co.uk/membership-account/" className="text-sm text-brand-100 hover:text-white">My Account</a>
              </div>
            </div>
            <div className="pi-hp-footer-col">
              <h4 className="font-display text-sm font-bold uppercase tracking-wide text-white">Legal</h4>
              <div className="mt-3 flex flex-col gap-2">
                <a href="https://planningindex.co.uk/privacy-policy/" className="text-sm text-brand-100 hover:text-white">Privacy Policy</a>
                <a href="https://planningindex.co.uk/terms-and-conditions/" className="text-sm text-brand-100 hover:text-white">Terms &amp; Conditions</a>
                <a href="https://planningindex.co.uk/contact/" className="text-sm text-brand-100 hover:text-white">Contact</a>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div className="pi-hp-footer-bottom border-t border-brand-500/40">
        <div className="mx-auto max-w-6xl px-6 py-4">
          <p className="text-center text-xs text-brand-200">© 2026 PlanningIndex. All rights reserved.</p>
        </div>
      </div>
    </footer>
  );
}
