<script setup lang="ts">
import type { AuthUser } from '~/stores/auth'
import { useSchoolIdentityStore } from '~/stores/schoolIdentity'

const authStore = useAuthStore()
const identityStore = useSchoolIdentityStore()
const { apiFetch } = useApi()
const toast = useToast()

onMounted(() => {
    identityStore.fetchIdentity()
})

const login = ref('')
const password = ref('')
const deviceName = ref('Perpustakaan Web')
const loading = ref(false)
const errorMessage = ref('')
const fieldErrors = ref<Record<string, string[]>>({})

interface LoginResponse {
    user: unknown
    token: string
}

interface MeResponse {
    user: unknown
}

const normalizeStringArray = (value: unknown): string[] => {
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

const normalizeAuthUser = (value: unknown): AuthUser => {
    const user =
        value && typeof value === 'object'
            ? (value as Record<string, unknown>)
            : {}

    return {
        id: Number(user.id ?? 0),
        name: String(user.name ?? ''),
        email: String(user.email ?? ''),
        username: user.username ? String(user.username) : undefined,
        roles: normalizeStringArray(user.roles),
        permissions: normalizeStringArray(user.permissions),
        must_change_password: Boolean(user.must_change_password),
    }
}

const extractErrorMessage = (error: unknown): string => {
    if (
        error &&
        typeof error === 'object' &&
        'data' in error
    ) {
        const data = (error as { data?: unknown }).data

        if (data && typeof data === 'object') {
            const payload = data as Record<string, unknown>

            const errors = payload.errors

            if (errors && typeof errors === 'object') {
                const firstError = Object.values(
                    errors as Record<string, unknown>,
                )[0]

                if (Array.isArray(firstError) && firstError.length > 0) {
                    return String(firstError[0])
                }

                if (typeof firstError === 'string') {
                    return firstError
                }
            }

            if (typeof payload.message === 'string') {
                return payload.message
            }
        }
    }

    if (error instanceof Error && error.message) {
        return error.message
    }

    return 'Login gagal. Periksa email atau username dan password Anda.'
}

const redirectAfterLogin = async (user: AuthUser) => {
    if (user.must_change_password) {
        await navigateTo('/change-password')
        return
    }
    if (user.roles.includes('SUPER_ADMIN')) {
        await navigateTo('/admin')
        return
    }

    if (user.roles.includes('LIBRARY_STAFF')) {
        await navigateTo('/staff')
        return
    }

    if (user.roles.includes('MEMBER')) {
        await navigateTo('/member')
        return
    }

    await navigateTo('/')
}

const submitLogin = async () => {
    errorMessage.value = ''
    fieldErrors.value = {}

    if (!login.value.trim()) {
        errorMessage.value = 'Email atau Username wajib diisi.'
        return
    }

    if (!password.value) {
        errorMessage.value = 'Password wajib diisi.'
        return
    }

    loading.value = true

    try {
        const loginResponse = await apiFetch<LoginResponse>(
            '/auth/login',
            {
                method: 'POST',
                body: {
                    login: login.value.trim(),
                    password: password.value,
                    device_name:
                        deviceName.value.trim() || undefined,
                },
            },
        )

        const token = loginResponse.data.token

        if (!token) {
            throw new Error('Token login tidak diterima dari server.')
        }

        const meResponse = await apiFetch<MeResponse>(
            '/auth/me',
            {
                method: 'GET',
                headers: {
                    Accept: 'application/json',
                    Authorization: `Bearer ${token}`,
                },
            },
        )

        const user = normalizeAuthUser(meResponse.data.user)

        if (!user.id) {
            throw new Error(
                'Data user dari server tidak lengkap.',
            )
        }

        authStore.setAuth(token, user)

        toast.add({
            severity: 'success',
            summary: 'Sukses',
            detail: 'Login berhasil.',
            life: 3000,
        })

        await redirectAfterLogin(user)
    } catch (error: unknown) {
        errorMessage.value = extractErrorMessage(error)

        if (
            error &&
            typeof error === 'object' &&
            'data' in error
        ) {
            const data = (error as { data?: unknown }).data

            if (data && typeof data === 'object') {
                const payload = data as Record<string, unknown>

                if (
                    payload.errors &&
                    typeof payload.errors === 'object'
                ) {
                    fieldErrors.value =
                        payload.errors as Record<string, string[]>
                }
            }
        }
    } finally {
        loading.value = false
    }
}
</script>

<template>
    <main class="relative isolate flex min-h-[80vh] items-center justify-center overflow-hidden bg-gradient-to-br from-slate-50 via-white to-blue-100/70 px-4 py-10 sm:py-14">
        <div
            aria-hidden="true"
            class="pointer-events-none absolute -right-20 -top-28 -z-10 h-80 w-80 rounded-full bg-blue-200/50 blur-3xl"
        />
        <div
            aria-hidden="true"
            class="pointer-events-none absolute -bottom-36 -left-20 -z-10 h-80 w-80 rounded-full bg-cyan-100/60 blur-3xl"
        />

        <div class="w-full max-w-md">
            <div class="rounded-3xl border border-white/80 bg-white/95 p-6 shadow-2xl shadow-blue-950/10 ring-1 ring-slate-200/70 backdrop-blur sm:p-9">
                <div class="mb-8 text-center">
                    <div
                        class="mx-auto mb-5 flex h-16 w-16 items-center justify-center overflow-hidden rounded-2xl shadow-sm ring-1 ring-slate-200"
                        :style="{ backgroundColor: identityStore.hasCustomLogo ? '#ffffff' : (identityStore.themeColor ? `${identityStore.themeColor}15` : '#eff6ff') }"
                    >
                        <img
                            v-if="identityStore.hasCustomLogo"
                            :src="identityStore.resolvedLogoUrl || ''"
                            alt="Logo Sekolah"
                            class="h-full w-full object-contain p-1"
                        >
                        <i
                            v-else
                            class="pi pi-book text-2xl"
                            :style="{ color: identityStore.themeColor || '#1d4ed8' }"
                            aria-hidden="true"
                        />
                    </div>
                    <p class="text-sm font-semibold tracking-wide" :style="{ color: identityStore.themeColor || '#1d4ed8' }">
                        {{ identityStore.schoolName }}
                    </p>

                    <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-950">
                        Masuk
                    </h1>

                    <p class="mt-2 text-sm leading-6 text-slate-500">
                        Masuk untuk mengakses akun perpustakaan Anda.
                    </p>
                </div>

                <form
                    class="space-y-5"
                    @submit.prevent="submitLogin"
                >
                    <div>
                        <label
                            for="login"
                            class="mb-2 block text-sm font-semibold text-slate-700"
                        >
                            Email atau Username
                        </label>

                        <input
                            id="login"
                            v-model="login"
                            type="text"
                            autocomplete="username"
                            placeholder="Email atau Username (NISN)"
                            class="min-h-12 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-base text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-blue-600 focus:ring-4 focus:ring-blue-100 focus-visible:ring-4 disabled:cursor-not-allowed disabled:bg-slate-50 sm:text-sm"
                            :disabled="loading"
                        >

                        <p
                            v-if="fieldErrors.login?.[0]"
                            class="mt-2 text-sm text-rose-700"
                        >
                            {{ fieldErrors.login[0] }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="password"
                            class="mb-2 block text-sm font-semibold text-slate-700"
                        >
                            Password
                        </label>

                        <input
                            id="password"
                            v-model="password"
                            type="password"
                            autocomplete="current-password"
                            placeholder="Masukkan password"
                            class="min-h-12 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-base text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-blue-600 focus:ring-4 focus:ring-blue-100 focus-visible:ring-4 disabled:cursor-not-allowed disabled:bg-slate-50 sm:text-sm"
                            :disabled="loading"
                        >

                        <p
                            v-if="fieldErrors.password?.[0]"
                            class="mt-2 text-sm text-rose-700"
                        >
                            {{ fieldErrors.password[0] }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="deviceName"
                            class="mb-2 block text-sm font-semibold text-slate-700"
                        >
                            Nama Perangkat
                        </label>

                        <input
                            id="deviceName"
                            v-model="deviceName"
                            type="text"
                            placeholder="Perpustakaan Web"
                            class="min-h-12 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-base text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-blue-600 focus:ring-4 focus:ring-blue-100 focus-visible:ring-4 disabled:cursor-not-allowed disabled:bg-slate-50 sm:text-sm"
                            :disabled="loading"
                        >
                    </div>

                    <div
                        v-if="errorMessage"
                        role="alert"
                        class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm leading-6 text-rose-800"
                    >
                        {{ errorMessage }}
                    </div>

                    <button
                        type="submit"
                        :disabled="loading"
                        class="flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-blue-700 px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-blue-900/15 transition hover:bg-blue-800 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-blue-300 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <i v-if="loading" class="pi pi-spinner pi-spin" />
                        {{ loading ? 'Memproses...' : 'Masuk' }}
                    </button>

                    <button
                        type="button"
                        :disabled="loading"
                        class="min-h-12 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-blue-200 hover:bg-blue-50/60 hover:text-blue-800 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-blue-100 disabled:cursor-not-allowed disabled:opacity-50"
                        @click="navigateTo('/')"
                    >
                        Kembali ke Beranda
                    </button>
                </form>
            </div>
        </div>
    </main>
</template>
