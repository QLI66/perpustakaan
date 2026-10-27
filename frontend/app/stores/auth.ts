import { FetchError } from 'ofetch'
import { defineStore } from 'pinia'

export interface AuthUser {
    id: number
    name: string
    email: string
    username?: string
    roles: string[]
    permissions: string[]
    must_change_password?: boolean
}

interface AuthState {
    token: string | null
    user: AuthUser | null
}

export const useAuthStore = defineStore('auth', {
    state: (): AuthState => ({
        token: null,
        user: null,
    }),

    getters: {
        isAuthenticated: (state): boolean => {
            return Boolean(state.token)
        },

        roles: (state): string[] => {
            return state.user?.roles ?? []
        },

        permissions: (state): string[] => {
            return state.user?.permissions ?? []
        },
    },

    actions: {
        hasPermission(permission: string): boolean {
            return this.user?.permissions.includes(permission) ?? false
        },

        hasRole(role: string): boolean {
            return this.user?.roles.includes(role) ?? false
        },

        async hydrate(): Promise<void> {
            // On the server, read the token from incoming request headers
            // (useRequestHeaders gives access to the browser's Cookie header
            // in the SSR context). On the client, fall back to useCookie.
            let tokenFromCookie: string | null

            if (import.meta.server) {
                const headers = useRequestHeaders(['cookie'])
                const cookieHeader = headers.cookie ?? ''
                const match = cookieHeader.match(/(?:^|;\s*)library_auth_token=([^;]+)/)
                tokenFromCookie = match ? decodeURIComponent(match[1]!) : null
            }
            else {
                const cookie = useCookie<string | null>('library_auth_token', {
                    sameSite: 'lax',
                    maxAge: 60 * 60 * 24 * 7,
                })
                tokenFromCookie = cookie.value ?? null
            }

            // If the cookie token differs from state token (e.g. account switch,
            // token refresh, or new SSR request with a different cookie),
            // synchronize the token and invalidate stale user profile.
            if (tokenFromCookie !== this.token) {
                this.token = tokenFromCookie
                this.user = null
            }

            if (!this.token || this.user) {
                return
            }

            try {
                interface MeResponse {
                    success: boolean
                    message?: string
                    data: {
                        user: unknown
                    }
                }

                const fetchHeaders: Record<string, string> = {
                    Accept: 'application/json',
                    Authorization: `Bearer ${this.token}`,
                }

                // Forward the Cookie header on the server so Laravel session
                // middleware sees the authenticated request correctly.
                if (import.meta.server) {
                    const reqHeaders = useRequestHeaders(['cookie'])
                    if (reqHeaders.cookie) {
                        fetchHeaders.cookie = reqHeaders.cookie
                    }
                }

                const response = await $fetch<MeResponse>(
                    '/auth/me',
                    {
                        baseURL: getApiBaseUrl(),
                        method: 'GET',
                        headers: fetchHeaders,
                    },
                )

                const rawUser = response.data?.user

                const user =
                    rawUser &&
                    typeof rawUser === 'object'
                        ? (rawUser as Record<string, unknown>)
                        : {}

                const normalizeStringArray = (
                    value: unknown,
                ): string[] => {
                    if (!Array.isArray(value)) {
                        return []
                    }

                    return value
                        .map((item) => {
                            if (typeof item === 'string') {
                                return item
                            }

                            if (
                                item &&
                                typeof item === 'object' &&
                                'name' in item &&
                                typeof item.name === 'string'
                            ) {
                                return item.name
                            }

                            return ''
                        })
                        .filter(Boolean)
                }

                this.user = {
                    id: Number(user.id ?? 0),
                    name: String(user.name ?? ''),
                    email: String(user.email ?? ''),
                    username: user.username ? String(user.username) : undefined,
                    roles: normalizeStringArray(user.roles),
                    permissions: normalizeStringArray(
                        user.permissions,
                    ),
                    must_change_password: Boolean(user.must_change_password),
                }
            }
            catch (error: unknown) {
                // Only clear auth if the server explicitly rejected the token (401)
                if (
                    error instanceof FetchError &&
                    error.response?.status === 401
                ) {
                    this.token = null
                    this.user = null

                    if (!import.meta.server) {
                        const cookie = useCookie<string | null>('library_auth_token', {
                            sameSite: 'lax',
                        })
                        cookie.value = null
                    }
                }
            }
        },

        setAuth(
            token: string,
            user: AuthUser,
        ): void {
            this.token = token
            this.user = user

            const cookie = useCookie<string | null>(
                'library_auth_token',
                {
                    sameSite: 'lax',
                    maxAge: 60 * 60 * 24 * 7,
                },
            )

            cookie.value = token
        },

        clearAuth(): void {
            this.token = null
            this.user = null

            const cookie = useCookie<string | null>(
                'library_auth_token',
                {
                    sameSite: 'lax',
                },
            )

            cookie.value = null
        },
    },
})