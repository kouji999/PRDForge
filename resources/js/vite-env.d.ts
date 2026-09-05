/// <reference types="vite/client" />
/// <reference path="./ziggy.d.ts" />

interface ImportMetaEnv {
    readonly VITE_APP_NAME: string;
    readonly [key: string]: string | boolean | undefined;
}

interface ImportMeta {
    readonly env: ImportMetaEnv;
}

declare module '*.png' {
    const src: string;
    export default src;
}

declare module '*.jpg' {
    const src: string;
    export default src;
}

declare module '*.svg' {
    const src: string;
    export default src;
}
