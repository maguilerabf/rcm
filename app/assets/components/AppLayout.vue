<template>
    <div class="min-h-screen flex bg-slate-50">
        <Sidebar :open="sidebarOpen" @close="sidebarOpen = false" />

        <div class="flex-1 flex flex-col min-w-0 lg:pl-64">
            <Header @toggle-sidebar="sidebarOpen = !sidebarOpen" />

            <main class="flex-1 px-4 sm:px-6 lg:px-8 py-6">
                <RouterView />
            </main>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { RouterView } from 'vue-router';
import Sidebar from './Sidebar.vue';
import Header from './Header.vue';

const sidebarOpen = ref(false);
</script>
