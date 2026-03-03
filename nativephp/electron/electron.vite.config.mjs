import { join } from 'path';
import { defineConfig, externalizeDepsPlugin } from 'electron-vite';

export default defineConfig({
    main: {
        build: {
            rollupOptions: {
                plugins: [
                    {
                        name: 'watch-external',
                        buildStart() {
                            const appPath = typeof process.env.APP_PATH === 'string'
                                ? process.env.APP_PATH.trim()
                                : '';

                            if (appPath !== '') {
                                this.addWatchFile(join(appPath, 'app', 'Providers', 'NativeAppServiceProvider.php'));
                            }
                        }
                    }
                ]
            },
        },
        plugins: [externalizeDepsPlugin()]
    }
});
