import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from './stores/auth'

const routes = [
    { path: '/login', name: 'login', component: () => import('./pages/Login.vue'), meta: { guest: true } },
    { path: '/', name: 'home', component: () => import('./pages/CaseList.vue') },
    { path: '/cases/new', name: 'case.create', component: () => import('./pages/CaseCreate.vue') },
    { path: '/cases/:id/character', name: 'case.character', component: () => import('./pages/CaseCharacter.vue') },
    { path: '/cases/:id/script', name: 'case.script', component: () => import('./pages/CaseScript.vue') },
    { path: '/cases/:id/images', name: 'case.images', component: () => import('./pages/CaseImages.vue') },
    { path: '/cases/:id/final', name: 'case.final', component: () => import('./pages/CaseFinal.vue') },
]

const router = createRouter({
    history: createWebHistory(),
    routes,
})

router.beforeEach(async (to) => {
    const auth = useAuthStore()

    if (!auth.user && !to.meta.guest) {
        try { await auth.fetchUser() } catch {}
    }

    if (!auth.isLoggedIn && !to.meta.guest) return { name: 'login' }
    if (auth.isLoggedIn && to.meta.guest) return { name: 'home' }
})

export default router
