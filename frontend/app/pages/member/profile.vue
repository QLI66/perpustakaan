<script setup lang="ts">
import type { MemberProfile } from '~/types/member'

definePageMeta({
    middleware: 'auth',
})

const { getProfile } = useMember()

const profile = ref<MemberProfile | null>(null)
const loading = ref(true)
const errorMessage = ref('')

const joinedDate = computed(() => {
    if (!profile.value?.joined_at) {
        return '-'
    }

    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'long',
    }).format(new Date(profile.value.joined_at))
})

const loadProfile = async () => {
    loading.value = true
    errorMessage.value = ''

    try {
        profile.value = await getProfile()
    } catch (error: unknown) {
        errorMessage.value =
            error instanceof Error
                ? error.message
                : 'Profil gagal dimuat.'
    } finally {
        loading.value = false
    }
}

onMounted(loadProfile)
</script>

<template>
    <MemberShell :profile="profile">
        <div class="space-y-6">
            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <p class="text-sm font-medium text-slate-500">
                    Akun
                </p>
                <h1 class="mt-1 text-3xl font-bold tracking-tight text-slate-900">
                    Profil Saya
                </h1>
                <p class="mt-2 text-sm text-slate-500">
                    Informasi anggota yang terhubung dengan akun perpustakaan Anda.
                </p>
            </section>

            <div
                v-if="errorMessage"
                class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700"
            >
                {{ errorMessage }}
            </div>

            <div
                v-if="loading"
                class="animate-pulse rounded-3xl bg-white p-8 shadow-sm ring-1 ring-slate-200"
            >
                <div class="h-7 w-48 rounded bg-slate-200"/>
                <div class="mt-2 h-4 w-32 rounded bg-slate-200"/>

                <div class="mt-8 grid gap-6 sm:grid-cols-2">
                    <div
                        v-for="i in 6"
                        :key="i"
                        class="h-16 rounded-xl bg-slate-100"
                    />
                </div>
            </div>

            <section
                v-else-if="profile"
                class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm"
            >
                <div class="bg-slate-900 px-6 py-8 text-white sm:px-8">
                    <p class="text-sm text-slate-300">
                        Nomor Anggota
                    </p>

                    <div class="mt-2 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <h2 class="text-3xl font-bold">
                                {{ profile.member_number }}
                            </h2>
                            <p class="mt-2 text-sm text-slate-300">
                                {{ profile.name }}
                            </p>
                        </div>

                        <span class="w-fit rounded-full bg-white/10 px-3 py-1.5 text-xs font-semibold text-white">
                            {{ profile.status }}
                        </span>
                    </div>
                </div>

                <div class="p-6 sm:p-8">
                    <div class="grid gap-6 sm:grid-cols-2">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Nama Lengkap
                            </p>
                            <p class="mt-1 text-sm font-semibold text-slate-900">
                                {{ profile.name }}
                            </p>
                        </div>

                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Nomor Identitas
                            </p>
                            <p class="mt-1 text-sm font-semibold text-slate-900">
                                {{ profile.identity_number || '-' }}
                            </p>
                        </div>

                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Jenis Member
                            </p>
                            <p class="mt-1 text-sm font-semibold text-slate-900">
                                {{ profile.member_type?.name || '-' }}
                            </p>
                        </div>

                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Kelas
                            </p>
                            <p class="mt-1 text-sm font-semibold text-slate-900">
                                {{ profile.class_level?.name || '-' }}
                            </p>
                        </div>

                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Jurusan
                            </p>
                            <p class="mt-1 text-sm font-semibold text-slate-900">
                                {{ profile.major?.name || '-' }}
                            </p>
                        </div>

                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Bergabung
                            </p>
                            <p class="mt-1 text-sm font-semibold text-slate-900">
                                {{ joinedDate }}
                            </p>
                        </div>

                        <div class="sm:col-span-2">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Email
                            </p>
                            <p class="mt-1 text-sm font-semibold text-slate-900">
                                {{ profile.user?.email || '-' }}
                            </p>
                        </div>

                        <div
                            v-if="profile.notes"
                            class="sm:col-span-2"
                        >
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Catatan
                            </p>

                            <p class="mt-2 rounded-xl bg-slate-50 p-4 text-sm leading-6 text-slate-700">
                                {{ profile.notes }}
                            </p>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </MemberShell>
</template>