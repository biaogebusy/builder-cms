// eslint-disable-next-line
import { defineConfig } from 'vite';

export default defineConfig(({ mode }) => {
  return {
    build: {
      manifest: true,
      rollupOptions: {
        input: 'js/third-party.js',
        output: {
          entryFileNames: (assetInfo) => {
            return `${assetInfo.name}.js`;
          },
        },
      },
      lib: {
        entry: 'js/third-party.js',
        name: 'ThirdPartyLib',
        formats: ['iife'],
        fileName: () => 'js/third-party.js',
      },
    },
    css: { devSourcemap: true },
    define: {
      'process.env.NODE_ENV':
        mode === 'production' ? '"production"' : '"development"',
    },
  };
});
