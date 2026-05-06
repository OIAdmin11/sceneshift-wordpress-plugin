/**
 * Scene Shift phone switcher.
 * Re-evaluates the AI vs alternative number on the visitor side every minute
 * so the displayed number stays accurate without a server round-trip.
 *
 * Data flows in as a JSON object on the wrapper element:
 *   data-scene-shift-phone='{ "allDay": true, "startMinute": 540, ... }'
 *
 * Schedule semantics mirror `pickWordpressPhoneToShow` in the portal:
 *  - allDay=true: AI number always wins (when present).
 *  - allDay=false: AI number inside [startMinute, endMinute) in the configured
 *    timezone. Overnight windows (start > end) span midnight.
 *  - days=[]: every weekday. Otherwise restrict to the listed weekdays (0=Sun).
 *  - alternativeNumber blank: the block is hidden outside the AI window.
 */
(function () {
	'use strict';

	function parseSchedule(el) {
		try {
			var raw = el.getAttribute('data-scene-shift-phone');
			if (!raw) return null;
			var parsed = JSON.parse(raw);
			return parsed && typeof parsed === 'object' ? parsed : null;
		} catch (_e) {
			return null;
		}
	}

	function nowInTimezone(timezone) {
		try {
			var fmt = new Intl.DateTimeFormat('en-US', {
				timeZone: timezone || 'UTC',
				hour12: false,
				weekday: 'short',
				hour: '2-digit',
				minute: '2-digit',
			});
			var parts = fmt.formatToParts(new Date());
			var map = {};
			for (var i = 0; i < parts.length; i++) {
				map[parts[i].type] = parts[i].value;
			}
			var weekdays = { Sun: 0, Mon: 1, Tue: 2, Wed: 3, Thu: 4, Fri: 5, Sat: 6 };
			var weekday = weekdays[map.weekday];
			var hour = parseInt(map.hour, 10);
			if (hour === 24) hour = 0;
			var minute = parseInt(map.minute, 10);
			if (weekday === undefined || isNaN(hour) || isNaN(minute)) return null;
			return { weekday: weekday, minute: hour * 60 + minute };
		} catch (_e) {
			return null;
		}
	}

	function isInWindow(schedule) {
		var local = nowInTimezone(schedule.timezone);
		if (!local) return false;
		if (Array.isArray(schedule.days) && schedule.days.length > 0 && schedule.days.indexOf(local.weekday) === -1) {
			return false;
		}
		var start = parseInt(schedule.startMinute, 10) || 0;
		var end = parseInt(schedule.endMinute, 10) || 0;
		if (start === end) return false;
		if (start < end) return local.minute >= start && local.minute < end;
		return local.minute >= start || local.minute < end;
	}

	function pick(schedule) {
		var ai = (schedule.aiNumber || '').trim();
		var alt = (schedule.alternativeNumber || '').trim();
		var showAi = ai && (schedule.allDay || isInWindow(schedule));
		if (showAi) return { number: ai, label: schedule.aiLabel || 'AI assistant' };
		if (alt) return { number: alt, label: schedule.alternativeLabel || 'Live agent' };
		return null;
	}

	function applyTo(el) {
		var schedule = parseSchedule(el);
		if (!schedule) return;
		var choice = pick(schedule);
		var labelEl = el.querySelector('[data-scene-shift-phone-label]');
		var linkEl = el.querySelector('[data-scene-shift-phone-link]');
		var numberEl = el.querySelector('[data-scene-shift-phone-number]');
		if (!choice) {
			el.style.display = 'none';
			return;
		}
		el.style.display = '';
		if (labelEl) labelEl.textContent = choice.label;
		if (numberEl) numberEl.textContent = choice.number;
		if (linkEl) linkEl.setAttribute('href', 'tel:' + choice.number.replace(/[^0-9+]/g, ''));
	}

	function refreshAll() {
		var nodes = document.querySelectorAll('[data-scene-shift-phone]');
		for (var i = 0; i < nodes.length; i++) applyTo(nodes[i]);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', refreshAll, { once: true });
	} else {
		refreshAll();
	}
	setInterval(refreshAll, 60 * 1000);
})();
