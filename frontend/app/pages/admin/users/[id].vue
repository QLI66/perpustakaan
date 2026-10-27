<script setup lang="ts">
import { useAdminService } from '~/services/admin'
import type { User } from '~/services/admin'
import type { ApiError } from '~/types/api'

interface UpdateUserPayload {
    name: string
    email: string
    roles: string[]
    password?: string
}

definePageMeta({
    middleware: 'admin',
})

const route = useRoute()
const router = useRouter()
const { getUser, updateUser, getRoles } = useAdminService()

const loading = ref(true)
const saving = ref(false)
const errorMessage = ref('')
const successMessage = ref('')

const name = ref('')
const email = ref('')
const password = ref('')
const selectedRoles = ref<string[]>([])
const allRoles = ref<Array<{id: number, name: string}>>([])
const user = ref<User | null>(null)
const coreRoleNames = new Set<string>(['SUPER_ADMIN', 'LIBRARY_STAFF', 'MEMBER'])
const coreRoleValidationMessage = 'Pilih maksimal satu role inti: SUPER_ADMIN, LIBRARY_STAFF, atau MEMBER.'
const isCoreRole = (roleName: string) => coreRoleNames.has(roleName)
const hasSelectedCoreRole = computed(() => selectedRoles.value.some(isCoreRole))
const hasMultipleCoreRoles = computed(
    () => selectedRoles.value.filter(isCoreRole).length > 1,
)

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

const load = async () => {
    loading.value = true
    try {
        const rolesRes = await getRoles()
        allRoles.value = rolesRes.data

        const userId = route.params.id as string
        const userRes = await getUser(userId)
        user.value = userRes.data

        name.value = user.value.name
        email.value = user.value.email
        selectedRoles.value = user.value.roles.map(r => r.name)
    } catch (error: unknown) {
        errorMessage.value = hasApiErrorMessage(error)
            ? error.data.message
            : 'Gagal memuat pengguna.'
    } finally {
        loading.value = false
    }
}

onMounted(() => {
    load()
})

const submit = async () => {
    errorMessage.value = ''
    successMessage.value = ''

    if (hasMultipleCoreRoles.value) {
        errorMessage.value = coreRoleValidationMessage
        return
    }

    saving.value = true

    try {
        const userId = route.params.id as string
        const payload: UpdateUserPayload = {
            name: name.value,
            email: email.value,
            roles: selectedRoles.value
        }
        
        if (password.value) {
            payload.password = password.value
        }

        await updateUser(userId, payload)
        successMessage.value = 'Pengguna berhasil diperbarui.'
        setTimeout(() => {
            router.push('/admin/users')
        }, 1500)
    } catch (error: unknown) {
        errorMessage.value = hasApiErrorMessage(error)
            ? error.data.message
            : 'Gagal memperbarui pengguna.'
    } finally {
        saving.value = false
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
                        <h1 class="text-xl font-bold text-slate-900">Ubah Pengguna</h1>
                    </div>
                </div>
            </div>

            <div v-if="loading" class="mt-8 text-center text-slate-500">Memuat data...</div>

            <div v-else>
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
                            <label class="block text-sm font-medium leading-6 text-slate-900">Password Baru (Biarkan kosong jika tidak ingin mengubah)</label>
                            <input v-model="password" type="password" minlength="8" class="mt-2 block w-full rounded-md border-0 py-1.5 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-indigo-600 sm:text-sm" />
                        </div>
                        
                        <div class="pt-4 border-t border-slate-200 mt-4">
                            <h3 class="text-sm font-medium leading-6 text-slate-900 mb-2">Pilih Role</h3>
                            <div class="space-y-2">
                                <label v-for="role in allRoles" :key="role.id" class="flex items-center gap-2">
                                    <input
                                        v-model="selectedRoles"
                                        type="checkbox"
                                        :value="role.name"
                                        :disabled="isCoreRole(role.name) && !selectedRoles.includes(role.name) && hasSelectedCoreRole"
                                        class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-600 disabled:cursor-not-allowed disabled:opacity-50"
                                    >
                                    <span class="text-sm text-slate-700">{{ role.name }}</span>
                                </label>
                            </div>
                            <p class="mt-2 text-xs text-slate-500">
                                Pilih maksimal satu role inti (SUPER_ADMIN, LIBRARY_STAFF, atau MEMBER); role tambahan tetap boleh dipilih.
                            </p>
                            <p v-if="hasMultipleCoreRoles" class="mt-2 text-sm text-red-700" role="alert">
                                {{ coreRoleValidationMessage }} Hapus pilihan role inti lainnya sebelum menyimpan.
                            </p>
                        </div>
                    </div>

                    <div class="pt-6 flex items-center justify-end gap-x-6">
                        <NuxtLink to="/admin/users" class="text-sm font-semibold leading-6 text-slate-900">Batal</NuxtLink>
                        <button type="submit" :disabled="saving" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 disabled:opacity-50">
                            {{ saving ? 'Menyimpan...' : 'Simpan Perubahan' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AdminShell>
</template>
