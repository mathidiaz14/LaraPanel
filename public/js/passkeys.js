(function () {
    function fromBase64Url(value) {
        const base64 = String(value).replace(/-/g, '+').replace(/_/g, '/');
        const padded = base64 + '='.repeat((4 - (base64.length % 4)) % 4);
        const binary = atob(padded);
        const bytes = new Uint8Array(binary.length);
        for (let i = 0; i < binary.length; i++) {
            bytes[i] = binary.charCodeAt(i);
        }
        return bytes.buffer;
    }

    function toBase64Url(buffer) {
        const bytes = new Uint8Array(buffer);
        let binary = '';
        for (let i = 0; i < bytes.length; i += 0x8000) {
            binary += String.fromCharCode.apply(null, bytes.subarray(i, i + 0x8000));
        }
        return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    }

    function decodeCreationOptions(options) {
        return {
            ...options,
            challenge: fromBase64Url(options.challenge),
            user: {
                ...options.user,
                id: fromBase64Url(options.user.id),
            },
            excludeCredentials: (options.excludeCredentials || []).map((item) => ({
                ...item,
                id: fromBase64Url(item.id),
            })),
        };
    }

    function decodeRequestOptions(options) {
        return {
            ...options,
            challenge: fromBase64Url(options.challenge),
            allowCredentials: (options.allowCredentials || []).map((item) => ({
                ...item,
                id: fromBase64Url(item.id),
            })),
        };
    }

    function serializeCredential(credential) {
        const source = credential.response || {};
        const response = {};

        ['clientDataJSON', 'attestationObject', 'authenticatorData', 'signature', 'userHandle'].forEach((key) => {
            if (source[key] !== null && source[key] !== undefined) {
                response[key] = toBase64Url(source[key]);
            }
        });

        return {
            id: credential.id,
            rawId: toBase64Url(credential.rawId),
            type: credential.type,
            response,
            clientExtensionResults: credential.getClientExtensionResults
                ? credential.getClientExtensionResults()
                : {},
        };
    }

    function errorMessage(data, fallback) {
        if (data && data.message) return data.message;
        if (data && data.errors) return Object.values(data.errors).flat().join(' ');
        return fallback;
    }

    async function request(url, options, fallbackMessage) {
        const response = await fetch(url, {
            ...options,
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(options.headers || {}),
            },
        });

        const text = await response.text();
        let data = null;
        try {
            data = text ? JSON.parse(text) : null;
        } catch (error) {
            data = null;
        }

        if (!response.ok) {
            throw new Error(errorMessage(data, fallbackMessage));
        }

        return { data, response, text };
    }

    function assertSupported() {
        if (!window.PublicKeyCredential || !navigator.credentials) {
            throw new Error('Este navegador no admite passkeys (WebAuthn).');
        }
    }

    async function register(config) {
        assertSupported();

        const { data } = await request(config.optionsUrl, {
            method: 'GET',
            headers: { 'X-CSRF-TOKEN': config.csrf },
        }, 'No se pudieron obtener las opciones de registro.');

        const credential = await navigator.credentials.create({
            publicKey: decodeCreationOptions(data.options),
        });

        if (!credential) {
            throw new Error('No se creó la passkey.');
        }

        await request(config.storeUrl, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': config.csrf },
            body: JSON.stringify({
                name: config.name || 'Passkey',
                credential: serializeCredential(credential),
            }),
        }, 'No se pudo guardar la passkey.');

        window.dispatchEvent(new CustomEvent('larapanel:passkey-registered'));
    }

    async function login(config) {
        assertSupported();

        const { data } = await request(config.optionsUrl, {
            method: 'GET',
            headers: { 'X-CSRF-TOKEN': config.csrf },
        }, 'No se pudieron obtener las opciones de acceso.');

        const credential = await navigator.credentials.get({
            publicKey: decodeRequestOptions(data.options),
        });

        if (!credential) {
            throw new Error('No se pudo verificar la passkey.');
        }

        await request(config.loginUrl, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': config.csrf },
            body: JSON.stringify({
                credential: serializeCredential(credential),
                remember: Boolean(config.remember),
            }),
        }, 'No se pudo iniciar sesión con la passkey.');

        window.dispatchEvent(new CustomEvent('larapanel:passkey-login'));
    }

    async function remove(config) {
        await request(config.url, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': config.csrf },
        }, 'No se pudo eliminar la passkey.');

        window.dispatchEvent(new CustomEvent('larapanel:passkey-removed'));
    }

    window.LaraPanelPasskeys = {
        register,
        login,
        remove,
        supported: Boolean(window.PublicKeyCredential && navigator.credentials),
    };
})();
