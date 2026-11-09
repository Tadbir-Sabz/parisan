'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../theme/parisan/assets/js/theme.js'), 'utf8');

function runTheme(options) {
	const attrs = {};
	const listeners = {};
	const menuListeners = {};
	const documentListeners = {};
	const classes = new Set();
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
	const firstLink = { focused: false, focus() { this.focused = true; } };
	const menuToggle = { hidden: true, textContent: '', attributes: {}, focused: false, addEventListener(name, callback) { menuListeners[name] = callback; }, setAttribute(name, value) { this.attributes[name] = value; }, getAttribute(name) { return this.attributes[name] || null; }, focus() { this.focused = true; } };
	const navClasses = new Set();
	const primaryNavigation = { addEventListener(name, callback) { menuListeners['nav:' + name] = callback; }, querySelector(selector) { return selector === 'a' ? firstLink : null; }, classList: { add(name) { navClasses.add(name); }, remove(name) { navClasses.delete(name); } } };
	const bookingSection = {};
	let observerCallback = null;
	class MockIntersectionObserver {
		constructor(callback) { observerCallback = callback; }
		observe() {}
	}
	const document = {
		documentElement: {
			style: {},
			classList: { add(name) { classes.add(name); } },
			setAttribute(name, value) { attrs[name] = value; },
			getAttribute(name) { return attrs[name] || null; },
		},
		querySelector(selector) {
			if (selector === '.parisan-theme-toggle') return toggle;
			if (selector === '.parisan-mobile-booking') return mobileBooking;
			if (selector === '.parisan-mobile-menu-toggle') return options.navigation ? menuToggle : null;
			if (selector === '.primary-navigation') return options.navigation ? primaryNavigation : null;
			return null;
		},
		getElementById(id) { return id === 'booking' ? bookingSection : null; },
		addEventListener(name, callback) { documentListeners[name] = callback; },
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
	return { attrs, listeners, storage, toggle, mobileBooking, observerCallback, document, menuToggle, menuListeners, documentListeners, classes, navClasses, firstLink };
}


const navigation = runTheme({ navigation: true, observer: false });
assert.equal(navigation.menuToggle.hidden, false, 'mobile navigation control is enabled when JavaScript is available');
assert.equal(navigation.classes.has('parisan-mobile-nav-ready'), true, 'progressive enhancement class is applied');
navigation.menuListeners.click();
assert.equal(navigation.menuToggle.attributes['aria-expanded'], 'true', 'opening navigation updates expanded state');
assert.equal(navigation.navClasses.has('is-open'), true, 'opening navigation reveals the menu');
assert.equal(navigation.firstLink.focused, true, 'opening navigation moves focus to the first destination');
navigation.documentListeners.keydown({ key: 'Escape' });
assert.equal(navigation.menuToggle.attributes['aria-expanded'], 'false', 'Escape closes the menu');
assert.equal(navigation.navClasses.has('is-open'), false, 'Escape hides the menu');
assert.equal(navigation.menuToggle.focused, true, 'Escape returns focus to the menu control');

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
