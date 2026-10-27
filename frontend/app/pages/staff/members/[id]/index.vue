<script setup lang="ts">
import {
    useMembersService,
    type MemberListItem,
} from '~/services/members'

definePageMeta({
    middleware: 'staff',
})

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const membersService = useMembersService()

const member = ref<MemberListItem | null>(null)
const loading = ref(true)
const errorMessage = ref('')

const canView = computed(() =>
    authStore.hasPermission('members.view') ||
    authStore.hasRole('SUPER_ADMIN'),
)

const memberId = computed(() => Number(route.params.id))

const loadMember = async () => {
    if (!canView.value) {
        loading.value = false
        return
    }

    if (!Number.isInteger(memberId.value) || memberId.value <= 0) {
        errorMessage.value = 'ID anggota tidak valid.'
        loading.value = false
        return
    }

    loading.value = true
    errorMessage.value = ''

    try {
        const result = await membersService.getMember(memberId.value)
        member.value = result.data
    } catch (error: unknown) {
        errorMessage.value =
            error instanceof Error
                ? error.message
                : 'Gagal memuat detail anggota.'
    } finally {
        loading.value = false
    }
}

const formatDate = (date: string | null) => {
    if (!date) return '-'

    return new Date(date).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    })
}

const getStatusClass = (status: MemberListItem['status']) => {
    switch (status) {
        case 'ACTIVE':
            return 'bg-emerald-100 text-emerald-800'
        case 'INACTIVE':
            return 'bg-slate-100 text-slate-700'
        case 'SUSPENDED':
            return 'bg-rose-100 text-rose-800'
        default:
            return 'bg-slate-100 text-slate-700'
    }
}

onMounted(loadMember)
</script>

<template>
    <StaffShell active="members">
        <div class="mx-auto max-w-5xl space-y-6">
            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-500">
                            Keanggotaan
                        </p>

                        <h1 class="mt-1 text-3xl font-bold tracking-tight text-slate-900">
                            Detail Anggota
                        </h1>

                        <p class="mt-2 text-sm text-slate-500">
                            Informasi lengkap anggota perpustakaan.
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <NuxtLink
                            v-if="authStore.hasPermission('members.update') || authStore.hasRole('SUPER_ADMIN')"
                            :to="`/staff/members/${route.params.id}/edit`"
                            class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800"
                        >
                            Edit
                        </NuxtLink>

                        <button
                            type="button"
                            class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                            @click="router.push('/staff/members')"
                        >
                            Kembali
                        </button>
                    </div>
                </div>
            </section>

            <div
                v-if="errorMessage"
                class="rounded-2xl border border-rose-200 bg-rose-50 p-5 text-sm text-rose-700"
            >
                <p>{{ errorMessage }}</p>

                <button
                    type="button"
                    class="mt-3 rounded-lg bg-white px-3 py-2 text-sm font-semibold text-rose-700 shadow-sm"
                    @click="loadMember"
                >
                    Coba Lagi
                </button>
            </div>

            <section
                v-if="!canView"
                class="rounded-2xl border border-rose-200 bg-rose-50 p-5 text-sm text-rose-700"
            >
                Anda tidak memiliki izin untuk melihat data anggota.
            </section>

            <section
                v-else-if="loading"
                class="space-y-4"
            >
                <div
                    v-for="i in 3"
                    :key="i"
                    class="h-36 animate-pulse rounded-2xl bg-slate-100"
                />
            </section>

            <template v-else-if="member">
                <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                    <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="font-mono text-sm text-slate-500">
                                {{ member.member_number }}
                            </p>

                            <h2 class="mt-1 text-2xl font-bold text-slate-900">
                                {{ member.name }}
                            </h2>

                            <p
                                v-if="member.member_type"
                                class="mt-2 text-sm text-slate-500"
                            >
                                {{ member.member_type.name }}
                            </p>
                        </div>

                        <span
                            :class="[
                                'w-fit rounded-full px-3 py-1 text-xs font-semibold',
                                getStatusClass(member.status),
                            ]"
                        >
                            {{ member.status }}
                        </span>
                    </div>
                </section>

                <section class="grid gap-6 lg:grid-cols-2">
                    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h3 class="text-base font-semibold text-slate-900">
                            Informasi Keanggotaan
                        </h3>

                        <dl class="mt-5 space-y-4">
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">
                                    Nomor Anggota
                                </dt>
                                <dd class="mt-1 font-mono text-sm text-slate-900">
                                    {{ member.member_number }}
                                </dd>
                            </div>

                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">
                                    Jenis Anggota
                                </dt>
                                <dd class="mt-1 text-sm text-slate-900">
                                    {{ member.member_type?.name || '-' }}
                                </dd>
                            </div>

                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">
                                    Tanggal Bergabung
                                </dt>
                                <dd class="mt-1 text-sm text-slate-900">
                                    {{ formatDate(member.joined_at) }}
                                </dd>
                            </div>

                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">
                                    Status
                                </dt>
                                <dd class="mt-1 text-sm text-slate-900">
                                    {{ member.status }}
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h3 class="text-base font-semibold text-slate-900">
                            Akademik
                        </h3>

                        <dl class="mt-5 space-y-4">
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">
                                    Kelas
                                </dt>
                                <dd class="mt-1 text-sm text-slate-900">
                                    {{ member.class_level?.name || '-' }}
                                </dd>
                            </div>

                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">
                                    Jurusan
                                </dt>
                                <dd class="mt-1 text-sm text-slate-900">
                                    {{ member.major?.name || '-' }}
                                </dd>
                            </div>
                        </dl>
                    </div>
                </section>

                <section class="grid gap-6 lg:grid-cols-2">
                    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h3 class="text-base font-semibold text-slate-900">
                            Identitas
                        </h3>

                        <dl class="mt-5 space-y-4">
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">
                                    Nomor Identitas
                                </dt>
                                <dd class="mt-1 text-sm text-slate-900">
                                    {{ member.identity_number || '-' }}
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h3 class="text-base font-semibold text-slate-900">
                            Akun Pengguna
                        </h3>

                        <dl class="mt-5 space-y-4">
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">
                                    Nama
                                </dt>
                                <dd class="mt-1 text-sm text-slate-900">
                                    {{ member.user?.name || '-' }}
                                </dd>
                            </div>

                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">
                                    Email
                                </dt>
                                <dd class="mt-1 break-all text-sm text-slate-900">
                                    {{ member.user?.email || '-' }}
                                </dd>
                            </div>
                        </dl>
                    </div>
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-base font-semibold text-slate-900">
                        Catatan
                    </h3>

                    <p class="mt-3 whitespace-pre-wrap text-sm leading-6 text-slate-600">
                        {{ member.notes || 'Tidak ada catatan.' }}
                    </p>
                </section>
            </template>
        </div>
    </StaffShell>
</template>