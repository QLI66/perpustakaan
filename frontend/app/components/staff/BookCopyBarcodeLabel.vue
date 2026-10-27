<script setup lang="ts">
import JsBarcode from 'jsbarcode'

const props = withDefaults(
    defineProps<{
        barcode: string
        title?: string
        inventoryNumber?: string | null
        schoolName?: string
        compact?: boolean
    }>(),
    {
        title: '',
        inventoryNumber: null,
        schoolName: 'Perpustakaan Sekolah',
        compact: false,
    },
)

const barcodeRef = ref<SVGSVGElement | null>(null)

const renderBarcode = () => {
    if (!barcodeRef.value || !props.barcode.trim()) {
        return
    }

    JsBarcode(
        barcodeRef.value,
        props.barcode.trim(),
        {
            format: 'CODE128',
            displayValue: true,
            fontSize: props.compact ? 12 : 14,
            height: props.compact ? 48 : 64,
            width: props.compact ? 1.6 : 2,
            margin: props.compact ? 6 : 10,
            textMargin: 4,
        },
    )
}

onMounted(renderBarcode)

watch(
    () => props.barcode,
    () => {
        nextTick(renderBarcode)
    },
)

const printBarcode = () => {
    if (!barcodeRef.value) {
        return
    }

    const svg = barcodeRef.value.outerHTML

    const printWindow = window.open(
        '',
        '_blank',
        'width=700,height=800',
    )

    if (!printWindow) {
        return
    }

    const escapedTitle = props.title
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;')

    const escapedInventory =
        (props.inventoryNumber ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;')

    const escapedSchool =
        props.schoolName
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;')

    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Barcode ${props.barcode}</title>
            <style>
                * {
                    box-sizing: border-box;
                }

                body {
                    margin: 0;
                    padding: 24px;
                    font-family: Arial, sans-serif;
                    background: white;
                    color: #111827;
                }

                .label {
                    width: 420px;
                    margin: 0 auto;
                    padding: 24px;
                    border: 1px solid #d1d5db;
                    border-radius: 12px;
                    text-align: center;
                }

                .school {
                    font-size: 12px;
                    font-weight: 700;
                    letter-spacing: 0.08em;
                    text-transform: uppercase;
                    margin-bottom: 12px;
                }

                .title {
                    font-size: 18px;
                    font-weight: 700;
                    margin-bottom: 12px;
                }

                .inventory {
                    margin-top: 8px;
                    font-family: monospace;
                    font-size: 12px;
                    color: #4b5563;
                }

                .barcode {
                    width: 100%;
                    height: auto;
                    margin-top: 8px;
                }

                @media print {
                    body {
                        padding: 0;
                    }

                    .label {
                        border: none;
                    }
                }
            </style>
        </head>
        <body>
            <div class="label">
                <div class="school">${escapedSchool}</div>
                ${escapedTitle ? `<div class="title">${escapedTitle}</div>` : ''}
                ${svg.replace(
                    '<svg',
                    '<svg class="barcode"',
                )}
                ${escapedInventory ? `<div class="inventory">Inventaris: ${escapedInventory}</div>` : ''}
            </div>

            <script>
                window.onload = function () {
                    window.print()
                }
            ${'</scr' + 'ipt>'}
        </body>
        </html>
    `)

    printWindow.document.close()

    printWindow.onafterprint = () => {
        try {
            printWindow.close()
        }
        catch {
            // Browser may already close the print window.
        }
    }

    printWindow.focus()
}
</script>

<template>
    <div
        class="rounded-2xl border border-slate-200 bg-white p-5"
    >
        <div class="text-center">
            <p class="text-xs font-bold uppercase tracking-widest text-slate-500">
                {{ schoolName }}
            </p>

            <p
                v-if="title"
                class="mt-2 font-bold text-slate-900"
            >
                {{ title }}
            </p>

            <div class="mt-4 flex justify-center overflow-hidden">
                <svg
                    ref="barcodeRef"
                    aria-label="Barcode buku"
                />
            </div>

            <p
                v-if="inventoryNumber"
                class="mt-2 font-mono text-xs text-slate-500"
            >
                Inventaris: {{ inventoryNumber }}
            </p>
        </div>

        <div class="mt-4 flex justify-center">
            <button
                type="button"
                class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800"
                @click="printBarcode"
            >
                Cetak Barcode
            </button>
        </div>
    </div>
</template>