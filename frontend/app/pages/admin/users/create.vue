<script setup lang="ts">
import { useAdminService } from '~/services/admin'
import type { ApiError } from '~/types/api'

definePageMeta({
    middleware: 'admin',
})

const router = useRouter()
const { createUser, getRoles } = useAdminService()

const loading = ref(false)
const errorMessage = ref('')
const successMessage = ref('')

const name = ref('')
const email = ref('')
const password = ref('')
const selectedRoles = ref<string[]>([])
const allRoles = ref<Array<{id: number, name: string}>>([])

const hasApiErrorMessage = (
    error: unknown,
): error is { data: Pick<ApiError, 'message'> } => {
    return (
        typeof error === 'object' &&
        error !== null &&
        'data' in error &&
        typeof error.data === 'object' &&
        error.data !== null &&
        'message' in error.data &&
        typeof error.data.message === 'string'
    )
}

const loadRoles = async () => {
    try {
        const res = await getRoles()
        allRoles.value = res.data
    } catch {
        // Ignored
    }
}

onMounted(() => {
    loadRoles()
})

const submit = async () => {
    errorMessage.value = ''
    
    if (!name.value || !email.value || !password.value) {
        errorMessage.value = 'Nama, email, dan password wajib diisi.'
        return
    }

    loading.value = true

    try {
        await createUser({
            name: name.value,
            email: email.value,
            password: password.value,
            roles: selectedRoles.value
        })

        successMessage.value = 'Pengguna berhasil dibuat.'
        setTimeout(() => {
            router.push('/admin/users')
        }, 1500)
    } catch (error: unknown) {
        errorMessage.value = hasApiErrorMessage(error)
            ? error.data.message
            : 'Gagal membuat pengguna.'
    } finally {
        loading.value = false
    }
}
</script>

<template>
    <AdminShell>
        <div class="px-4 py-8 sm:px-6 lg:px-8">
            <div class="sm:flex sm:items-center mb-8">
                <div class="sm:flex-auto">
                    <div class="flex items-center gap-4">
                        <NuxtLink to="/admin/users" class="text-sm font-medium text-slate-500 hover:text-slate-700">&larr; Kembali</NuxtLink>
                        <h1 class="text-xl font-bold text-slate-900">Tambah Pengguna</h1>
                    </div>
                </div>
            </div>

            <div v-if="errorMessage" class="mb-6 rounded-lg bg-red-50 p-4"><p class="text-sm text-red-700">{{ errorMessage }}</p></div>
            <div v-if="successMessage" class="mb-6 rounded-lg bg-green-50 p-4"><p class="text-sm text-green-700">{{ successMessage }}</p></div>

            <form @submit.prevent="submit" class="space-y-8 bg-white shadow-sm ring-1 ring-slate-200 sm:rounded-xl p-6 lg:p-8 max-w-2xl">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium leading-6 text-slate-900">Nama</label>
                        <input v-model="name" type="text" required class="mt-2 block w-full rounded-md border-0 py-1.5 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-indigo-600 sm:text-sm" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium leading-6 text-slate-900">Email</label>
                        <input v-model="email" type="email" required class="mt-2 block w-full rounded-md border-0 py-1.5 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-indigo-600 sm:text-sm" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium leading-6 text-slate-900">Password</label>
                        <input v-model="password" type="password" required minlength="8" class="mt-2 block w-full rounded-md border-0 py-1.5 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-indigo-600 sm:text-sm" />
                    </div>
                    
                    <div class="pt-4 border-t border-slate-200 mt-4">
                        <h3 class="text-sm font-medium leading-6 text-slate-900 mb-2">Pilih Role</h3>
                        <div class="space-y-2">
                            <label v-for="role in allRoles" :key="role.id" class="flex items-center gap-2">
                                <input type="checkbox" :value="role.name" v-model="selectedRoles" class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-600" />
                                <span class="text-sm text-slate-700">{{ role.name }}</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="pt-6 flex items-center justify-end gap-x-6">
                    <NuxtLink to="/admin/users" class="text-sm font-semibold leading-6 text-slate-900">Batal</NuxtLink>
                    <button type="submit" :disabled="loading" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 disabled:opacity-50">
                        {{ loading ? 'Menyimpan...' : 'Simpan Pengguna' }}
                    </button>
                </div>
            </form>
        </div>
    </AdminShell>
</template>
