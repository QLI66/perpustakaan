<script setup lang="ts">
import {
    useMembersService,
    type MemberReference,
} from '~/services/members'

definePageMeta({
    middleware: 'staff',
})

const router = useRouter()
const authStore = useAuthStore()
const service = useMembersService()

const memberTypes = ref<MemberReference[]>([])
const classLevels = ref<MemberReference[]>([])
const majors = ref<MemberReference[]>([])

const loading = ref(true)
const saving = ref(false)
const errorMessage = ref('')

const canCreate = computed(() =>
    authStore.hasPermission('members.create') ||
    authStore.hasRole('SUPER_ADMIN'),
)

const form = ref({
    member_number: '',
    name: '',
    member_type_id: '' as number | '',
    identity_number: '',
    class_level_id: '' as number | '',
    major_id: '' as number | '',
    joined_at: new Date().toISOString().slice(0, 10),
    status: 'ACTIVE' as 'ACTIVE' | 'INACTIVE' | 'SUSPENDED',
    notes: '',
})

const loadReferences = async () => {
    try {
        const [
            memberTypeResult,
            classLevelResult,
            majorResult,
        ] = await Promise.all([
            service.getMemberTypes(),
            service.getClassLevels(),
            service.getMajors(),
        ])

        memberTypes.value = memberTypeResult.data || []
        classLevels.value = classLevelResult.data || []
        majors.value = majorResult.data || []
    } catch (error: unknown) {
        errorMessage.value =
            error instanceof Error
                ? error.message
                : 'Gagal memuat data referensi.'
    } finally {
        loading.value = false
    }
}

const submit = async () => {
    errorMessage.value = ''

    if (!form.value.member_number.trim()) {
        errorMessage.value = 'Nomor anggota wajib diisi.'
        return
    }

    if (!form.value.name.trim()) {
        errorMessage.value = 'Nama anggota wajib diisi.'
        return
    }

    if (!form.value.member_type_id) {
        errorMessage.value = 'Jenis anggota wajib dipilih.'
        return
    }

    saving.value = true

    try {
        const result = await service.createMember({
            member_number: form.value.member_number.trim(),
            name: form.value.name.trim(),
            member_type_id: Number(form.value.member_type_id),
            identity_number:
                form.value.identity_number.trim() || null,
            class_level_id:
                form.value.class_level_id
                    ? Number(form.value.class_level_id)
                    : null,
            major_id:
                form.value.major_id
                    ? Number(form.value.major_id)
                    : null,
            joined_at: form.value.joined_at,
            status: form.value.status,
            notes:
                form.value.notes.trim() || null,
        })

        await router.push(
            `/staff/members/${result.data.id}`,
        )
    } catch (error: unknown) {
        errorMessage.value =
            error instanceof Error
                ? error.message
                : 'Gagal membuat anggota.'
    } finally {
        saving.value = false
    }
}

onMounted(() => {
    if (!canCreate.value) {
        loading.value = false
        return
    }

    loadReferences()
})
</script>

<template>
    <StaffShell active="members">
        <div class="mx-auto max-w-4xl space-y-6">
            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium text-slate-500">
                            Keanggotaan
                        </p>

                        <h1 class="mt-1 text-3xl font-bold text-slate-900">
                            Tambah Anggota
                        </h1>

                        <p class="mt-2 text-sm text-slate-500">
                            Tambahkan anggota baru perpustakaan.
                        </p>
                    </div>

                    <NuxtLink
                        to="/staff/members"
                        class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700"
                    >
                        Batal
                    </NuxtLink>
                </div>
            </section>

            <section
                v-if="!canCreate"
                class="rounded-2xl border border-rose-200 bg-rose-50 p-5 text-sm text-rose-700"
            >
                Anda tidak memiliki izin membuat anggota.
            </section>

            <section
                v-else
                class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8"
            >
                <div
                    v-if="errorMessage"
                    class="mb-5 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700"
                >
                    {{ errorMessage }}
                </div>

                <div
                    v-if="loading"
                    class="space-y-3"
                >
                    <div
                        v-for="i in 7"
                        :key="i"
                        class="h-12 animate-pulse rounded-xl bg-slate-100"
                    />
                </div>

                <form
                    v-else
                    class="space-y-6"
                    @submit.prevent="submit"
                >
                    <div class="grid gap-5 md:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-semibold text-slate-900">
                                Nomor Anggota *
                            </label>

                            <input
                                v-model="form.member_number"
                                type="text"
                                maxlength="50"
                                required
                                placeholder="MBR-2026-0011"
                                class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm"
                            >
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-semibold text-slate-900">
                                Nama *
                            </label>

                            <input
                                v-model="form.name"
                                type="text"
                                required
                                placeholder="Nama lengkap"
                                class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm"
                            >
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-semibold text-slate-900">
                                Jenis Anggota *
                            </label>

                            <select
                                v-model="form.member_type_id"
                                required
                                class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm"
                            >
                                <option value="">
                                    Pilih jenis anggota
                                </option>

                                <option
                                    v-for="item in memberTypes"
                                    :key="item.id"
                                    :value="item.id"
                                >
                                    {{ item.name }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-semibold text-slate-900">
                                Nomor Identitas
                            </label>

                            <input
                                v-model="form.identity_number"
                                type="text"
                                maxlength="50"
                                placeholder="NIS / NIP"
                                class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm"
                            >
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-semibold text-slate-900">
                                Kelas
                            </label>

                            <select
                                v-model="form.class_level_id"
                                class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm"
                            >
                                <option value="">
                                    Tidak ada / bukan siswa
                                </option>

                                <option
                                    v-for="item in classLevels"
                                    :key="item.id"
                                    :value="item.id"
                                >
                                    {{ item.name }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-semibold text-slate-900">
                                Jurusan
                            </label>

                            <select
                                v-model="form.major_id"
                                class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm"
                            >
                                <option value="">
                                    Tidak ada / bukan siswa
                                </option>

                                <option
                                    v-for="item in majors"
                                    :key="item.id"
                                    :value="item.id"
                                >
                                    {{ item.name }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-semibold text-slate-900">
                                Tanggal Bergabung *
                            </label>

                            <input
                                v-model="form.joined_at"
                                type="date"
                                required
                                class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm"
                            >
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-semibold text-slate-900">
                                Status
                            </label>

                            <select
                                v-model="form.status"
                                class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm"
                            >
                                <option value="ACTIVE">Aktif</option>
                                <option value="INACTIVE">Tidak Aktif</option>
                                <option value="SUSPENDED">Ditangguhkan</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-900">
                            Catatan
                        </label>

                        <textarea
                            v-model="form.notes"
                            rows="4"
                            placeholder="Catatan anggota..."
                            class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm"
                        />
                    </div>

                    <div class="flex justify-end border-t border-slate-100 pt-5">
                        <button
                            type="submit"
                            :disabled="saving"
                            class="rounded-xl bg-slate-900 px-6 py-3 text-sm font-semibold text-white disabled:opacity-50"
                        >
                            {{ saving ? 'Menyimpan...' : 'Simpan Anggota' }}
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </StaffShell>
</template>