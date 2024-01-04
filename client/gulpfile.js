const { src, dest, watch, series, parallel } = require('gulp'),
    autoprefixer  = require('gulp-autoprefixer'),
    cssimport     = require("gulp-cssimport"),
    cleancss      = require('gulp-clean-css'),
    concat        = require('gulp-concat-util'),
    del           = require('del'),
    gulpif 		= require('gulp-if'),
    path          = require('path'),
    plumber       = require('gulp-plumber'),
    sass          = require('gulp-sass')(require('sass')),
    sourcemaps    = require('gulp-sourcemaps'),
    stripdebug    = require('gulp-strip-debug'),
    uglify        = require('gulp-uglify'),
    through2		= require('through2');

//load paths
const paths = require('./gulp-paths.json');

// load script files
const scriptFiles = require('./gulp-script-builds.json');

const sassOptions = {
    errLogToConsole: true,
    outputStyle: 'compressed'
};

const autoprefixerOptions = {
    browserlist: ['last 2 versions', '> 1%', 'IE >= 9'],
    cascade: false,
    supports: false
};

var debugEnabled = false;

function styles(cb) {
    src(paths.styles.src + paths.styles.filter)
        .pipe(plumber({
            errorHandler: onError
        }))
        .pipe(sourcemaps.init())
        .pipe(cssimport({matchPattern: "*.css"}))
        .pipe(sass(sassOptions).on('error', sass.logError))
        .pipe(autoprefixer(autoprefixerOptions))
        .pipe(cleancss({processImport: true, keepSpecialComments: 0}))
        .pipe(sourcemaps.write('.', {
            sourceMappingURLPrefix: paths.themedir + '/' + paths.styles.dist
        }))
        .pipe( through2.obj( function( file, enc, cb ) {
            var date = new Date();
            file.stat.atime = date;
            file.stat.mtime = date;
            cb( null, file );
        }))
        .pipe(dest(paths.styles.dist));
    cb();
}

function scripts(cb) {
    var scriptNames = Object.keys(scriptFiles);
    scriptNames.forEach(function(scriptName) {
        src(
            scriptFiles[scriptName],
            {
                cwd: process.cwd(),
                nosort: true
            }
        )
            .pipe(plumber({
                errorHandler: onError
            }))
            .pipe(sourcemaps.init())
            .pipe(concat(scriptName))
            .pipe(
                gulpif(
                    !debugEnabled,
                    stripdebug()
                )
            )
            .pipe(
                gulpif(
                    !debugEnabled,
                    uglify({mangle: false, compress: {drop_console: true}})
                )
            )
            .pipe(sourcemaps.write('.', {
                sourceMappingURLPrefix: paths.themedir + '/' + paths.scripts.dist
            }))
            .pipe( through2.obj( function( file, enc, cb ) {
                var date = new Date();
                file.stat.atime = date;
                file.stat.mtime = date;
                cb( null, file );
            }))
            .pipe(dest(paths.scripts.dist));
    });
    cb();
}

function cleanscripts(cb) {
    del([
        paths.scripts.dist + paths.scripts.distfilter
    ]);
    cb();
}

function cleanstyles(cb) {
    del([
        paths.styles.dist + paths.styles.distfilter
    ]);
    cb();
}

function watchAll() {
    // watch for style changes
    watch(paths.styles.src + paths.styles.filter, series(cleanstyles, styles));
    // watch for script changes
    watch(paths.scripts.src + paths.scripts.filter, series(cleanscripts, scripts));
}

function enableDebug(cb) {
    debugEnabled = true;
    cb();
}

function onError(err) {
    console.log(err);
}

exports.clean = series(
    parallel(
        cleanstyles,
        cleanscripts,
    )
);

exports.build = series(
    parallel(
        cleanstyles,
        cleanscripts,
    ),
    parallel(
        styles,
        scripts,
    )
);

exports.css = series(
    cleanstyles,
    styles
);

exports.js = series(
    cleanscripts,
    scripts
);

exports.jsdebug = series(
    enableDebug,
    cleanscripts,
    scripts
);

exports.default = series(
    parallel(
        cleanstyles,
        cleanscripts,
    ),
    parallel(
        styles,
        scripts,
    ),
    watchAll
);

exports.debug = series(
    enableDebug,
    parallel(
        cleanstyles,
        cleanscripts,
    ),
    parallel(
        styles,
        scripts,
    )
);
