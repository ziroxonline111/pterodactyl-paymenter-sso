import { http } from '@pterodactyl/sdk';

const BASE = '/extensions/paymenter-sso';

export interface SignInState {
    /** The button's label, or null when the extension is not set up (or someone is signed in already). */
    button: string | null;
    /** What happened to the last attempt in this browser, once. */
    notice: string | null;
    /** The panel's two-factor checkpoint token, after Paymenter vouched for an account that uses two-factor. */
    checkpoint: string | null;
}

export const stateQueryKey = ['extensions', 'paymenter-sso', 'state'] as const;

export async function getState(): Promise<SignInState> {
    return (await http.get<{ data: SignInState }>(`${BASE}/state`)).data.data;
}

/** Starts a sign-in and returns Paymenter's authorization URL. */
export async function startSignIn(redirect: string | undefined): Promise<string> {
    return (await http.post<{ data: { url: string } }>(`${BASE}/start`, { redirect })).data.data.url;
}

/** A full page load, so the browser leaves the panel for Paymenter. */
export function leaveFor(url: string): void {
    window.location.assign(url);
}
