import assert from 'node:assert/strict'
import test from 'node:test'
import { createVisibilityPolling } from '../app/utils/visibility-polling.ts'

class MockVisibilityDocument {
    visibilityState = 'visible'
    listeners = new Set()

    addEventListener(_eventName, listener) {
        this.listeners.add(listener)
    }

    removeEventListener(_eventName, listener) {
        this.listeners.delete(listener)
    }

    changeVisibility(visibilityState) {
        this.visibilityState = visibilityState
        for (const listener of this.listeners) {
            listener()
        }
    }
}

test('polling stops while hidden and resumes once after a visible refresh', async (context) => {
    context.mock.timers.enable({ apis: ['setInterval'] })

    const document = new MockVisibilityDocument()
    let refreshCount = 0
    let pollCount = 0
    const polling = createVisibilityPolling(
        document,
        async () => {
            refreshCount++
        },
        () => {
            pollCount++
        },
    )

    await polling.start()
    assert.equal(refreshCount, 1)

    context.mock.timers.tick(5000)
    assert.equal(pollCount, 1)

    document.changeVisibility('hidden')
    context.mock.timers.tick(15000)
    assert.equal(pollCount, 1)

    document.changeVisibility('visible')
    await Promise.resolve()
    assert.equal(refreshCount, 2)

    document.changeVisibility('visible')
    await Promise.resolve()
    assert.equal(refreshCount, 3)

    context.mock.timers.tick(5000)
    assert.equal(pollCount, 2)

    polling.dispose()
    assert.equal(document.listeners.size, 0)
    context.mock.timers.tick(10000)
    assert.equal(pollCount, 2)
})

test('a refresh started before hiding cannot restart polling in the background', async (context) => {
    context.mock.timers.enable({ apis: ['setInterval'] })

    const document = new MockVisibilityDocument()
    let finishInitialRefresh
    let pollCount = 0
    const initialRefresh = new Promise((resolve) => {
        finishInitialRefresh = resolve
    })
    const polling = createVisibilityPolling(
        document,
        async () => {},
        () => {
            pollCount++
        },
    )

    const starting = polling.start(() => initialRefresh)
    document.changeVisibility('hidden')
    finishInitialRefresh()
    await starting

    context.mock.timers.tick(15000)
    assert.equal(pollCount, 0)

    document.changeVisibility('visible')
    await Promise.resolve()
    context.mock.timers.tick(5000)
    assert.equal(pollCount, 1)

    polling.dispose()
})
