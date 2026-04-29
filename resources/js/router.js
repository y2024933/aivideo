import { createRouter, createWebHistory } from 'vue-router'

const routes = [
    { path: '/', name: 'home', component: () => import('./pages/CaseList.vue') },
    { path: '/cases/new', name: 'case.create', component: () => import('./pages/CaseCreate.vue') },
    { path: '/cases/:id/character', name: 'case.character', component: () => import('./pages/CaseCharacter.vue') },
    { path: '/cases/:id/script', name: 'case.script', component: () => import('./pages/CaseScript.vue') },
    { path: '/cases/:id/images', name: 'case.images', component: () => import('./pages/CaseImages.vue') },
    { path: '/cases/:id/final', name: 'case.final', component: () => import('./pages/CaseFinal.vue') },
]

export default createRouter({
    history: createWebHistory(),
    routes,
})
