(function () {
	'use strict';
	var root = document.documentElement;
	var toggle = document.querySelector('.parisan-theme-toggle');
	var mobileBooking = document.querySelector('.parisan-mobile-booking');
	var bookingSection = document.getElementById('booking');
	var storageKey = 'parisan-theme-preference';

	function readPreference() {
		try {
			var saved = window.localStorage.getItem(storageKey);
			return saved === 'light' || saved === 'dark' ? saved : null;
		} catch (error) { return null; }
	}
	function systemPreference() {
		return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
	}
	function applyTheme(theme, persist) {
		var isDark = theme === 'dark';
		root.setAttribute('data-parisan-theme', isDark ? 'dark' : 'light');
		root.style.colorScheme = isDark ? 'dark' : 'light';
		if (toggle) {
			toggle.hidden = false;
			toggle.setAttribute('aria-pressed', isDark ? 'true' : 'false');
			toggle.setAttribute('aria-label', isDark ? 'فعال‌سازی حالت روشن' : 'فعال‌سازی حالت تیره');
			toggle.textContent = isDark ? 'حالت روشن' : 'حالت تیره';
		}
		if (persist) {
			try { window.localStorage.setItem(storageKey, isDark ? 'dark' : 'light'); } catch (error) { /* Storage is optional. */ }
		}
	}
	applyTheme(readPreference() || systemPreference(), false);
	if (toggle) {
		toggle.addEventListener('click', function () {
			applyTheme(root.getAttribute('data-parisan-theme') === 'dark' ? 'light' : 'dark', true);
		});
	}
	if (mobileBooking && bookingSection && 'IntersectionObserver' in window) {
		var observer = new IntersectionObserver(function (entries) {
			entries.forEach(function (entry) { mobileBooking.hidden = entry.isIntersecting; });
		}, { root: null, threshold: 0.08 });
		observer.observe(bookingSection);
	}
}());
