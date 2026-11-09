'use strict';
const assert = require('node:assert/strict');
const { chromium } = require('playwright');

(async () => {
	const browser = await chromium.launch({ headless: true });
	try {
		const page = await browser.newPage({ viewport: { width: 1440, height: 1000 }, deviceScaleFactor: 1 });
		const errors = [];
		const consoleErrors = [];
		const failedRequests = [];
		function collectBrowserDiagnostics(targetPage) {
			targetPage.on('pageerror', error => errors.push(error.message));
			targetPage.on('console', message => {
				if (message.type() === 'error') consoleErrors.push(message.text());
			});
			targetPage.on('requestfailed', request => failedRequests.push(request.method() + ' ' + request.url() + ': ' + (request.failure() ? request.failure().errorText : 'unknown failure')));
		}
		collectBrowserDiagnostics(page);
		await page.goto('http://127.0.0.1:8080/', { waitUntil: 'networkidle' });
		const fontDiagnostics = await page.evaluate(async () => {
			try { await document.fonts.load('16px Vazirmatn'); await document.fonts.ready; }
			catch (error) { return { error: error.message, faces: Array.from(document.fonts, face => ({ family: face.family, status: face.status, weight: face.weight })) }; }
			return { loaded: document.fonts.check('16px Vazirmatn'), faces: Array.from(document.fonts, face => ({ family: face.family, status: face.status, weight: face.weight })) };
		});
		assert.equal(fontDiagnostics.loaded, true, 'bundled Vazirmatn font loads successfully: ' + JSON.stringify(fontDiagnostics));
		assert.equal(await page.evaluate(() => performance.getEntriesByType('resource').some(entry => entry.name.includes('/assets/fonts/Vazirmatn'))), true, 'Vazirmatn is served from the theme package, not an external CDN');
		assert.match(await page.locator('body').evaluate(el => getComputedStyle(el).fontFamily), /Vazirmatn/, 'Persian-first Vazirmatn font is applied');
		const themeToggle = page.locator('.parisan-theme-toggle');
		assert.equal(await themeToggle.isVisible(), true, 'day/night theme control is available');
		if (await page.locator('html').getAttribute('data-parisan-theme') !== 'light') await themeToggle.click();
		assert.equal(await page.locator('html').getAttribute('data-parisan-theme'), 'light', 'day theme can be activated');
		const desktopHeroGeometry = await page.evaluate(() => {
			const art = document.querySelector('.hero-art').getBoundingClientRect();
			const copy = document.querySelector('.hero-copy').getBoundingClientRect();
			return { artRight: art.right, copyLeft: copy.left, artWidth: art.width, copyWidth: copy.width };
		});
		assert.ok(desktopHeroGeometry.artWidth > 400 && desktopHeroGeometry.copyWidth > 360, 'desktop hero keeps both columns substantial: ' + JSON.stringify(desktopHeroGeometry));
		assert.ok(desktopHeroGeometry.artRight <= desktopHeroGeometry.copyLeft + 2, 'desktop hero copy never overlaps the photograph: ' + JSON.stringify(desktopHeroGeometry));
		// Force native lazy-loaded fixture thumbnails to load before asserting and capturing evidence.
		const cardImages = page.locator('.service-card img, .portfolio-card img, .specialist-card img, .post-card-image img');
		await cardImages.evaluateAll(images => images.forEach(image => { image.loading = 'eager'; }));
		await cardImages.evaluateAll(images => Promise.all(images.map(image => image.decode().catch(() => null))));
		await page.screenshot({ path: 'artifacts/parisan-desktop.png', fullPage: true });
		await page.screenshot({ path: 'artifacts/parisan-desktop-viewport.png', fullPage: false });
		await themeToggle.click();
		assert.equal(await page.locator('html').getAttribute('data-parisan-theme'), 'dark', 'neon night theme can be activated');
		assert.equal(await page.locator('body').evaluate(el => getComputedStyle(el).backgroundColor), 'rgb(23, 19, 30)', 'night theme uses the deep aubergine canvas');
		assert.match(await page.locator('.button').first().evaluate(el => getComputedStyle(el).backgroundImage), /linear-gradient/, 'night theme uses the neon accent treatment');
		assert.match(await page.locator('.hero-art').evaluate(el => getComputedStyle(el).boxShadow), /rgba\(255, 63, 207/, 'night hero artwork has a restrained neon glow');
		await page.screenshot({ path: 'artifacts/parisan-desktop-night.png', fullPage: true });
		await page.screenshot({ path: 'artifacts/parisan-desktop-night-viewport.png', fullPage: false });
		await themeToggle.click();
		assert.equal(await page.locator('html').getAttribute('data-parisan-theme'), 'light', 'day theme can be restored');
		await page.reload({ waitUntil: 'networkidle' });
		assert.equal(await page.locator('html').getAttribute('data-parisan-theme'), 'light', 'day/night selection persists after reload');
		assert.equal(await page.locator('main#main-content').count(), 1, 'homepage main landmark renders');
		assert.match(await page.locator('.site-title').innerText(), /پاریسان/, 'browser demo uses Persian salon branding');
		assert.equal(await page.locator('.hero-copy h1').count(), 1, 'homepage hero heading renders');
		assert.equal(await page.locator('.parisan-home-service-grid .service-card').count(), 2, 'homepage renders seeded service cards');
		assert.equal(await page.locator('.parisan-portfolio-grid .portfolio-card').count(), 3, 'homepage renders seeded portfolio cards');
		assert.equal(await page.locator('.parisan-home-team-grid .specialist-card').count(), 2, 'homepage renders seeded specialist cards');
		assert.equal(await page.locator('.post-grid .post-card').count(), 1, 'homepage renders seeded journal article without WordPress starter content');
		assert.equal(await page.locator('.parisan-home-service-grid .service-card img').count(), 2, 'service cards have featured images');
		assert.equal(await page.locator('.parisan-portfolio-grid .portfolio-card img').count(), 3, 'portfolio cards have featured images');
		assert.equal(await page.locator('.parisan-home-team-grid .specialist-card img').count(), 2, 'specialist cards have featured images');
		assert.equal(await page.locator('.post-grid .post-card-image img').count(), 1, 'journal card has a featured image');
		const allCardImages = page.locator('.service-card img, .portfolio-card img, .specialist-card img, .post-card-image img');
		// Reload resets native lazy-loading state; force all fixture images eager before the assertion.
		await allCardImages.evaluateAll(images => images.forEach(image => { image.loading = 'eager'; }));
		await allCardImages.evaluateAll(images => Promise.all(images.map(image => image.decode().catch(() => null))));
		await page.waitForFunction(() => {
			const images = Array.from(document.querySelectorAll('.service-card img, .portfolio-card img, .specialist-card img, .post-card-image img'));
			return images.length === 8 && images.every(image => image.complete && image.naturalWidth > 0);
		}, null, { timeout: 15000 });
		assert.equal(await allCardImages.count(), 8, 'all eight seeded card images are present');
		assert.equal(await allCardImages.evaluateAll(images => images.every(image => image.complete && image.naturalWidth > 0)), true, 'all seeded card images load successfully');
		assert.equal(await page.locator('.parisan-booking-notice').count(), 1, 'disabled booking notice renders');
		assert.match(await page.locator('.parisan-booking-notice').innerText(), /رزرو آنلاین در حال حاضر فعال نیست/, 'booking remains visibly disabled by default');
		assert.equal(await page.locator('.parisan-mobile-menu-toggle').isVisible(), false, 'mobile menu toggle is hidden on desktop');
		assert.equal(await page.locator('.primary-navigation a').count(), 9, 'fallback navigation destinations render on desktop');

		await page.setViewportSize({ width: 390, height: 844 });
		assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), true, 'mobile homepage has no horizontal page overflow');
		assert.equal(await page.evaluate(() => getComputedStyle(document.querySelector('.intro-grid')).gridTemplateColumns.split(' ').length), 1, 'mobile intro content uses a single readable column');
		assert.ok(await page.locator('.site-title').evaluate(el => getComputedStyle(el).whiteSpace) === 'nowrap', 'mobile site title remains on one line');
		assert.ok(await page.locator('.hero-description').evaluate(el => parseFloat(getComputedStyle(el).fontSize) >= 16), 'mobile body copy remains comfortably readable');
		const menuToggle = page.locator('.parisan-mobile-menu-toggle');
		const navigation = page.locator('.primary-navigation');
		assert.equal(await menuToggle.isVisible(), true, 'mobile menu control is visible');
		assert.equal(await navigation.isVisible(), false, 'mobile navigation starts collapsed after enhancement');
		await menuToggle.click();
		assert.equal(await menuToggle.getAttribute('aria-expanded'), 'true', 'mobile menu announces expanded state');
		assert.equal(await navigation.isVisible(), true, 'mobile navigation opens');
		assert.equal(await page.evaluate(() => document.activeElement && document.activeElement.closest('.primary-navigation') !== null), true, 'opening menu moves focus into navigation');
		await page.keyboard.press('Escape');
		assert.equal(await menuToggle.getAttribute('aria-expanded'), 'false', 'Escape closes mobile navigation');
		assert.equal(await navigation.isVisible(), false, 'Escape collapses mobile navigation');
		assert.equal(await menuToggle.evaluate(el => document.activeElement === el), true, 'Escape restores focus to menu control');
		const mobileBooking = page.locator('.parisan-mobile-booking');
		await page.waitForFunction(() => document.querySelector('.parisan-mobile-booking').hidden, null, { timeout: 5000 });
		assert.equal(await mobileBooking.isVisible(), false, 'mobile booking shortcut stays out of the hero artwork');
		await page.locator('#services').scrollIntoViewIfNeeded();
		await page.waitForFunction(() => !document.querySelector('.parisan-mobile-booking').hidden, null, { timeout: 5000 });
		assert.equal(await mobileBooking.isVisible(), true, 'mobile booking shortcut appears in the intervening content area');
		await page.locator('#booking').scrollIntoViewIfNeeded();
		await page.waitForFunction(() => document.querySelector('.parisan-mobile-booking').hidden, null, { timeout: 5000 });
		assert.equal(await page.locator('#booking').isVisible(), true, 'booking section remains visible and usable');
		assert.equal(await page.locator('#booking a.button').isVisible(), true, 'disabled booking state retains its direct salon contact action');
		assert.equal(await mobileBooking.isVisible(), false, 'mobile booking shortcut stays hidden while booking section is visible');
		await page.locator('#services').scrollIntoViewIfNeeded();
		await page.waitForFunction(() => !document.querySelector('.parisan-mobile-booking').hidden, null, { timeout: 5000 });
		assert.equal(await mobileBooking.isVisible(), true, 'mobile booking shortcut returns after leaving the booking section');
		await page.evaluate(() => window.scrollTo(0, 0));
		await page.waitForFunction(() => document.querySelector('.parisan-mobile-booking').hidden, null, { timeout: 5000 });
		await page.screenshot({ path: 'artifacts/parisan-mobile.png', fullPage: true });
		await page.screenshot({ path: 'artifacts/parisan-mobile-viewport.png', fullPage: false });
		await themeToggle.click();
		assert.equal(await page.locator('html').getAttribute('data-parisan-theme'), 'dark', 'mobile night theme can be activated');
		assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), true, 'mobile night theme has no horizontal overflow');
		await page.screenshot({ path: 'artifacts/parisan-mobile-night.png', fullPage: true });
		await page.screenshot({ path: 'artifacts/parisan-mobile-night-viewport.png', fullPage: false });
		await themeToggle.click();

		const fallbackContext = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 1 });
		const fallbackPage = await fallbackContext.newPage();
		collectBrowserDiagnostics(fallbackPage);
		await fallbackPage.addInitScript(() => {
			Object.defineProperty(window, 'IntersectionObserver', { configurable: true, value: undefined });
		});
		await fallbackPage.goto('http://127.0.0.1:8080/', { waitUntil: 'networkidle' });
		assert.equal(await fallbackPage.locator('.parisan-mobile-booking').isVisible(), false, 'no-observer fallback never displays a floating overlay');
		assert.equal(await fallbackPage.locator('#booking').isVisible(), true, 'booking/contact section remains available without IntersectionObserver');
		assert.equal(await fallbackPage.locator('#booking a.button').isVisible(), true, 'salon contact action remains available without IntersectionObserver');
		const fallbackMenu = fallbackPage.locator('.parisan-mobile-menu-toggle');
		await fallbackMenu.click();
		assert.equal(await fallbackPage.locator('.primary-navigation').isVisible(), true, 'main navigation remains available without IntersectionObserver');
		await fallbackContext.close();

		assert.deepEqual(errors, [], 'browser reports no uncaught JavaScript errors');
		assert.deepEqual(consoleErrors, [], 'browser reports no console errors');
		assert.deepEqual(failedRequests, [], 'browser reports no failed page requests');
		console.log('PASS: seeded content, desktop/mobile layout, booking shortcut over hero/intervening/booking sections, no-observer fallback, navigation, and browser diagnostics.');
	} finally {
		await browser.close();
	}
})().catch(error => {
	console.error(error);
	process.exit(1);
});
