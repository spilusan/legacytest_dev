/*
 * For running gull you need the following requirements:
 * nodejs (a recent version)
 * npm install gulp-cli --global
 * npm install gulp run-sequence gulp-compass gulp-clean-css gulp-replace gulp-intercept --save-dev
 * 
 */

var gulp = require('gulp');
var del = require('del');
var requireDir = require('require-dir');
var runSequence = require('run-sequence');

var dir = requireDir('./gulp-tasks', {
	camelcase: true,
	recurse: true
});

// Watch .scss files in ./compass/sass and build
gulp.task('compass-watch', function () {
	dir.styles.cssSass({
		logging: true
	});

	try {
		return gulp.watch('compass/**/*.scss', [
			'css-sass'
		]);
	} catch (err) {
		Console.log('Error watching files./n/n');
		Console.log('If running linux try the following command: ');
		Console.log('"echo fs.inotify.max_user_watches=524288 | sudo tee -a /etc/sysctl.conf && sudo sysctl -p"');
	}
});

// Watch .css files in ./css
gulp.task('css-watch', function () {
	dir.styles.cssSass({
		logging: true
	});

	return gulp.watch('compass/**/*.scss', [
		'css-sass'
	]);
});

// Watch .js files in ./js and build
gulp.task('js-watch', function () {
	return gulp.watch('js/**/*.js', [
		'js-build-debug'
	]);
});

// Watch .html files in ./js and build
gulp.task('tp-watch', function () {
	return gulp.watch('js/**/*.html', [
		'tp-build-debug'
	]);
});

//------------ Main gulp tasks -------------

// Watch files and build run debug build
gulp.task('watch', [
	'build',
	'css-watch',
	'compass-watch',
	'js-watch',
	'tp-watch'
], function () {
	console.log('\nWatching files... (^C to stop)\n');
});

// Build for local debuging
gulp.task('build', [
	'css-build-debug',
	'js-build-debug',
	'tp-build-debug'
]);

// Build for production
gulp.task('release', [
	'css-build-release',
	'js-build-release',
	'tp-build-release'
]);

// Clean
gulp.task('clean', function () {
	return del([
		'public/css/*',
		'public/js/*'
	]);
});


gulp.task('default', ['watch']);





