var gulp = require('gulp');
var cssmin = require('gulp-clean-css');
var debug = require('gulp-debug');

gulp.task('css-minify', function () {
	return gulp.src('public/css/**/*.css')
		.pipe(cssmin({ debug: true }, function (details) {
			var save = ((details.stats.minifiedSize / details.stats.originalSize) * 100).toFixed(2);
			console.log(details.name + ': ' + details.stats.minifiedSize + ' (' + save + '%)');
		}))
		.pipe(debug({ title: 'CSS Minify:' }))
		.pipe(gulp.dest('public/css'));
});