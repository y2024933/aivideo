<script setup>
import { ref } from 'vue'
import { RouterLink, RouterView, useRouter, useRoute } from 'vue-router'
import { useAuthStore } from './stores/auth'
import Dropdown from './components/Dropdown.vue'
import DropdownLink from './components/DropdownLink.vue'
import NavLink from './components/NavLink.vue'
import ResponsiveNavLink from './components/ResponsiveNavLink.vue'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const showingNavigationDropdown = ref(false)

async function logout() {
    await auth.logout()
    router.push({ name: 'login' })
}
</script>

<template>
    <div>
        <div class="min-h-screen bg-gray-100">
            <!-- 導覽列（登入後顯示） -->
            <nav v-if="auth.isLoggedIn" class="bg-white border-b border-gray-100">
                <!-- 主導覽列 -->
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex justify-between h-16">
                        <div class="flex">
                            <!-- Logo -->
                            <div class="shrink-0 flex items-center">
                                <RouterLink to="/" class="text-lg font-bold text-gray-800">
                                    AI Video
                                </RouterLink>
                            </div>

                            <!-- 導覽連結 -->
                            <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                                <NavLink to="/" :active="route.name === 'home'">
                                    建案列表
                                </NavLink>
                                <NavLink to="/cases/new" :active="route.name === 'case.create'">
                                    新建案
                                </NavLink>
                                <NavLink to="/guide" :active="route.name === 'guide'">
                                    操作說明
                                </NavLink>
                            </div>
                        </div>

                        <div class="hidden sm:flex sm:items-center sm:ms-6">
                            <!-- 使用者下拉選單 -->
                            <div class="ms-3 relative">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <span class="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150"
                                            >
                                                {{ auth.user?.name || auth.user?.email || '使用者' }}

                                                <svg
                                                    class="ms-2 -me-0.5 h-4 w-4"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 20 20"
                                                    fill="currentColor"
                                                >
                                                    <path
                                                        fill-rule="evenodd"
                                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                        clip-rule="evenodd"
                                                    />
                                                </svg>
                                            </button>
                                        </span>
                                    </template>

                                    <template #content>
                                        <DropdownLink as="button" @click="logout">
                                            登出
                                        </DropdownLink>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>

                        <!-- 漢堡選單按鈕（手機版） -->
                        <div class="-me-2 flex items-center sm:hidden">
                            <button
                                @click="showingNavigationDropdown = !showingNavigationDropdown"
                                class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out"
                            >
                                <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                    <path
                                        :class="{
                                            hidden: showingNavigationDropdown,
                                            'inline-flex': !showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        :class="{
                                            hidden: !showingNavigationDropdown,
                                            'inline-flex': showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- 響應式導覽選單（手機版） -->
                <div
                    :class="{ block: showingNavigationDropdown, hidden: !showingNavigationDropdown }"
                    class="sm:hidden"
                >
                    <div class="pt-2 pb-3 space-y-1">
                        <ResponsiveNavLink to="/" :active="route.name === 'home'">
                            建案列表
                        </ResponsiveNavLink>
                        <ResponsiveNavLink to="/cases/new" :active="route.name === 'case.create'">
                            新建案
                        </ResponsiveNavLink>
                        <ResponsiveNavLink to="/guide" :active="route.name === 'guide'">
                            操作說明
                        </ResponsiveNavLink>
                    </div>

                    <!-- 響應式使用者資訊 -->
                    <div class="pt-4 pb-1 border-t border-gray-200">
                        <div class="px-4">
                            <div class="font-medium text-base text-gray-800">
                                {{ auth.user?.name || '使用者' }}
                            </div>
                            <div class="font-medium text-sm text-gray-500">
                                {{ auth.user?.email }}
                            </div>
                        </div>

                        <div class="mt-3 space-y-1">
                            <ResponsiveNavLink as="button" to="/" @click="logout">
                                登出
                            </ResponsiveNavLink>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- 頁面內容 -->
            <main>
                <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                    <RouterView />
                </div>
            </main>
        </div>
    </div>
</template>
