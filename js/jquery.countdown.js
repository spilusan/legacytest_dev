// Our countdown plugin takes a callback, a duration, and an optional message
$.fn.countDown = function (duration) {
    var container = $(this[0]).html(duration);
    var countdown = setInterval(function () {
        if (--duration) {
            container.html(duration);
        } else {
        	container.html(duration);
            clearInterval(countdown);
        }
    }, 1000);
};