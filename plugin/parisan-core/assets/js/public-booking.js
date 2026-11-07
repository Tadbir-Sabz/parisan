(function () {
	'use strict';
	const config = window.ParisanPublicBooking;
	if (!config || !config.restUrl) return;
	const root = document.querySelector('[data-parisan-booking]');
	if (!root) return;
	const createForm = root.querySelector('[data-parisan-create-form]');
	const serviceSelect = root.querySelector('[name="service_id"]');
	const staffSelect = root.querySelector('[name="staff_id"]');
	const dateInput = root.querySelector('[name="date"]');
	const slotSelect = root.querySelector('[name="starts_at_local"]');
	const resultPanel = root.querySelector('[data-parisan-result]');
	const statusNode = root.querySelector('[data-parisan-status]');
	const liveNode = root.querySelector('[data-parisan-live]');
	const tokenPanel = root.querySelector('[data-parisan-token-panel]');
	const tokenOutput = root.querySelector('[data-parisan-token]');
	const existingToken = root.querySelector('[data-parisan-existing-token]');
	const accessForm = root.querySelector('[data-parisan-access-form]');
	const summary = root.querySelector('[data-parisan-summary]');
	const summaryText = root.querySelector('[data-parisan-summary-text]');
	const cancelButton = root.querySelector('[data-parisan-cancel]');
	const copyButton = root.querySelector('[data-parisan-copy]');
	let activeToken = '';
	let pendingIdempotencyKey = '';
	const message = (key) => (config.strings && config.strings[key]) || config.strings.genericError;
	const setLive = (value) => { if (liveNode) liveNode.textContent = value || ''; };
	const setStatus = (value) => { if (statusNode) statusNode.textContent = value || ''; };
	async function api(path, options) {
		const response = await fetch(config.restUrl + path, Object.assign({
			credentials: 'same-origin', cache: 'no-store', headers: { 'Accept': 'application/json' }
		}, options || {}));
		let data = {};
		try { data = await response.json(); } catch (error) { data = {}; }
		if (!response.ok) {
			const failure = new Error(data.message || message('genericError'));
			failure.status = response.status;
			throw failure;
		}
		return data;
	}
	function resetSlots(placeholder) {
		slotSelect.innerHTML = '';
		const option = document.createElement('option');
		option.value = '';
		option.textContent = placeholder || message('selectTargets');
		slotSelect.appendChild(option);
		slotSelect.disabled = true;
	}
	function filterStaff() {
		const serviceId = serviceSelect.value;
		let available = 0;
		Array.from(staffSelect.options).forEach((option) => {
			if (!option.value) return;
			const services = (option.dataset.services || '').split(',').filter(Boolean);
			const visible = !!serviceId && services.includes(serviceId);
			option.hidden = !visible;
			option.disabled = !visible;
			if (visible) available++;
		});
		staffSelect.value = '';
		staffSelect.disabled = available === 0;
		staffSelect.options[0].textContent = available ? 'انتخاب متخصص' : (serviceId ? 'برای این خدمت متخصصی تعریف نشده است' : 'ابتدا خدمت را انتخاب کنید');
		resetSlots(message('selectTargets'));
	}
	async function loadSlots() {
		resetSlots(message('loading'));
		const serviceId = serviceSelect.value;
		const staffId = staffSelect.value;
		const date = dateInput.value;
		if (!serviceId || !staffId || !date) {
			resetSlots(message('selectTargets'));
			return;
		}
		setLive(message('loading'));
		try {
			const query = new URLSearchParams({ service_id: serviceId, staff_id: staffId, date });
			const data = await api('availability?' + query.toString());
			resetSlots(data.slots && data.slots.length ? 'انتخاب ساعت' : message('noSlots'));
			(data.slots || []).forEach((slot) => {
				if (!slot.starts_at_local) return;
				const option = document.createElement('option');
				option.value = slot.starts_at_local;
				option.textContent = slot.starts_at_local.slice(-5);
				slotSelect.appendChild(option);
			});
			slotSelect.disabled = !data.slots || !data.slots.length;
			setLive(data.slots && data.slots.length ? '' : message('noSlots'));
		} catch (error) {
			resetSlots(error.message || message('genericError'));
			setLive(error.message || message('genericError'));
		}
	}
	function makeIdempotencyKey() {
		if (!window.crypto || !window.crypto.getRandomValues) throw new Error('مرورگر امکان تولید شناسه امن درخواست را ندارد.');
		const bytes = new Uint8Array(32);
		window.crypto.getRandomValues(bytes);
		return Array.from(bytes, (value) => value.toString(16).padStart(2, '0')).join('');
	}
	serviceSelect.addEventListener('change', filterStaff);
	staffSelect.addEventListener('change', loadSlots);
	dateInput.addEventListener('change', loadSlots);
	createForm.addEventListener('submit', async (event) => {
		event.preventDefault();
		const submit = createForm.querySelector('[type="submit"]');
		const data = new FormData(createForm);
		const payload = {
			service_id: Number(data.get('service_id')), staff_id: Number(data.get('staff_id')),
			starts_at_local: String(data.get('starts_at_local') || ''),
			customer_name: String(data.get('customer_name') || '').trim(),
			customer_phone: String(data.get('customer_phone') || '').trim(),
			customer_email: String(data.get('customer_email') || '').trim()
		};
		if (!payload.service_id || !payload.staff_id || !payload.starts_at_local) {
			setLive(message('selectTargets'));
			return;
		}
		submit.disabled = true;
		setStatus(message('loading'));
		resultPanel.hidden = false;
		try {
			const response = await api('bookings', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', 'Idempotency-Key': (pendingIdempotencyKey || (pendingIdempotencyKey = makeIdempotencyKey())) },
				body: JSON.stringify(payload)
			});
			pendingIdempotencyKey = '';
			activeToken = response.customer_access_token || '';
			if (!activeToken) throw new Error(message('genericError'));
			tokenOutput.value = activeToken;
			existingToken.value = activeToken;
			tokenPanel.hidden = false;
			setStatus(message('bookingCreated'));
			setLive(message('bookingCreated'));
			await loadCustomerBooking(activeToken);
		} catch (error) {
			if (error.status) pendingIdempotencyKey = '';
			setStatus(error.message || message('genericError'));
			setLive(error.message || message('genericError'));
		} finally {
			submit.disabled = false;
		}
	});
	async function loadCustomerBooking(token) {
		if (!token) throw new Error(message('genericError'));
		activeToken = token;
		const data = await api('customer-booking', { method: 'GET', headers: { 'X-Parisan-Booking-Token': token } });
		const labels = {
			pending: 'در انتظار بررسی سالن', confirmed: 'تأیید شده', cancelled: 'لغو شده',
			completed: 'انجام شده', expired: 'منقضی شده', no_show: 'عدم مراجعه'
		};
		const date = data.starts_at_local_display || data.starts_at_local || '';
		summaryText.textContent = [
			'وضعیت: ' + (labels[data.status] || data.status || '—'),
			'خدمت: ' + (data.service || '—'), 'متخصص: ' + (data.specialist || '—'), 'زمان: ' + (date || '—')
		].join(' | ');
		summary.hidden = false;
		cancelButton.hidden = !data.can_cancel;
		setLive(message('bookingLoaded'));
		return data;
	}
	accessForm.addEventListener('submit', async (event) => {
		event.preventDefault();
		setLive(message('loading'));
		try { await loadCustomerBooking(String(existingToken.value || '').trim()); }
		catch (error) {
			summary.hidden = true;
			cancelButton.hidden = true;
			setLive(error.message || message('genericError'));
		}
	});
	cancelButton.addEventListener('click', async () => {
		if (!activeToken || !window.confirm('از لغو این رزرو مطمئن هستید؟')) return;
		cancelButton.disabled = true;
		setLive(message('loading'));
		try {
			await api('customer-booking/cancel', { method: 'POST', headers: { 'X-Parisan-Booking-Token': activeToken } });
			await loadCustomerBooking(activeToken);
			setLive(message('bookingCancelled'));
		} catch (error) { setLive(error.message || message('genericError')); }
		finally { cancelButton.disabled = false; }
	});
	copyButton.addEventListener('click', async () => {
		try {
			if (navigator.clipboard && navigator.clipboard.writeText) await navigator.clipboard.writeText(tokenOutput.value);
			else {
				tokenOutput.focus(); tokenOutput.select();
				if (!document.execCommand('copy')) throw new Error('copy failed');
			}
			setLive(message('copySuccess'));
		} catch (error) { setLive(message('copyFailure')); }
	});
})();
