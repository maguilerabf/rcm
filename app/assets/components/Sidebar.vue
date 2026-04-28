<template>
    <!-- Mobile overlay -->
    <TransitionRoot as="template" :show="open">
        <Dialog as="div" class="relative z-40 lg:hidden" @close="$emit('close')">
            <TransitionChild as="template" enter="transition-opacity ease-linear duration-200" enter-from="opacity-0" enter-to="opacity-100" leave="transition-opacity ease-linear duration-150" leave-from="opacity-100" leave-to="opacity-0">
                <div class="fixed inset-0 bg-slate-900/60" />
            </TransitionChild>
            <div class="fixed inset-0 flex">
                <TransitionChild as="template" enter="transition ease-in-out duration-200 transform" enter-from="-translate-x-full" enter-to="translate-x-0" leave="transition ease-in-out duration-150 transform" leave-from="translate-x-0" leave-to="-translate-x-full">
                    <DialogPanel class="relative flex w-72 flex-col bg-slate-900">
                        <SidebarContent @navigate="$emit('close')" />
                    </DialogPanel>
                </TransitionChild>
            </div>
        </Dialog>
    </TransitionRoot>

    <!-- Desktop -->
    <aside class="hidden lg:flex lg:w-64 lg:flex-col lg:fixed lg:inset-y-0 z-30">
        <SidebarContent />
    </aside>
</template>

<script setup>
import { Dialog, DialogPanel, TransitionChild, TransitionRoot } from '@headlessui/vue';
import SidebarContent from './SidebarContent.vue';

defineProps({ open: Boolean });
defineEmits(['close']);
</script>
