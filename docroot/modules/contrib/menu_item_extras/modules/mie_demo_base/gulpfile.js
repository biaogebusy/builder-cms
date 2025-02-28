/**
 * @file
 * Eslint-disable .*/

'use strict';

/* SETUP */
const gulp = require('gulp');
const sourcemaps = require('gulp-sourcemaps');
const notify = require("gulp-notify");
const watch = require('gulp-watch');
const webpack = require('webpack');
const webpackStream = require('webpack-stream');
const gutil = require('gulp-util');
const glob = require('glob');
const path = require('path');
const plumber = require('gulp-plumber');
// SASS.
const sass = require('gulp-sass');
const sassLint = require('gulp-sass-lint');
const icomoonBuilder = require('gulp-icomoon-builder');
const autoprefixer = require('gulp-autoprefixer');
// JS.
const jshint = require('gulp-jshint');
const jshintStylish = require('jshint-stylish');

/* CONFIGURATION */
const configs = {
  bowerDir: './bower_components',
  npmDir: './node_modules',
  browsersSupport: ['last 2 versions', '> 2%', 'ie 11'],

  sassDocSrc: ['./scss/**/*.scss'],

  allScripts: ['./js/bundle/*.js', './js/standalone/*.js'],
  standaloneScripts: './js/standalone/*.js',
  bundleScripts: './js/bundle/*.js',
  scriptsDist: './js/dist',

  icomoon: ['./fonts/icomoon/selection.json'],

  testEnv: false
};
configs.sassIncludePaths = [`${configs.npmDir} / foundation - sites / scss`];

const webpackBundleConfig = require('./webpack.bundle.config')(configs);
const webpackStandaloneConfig = require('./webpack.standalone.config')(configs);

/* TASKS */

/* SCSS TASKS */
gulp.task('scss-lint', () => {
  gulp.src(configs.sassDocSrc)
    .pipe(sassLint({
      configFile: '.scss-lint.yml'
    }))
    .pipe(sassLint.format(notify()))
    .pipe(process.env.NODE_ENV && process.env.NODE_ENV === 'test' ? sassLint.failOnError() : gutil.noop());
});

gulp.task('scss-compile', () => {
  gulp.src(configs.sassDocSrc)
    .pipe(sourcemaps.init())
    .pipe(
      sass({
        includePaths: configs.sassIncludePaths
      })
        // Catch any SCSS errors and prevent them from crashing gulp.
        .on('error', function (error) {
          console.error('>>> ERROR', error);
          if (process.env.NODE_ENV && process.env.NODE_ENV === 'test') {
            process.exit.bind(process, 1);
          }
          else {
            notify().write(error);
            this.emit('end');
          }
        })
    )
    .pipe(autoprefixer(configs.browsersSupport))
    .pipe(sourcemaps.write())
    .pipe(gulp.dest('./css/'));
});

/* JS TASKS */
gulp.task('js-lint', () => {
  return gulp.src(configs.allScripts)
    .pipe(jshint())
    .pipe(jshint.reporter(jshintStylish))
    // Use gulp-notify as jshint reporter.
    .pipe(process.env.NODE_ENV && process.env.NODE_ENV === 'test' ? jshint.reporter('fail') : gutil.noop())
    .pipe(notify(function (file) {
      if (!file.jshint) {
        return false;
      }
      // Don't show something if success.
      if (file.jshint.success) {
        return false;
      }

      let errors = file.jshint.results.map(function (data) {
        if (data.error) {
          return `(${data.error.line}: ${data.error.character}) ${data.error.reason}`;
        }
      }).join("\n");

      return `${file.relative} (${file.jshint.results.length} errors)\n ${errors}`;
    }));
});

gulp.task('js-bundle', () => {
  gulp.src(configs.bundleScripts)
    .pipe(plumber(function (error) {
      gutil.log(error.message);
      if (process.env.NODE_ENV && process.env.NODE_ENV === 'test') {
        process.exit.bind(process, 1);
      }
      else {
        this.emit('end');
      }
    }))
    .pipe(webpackStream(webpackBundleConfig, webpack))
    .pipe(gulp.dest(configs.scriptsDist));
});

gulp.task('js-standalone', done => {
  glob(configs.standaloneScripts, {}, (err, files) => {
    if (err) {
      done(err);
    }
    files.map(entry => webpackStandaloneConfig.entry[path.basename(entry)] = entry);

    gulp.src(configs.standaloneScripts)
      .pipe(plumber(function (error) {
        gutil.log(error.message);
        if (process.env.NODE_ENV && process.env.NODE_ENV === 'test') {
          process.exit.bind(process, 1);
        }
        else {
          this.emit('end');
        }
      }))
      .pipe(webpackStream(webpackStandaloneConfig), webpack)
      .pipe(gulp.dest(configs.scriptsDist));

    webpackStandaloneConfig.entry = {};
    done();
  });
});

/* BUILD FONTS */
gulp.task('build-fonts', () => {
  gulp.src(configs.icomoon)
    .pipe(icomoonBuilder({
      templateType: 'map',
    }))
    .on('error', function (error) {
      console.log(error);
      notify().write(error);
    })

    .pipe(gulp.dest('scss/base'))
    .on('error', function (error) {
      console.log(error);
      notify().write(error);
    });
});

gulp.task('test', () => {
  process.env.NODE_ENV = 'test';

  gulp.start('scss-lint');


  gulp.start('scss-compile');
  gulp.start('js-lint');
  gulp.start('js-bundle');
  gulp.start('js-standalone');
});

/* WATCHER */
gulp.task('watch', () => {
  // SASS Watch.
  watch(configs.sassDocSrc, () => {
    gulp.start('scss-lint');
    gulp.start('scss-compile');
  });

  // JS Watch.
  /*watch(configs.allScripts, () => {
   gulp.start('js-lint');
   });*/
  watch(configs.standaloneScripts, () => {
    gulp.start('js-standalone');
  });
  watch(configs.bundleScripts, () => {
    gulp.start('js-bundle');
  });

  // Fonts Watch.
  watch(configs.icomoon, () => {
    gulp.start('build-fonts');
  });
});

gulp.task('default', ['watch']);
