declare module '*.vue' {
    import type { DefineComponent } from 'vue';
    const component: DefineComponent;
    export default component;
}
/// <reference types="vite-plugin-pwa/client" />

declare module 'virtual:pwa-register' {
    export function registerSW(options?: {
        immediate?: boolean;
    }): (reloadPage?: boolean) => Promise<void>;
}
