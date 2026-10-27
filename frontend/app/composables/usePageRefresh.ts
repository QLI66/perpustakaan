export const usePageRefresh = (
    refresh: () => Promise<void>,
    intervalMs?: number,
) => {
    let interval: ReturnType<typeof setInterval> | null = null
    let activityTimer: ReturnType<typeof setTimeout> | null = null
    let refreshing = false

    const runRefresh = async () => {
        if (
            refreshing ||
            document.visibilityState !== 'visible'
        ) {
            return
        }

        refreshing = true

        try {
            await refresh()
        } finally {
            refreshing = false
        }
    }

    const onActivity = () => {
        if (document.visibilityState !== 'visible') {
            return
        }

        if (activityTimer) {
            clearTimeout(activityTimer)
        }

        activityTimer = setTimeout(() => {
            activityTimer = null
            void runRefresh()
        }, 150)
    }

    onMounted(() => {
        window.addEventListener('focus', onActivity)
        document.addEventListener('visibilitychange', onActivity)

        if (intervalMs) {
            interval = setInterval(() => {
                void runRefresh()
            }, intervalMs)
        }
    })

    onUnmounted(() => {
        window.removeEventListener('focus', onActivity)
        document.removeEventListener('visibilitychange', onActivity)

        if (interval) {
            clearInterval(interval)
            interval = null
        }

        if (activityTimer) {
            clearTimeout(activityTimer)
            activityTimer = null
        }
    })
}
