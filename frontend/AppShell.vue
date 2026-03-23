<script setup>
import { computed } from 'vue';
import CustomerPage from './pages/CustomerPage.vue';
import AdminListPage from './pages/AdminListPage.vue';
import AdminFormPage from './pages/AdminFormPage.vue';
import AdminLoginPage from './pages/AdminLoginPage.vue';

const mountNode = document.getElementById('app');
const payloadNode = document.getElementById('app-payload');

const appName = mountNode?.dataset.app ?? 'customer';
const payload = payloadNode?.textContent ? JSON.parse(payloadNode.textContent) : {};

const component = computed(() => {
    switch (appName) {
        case 'admin':
            return AdminListPage;
        case 'admin-form':
            return AdminFormPage;
        case 'admin-login':
            return AdminLoginPage;
        default:
            return CustomerPage;
    }
});
</script>

<template>
    <component :is="component" :payload="payload" />
</template>
