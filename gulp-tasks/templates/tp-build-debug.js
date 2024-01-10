var gulp = require('gulp');
var debug = require('gulp-debug');

gulp.task('tp-build-debug', function () {
	gulp
		.src('js/**/*.html')
		.pipe(debug({ title: 'HTML Template:' }))
		.pipe(gulp.dest('public/js'));
});