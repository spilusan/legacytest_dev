var gulp = require('gulp');
var debug = require('gulp-debug');

gulp.task('css-static-copy', function () {
	gulp.src('css/**/*.*')
		.pipe(debug({ title: 'CSS Copy:' }))
		.pipe(gulp.dest('public/css'));
});