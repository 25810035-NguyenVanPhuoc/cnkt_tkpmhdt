import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import router from './router';
import { setUnauthorizedHandler } from './api-client';
import { useAuthStore } from './stores/auth-store';

const app = createApp(App);
const pinia = createPinia();

app.use(pinia);
app.use(router);

setUnauthorizedHandler(() => {
    useAuthStore().logout();
    router.push({ name: 'login' });
});

app.mount('#app');
