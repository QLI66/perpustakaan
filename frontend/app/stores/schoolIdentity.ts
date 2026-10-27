import { defineStore } from 'pinia'
import { getApiBaseUrl, useApi } from '~/composables/useApi'
import { useAuthStore } from '~/stores/auth'

export interface SchoolIdentityData {
    school_name: string
    theme_color: string
    has_custom_logo: boolean
    logo_url: string | null
    updated_at?: string | null
}

interface SchoolIdentityState {
    schoolName: string
    themeColor: string
    hasCustomLogo: boolean
    logoUrl: string | null
    isLoaded: boolean
    loading: boolean
}

const DEFAULT_SCHOOL_NAME = 'Perpustakaan Sekolah'
const DEFAULT_THEME_COLOR = '#1d4ed8'
const identityFetches = new WeakMap<object, Promise<void>>()

export const useSchoolIdentityStore = defineStore('schoolIdentity', {
    state: (): SchoolIdentityState => ({
        schoolName: DEFAULT_SCHOOL_NAME,
        themeColor: DEFAULT_THEME_COLOR,
        hasCustomLogo: false,
        logoUrl: null,
        isLoaded: false,
        loading: false,
    }),

    getters: {
        resolvedLogoUrl(): string | null {
            if (!this.hasCustomLogo) {
                return null
            }
            // Return logo API route with cache-busting timestamp
            const baseUrl = getApiBaseUrl()
            return `${baseUrl}/settings/school-identity/logo?t=${Date.now()}`
        },
    },

    actions: {
        async fetchIdentity(force = false): Promise<void> {
            const inFlight = identityFetches.get(this)
            if (inFlight) {
                await inFlight
                return
            }

            if (this.isLoaded && !force) {
                return
            }

            this.loading = true
            const request = (async () => {
                const { apiFetch } = useApi()

                try {
                    const response = await apiFetch<SchoolIdentityData>('/settings/school-identity', {
                        method: 'GET',
                    })

                    if (response?.data) {
                        this.schoolName = response.data.school_name || DEFAULT_SCHOOL_NAME
                        this.themeColor = response.data.theme_color || DEFAULT_THEME_COLOR
                        this.hasCustomLogo = Boolean(response.data.has_custom_logo)
                        this.logoUrl = response.data.logo_url
                        this.isLoaded = true
                    }
                } catch {
                    this.schoolName = DEFAULT_SCHOOL_NAME
                    this.themeColor = DEFAULT_THEME_COLOR
                    this.hasCustomLogo = false
                    this.logoUrl = null
                } finally {
                    this.loading = false
                }
            })()

            identityFetches.set(this, request)
            try {
                await request
            } finally {
                if (identityFetches.get(this) === request) {
                    identityFetches.delete(this)
                }
            }
        },

        async updateIdentity(payload: { school_name: string; theme_color: string }): Promise<void> {
            const { apiFetch } = useApi()
            const authStore = useAuthStore()

            const response = await apiFetch<SchoolIdentityData>('/settings/school-identity', {
                method: 'PUT',
                headers: {
                    Authorization: `Bearer ${authStore.token}`,
                },
                body: payload,
            })

            if (response?.data) {
                this.schoolName = response.data.school_name || DEFAULT_SCHOOL_NAME
                this.themeColor = response.data.theme_color || DEFAULT_THEME_COLOR
                this.hasCustomLogo = Boolean(response.data.has_custom_logo)
                this.logoUrl = response.data.logo_url
            }
        },

        async uploadLogo(file: File): Promise<void> {
            const authStore = useAuthStore()
            const baseUrl = getApiBaseUrl()

            const formData = new FormData()
            formData.append('logo', file)

            const res = await fetch(`${baseUrl}/settings/school-identity/logo`, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    Authorization: `Bearer ${authStore.token}`,
                },
                body: formData,
            })

            const json = await res.json()
            if (!res.ok || !json.success) {
                const errors = (json.errors || {}) as Record<string, string[]>
                const message = json.message || Object.values(errors)?.[0]?.[0] || 'Gagal mengunggah logo.'
                throw new Error(message)
            }

            if (json.data) {
                this.schoolName = json.data.school_name || DEFAULT_SCHOOL_NAME
                this.themeColor = json.data.theme_color || DEFAULT_THEME_COLOR
                this.hasCustomLogo = Boolean(json.data.has_custom_logo)
                this.logoUrl = json.data.logo_url
            }
        },

        async deleteLogo(): Promise<void> {
            const { apiFetch } = useApi()
            const authStore = useAuthStore()

            const response = await apiFetch<SchoolIdentityData>('/settings/school-identity/logo', {
                method: 'DELETE',
                headers: {
                    Authorization: `Bearer ${authStore.token}`,
                },
            })

            if (response?.data) {
                this.hasCustomLogo = false
                this.logoUrl = null
            }
        },
    },
})
