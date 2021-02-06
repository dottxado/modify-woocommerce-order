(function ($) {
	var countdown = $('*[data-action="countdown"]');
	if (countdown.length === 0) {
		return;
	}
	var countdown_time = countdown.text();
	var countdown_exploded = countdown_time.match(/(\d{1,2}):(\d{1,2})/);
	var countdown_minutes = parseInt(countdown_exploded[1]);
	var countdown_seconds = parseInt(countdown_exploded[2]);
	var time = 60 * countdown_minutes + countdown_seconds;
	startTimer(time, countdown);

	function startTimer(duration, display) {
		var timer = duration, minutes, seconds;
		var intervalId = setInterval(function () {
			minutes = parseInt(timer / 60, 10);
			seconds = parseInt(timer % 60, 10);

			if (minutes === 0 && seconds === 0) {
				clearInterval(intervalId);
			}

			minutes = minutes < 10 ? "0" + minutes : minutes;
			seconds = seconds < 10 ? "0" + seconds : seconds;

			display.text(minutes + ":" + seconds);

			if (--timer < 0) {
				timer = duration;
			}
		}, 1000);
	}

})(jQuery);