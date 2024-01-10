var gulp = require('gulp');
var bundle = require('gulp-bundle-assets');

var bundleConfig = {
    bundle: {
        main: {
            scripts: [
                './content/js/foo.js',
                './content/js/baz.js'
            ],
            styles: './content/**/*.css'
        },
        vendor: {
            scripts: './bower_components/angular/angular.js'
        }
    },
    copy: './content/**/*.{png,svg}'
};

gulp.task('bundle', function() {
    return gulp.src(bundleConfig)
        .pipe(bundle())
        .pipe(gulp.dest('public'));
});