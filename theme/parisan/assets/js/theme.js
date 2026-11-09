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

	var menuToggle = document.querySelector('.parisan-mobile-menu-toggle');
	var primaryNavigation = document.querySelector('.primary-navigation');

	if (menuToggle && primaryNavigation) {
		menuToggle.hidden = false;
		root.classList.add('parisan-mobile-nav-ready');

		function closePrimaryNavigation(returnFocus) {
			menuToggle.setAttribute('aria-expanded', 'false');
			menuToggle.textContent = 'باز کردن منو';
			primaryNavigation.classList.remove('is-open');
			if (returnFocus) { menuToggle.focus(); }
		}

		menuToggle.addEventListener('click', function () {
			var isOpen = menuToggle.getAttribute('aria-expanded') === 'true';
			if (isOpen) {
				closePrimaryNavigation(false);
				return;
			}
			menuToggle.setAttribute('aria-expanded', 'true');
			menuToggle.textContent = 'بستن منو';
			primaryNavigation.classList.add('is-open');
			var firstLink = primaryNavigation.querySelector('a');
			if (firstLink) { firstLink.focus(); }
		});

		primaryNavigation.addEventListener('click', function (event) {
			if (event.target && event.target.closest && event.target.closest('a')) {
				closePrimaryNavigation(false);
			}
		});

		document.addEventListener('keydown', function (event) {
			if (event.key === 'Escape' && menuToggle.getAttribute('aria-expanded') === 'true') {
				closePrimaryNavigation(true);
			}
		});
	}

	applyTheme(readPreference() || systemPreference(), false);
	if (toggle) {
		toggle.addEventListener('click', function () {
			applyTheme(root.getAttribute('data-parisan-theme') === 'dark' ? 'light' : 'dark', true);
		});
	}
	var heroSection = document.querySelector('.hero-section');
	if (mobileBooking) {
		// Start hidden to prevent a first-paint overlap before the observer's initial callback.
		mobileBooking.hidden = true;
		if (typeof window.IntersectionObserver === 'function') {
			var heroIsVisible = false;
			var bookingIsVisible = false;
			var observer = new window.IntersectionObserver(function (entries) {
			entries.forEach(function (entry) {
				if (entry.target === heroSection) { heroIsVisible = entry.isIntersecting; }
				if (entry.target === bookingSection) { bookingIsVisible = entry.isIntersecting; }
			});
			mobileBooking.hidden = heroIsVisible || bookingIsVisible;
		}, { root: null, threshold: 0.08 });
			if (heroSection) { observer.observe(heroSection); }
			if (bookingSection) { observer.observe(bookingSection); }
		} else {
			// Without reliable visibility tracking, prefer no floating overlay to covering content.
			mobileBooking.hidden = true;
		}
	}
}());
