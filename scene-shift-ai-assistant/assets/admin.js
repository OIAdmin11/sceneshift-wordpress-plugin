/**
 * Scene Shift admin page enhancements.
 * Toggles schedule fields when "all day" is checked so the form reads cleanly.
 */
(function () {
	'use strict';

	function syncAllDayToggle() {
		var allDay = document.querySelector('input[name="phone[allDay]"]');
		if (!allDay) return;

		var fieldNames = ['phone[startTime]', 'phone[endTime]', 'phone[timezone]'];
		var inputs = fieldNames
			.map(function (name) { return document.querySelector('[name="' + name + '"]'); })
			.filter(Boolean);

		function update() {
			var disabled = allDay.checked;
			inputs.forEach(function (input) {
				input.disabled = disabled;
				if (input.parentElement) {
					input.parentElement.style.opacity = disabled ? '0.5' : '1';
				}
			});
		}

		allDay.addEventListener('change', update);
		update();
	}

	function syncCustomTheme() {
		var theme = document.querySelector('select[name="chat[theme]"]');
		if (!theme) return;

		var colorFields = ['accentColor', 'surfaceColor', 'textColor', 'userBubbleColor', 'launcherColor']
			.map(function (name) { return document.querySelector('[name="chat[' + name + ']"]'); })
			.filter(Boolean);

		function update() {
			var visible = theme.value === 'custom';
			colorFields.forEach(function (input) {
				if (input.parentElement) {
					input.parentElement.style.display = visible ? '' : 'none';
				}
			});
		}

		theme.addEventListener('change', update);
		update();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () {
			syncAllDayToggle();
			syncCustomTheme();
		});
	} else {
		syncAllDayToggle();
		syncCustomTheme();
	}
})();
