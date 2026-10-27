type VisibilityDocument = Pick<
    Document,
    'visibilityState' | 'addEventListener' | 'removeEventListener'
>

type RefreshReservations = () => Promise<unknown>

export const createVisibilityPolling = (
    document: VisibilityDocument,
    refresh: RefreshReservations,
    poll: () => void,
    intervalMs = 5000,
) => {
    let timer: ReturnType<typeof setInterval> | null = null
    let generation = 0
    let started = false
    let disposed = false

    const stopPolling = () => {
        if (timer !== null) {
            clearInterval(timer)
            timer = null
        }
    }

    const startPolling = () => {
        stopPolling()

        if (disposed || document.visibilityState !== 'visible') {
            return
        }

        timer = setInterval(() => {
            if (document.visibilityState !== 'visible') {
                stopPolling()
                return
            }

            poll()
        }, intervalMs)
    }

    const refreshAndResumePolling = async (
        refreshReservations: RefreshReservations,
    ) => {
        const currentGeneration = ++generation
        await refreshReservations()

        if (!disposed && currentGeneration === generation) {
            startPolling()
        }
    }

    const onVisibilityChange = () => {
        generation++
        stopPolling()

        if (document.visibilityState === 'visible') {
            void refreshAndResumePolling(refresh)
        }
    }

    return {
        start: (initialRefresh: RefreshReservations = refresh) => {
            if (started || disposed) {
                return Promise.resolve()
            }

            started = true
            document.addEventListener('visibilitychange', onVisibilityChange)
            return refreshAndResumePolling(initialRefresh)
        },
        dispose: () => {
            if (disposed) {
                return
            }

            disposed = true
            generation++
            stopPolling()
            document.removeEventListener(
                'visibilitychange',
                onVisibilityChange,
            )
        },
    }
}
