<script setup lang="ts">
import { getApiErrorMessage } from '~/composables/useApi'
import { useAdminService } from '~/services/admin'
import type { Permission } from '~/services/admin'

definePageMeta({
    middleware: 'admin',
})

const router = useRouter()
const { createRole, getPermissions } = useAdminService()

const loading = ref(true)
const saving = ref(false)
const errorMessage = ref('')
const successMessage = ref('')

const name = ref('')
const selectedPermissions = ref<string[]>([])
const allPermissions = ref<Permission[]>([])

const load = async () => {
    loading.value = true
    try {
        const permsRes = await getPermissions()
        allPermissions.value = permsRes.data
    } catch (error: unknown) {
        errorMessage.value = getApiErrorMessage(
            error,
            'Gagal memuat daftar hak akses.',
        )
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
    saving.value = true

    try {
        await createRole({
            name: name.value,
            permissions: selectedPermissions.value
        })
        
        successMessage.value = 'Peran berhasil dibuat.'
        setTimeout(() => {
            router.push('/admin/roles')
        }, 1500)
    } catch (error: unknown) {
        errorMessage.value = getApiErrorMessage(error, 'Gagal membuat peran.')
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
                        <NuxtLink to="/admin/roles" class="text-sm font-medium text-slate-500 hover:text-slate-700">&larr; Kembali</NuxtLink>
                        <h1 class="text-xl font-bold text-slate-900">Tambah Peran (Role)</h1>
                    </div>
                </div>
            </div>

            <div v-if="loading" class="mt-8 text-center text-slate-500">Memuat data...</div>

            <div v-else>
                <div v-if="errorMessage" class="mb-6 rounded-lg bg-red-50 p-4"><p class="text-sm text-red-700">{{ errorMessage }}</p></div>
                <div v-if="successMessage" class="mb-6 rounded-lg bg-green-50 p-4"><p class="text-sm text-green-700">{{ successMessage }}</p></div>

                <form @submit.prevent="submit" class="space-y-8 bg-white shadow-sm ring-1 ring-slate-200 sm:rounded-xl p-6 lg:p-8">
                    <div class="space-y-4 max-w-2xl">
                        <div>
                            <label class="block text-sm font-medium leading-6 text-slate-900">Nama Peran</label>
                            <input v-model="name" type="text" required class="mt-2 block w-full rounded-md border-0 py-1.5 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-indigo-600 sm:text-sm" />
                        </div>
                    </div>

                    <div class="pt-4 border-t border-slate-200 mt-8">
                        <h3 class="text-lg font-medium leading-6 text-slate-900 mb-4">Hak Akses (Permissions)</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                            <label v-for="permission in allPermissions" :key="permission.id" class="flex items-start gap-2 p-3 rounded-lg border border-slate-200 hover:bg-slate-50 cursor-pointer">
                                <input type="checkbox" :value="permission.name" v-model="selectedPermissions" class="mt-0.5 h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-600" />
                                <span class="text-sm text-slate-700 break-all">{{ permission.name }}</span>
                            </label>
                        </div>
                    </div>

                    <div class="pt-6 flex items-center justify-end gap-x-6 border-t border-slate-200">
                        <NuxtLink to="/admin/roles" class="text-sm font-semibold leading-6 text-slate-900">Batal</NuxtLink>
                        <button type="submit" :disabled="saving" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 disabled:opacity-50">
                            {{ saving ? 'Menyimpan...' : 'Simpan Peran' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AdminShell>
</template>
