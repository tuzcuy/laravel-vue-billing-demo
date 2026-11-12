import axios from 'axios';

export const api = axios.create({
    baseURL: '/api',
    headers: { Accept: 'application/json' },
});

/** Flattens a Laravel 422 validation response into one readable string. */
export function errorMessage(error) {
    const data = error?.response?.data;
    if (data?.errors) {
        return Object.values(data.errors).flat().join(' ');
    }
    return data?.message ?? 'Something went wrong. Please try again.';
}
