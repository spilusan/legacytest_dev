var gulp = require('gulp');
var sourcemaps = require('gulp-sourcemaps');
var gulpSass = require('gulp-sass');
var debug = require('gulp-debug');
var gulpIgnore = require('gulp-ignore');

var options = {
    sourcemap: false,
    logging: true
};

/**
 * ~/ShipServPages$ gulp css-compass
 * 
 * Compile compass files
 */
gulp.task('css-sass', function () {
    var sass = gulpSass({
        includePaths: [
            'compass/sass/'
        ]
    });

    var flow = null;

    flow = gulp.src('compass/sass/**/*.scss')
        .pipe(gulpIgnore.exclude('**/_*.scss'));

    if (options.sourcemap) {
        flow = flow.pipe(sourcemaps.init());
    }

    flow = flow.pipe(debug({ title: 'SASS', showFiles: options.logging }))
        .pipe(sass);

    if (options.sourcemap) {
        flow = flow.pipe(sourcemaps.write('./maps'));
    }

    return flow.pipe(gulp.dest('public/css'));
});

module.exports = function (setOptions) {
    Object.assign(options, setOptions);
};



