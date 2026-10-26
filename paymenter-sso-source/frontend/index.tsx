import './styles.css';
import { definePterodactylExtension } from '@pterodactyl/sdk';
import LoginButton from './LoginButton';

export default definePterodactylExtension({
    setup({ slots }) {
        slots.register('auth.login.form.after', LoginButton);
    },
});
