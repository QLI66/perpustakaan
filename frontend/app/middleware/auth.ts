export default defineNuxtRouteMiddleware(async (to) => {
    const authStore = useAuthStore()

    await authStore.hydrate()

    if (!authStore.isAuthenticated) {
        return navigateTo('/login')
    }

    const isMemberRoute =
        to.path === '/member' ||
        to.path.startsWith('/member/')

    if (!isMemberRoute || authStore.hasRole('MEMBER')) {
        return
    }

    if (authStore.hasRole('SUPER_ADMIN')) {
        return navigateTo('/admin')
    }

    if (authStore.hasRole('LIBRARY_STAFF')) {
        return navigateTo('/staff')
    }

    return navigateTo('/')
})