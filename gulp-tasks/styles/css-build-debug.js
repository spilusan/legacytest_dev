var gulp = require('gulp');
var cssSass = require('./css-sass.js');
var runSequence = require('run-sequence').use(gulp);

gulp.task('css-build-debug', function () {
    cssSass({
        sourcemap: true
    });

    return runSequence([
        'css-static-copy',
        'css-sass'
    ]);
});

