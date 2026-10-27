import type { ApiResponse } from '~/types/api'

const isRecord = (value: unknown): value is Record<string, unknown> =>
    value !== null && typeof value === 'object' && !Array.isArray(value)

export const getApiErrorMessage = (
    error: unknown,
    fallback: string,
): string => {
    if (!isRecord(error) || !isRecord(error.data)) {
        return fallback
    }

    return typeof error.data.message === 'string' && error.data.message
        ? error.data.message
        : fallback
}

const isLoopbackHostname = (hostname: string): boolean =>
    ['localhost', '127.0.0.1', '[::1]', '::1'].includes(hostname.toLowerCase())

const isLoopbackApiBaseUrl = (baseUrl: string): boolean => {
    try {
        return isLoopbackHostname(new URL(baseUrl, 'http://localhost').hostname)
    } catch {
        return false
    }
}

const validateApiResponse = <T>(response: unknown): ApiResponse<T> => {
    if (
        !isRecord(response) ||
        typeof response.success !== 'boolean' ||
        typeof response.message !== 'string'
    ) {
        throw new Error('Respons API tidak valid.')
    }

    if (!response.success) {
        throw new Error(response.message || 'Permintaan API gagal.')
    }

    if (!Object.prototype.hasOwnProperty.call(response, 'data')) {
        throw new Error('Respons API tidak berisi data.')
    }

    return {
        success: true,
        message: response.message,
        data: response.data as T,
    }
}

export const getApiBaseUrl = (): string => {
    let configuredBaseUrl = process.env.NUXT_PUBLIC_API_BASE_URL?.trim()
    try {
        const config = useRuntimeConfig()
        if (config?.public?.apiBaseUrl) {
            configuredBaseUrl = (config.public.apiBaseUrl as string).trim()
        }
    } catch {
        // outside nuxt context
    }

    if (import.meta.client) {
        const hostname = window.location.hostname
        if (
            configuredBaseUrl
            && (!isLoopbackApiBaseUrl(configuredBaseUrl) || isLoopbackHostname(hostname))
        ) {
            return configuredBaseUrl
        }
        if (hostname) {
            return `${window.location.origin}/api`
        }
    }

    if (import.meta.server) {
        try {
            const reqUrl = useRequestURL()
            if (
                configuredBaseUrl
                && (!isLoopbackApiBaseUrl(configuredBaseUrl) || isLoopbackHostname(reqUrl.hostname))
            ) {
                return configuredBaseUrl
            }
            if (reqUrl.hostname) {
                return `${reqUrl.origin}/api`
            }
        } catch {
            // outside request context
        }
    }

    if (configuredBaseUrl) {
        return configuredBaseUrl
    }

    const config = useRuntimeConfig()
    return (config.public.apiBaseUrl as string) || 'http://127.0.0.1/api'
}

export const useApi = () => {
    const apiFetch = <T>(
        path: string,
        options: Parameters<typeof $fetch<T>>[1] = {},
    ) => {
        return $fetch<unknown>(
            path,
            {
                baseURL: getApiBaseUrl(),
                ...options,
            },
        ).then((response) => validateApiResponse<T>(response))
    }

    return {
        apiFetch,
    }
}
