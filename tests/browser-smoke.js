'use strict';
const assert = require('node:assert/strict');
const { chromium, firefox, webkit } = require('playwright');

(async () => {
	const browserName = process.env.PARISAN_BROWSER || 'chromium';
	const browserType = ({ chromium, firefox, webkit })[browserName];
	assert.ok(browserType, 'PARISAN_BROWSER must be chromium, firefox, or webkit');
	const browser = await browserType.launch({ headless: true });
	try {
		const page = await browser.newPage({ viewport: { width: 1440, height: 1000 }, deviceScaleFactor: 1 });
		const errors = [];
		const consoleErrors = [];
		const failedRequests = [];
		function collectBrowserDiagnostics(targetPage) {
			targetPage.on('pageerror', error => errors.push(error.message));
			targetPage.on('console', message => {
				if (message.type() === 'error') consoleErrors.push(message.text() + ' @ ' + JSON.stringify(message.location()));
			});
			targetPage.on('requestfailed', request => failedRequests.push(request.method() + ' ' + request.url() + ': ' + (request.failure() ? request.failure().errorText : 'unknown failure')));
			targetPage.on('response', response => { if (response.status() >= 400) failedRequests.push('HTTP ' + response.status() + ' ' + response.request().method() + ' ' + response.url()); });
		}
		async function runAccessibilityAudit(targetPage, label) {
			await targetPage.addScriptTag({ path: require.resolve('axe-core/axe.min.js') });
			const audit = await targetPage.evaluate(async () => window.axe.run(document, {
				runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'best-practice'] }
			}));
			const violations = audit.violations.map(item => ({ id: item.id, impact: item.impact, description: item.help, nodes: item.nodes.map(node => ({ target: node.target, html: node.html, summary: node.failureSummary })) }));
			const incomplete = audit.incomplete.map(item => ({ id: item.id, impact: item.impact, description: item.help, nodes: item.nodes.map(node => ({ target: node.target, html: node.html, summary: node.failureSummary })) }));
			console.log('ACCESSIBILITY ' + JSON.stringify({ label, passes: audit.passes.length, incomplete, violations }));
			const blockers = violations.filter(item => item.impact === 'critical' || item.impact === 'serious');
			assert.deepEqual(blockers, [], label + ' has no serious or critical axe-core violations: ' + JSON.stringify(blockers));
		}
		collectBrowserDiagnostics(page);
		await page.goto(process.env.PARISAN_TEST_URL || 'http://127.0.0.1:8080/', { waitUntil: 'networkidle' });
		assert.equal(await page.locator('html').getAttribute('dir'), 'rtl', 'homepage renders with explicit RTL document direction');
		assert.equal(await page.evaluate(() => document.documentElement.innerHTML.includes('images.unsplash.com')), false, 'homepage relies solely on local assets, not external Unsplash URLs');
		assert.equal(await page.locator('.parisan-home-content').count(), 0, 'homepage does not duplicate blog posts right above the footer');

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
		assert.equal(await page.locator('.hero-art-photo-day').isVisible(), true, 'day theme shows its own hero portrait');
		assert.equal(await page.locator('.hero-art-photo-night').isVisible(), false, 'night hero portrait stays hidden in day mode');
		const dayHeroPhoto = await page.locator('.hero-art-photo-day').getAttribute('src');
		const nightHeroPhoto = await page.locator('.hero-art-photo-night').getAttribute('src');
		assert.ok(dayHeroPhoto && nightHeroPhoto && dayHeroPhoto !== nightHeroPhoto, 'day and night hero modes use distinct photography');
		assert.equal(await page.locator('.hero-art-photo-day').evaluate(img => img.complete && img.naturalWidth > 0), true, 'day hero portrait loads with valid image dimensions');
		if (await page.locator('html').getAttribute('data-parisan-theme') !== 'light') await themeToggle.click();
		assert.equal(await page.locator('html').getAttribute('data-parisan-theme'), 'light', 'day theme can be activated');
		await runAccessibilityAudit(page, 'desktop-day');
		const desktopHeroGeometry = await page.evaluate(() => {
			const art = document.querySelector('.hero-art').getBoundingClientRect();
			const copy = document.querySelector('.hero-copy').getBoundingClientRect();
			return { artRight: art.right, copyLeft: copy.left, artWidth: art.width, copyWidth: copy.width };
		});
		assert.ok(desktopHeroGeometry.artWidth > 400 && desktopHeroGeometry.copyWidth > 360, 'desktop hero keeps both columns substantial: ' + JSON.stringify(desktopHeroGeometry));
		assert.ok(desktopHeroGeometry.artRight <= desktopHeroGeometry.copyLeft + 2, 'desktop hero copy never overlaps the photograph: ' + JSON.stringify(desktopHeroGeometry));
		// Force native lazy-loaded/async-decoded thumbnails to decode synchronously before full-page capture.
		const cardImages = page.locator('.service-card img, .portfolio-card img, .specialist-card img, .post-card-image img');
		await cardImages.evaluateAll(images => images.forEach(image => {
			image.loading = 'eager';
			image.decoding = 'sync';
		}));
		await page.waitForFunction(() => {
			const images = Array.from(document.querySelectorAll('.service-card img, .portfolio-card img, .specialist-card img, .post-card-image img'));
			return images.length === 12 && images.every(image => image.complete && image.naturalWidth > 0);
		}, null, { timeout: 15000 });
		await cardImages.evaluateAll(images => Promise.all(images.map(image => image.decode().catch(() => null))));
		await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
		await page.screenshot({ path: 'artifacts/parisan-desktop.png', fullPage: true });
		await page.screenshot({ path: 'artifacts/parisan-desktop-viewport.png', fullPage: false });

		await themeToggle.click();
		assert.equal(await page.locator('html').getAttribute('data-parisan-theme'), 'dark', 'neon night theme can be activated');
		assert.equal(await page.locator('.hero-art-photo-day').isVisible(), false, 'day hero portrait is hidden in night mode');
		assert.equal(await page.locator('.hero-art-photo-night').isVisible(), true, 'night mode reveals its dedicated hero portrait');
		await runAccessibilityAudit(page, 'desktop-night');
		assert.equal(await page.locator('.hero-art-photo-night').evaluate(img => img.complete && img.naturalWidth > 0), true, 'night hero portrait loads with valid image dimensions');
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
		assert.match(await page.locator('.parisan-brand-lockup').innerText(), /Parisan/, 'browser demo renders the Parisan wordmark and lotus mark');
		assert.equal(await page.getByRole('link', { name: 'Parisan', exact: true }).count(), 1, 'brand link accessible name matches its visible wordmark');
		assert.equal(await page.locator('.hero-copy h1').count(), 1, 'homepage hero heading renders');
		assert.equal(await page.locator('.hero-benefit').count(), 4, 'reference-style four-benefit trust bar renders');
		assert.equal(await page.locator('.parisan-home-service-grid .service-card').count(), 3, 'homepage renders three balanced service cards');
		assert.equal(await page.locator('.parisan-portfolio-grid .portfolio-card').count(), 3, 'homepage renders three balanced portfolio cards');
		assert.equal(await page.locator('.parisan-home-team-grid .specialist-card').count(), 3, 'homepage renders three balanced specialist cards');
		assert.equal(await page.locator('.post-grid .post-card').count(), 3, 'homepage renders three balanced journal articles without WordPress starter content');
		assert.equal(await page.locator('.parisan-home-service-grid .service-card img').count(), 3, 'all service cards have images');
		assert.equal(await page.locator('.parisan-portfolio-grid .portfolio-card img').count(), 3, 'all portfolio cards have images');
		assert.equal(await page.locator('.parisan-home-team-grid .specialist-card img').count(), 3, 'all specialist cards have images');
		assert.equal(await page.locator('.post-grid .post-card-image img').count(), 3, 'all journal cards have images');
		for (const selector of [
			'.parisan-home-service-grid .service-card img',
			'.parisan-portfolio-grid .portfolio-card img',
			'.parisan-home-team-grid .specialist-card img',
			'.post-grid .post-card-image img',
		]) {
			const srcs = await page.locator(selector).evaluateAll(imgs => imgs.map(img => img.currentSrc || img.src));
			assert.equal(new Set(srcs).size, srcs.length, 'card images are distinct within section ' + selector + ': ' + JSON.stringify(srcs));
		}

		const allCardImages = page.locator('.service-card img, .portfolio-card img, .specialist-card img, .post-card-image img');
		// Reload resets native lazy-loading state; force all fixture images eager before the assertion.
		await allCardImages.evaluateAll(images => images.forEach(image => { image.loading = 'eager'; }));
		await allCardImages.evaluateAll(images => Promise.all(images.map(image => image.decode().catch(() => null))));
		await page.waitForFunction(() => {
			const images = Array.from(document.querySelectorAll('.service-card img, .portfolio-card img, .specialist-card img, .post-card-image img'));
			return images.length === 12 && images.every(image => image.complete && image.naturalWidth > 0);
		}, null, { timeout: 15000 });
		assert.equal(await allCardImages.count(), 12, 'all twelve card images are present');
		assert.equal(await allCardImages.evaluateAll(images => images.every(image => image.complete && image.naturalWidth > 0)), true, 'all card images load successfully');
		assert.equal(await page.locator('.parisan-booking-notice').count(), 1, 'disabled booking notice renders');
		assert.match(await page.locator('.parisan-booking-notice').innerText(), /رزرو آنلاین در حال حاضر فعال نیست/, 'booking remains visibly disabled by default');
		assert.equal(await page.locator('.parisan-mobile-menu-toggle').isVisible(), false, 'mobile menu toggle is hidden on desktop');
		assert.equal(await page.locator('.primary-navigation a').count(), 6, 'concise six-item fallback navigation renders on desktop');

		await page.keyboard.press('Tab');
		assert.equal(await page.evaluate(() => document.activeElement && document.activeElement.classList.contains('skip-link')), true, 'first keyboard Tab focuses the skip-to-content link');
		await page.keyboard.press('Tab');
		assert.equal(await page.evaluate(() => document.activeElement && document.activeElement.closest('.site-header') !== null), true, 'subsequent keyboard Tab moves focus into the site header navigation/actions');

		await page.setViewportSize({ width: 390, height: 844 });
		assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), true, 'mobile homepage has no horizontal page overflow');
		assert.equal(await page.evaluate(() => getComputedStyle(document.querySelector('.intro-grid')).gridTemplateColumns.split(' ').length), 1, 'mobile intro content uses a single readable column');
		assert.ok(await page.locator('.site-title').evaluate(el => getComputedStyle(el).whiteSpace) === 'nowrap', 'mobile site title remains on one line');
		assert.ok(await page.locator('.hero-description').evaluate(el => parseFloat(getComputedStyle(el).fontSize) >= 16), 'mobile body copy remains comfortably readable');
		await runAccessibilityAudit(page, 'mobile-day');
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
		await menuToggle.click();
		await page.locator('.intro-section').click({ position: { x: 12, y: 12 } });
		assert.equal(await menuToggle.getAttribute('aria-expanded'), 'false', 'clicking outside the header dismisses mobile navigation');
		await menuToggle.click();
		await page.setViewportSize({ width: 800, height: 900 });
		assert.equal(await menuToggle.getAttribute('aria-expanded'), 'false', 'crossing into desktop viewport closes stale mobile navigation state');
		await page.setViewportSize({ width: 390, height: 844 });
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
		await runAccessibilityAudit(page, 'mobile-night');
		await page.screenshot({ path: 'artifacts/parisan-mobile-night.png', fullPage: true });
		await page.screenshot({ path: 'artifacts/parisan-mobile-night-viewport.png', fullPage: false });
		await themeToggle.click();

		// Exercise the real WordPress content templates, not only the marketing homepage.
		const contentRoutes = [
			{ label: 'service-detail', selector: '.parisan-home-service-grid .service-card h3 a' },
			{ label: 'portfolio-detail', selector: '.parisan-portfolio-grid .portfolio-card h3 a' },
			{ label: 'specialist-detail', selector: '.parisan-home-team-grid .specialist-card h3 a' },
			{ label: 'article-detail', selector: '.post-grid .post-card h3 a' },
		];
		const routeTargets = [];
		for (const route of contentRoutes) {
			const target = await page.locator(route.selector).first().getAttribute('href');
			assert.ok(target, route.label + ' has a real internal destination');
			routeTargets.push({ label: route.label, url: new URL(target, page.url()).toString() });
		}
		for (const route of routeTargets) {
			await page.goto(route.url, { waitUntil: 'networkidle' });
			assert.equal(await page.locator('main#main-content').count(), 1, route.label + ' renders exactly one main landmark');
			assert.equal(await page.locator('main#main-content h1').count(), 1, route.label + ' renders exactly one primary heading');
			assert.equal(await page.locator('.parisan-breadcrumbs, nav[aria-label*="مسیر"]').count() > 0, true, route.label + ' includes semantic breadcrumb navigation');
			assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), true, route.label + ' has no desktop horizontal overflow');
			await runAccessibilityAudit(page, route.label + '-desktop');
			await page.setViewportSize({ width: 390, height: 844 });
			assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), true, route.label + ' has no mobile horizontal overflow');
			await runAccessibilityAudit(page, route.label + '-mobile');
			await page.setViewportSize({ width: 1440, height: 1000 });
		}
		await page.goto(new URL('/?s=' + encodeURIComponent('مراقبت'), page.url()).toString(), { waitUntil: 'networkidle' });
		assert.equal(await page.locator('#search-results-title').count(), 1, 'search route renders its labelled result heading');
		assert.equal(await page.locator('.search-results-count[role="status"]').count(), 1, 'search route announces the result count');
		await runAccessibilityAudit(page, 'search-results');
		await page.goto(new URL('/?p=999999', page.url()).toString(), { waitUntil: 'networkidle' });
		assert.equal(await page.locator('main.not-found-page h1').count(), 1, '404 route renders its dedicated recovery page');
		assert.equal(await page.getByRole('link', { name: /بازگشت به صفحه اصلی/ }).count(), 1, '404 route offers a clear home recovery action');
		await runAccessibilityAudit(page, 'not-found');
		await page.goto(process.env.PARISAN_TEST_URL || 'http://127.0.0.1:8080/', { waitUntil: 'networkidle' });
		await page.setViewportSize({ width: 390, height: 844 });

		const fallbackContext = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 1 });
		const fallbackPage = await fallbackContext.newPage();
		collectBrowserDiagnostics(fallbackPage);
		await fallbackPage.addInitScript(() => {
			Object.defineProperty(window, 'IntersectionObserver', { configurable: true, value: undefined });
		});
		await fallbackPage.goto(process.env.PARISAN_TEST_URL || 'http://127.0.0.1:8080/', { waitUntil: 'networkidle' });
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
