/**
 * Persian calendar administration behavior.
 *
 * Enhances the server-rendered RTL calendar with keyboard grid navigation and
 * progressive disclosure for the holiday/override forms. Everything remains
 * usable without JavaScript; the server is the only authority that validates
 * dates, times, permissions, and conflicts.
 */
(function () {
	'use strict';

	const PERSIAN_DIGITS = {
		'۰': '0', '۱': '1', '۲': '2', '۳': '3', '۴': '4',
		'۵': '5', '۶': '6', '۷': '7', '۸': '8', '۹': '9',
		'٠': '0', '١': '1', '٢': '2', '٣': '3', '٤': '4',
		'٥': '5', '٦': '6', '٧': '7', '٨': '8', '٩': '9'
	};
	const LATIN_TO_PERSIAN = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

	function toLatin(value) {
		return String(value || '').replace(/[۰-۹٠-٩]/g, function (digit) {
			return PERSIAN_DIGITS[digit] || digit;
		});
	}

	function toPersian(value) {
		return String(value || '').replace(/[0-9]/g, function (digit) {
			return LATIN_TO_PERSIAN[Number(digit)] || digit;
		});
	}

	function parseJalali(value) {
		const normalized = toLatin(value).trim();
		const match = normalized.match(/^(\d{4})[/\-.](\d{1,2})[/\-.](\d{1,2})$/);
		if (!match) {
			return null;
		}
		const parts = {
			year: Number(match[1]),
			month: Number(match[2]),
			day: Number(match[3])
		};
		if (parts.year < 1200 || parts.year > 1600 || parts.month < 1 || parts.month > 12 || parts.day < 1 || parts.day > 31) {
			return null;
		}
		return parts;
	}

	function normalizeDateInput(input) {
		const parsed = parseJalali(input.value);
		if (!parsed) {
			return;
		}
		input.value = toPersian(
			partsPad(parsed.year, 4) + '/' + partsPad(parsed.month, 2) + '/' + partsPad(parsed.day, 2)
		);
	}

	function partsPad(value, length) {
		return String(value).padStart(length, '0');
	}

	function ensureErrorNode(form) {
		let node = form.querySelector('[data-parisan-form-error]');
		if (!node) {
			node = document.createElement('p');
			node.className = 'parisan-calendar-form-error';
			node.setAttribute('data-parisan-form-error', '');
			node.setAttribute('role', 'alert');
			const submit = form.querySelector('p.submit, .submit');
			if (submit && submit.parentNode) {
				submit.parentNode.insertBefore(node, submit);
			} else {
				form.appendChild(node);
			}
		}
		return node;
	}

	function setFormError(form, message) {
		const node = ensureErrorNode(form);
		node.textContent = message || '';
	}

	/* Month grid keyboard navigation -------------------------------------- */

	function initGrid() {
		const grid = document.querySelector('[data-parisan-calendar-grid]');
		if (!grid) {
			return;
		}
		const isRtl = window.getComputedStyle(grid).direction === 'rtl';

		grid.addEventListener('keydown', function (event) {
			const target = event.target.closest('[data-calendar-day]');
			if (!target) {
				return;
			}
			const days = Array.prototype.slice.call(grid.querySelectorAll('[data-calendar-day]'));
			const index = days.indexOf(target);
			if (index < 0) {
				return;
			}
			let next = -1;

			switch (event.key) {
				case 'ArrowLeft':
					next = isRtl ? index + 1 : index - 1;
					break;
				case 'ArrowRight':
					next = isRtl ? index - 1 : index + 1;
					break;
				case 'ArrowDown':
					next = index + 7;
					break;
				case 'ArrowUp':
					next = index - 7;
					break;
				case 'Home':
					next = index - (index % 7);
					break;
				case 'End':
					next = index - (index % 7) + 6;
					break;
				case 'PageDown':
					next = index + 7;
					break;
				case 'PageUp':
					next = index - 7;
					break;
				default:
					return;
			}

			if (next < 0 || next >= days.length || !days[next]) {
				return;
			}
			event.preventDefault();
			days[next].focus();
		});
	}

	/* Override form ------------------------------------------------------- */

	function initOverrideForm() {
		const form = document.querySelector('[data-parisan-override-form]');
		if (!form) {
			return;
		}
		const scope = form.querySelector('#parisan-override-scope');
		const staff = form.querySelector('#parisan-override-staff');
		const state = form.querySelector('[data-parisan-override-state]');
		const date = form.querySelector('#parisan-override-date');
		const timeFields = Array.prototype.slice.call(form.querySelectorAll('[data-parisan-time-field]'));
		const start = form.querySelector('#parisan-override-start');
		const end = form.querySelector('#parisan-override-end');
		const breakStart = form.querySelector('#parisan-override-break-start');
		const breakEnd = form.querySelector('#parisan-override-break-end');

		function toggleStaff() {
			if (!scope || !staff) {
				return;
			}
			const isStaff = scope.value === 'staff';
			staff.disabled = !isStaff;
			staff.required = isStaff;
		}

		function toggleHours() {
			const closed = state && state.value === 'closed';
			timeFields.forEach(function (field) {
				field.hidden = closed;
				Array.prototype.forEach.call(field.querySelectorAll('input'), function (input) {
					input.disabled = closed;
				});
			});
		}

		function validateTimes() {
			if (!start || !end || !start.value || !end.value) {
				return 'ساعت شروع و پایان را وارد کنید.';
			}
			if (end.value <= start.value) {
				return 'ساعت پایان باید بعد از ساعت شروع باشد.';
			}
			const hasBreakStart = breakStart && breakStart.value;
			const hasBreakEnd = breakEnd && breakEnd.value;
			if (hasBreakStart !== hasBreakEnd) {
				return 'برای استراحت، هم ساعت شروع و هم پایان را وارد کنید.';
			}
			if (hasBreakStart && hasBreakEnd) {
				if (breakEnd.value <= breakStart.value) {
					return 'پایان استراحت باید بعد از شروع آن باشد.';
				}
				if (breakStart.value < start.value || breakEnd.value > end.value) {
					return 'بازه استراحت باید داخل ساعات کاری باشد.';
				}
			}
			return '';
		}

		if (scope) {
			scope.addEventListener('change', toggleStaff);
		}
		if (state) {
			state.addEventListener('change', toggleHours);
		}
		if (date) {
			date.addEventListener('blur', function () {
				normalizeDateInput(date);
			});
		}

		form.addEventListener('submit', function (event) {
			setFormError(form, '');
			if (date && !parseJalali(date.value)) {
				event.preventDefault();
				setFormError(form, 'تاریخ شمسی معتبر نیست. نمونه درست: ۱۴۰۵/۰۸/۲۲');
				date.focus();
				return;
			}
			if (!state || state.value !== 'closed') {
				const message = validateTimes();
				if (message) {
					event.preventDefault();
					setFormError(form, message);
					(start || form).focus();
				}
			}
		});

		toggleStaff();
		toggleHours();
	}

	/* Partial-day exception form ------------------------------------------ */

	function initExceptionForm() {
		const form = document.querySelector('[data-parisan-exception-form]');
		if (!form) {
			return;
		}
		const allDay = form.querySelector('[data-parisan-all-day]');
		const date = form.querySelector('#parisan-exception-date');
		const timeFields = Array.prototype.slice.call(form.querySelectorAll('[data-parisan-exception-time]'));

		function toggleAllDay() {
			const checked = Boolean(allDay && allDay.checked);
			timeFields.forEach(function (field) {
				field.hidden = checked;
				Array.prototype.forEach.call(field.querySelectorAll('input'), function (input) {
					input.disabled = checked;
				});
			});
		}

		if (allDay) {
			allDay.addEventListener('change', toggleAllDay);
		}
		if (date) {
			date.addEventListener('blur', function () {
				normalizeDateInput(date);
			});
		}

		form.addEventListener('submit', function (event) {
			setFormError(form, '');
			if (date && !parseJalali(date.value)) {
				event.preventDefault();
				setFormError(form, 'تاریخ شمسی معتبر نیست. نمونه درست: ۱۴۰۵/۰۸/۲۲');
				date.focus();
				return;
			}
			if (allDay && allDay.checked) {
				return;
			}
			const start = form.querySelector('#parisan-exception-start');
			const end = form.querySelector('#parisan-exception-end');
			if (!start || !end || !start.value || !end.value) {
				event.preventDefault();
				setFormError(form, 'ساعت شروع و پایان را وارد کنید یا گزینه تمام روز را انتخاب کنید.');
				return;
			}
			if (end.value <= start.value) {
				event.preventDefault();
				setFormError(form, 'ساعت پایان باید بعد از ساعت شروع باشد.');
				end.focus();
			}
		});

		toggleAllDay();
	}

	function init() {
		initGrid();
		initOverrideForm();
		initExceptionForm();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
