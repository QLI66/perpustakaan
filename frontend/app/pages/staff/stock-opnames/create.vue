<script setup lang="ts">
import { FetchError } from 'ofetch'
import { useStockOpnamesService } from '~/services/stock-opnames'
import type { StockOpnameLocation } from '~/services/stock-opnames'
import type { ApiError } from '~/types/api'

definePageMeta({
  middleware: 'staff',
})

const router = useRouter()
const authStore = useAuthStore()
const stockOpnamesService = useStockOpnamesService()
const { apiFetch } = useApi()

const saving = ref(false)
const loadingLocations = ref(false)
const errorMessage = ref('')

const locations = ref<StockOpnameLocation[]>([])

const form = reactive({
  opname_date: new Date().toISOString().slice(0, 10),
  location_id: null as number | null,
  notes: '',
})

const loadLocations = async () => {
  loadingLocations.value = true

  try {
    const response = await apiFetch<{ items: StockOpnameLocation[] }>('/locations', {
      method: 'GET',
      headers: {
        Accept: 'application/json',
        Authorization: authStore.token
          ? `Bearer ${authStore.token}`
          : '',
      },
      params: {
        per_page: 100,
        page: 1,
      },
    })

    locations.value = response.data.items || []
  } catch (error) {
    console.error('Gagal memuat lokasi:', error)
    locations.value = []
  } finally {
    loadingLocations.value = false
  }
}

const cancel = () => {
  router.push('/staff/stock-opnames')
}

const save = async () => {
  errorMessage.value = ''

  if (!form.opname_date) {
    errorMessage.value = 'Tanggal opname wajib diisi.'
    return
  }

  saving.value = true

  try {
    const response = await stockOpnamesService.createStockOpname({
      opname_date: form.opname_date,
      location_id: form.location_id,
      notes: form.notes || null,
    })

    router.push(`/staff/stock-opnames/${response.data.id}`)
  } catch (error: unknown) {
    const payload = error instanceof FetchError
      ? error.data as ApiError | undefined
      : undefined

    errorMessage.value =
      payload?.message ||
      (error instanceof Error ? error.message : undefined) ||
      'Gagal membuat Stock Opname.'
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  loadLocations()
})
</script>

<template>
  <StaffShell active="stock-opnames">
  <div class="mx-auto max-w-3xl space-y-6">
    <div>
      <button
        type="button"
        class="mb-3 text-sm font-medium text-slate-500 hover:text-slate-700"
        @click="cancel"
      >
        ← Kembali ke Stock Opname
      </button>

      <h1 class="text-2xl font-bold text-slate-900">
        Buat Stock Opname
      </h1>

      <p class="mt-1 text-sm text-slate-500">
        Buat dokumen opname baru sebelum proses pemeriksaan fisik dimulai.
      </p>
    </div>

    <div
      v-if="errorMessage"
      class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
    >
      {{ errorMessage }}
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
      <div class="space-y-5">
        <div>
          <label class="mb-1.5 block text-sm font-medium text-slate-700">
            Tanggal Opname
            <span class="text-red-500">*</span>
          </label>

          <input
            v-model="form.opname_date"
            type="date"
            class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
          />

          <p class="mt-1.5 text-xs text-slate-500">
            Tanggal pelaksanaan stock opname.
          </p>
        </div>

        <div>
          <label class="mb-1.5 block text-sm font-medium text-slate-700">
            Lokasi
          </label>

          <select
            v-model="form.location_id"
            :disabled="loadingLocations"
            class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100 disabled:cursor-not-allowed disabled:bg-slate-50"
          >
            <option :value="null">
              Semua Lokasi
            </option>

            <option
              v-for="location in locations"
              :key="location.id"
              :value="location.id"
            >
              {{ location.code }} — {{ location.name }}
            </option>
          </select>

          <p class="mt-1.5 text-xs text-slate-500">
            Pilih lokasi tertentu atau biarkan Semua Lokasi.
          </p>
        </div>

        <div>
          <label class="mb-1.5 block text-sm font-medium text-slate-700">
            Catatan
          </label>

          <textarea
            v-model="form.notes"
            rows="5"
            maxlength="5000"
            placeholder="Contoh: Stock opname awal semester..."
            class="w-full resize-y rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
          />

          <div class="mt-1 flex justify-end text-xs text-slate-400">
            {{ form.notes.length }}/5000
          </div>
        </div>
      </div>

      <div class="mt-8 flex flex-col-reverse gap-3 border-t border-slate-200 pt-5 sm:flex-row sm:justify-end">
        <button
          type="button"
          class="rounded-lg border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
          :disabled="saving"
          @click="cancel"
        >
          Batal
        </button>

        <button
          type="button"
          class="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
          :disabled="saving"
          @click="save"
        >
          {{ saving ? 'Menyimpan...' : 'Simpan Stock Opname' }}
        </button>
      </div>
    </div>
  </div>
  </StaffShell>
</template>


