import { defineConfig } from 'vite';
import nette from '@nette/vite-plugin';

export default defineConfig({
	plugins: [
		nette({
			entry: ['admin.js', 'front.js'],
		}),
	],

	build: {
		emptyOutDir: true,
	},

	css: {
		devSourcemap: true,
		preprocessorOptions: {
			scss: {
				// Bootstrap 5.3 still uses @import and legacy color functions internally
				quietDeps: true,
				silenceDeprecations: ['import', 'global-builtin', 'color-functions'],
			},
		},
	},
});
