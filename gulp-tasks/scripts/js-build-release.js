var gulp = require('gulp');
var debug = require('gulp-debug');
var filter = require('gulp-filter');
var replace = require('gulp-replace');

gulp.task('js-build-release', function () {
    var libFilter = filter(['js/lib/**'], { restore: true });

    gulp.src('js/**/*.js*')
        .pipe(debug({ title: 'JavaScript:' }))
        .pipe(libFilter)
        .pipe(debug({ title: 'FixAMD:' }))
        .pipe(replace(/(&&\s+?define.amd)/g, '$1 && false')) // AMD fix (see bellow)
        .pipe(libFilter.restore)
        .pipe(gulp.dest('public/js'));
});

// AMD fix:
// Files in ./js/lib need to have AMD module functionality disabled, as 3rd party libaries that use jQuery
// as a module will break without this. When all SJ is loaded via requireJS, this will not be an issue. 