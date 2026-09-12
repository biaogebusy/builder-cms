/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

import path from 'path';
import webpack from 'webpack';
import TerserPlugin from 'terser-webpack-plugin';
import { createRequire } from 'module';

const require = createRequire(import.meta.url);
const manifest = require('./node_modules/ckeditor5/build/ckeditor5-dll.manifest.json');
const glob = require('glob');

const entries = glob.sync('./{modules/*/js,js}/ckeditor5_plugins/**/*.js').reduce((entries, entry) => {
    const entryName = path.parse(entry).name;
    if (entryName !== 'index') {
        entries[entryName] = entry.replace(entryName + '/src/' + entryName + '.js', '');
    }
    return entries;
}, {});

const configs = [];
Object.entries(entries).forEach((mapping) => {
    const name = mapping[0];
    const dir = mapping[1];

    const bc = {
        mode: 'production',
        optimization: {
            minimize: true,
            minimizer: [
                new TerserPlugin({
                    terserOptions: {
                        output: {
                            comments: /^!/
                        }
                    },
                    test: /\.js(\?.*)?$/i,
                    extractComments: false,
                }),
            ],
            moduleIds: 'named',
        },
        entry: {
            path: dir.startsWith('./') ? dir + `${name}/src/index.js` : './' + dir + `${name}/src/index.js`,
        },
        output: {
            path: path.resolve(dir, '../build'),
            filename: `${name}.js`,
            library: ['CKEditor5', name],
            libraryTarget: 'umd',
            libraryExport: 'default',
        },
        plugins: [
            new webpack.BannerPlugin("Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.\nFor licensing, see https://ckeditor.com/legal/ckeditor-oss-license"),
            new webpack.DllReferencePlugin({
                manifest: manifest,
                scope: 'ckeditor5/src',
                name: 'CKEditor5.dll',
            }),
        ],
        module: {
            rules: [
                { test: /\.svg$/, use: 'raw-loader' },
                { test: /\.css$/, use: ['style-loader', 'css-loader'] }
            ],
        },
    };

    configs.push(bc);
});

export default configs;
