const path = require('path');
const webpack = require('webpack');
const { styles } = require('@ckeditor/ckeditor5-dev-utils');
const TerserPlugin = require('terser-webpack-plugin');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');

const pluginName = 'ckeditorTemplates';

module.exports = [
  {
    mode: 'production',
    optimization: {
      minimize: true,
      minimizer: [
        new TerserPlugin({
          terserOptions: {
            format: {
              comments: false
            }
          },
          test: /\.js(\?.*)?$/i,
          extractComments: false
        })
      ],
      moduleIds: 'named'
    },
    entry: {
      path: path.resolve(__dirname, 'js/ckeditor5_plugins/ckeditor_templates/src/app.js')
    },
    output: {
      path: path.resolve(__dirname, 'js/dist'),
      filename: `${pluginName}.js`,
      library: ['CKEditor5', pluginName],
      libraryTarget: 'umd',
      libraryExport: 'default'
    },
    plugins: [
      new webpack.DllReferencePlugin({
        manifest: require('./node_modules/ckeditor5/build/ckeditor5-dll.manifest.json'),
        scope: 'ckeditor5/src',
        name: 'CKEditor5.dll'
      }),
      new MiniCssExtractPlugin({
        filename: '../../css/cke5_templates.dialog.css'
      })
    ],
    module: {
      rules: [
        {
          test: /js[/\\]ckeditor5_plugins[/\\].*?[/\\]theme[/\\]icons[/\\][^/\\]+\.svg$/,
          use: ['raw-loader']
        },
        {
          test: /js[/\\]ckeditor5_plugins[/\\].*?[/\\]theme[/\\][^/\\]+\.css$/,
          use: [
            {
              loader: 'style-loader',
              options: {
                injectType: 'singletonStyleTag',
                attributes: {
                  'data-cke': true
                }
              }
            },
            {
              loader: 'postcss-loader',
              options: styles.getPostCssConfig({
                themeImporter: {
                  themePath: require.resolve('@ckeditor/ckeditor5-theme-lark')
                },
                minify: true
              })
            }
          ]
        },
        {
          test: /sass[/\\][^/\\]+\.s[ac]ss$/,
          use: [
            MiniCssExtractPlugin.loader,
            'css-loader',
            'sass-loader'
          ]
        }
      ]
    }
  }
];
