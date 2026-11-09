'use strict';
const assert = require('node:assert/strict');
const { chromium } = require('playwright');

(async () => {
	const browser = await chromium.launch({ headless: true });
	try {
		const page = await browser.newPage({ viewport: { width: 1440, height: 1000 }, deviceScaleFactor: 1 });
		const errors = [];
		page.on('pageerror', error => errors.push(error.message));
		await page.goto('http://127.0.0.1:8080/', { waitUntil: 'networkidle' });
		await page.screenshot({ path: 'artifacts/parisan-desktop.png', fullPage: true });
		assert.equal(await page.locator('main#main-content').count(), 1, 'homepage main landmark renders');
		assert.equal(await page.locator('.hero-copy h1').count(), 1, 'homepage hero heading renders');
		assert.equal(await page.locator('.parisan-home-service-grid .service-card').count(), 2, 'homepage renders seeded service cards');
		assert.equal(await page.locator('.parisan-portfolio-grid .portfolio-card').count(), 3, 'homepage renders seeded portfolio cards');
		assert.equal(await page.locator('.parisan-home-team-grid .specialist-card').count(), 2, 'homepage renders seeded specialist cards');
		assert.equal(await page.locator('.post-grid .post-card').count(), 1, 'homepage renders seeded journal article without WordPress starter content');
		assert.equal(await page.locator('.parisan-home-service-grid .service-card img').count(), 2, 'service cards have featured images');
		assert.equal(await page.locator('.parisan-portfolio-grid .portfolio-card img').count(), 3, 'portfolio cards have featured images');
		assert.equal(await page.locator('.parisan-home-team-grid .specialist-card img').count(), 2, 'specialist cards have featured images');
		assert.equal(await page.locator('.post-grid .post-card-image img').count(), 1, 'journal card has a featured image');
		assert.equal(await page.locator('.service-card img, .portfolio-card img, .specialist-card img, .post-card-image img').evaluateAll(images => images.every(image => image.complete && image.naturalWidth > 0)), true, 'all seeded card images load successfully');
		assert.equal(await page.locator('.parisan-booking-notice').count(), 1, 'disabled booking notice renders');
		assert.match(await page.locator('.parisan-booking-notice').innerText(), /رزرو آنلاین در حال حاضر فعال نیست/, 'booking remains visibly disabled by default');
		assert.equal(await page.locator('.parisan-mobile-menu-toggle').isVisible(), false, 'mobile menu toggle is hidden on desktop');
		assert.equal(await page.locator('.primary-navigation a').count(), 9, 'fallback navigation destinations render on desktop');

		await page.setViewportSize({ width: 390, height: 844 });
		await page.waitForTimeout(100);
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
		await page.screenshot({ path: 'artifacts/parisan-mobile.png', fullPage: true });

		assert.deepEqual(errors, [], 'browser reports no uncaught JavaScript errors');
		console.log('PASS: seeded service/portfolio/team/journal content, desktop/mobile layout, disabled booking state, responsive navigation, keyboard/Escape, focus, and browser console.');
	} finally {
		await browser.close();
	}
})().catch(error => {
	console.error(error);
	process.exit(1);
});
