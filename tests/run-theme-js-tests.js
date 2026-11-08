'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../theme/parisan/assets/js/theme.js'), 'utf8');

function runTheme(options) {
	const attrs = {};
	const listeners = {};
	const storage = {
		value: options.saved || null,
		getItem() { return this.value; },
		setItem(key, value) { this.value = value; },
	};
	const toggle = {
		hidden: true,
		textContent: '',
		attributes: {},
		addEventListener(name, callback) { listeners[name] = callback; },
		setAttribute(name, value) { this.attributes[name] = value; },
	};
	const mobileBooking = { hidden: false };
	const bookingSection = {};
	let observerCallback = null;
	class MockIntersectionObserver {
		constructor(callback) { observerCallback = callback; }
		observe() {}
	}
	const document = {
		documentElement: {
			style: {},
			setAttribute(name, value) { attrs[name] = value; },
			getAttribute(name) { return attrs[name] || null; },
		},
		querySelector(selector) {
			if (selector === '.parisan-theme-toggle') return toggle;
			if (selector === '.parisan-mobile-booking') return mobileBooking;
			return null;
		},
		getElementById(id) { return id === 'booking' ? bookingSection : null; },
	};
	const window = {
		localStorage: storage,
		matchMedia() { return { matches: Boolean(options.systemDark) }; },
	};
	const context = { document, window };
	if (options.observer !== false) {
		context.IntersectionObserver = MockIntersectionObserver;
		window.IntersectionObserver = MockIntersectionObserver;
	}
	vm.runInNewContext(source, context, { filename: 'theme.js' });
	return { attrs, listeners, storage, toggle, mobileBooking, observerCallback, document };
}

const systemDark = runTheme({ systemDark: true });
assert.equal(systemDark.attrs['data-parisan-theme'], 'dark');
assert.equal(systemDark.toggle.hidden, false);
assert.equal(systemDark.toggle.attributes['aria-pressed'], 'true');
assert.equal(systemDark.toggle.textContent, 'حالت روشن');

systemDark.listeners.click();
assert.equal(systemDark.attrs['data-parisan-theme'], 'light');
assert.equal(systemDark.toggle.attributes['aria-pressed'], 'false');
assert.equal(systemDark.storage.value, 'light');

const savedChoice = runTheme({ saved: 'light', systemDark: true });
assert.equal(savedChoice.attrs['data-parisan-theme'], 'light', 'saved preference overrides operating-system preference');

const mobile = runTheme({ observer: true });
mobile.observerCallback([{ isIntersecting: true }]);
assert.equal(mobile.mobileBooking.hidden, true, 'sticky CTA hides while booking section is visible');
mobile.observerCallback([{ isIntersecting: false }]);
assert.equal(mobile.mobileBooking.hidden, false, 'sticky CTA returns outside booking section');

const noObserver = runTheme({ observer: false });
assert.equal(noObserver.mobileBooking.hidden, false, 'mobile shortcut remains usable without IntersectionObserver');

console.log('PASS: theme preference, persistence, accessible toggle state, and mobile booking visibility.');
