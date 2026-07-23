# Planning Index Checkout

WordPress plugin providing a React-based multi-step checkout for Planning Index subscriptions with Paid Memberships Pro and Stripe.

## Structure

- `planningindex-checkout/` — The deployable WordPress plugin
  - `planningindex-checkout.php` — Main plugin file (entry point)
  - `src/` — PHP source (controllers, hooks, asset enqueuing, admin settings)
  - `react/` — React + Vite + TypeScript frontend source
  - `build/` — Compiled production assets (JS, CSS, manifest)
  - `assets/` — PHP-side fallback assets
- `pmpro-per-council/` — Legacy plugin (v2.2.0) being replaced

## Building the React frontend

```bash
cd planningindex-checkout/react
npm install
npm run build
```

The build outputs to `planningindex-checkout/build/` with a Vite manifest at `build/.vite/manifest.json`.

## Deployment

1. Zip the `planningindex-checkout/` directory
2. Upload to SiteGround via WordPress admin (Plugins > Add New > Upload Plugin)
3. Activate the plugin
4. Configure the per-council membership level under the Planning Index settings page
5. Ensure PMPro and the Stripe gateway are installed and configured
