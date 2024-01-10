define([
	'jquery',
	'underscore',
	'Backbone',
	'handlebars',
	'libs/jquery.calendar',
	'text!templates/components/dateRangePicker/tpl/index.html'
], function(
	$, 
	_, 
	Backbone, 
	Hb,
	jquryCalendar,
	Tpl
){
	var view = Backbone.View.extend({
		color: '#087EC5',
		months: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
		days:  ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
		popup: null,
		dateFrom: null,
		dateTo: null,
		template: Handlebars.compile(Tpl),
		element: null,
		aligment: null,
		initialize: function () {
			var thisView = this;
			this.formattedDate = this.formattedDate.bind(this);
			this.renderCalendar = this.renderCalendar.bind(this);
			this.reRenderCalendar = this.reRenderCalendar.bind(this);
			this.resize = this.resize.bind(this);

			$( window ).resize(function() {
				thisView.resize();
			});
			
			$(document).mouseup(function(e) {
				var container = $("#shipservDateRangePicker");

				if (container.length) {
					// if the target of the click isn't the container nor a descendant of the container
					if (!container.is(e.target) && container.has(e.target).length === 0) {
						container.remove();
					}
				}
			});
		},

		show: function(element, aligment, callback, dateFrom, dateTo) {
			if ($("#shipservDateRangePicker").length) {
				// already visible, close it instead (toggle)
				$("#shipservDateRangePicker").remove();
				return;
			}

			this.element = element;
			this.aligment = aligment;

			var thisView = this;
			if (dateFrom && dateTo) {
				var today = dateTo;
				pervYear = dateFrom;
			} else {
				var today = new Date();
				pervYear = new Date(new Date().setFullYear(today.getFullYear() - 1));
			}
			
			this.popup = $(this.template());
			$('body').append(this.popup);

			var isRangeSelected = false;
			var dataRange;
			$('.shipserv-rangeselect').each(function(){
				if ($(this).data('range') !== 'custom') {
					dataRange = thisView.dateRangeByKey($(this).data('range'));
					if (
						thisView.formattedDate(dataRange.from) === thisView.formattedDate(pervYear)
						&& thisView.formattedDate(dataRange.to) === thisView.formattedDate(today)) {
						$(this).addClass('selected');
						isRangeSelected = true;
					}
				} else {
					if (isRangeSelected === false) {
						$(this).addClass('selected');
					}
				}
			});

			this.resize();

			var calendarFrom = $('#shipservCalendarFrom');
			var calendarTo = $('#shipservCalendarTo');
			var calendarFromLabel = $('#shipservCalendarFromLabel');
			var calendarToLabel = $('#shipservCalendarToLabel');
			calendarFrom.data('date', pervYear);
			calendarTo.data('date', today);

			calendarFromLabel.val(this.formattedDate(pervYear));
			calendarToLabel.val(this.formattedDate(today));
			
			this.renderCalendar(calendarFrom, calendarFromLabel, pervYear);
			this.renderCalendar(calendarTo, calendarToLabel, today);

			$(this.popup).find('.shipservCalendarClose').click(function(e){
				e.preventDefault();
				$(thisView.popup).remove();
			});

			$(this.popup).find('.shipservCalendarSelect').click(function(e){
				e.preventDefault();
				if (typeof callback === "function") {
						var fromDate = calendarFrom.data('date');
						var toDate = calendarTo.data('date');
						callback(fromDate, toDate, thisView.formattedDate(fromDate), thisView.formattedDate(toDate));
				}
				$(thisView.popup).remove();
			});
			
			calendarFromLabel.keyup(function(){
				thisView.reRenderCalendar($(this).val(), calendarFrom, calendarFromLabel);
				$('.shipserv-rangeselect').each(function(){
					$(this).removeClass('selected');
				});
				$('.customrange').addClass('selected');
			});

			calendarToLabel.keyup(function(){
				thisView.reRenderCalendar($(this).val(), calendarTo, calendarToLabel);
				$('.shipserv-rangeselect').each(function(){
					$(this).removeClass('selected');
				});
				$('.customrange').addClass('selected');
			});

			this.rangeSelectorClickToggle();
		},

		formattedDate: function(d) {
			var leadedDay = (d.getDate() < 10) ? '0' + d.getDate() : d.getDate();
 			var convertedDate = leadedDay + "-" + this.months[d.getMonth()] + "-" + d.getFullYear();
			return convertedDate;
		},

		renderCalendar: function(element, updateElement, startDate) {
			var thisView = this;
			element.data('date', startDate);
			element.calendar({
				color: this.color,
				months: this.months,
				days:  this.days,
				startDate: startDate,
				onSelect: function (event) {
					element.data('date', event.date);
					$(updateElement).val(thisView.formattedDate(event.date));
					$('.shipserv-rangeselect').each(function(){
						$(this).removeClass('selected');
					});
					$('.customrange').addClass('selected');
				}
			});
		},

		reRenderCalendar: function(dateText, calendar, calendarLabel) {
			var months = this.months.map(function(v) { return v.toLowerCase() });
			var elementParts = dateText.split('-');

			if (elementParts.length === 3) {
				var year = parseInt(elementParts[2]);
				var month = months.indexOf(elementParts[1].toLowerCase());
				var day = parseInt(elementParts[0]);
				if (year>1000 && day <= 31 && (month > 0 || month <= 12)) {
					var selectedDate = new Date(year, month, day);
					this.renderCalendar(calendar, calendarLabel, selectedDate);
				}
			}
		},

		rangeSelectorClickToggle: function() {
			var thisView = this;
			var calendarFrom = $('#shipservCalendarFrom');
			var calendarTo = $('#shipservCalendarTo');
			var calendarFromLabel = $('#shipservCalendarFromLabel');
			var calendarToLabel = $('#shipservCalendarToLabel');

			$('.shipserv-rangeselect').click(function() {
				$('.shipserv-rangeselect').each(function(){
					$(this).removeClass('selected');
				});

				$(this).addClass('selected');
				var rangeKey = parseInt($(this).data('range'));

				var dateRange = thisView.dateRangeByKey(rangeKey);
				calendarFromLabel.val(thisView.formattedDate(dateRange.from));
				calendarToLabel.val(thisView.formattedDate(dateRange.to));
				thisView.renderCalendar(calendarFrom, calendarFromLabel, dateRange.from);
				thisView.renderCalendar(calendarTo, calendarToLabel, dateRange.to);
			});
		},


		dateRangeByKey: function(rangeKey) {
			var today, prevDate;

			switch (rangeKey) {
				case 1:
					today = new Date();
					prevDate = new Date();
					prevDate.setDate(prevDate.getDate() - 7);
					break;
				case 2:
					today = new Date();
					prevDate = new Date();
					prevDate.setMonth(prevDate.getMonth() - 1);
					break;
				case 3:
					today = new Date();
					prevDate = new Date();
					prevDate.setMonth(prevDate.getMonth() - 3);
					break;
				case 4:
					today = new Date();
					prevDate = new Date(new Date().setFullYear(today.getFullYear() - 1));
					break;
				case 5:
					today = new Date(new Date().setFullYear(new Date().getFullYear() - 1));
					prevDate = new Date(new Date().setFullYear(new Date().getFullYear() - 2));
					break;
				default:
					today = new Date();
					prevDate = new Date(new Date().setFullYear(today.getFullYear() - 1));
					break;
			}

			return {
				from: prevDate,
				to: today
			};
		},

		resize: function() {
			// position the selector to the provided element
			if ($("#shipservDateRangePicker").length) {
				var clickedElementPosition = this.element.offset();
				var topPos = Math.round(clickedElementPosition.top + 45);
				var leftPos = 0;

				switch(this.aligment) {
					case 'right':
						leftPos = Math.round(clickedElementPosition.left) - Math.round($("#shipservDateRangePicker").width() - this.element.width() - 12);
						break;
					case 'center':
						leftPos = Math.round(clickedElementPosition.left) - Math.round(($("#shipservDateRangePicker").width() / 2) - (this.element.width() / 2) - 6);
						break;
					default: 
						leftPos = Math.round(clickedElementPosition.left);
						break;
				}

				$("#shipservDateRangePicker").css('top', topPos + 'px');
				$("#shipservDateRangePicker").css('left', leftPos + 'px');
			}
		}
});

	return new view();
});
