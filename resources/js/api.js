// Calls the JSON API with the logged-in session cookie (Sanctum stateful auth).

function xsrfToken() {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
    return match ? decodeURIComponent(match[1]) : '';
}

export async function api(method, path, body) {
    const res = await fetch(`/api${path}`, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
        },
        body: body ? JSON.stringify(body) : undefined,
    });

    if (res.status === 204) return null;

    const data = await res.json().catch(() => ({}));

    if (!res.ok) {
        const error = new Error(data.message || res.statusText);
        error.status = res.status;
        error.errors = data.errors || {};
        throw error;
    }

    return data;
}

// First validation message, or the general error message.
export function errorText(error) {
    return Object.values(error.errors || {})[0]?.[0] || error.message;
}
