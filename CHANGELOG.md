# Sign in with Paymenter for Pterodactyl 2.0: changelog

## 1.0.0 (2026-10-25)

- A **Sign in with Paymenter** button below the login form (label configurable), shown once the Paymenter URL, the
  client ID and the client secret are set.
- OAuth 2.0 authorization code flow against Paymenter's built-in OAuth server (`/oauth/authorize`,
  `/api/oauth/token`, `GET /api/me` with the scope `profile`), with state and PKCE (S256). Paymenter's authorization
  page is shown on every sign-in (`prompt=consent`), so customers who have to sign in to Paymenter first are not
  left on its login page.
- Customers are matched by the email address Paymenter has verified, and from then on by their Paymenter account id,
  so changing the address in Paymenter does not lock them out. Unverified addresses and addresses without a panel
  account are refused with a clear message; no panel accounts are created.
- Root administrators are refused unless the setting *Allow root administrators to sign in with Paymenter* is on.
- Two-factor authentication applies: accounts that use it get the panel's own checkpoint after Paymenter.
- After signing in, the customer goes to the page they were trying to open (only paths on the panel are accepted).
- The client secret is stored encrypted and never sent to a browser. The sign-in steps share the panel's sign-in rate
  limit, starting one needs the panel's CSRF token, and every sign-in and refusal is written to the activity log
  (`paymenter-sso:login`, `paymenter-sso:refused`).
