/**
 * @file
 * Eslint-disable .*/

'use strict';

module.exports = (configs) => {
  return {
    entry: {},
    output: {
      filename: '[name]'
    },
    module: {
      rules: [
        {
          test: /\.(js|jsx)$/,
          exclude: /(node_modules)/,
          loader: 'babel-loader',
          query: {
            presets: [
              ['env', {
                'targets': {
                  'browsers': configs.browsersSupport
                }
              }]
            ]
          }
        }
      ]
    }
  };
};
