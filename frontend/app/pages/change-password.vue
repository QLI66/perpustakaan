<script setup lang="ts">
import type { ApiError } from '~/types/api'
import type { AuthUser } from '~/stores/auth'

definePageMeta({
    middleware: 'auth',
})

const authStore = useAuthStore()
const { apiFetch } = useApi()

// Check if user really needs to change password, otherwise redirect away
onMounted(() => {
    if (!authStore.user?.must_change_password) {
        redirectAfterChange(authStore.user)
    }
})

const currentPassword = ref('')
const newPassword = ref('')
const newPasswordConfirmation = ref('')
const loading = ref(false)
const errorMessage = ref('')
const successMessage = ref('')
const fieldErrors = ref<Record<string, string[]>>({})

const redirectAfterChange = async (user: AuthUser | null) => {
    if (user?.roles?.includes('SUPER_ADMIN')) {
        await navigateTo('/admin')
        return
    }
    if (user?.roles?.includes('LIBRARY_STAFF')) {
        await navigateTo('/staff')
        return
    }
    if (user?.roles?.includes('MEMBER')) {
        await navigateTo('/member')
        return
    }
    await navigateTo('/')
}

const submitChangePassword = async () => {
    errorMessage.value = ''
    successMessage.value = ''
    fieldErrors.value = {}

    if (!currentPassword.value || !newPassword.value || !newPasswordConfirmation.value) {
        errorMessage.value = 'Semua field wajib diisi.'
        return
    }

    if (newPassword.value.length < 8) {
        fieldErrors.value = { new_password: ['Password baru minimal 8 karakter.'] }
        return
    }

    if (!/[A-Za-z]/.test(newPassword.value)) {
        fieldErrors.value = { new_password: ['Password baru harus mengandung minimal 1 huruf.'] }
        return
    }

    if (!/[0-9]/.test(newPassword.value)) {
        fieldErrors.value = { new_password: ['Password baru harus mengandung minimal 1 angka.'] }
        return
    }

    if (newPassword.value === currentPassword.value) {
        fieldErrors.value = { new_password: ['Password baru harus berbeda dari password lama.'] }
        return
    }

    if (newPassword.value !== newPasswordConfirmation.value) {
        fieldErrors.value = { new_password: ['Konfirmasi password baru tidak cocok.'] }
        return
    }

    loading.value = true

    try {
        await apiFetch('/auth/change-password', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                Authorization: `Bearer ${authStore.token}`,
            },
            body: {
                current_password: currentPassword.value,
                new_password: newPassword.value,
                new_password_confirmation: newPasswordConfirmation.value
            }
        })

        successMessage.value = 'Password berhasil diubah. Mengarahkan Anda...'
        
        // Update user state locally
        if (authStore.user) {
            authStore.user.must_change_password = false
        }
        
        setTimeout(() => {
            redirectAfterChange(authStore.user)
        }, 1500)

    } catch (error: unknown) {
        const payload =
            error && typeof error === 'object' && 'data' in error
                ? error.data as ApiError
                : undefined
        if (payload?.errors) {
            fieldErrors.value = payload.errors
        } else if (payload?.message) {
            errorMessage.value = payload.message
        } else {
            errorMessage.value = 'Gagal mengubah password.'
        }
    } finally {
        loading.value = false
    }
}
</script>

<template>
    <main class="min-h-[80vh] bg-slate-50 px-4 py-10">
        <div class="mx-auto max-w-md">
            <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200 sm:p-8">
                <div class="mb-8 text-center">
                    <p class="text-sm font-medium text-amber-600">
                        Keamanan Akun
                    </p>

                    <h1 class="mt-2 text-2xl font-bold text-slate-900">
                        Ganti Password Awal
                    </h1>

                    <p class="mt-2 text-sm text-slate-500">
                        Anda diwajibkan untuk mengganti password awal demi keamanan akun Anda.
                    </p>
                </div>

                <div v-if="successMessage" class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-700 font-medium text-center">
                    {{ successMessage }}
                </div>

                <form v-else class="space-y-5" @submit.prevent="submitChangePassword">
                    <div>
                        <label for="current_password" class="mb-2 block text-sm font-medium text-slate-700">
                            Password Saat Ini (Password Awal)
                        </label>

                        <input
                            id="current_password"
                            v-model="currentPassword"
                            type="password"
                            placeholder="Masukkan password awal Anda"
                            class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                            :disabled="loading"
                        >

                        <p v-if="fieldErrors.current_password?.[0]" class="mt-1 text-xs text-rose-600">
                            {{ fieldErrors.current_password[0] }}
                        </p>
                    </div>

                    <div>
                        <label for="new_password" class="mb-2 block text-sm font-medium text-slate-700">
                            Password Baru
                        </label>

                        <input
                            id="new_password"
                            v-model="newPassword"
                            type="password"
                            placeholder="Minimal 8 karakter"
                            class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                            :disabled="loading"
                        >

                        <p v-if="fieldErrors.new_password?.[0]" class="mt-1 text-xs text-rose-600">
                            {{ fieldErrors.new_password[0] }}
                        </p>
                    </div>

                    <div>
                        <label for="new_password_confirmation" class="mb-2 block text-sm font-medium text-slate-700">
                            Konfirmasi Password Baru
                        </label>

                        <input
                            id="new_password_confirmation"
                            v-model="newPasswordConfirmation"
                            type="password"
                            placeholder="Ketik ulang password baru"
                            class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                            :disabled="loading"
                        >
                    </div>

                    <div v-if="errorMessage" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
                        {{ errorMessage }}
                    </div>

                    <button
                        type="submit"
                        :disabled="loading"
                        class="w-full rounded-xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50 mt-4"
                    >
                        {{ loading ? 'Menyimpan...' : 'Simpan Password Baru' }}
                    </button>
                </form>
            </div>
        </div>
    </main>
</template>



