var gulp = require('gulp');
var runSequence = require('run-sequence').use(gulp);

gulp.task('css-build-release', function () {
    return runSequence([
        'css-static-copy', 
        'css-sass', 
        'css-minify'
    ]);
});
