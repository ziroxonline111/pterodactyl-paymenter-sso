import { afterEach, expect, test, vi } from 'vitest';
import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { createExtensionTestHost, type ExtensionTestHost } from '@pterodactyl/sdk/testing';
import LoginButton from './LoginButton';
import { getState, leaveFor, startSignIn, type SignInState } from './api';

vi.mock('./api', async (importOriginal) => ({
    ...(await importOriginal<typeof import('./api')>()),
    getState: vi.fn(),
    startSignIn: vi.fn(),
    leaveFor: vi.fn(),
}));

let host: ExtensionTestHost | undefined;

afterEach(() => {
    cleanup();
    host?.dispose();
    host = undefined;
});

function renderButton(state: SignInState, search: Record<string, string> = {}) {
    vi.mocked(getState).mockResolvedValue(state);
    const query = new URLSearchParams(search).toString();
    host = createExtensionTestHost({ extensionId: 'paymenter-sso', prefix: 'pmsso', path: `/auth/login${query ? `?${query}` : ''}` });
    render(<LoginButton data={{ pathname: '/auth/login', search, params: {} }} />, { wrapper: host.Wrapper });
}

test('shows nothing while the extension is not set up', async () => {
    renderButton({ button: null, notice: null, checkpoint: null });

    await waitFor(() => expect(getState).toHaveBeenCalled());
    expect(screen.queryByRole('button')).toBeNull();
    expect(screen.queryByText('or')).toBeNull();
});

test('shows the button with the configured label', async () => {
    renderButton({ button: 'Sign in with Nordic Billing', notice: null, checkpoint: null });

    expect(await screen.findByRole('button', { name: 'Sign in with Nordic Billing' })).toBeTruthy();
    expect(screen.getByText('or')).toBeTruthy();
});

test('starts the sign-in with the page\'s redirect and leaves for Paymenter', async () => {
    vi.mocked(startSignIn).mockResolvedValue('https://billing.example.com/oauth/authorize?state=abc');
    renderButton({ button: 'Sign in with Paymenter', notice: null, checkpoint: null }, { redirect: '/server/1a2b3c4d' });

    fireEvent.click(await screen.findByRole('button', { name: 'Sign in with Paymenter' }));

    await waitFor(() => expect(leaveFor).toHaveBeenCalledWith('https://billing.example.com/oauth/authorize?state=abc'));
    expect(startSignIn).toHaveBeenCalledWith('/server/1a2b3c4d');
});

test('shows why the last attempt did not sign in, also when the button is hidden', async () => {
    renderButton({ button: null, notice: 'Administrator accounts cannot sign in with Paymenter on this panel.', checkpoint: null });

    expect(await screen.findByText('Administrator accounts cannot sign in with Paymenter on this panel.')).toBeTruthy();
    expect(screen.queryByRole('button')).toBeNull();
});

test('a failed start is shown and the button can be used again', async () => {
    vi.mocked(startSignIn).mockRejectedValue(new Error('Sign in with Paymenter is not available on this panel.'));
    renderButton({ button: 'Sign in with Paymenter', notice: null, checkpoint: null });

    fireEvent.click(await screen.findByRole('button', { name: 'Sign in with Paymenter' }));

    expect(await screen.findByText('Sign in with Paymenter is not available on this panel.')).toBeTruthy();
    expect(leaveFor).not.toHaveBeenCalled();
    expect((screen.getByRole('button', { name: 'Sign in with Paymenter' }) as HTMLButtonElement).disabled).toBe(false);
});

test('opens the panel\'s two-factor checkpoint with the token from the server', async () => {
    renderButton({ button: 'Sign in with Paymenter', notice: null, checkpoint: 'checkpoint-token' }, { redirect: '/server/1a2b3c4d' });

    await waitFor(() =>
        expect(host!.location()).toMatchObject({
            pathname: '/auth/login/checkpoint',
            search: { redirect: '/server/1a2b3c4d' },
            state: { token: 'checkpoint-token' },
        })
    );
});
