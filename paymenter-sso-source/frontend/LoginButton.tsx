import { useEffect, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Alert, Button, Spinner, httpErrorToHuman, useOpenLoginCheckpoint, type RouteSlotData } from '@pterodactyl/sdk';
import { getState, leaveFor, startSignIn, stateQueryKey } from './api';

/** The login page's "redirect" value (where the visitor was going), passed on for after the sign-in. */
function redirectOf(search: unknown): string | undefined {
    const value = typeof search === 'object' && search !== null ? (search as Record<string, unknown>).redirect : undefined;

    return typeof value === 'string' && value !== '' ? value : undefined;
}

/** Below the login form: the message of the last attempt, an "or" divider and the Paymenter button. */
export default function LoginButton({ data }: { data: RouteSlotData }) {
    const state = useQuery({ queryKey: stateQueryKey, queryFn: getState, retry: false, staleTime: 0, gcTime: 0 });
    const openCheckpoint = useOpenLoginCheckpoint();
    const [starting, setStarting] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const checkpoint = state.data?.checkpoint ?? null;

    useEffect(() => {
        if (checkpoint !== null) void openCheckpoint(checkpoint);
    }, [checkpoint, openCheckpoint]);

    if (checkpoint !== null) {
        return (
            <div className={'pmsso:mt-6 pmsso:flex pmsso:justify-center'}>
                <Spinner size={'small'} />
            </div>
        );
    }

    const label = state.data?.button ?? null;
    const notice = error ?? state.data?.notice ?? null;
    if (label === null && notice === null) return null;

    const start = async () => {
        setStarting(true);
        setError(null);
        try {
            leaveFor(await startSignIn(redirectOf(data.search)));
        } catch (exception) {
            setError(httpErrorToHuman(exception));
            setStarting(false);
        }
    };

    return (
        <div className={'pmsso:mt-6 pmsso:flex pmsso:flex-col pmsso:gap-4'}>
            {notice !== null && <Alert type={'danger'}>{notice}</Alert>}
            {label !== null && (
                <>
                    <div
                        className={
                            'pmsso:flex pmsso:items-center pmsso:gap-3 pmsso:text-xs pmsso:uppercase pmsso:tracking-wide pmsso:text-muted-foreground'
                        }
                    >
                        <span className={'pmsso:h-px pmsso:flex-1 pmsso:bg-border'} />
                        or
                        <span className={'pmsso:h-px pmsso:flex-1 pmsso:bg-border'} />
                    </div>
                    <Button size={'xlarge'} isSecondary isLoading={starting} onClick={start} className={'pmsso:w-full'}>
                        {label}
                    </Button>
                </>
            )}
        </div>
    );
}
