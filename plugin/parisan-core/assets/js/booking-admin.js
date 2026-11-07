(function () {
  "use strict";

  function initParisanBookingAdmin() {
    const config = window.ParisanBookingAdmin;
    const staff = document.getElementById("parisan-manual-staff");
    const service = document.getElementById("parisan-manual-service");
    const date = document.getElementById("parisan-manual-date");
    const time = document.getElementById("parisan-manual-time");
    const status = document.getElementById("parisan-manual-slot-status");
    const submit = document.getElementById("parisan-manual-submit");

    if (!config || !staff || !service || !date || !time || !status || !submit) {
      return;
    }

    let requestId = 0;

    function resetTimes(message) {
      time.innerHTML = "";
      const placeholder = document.createElement("option");
      placeholder.value = "";
      placeholder.textContent = "ابتدا تاریخ، متخصص و خدمت را انتخاب کنید";
      time.appendChild(placeholder);
      time.disabled = true;
      submit.disabled = true;
      status.textContent = message || "";
    }

    async function loadTimes() {
      const staffId = staff.value;
      const serviceId = service.value;
      const selectedDate = date.value;

      if (!staffId || !serviceId || !selectedDate) {
        resetTimes("برای مشاهده زمان‌های آزاد، متخصص، خدمت و تاریخ را انتخاب کنید.");
        return;
      }

      const currentRequest = ++requestId;
      resetTimes("در حال بررسی ظرفیت...");
      const body = new URLSearchParams({
        action: "parisan_admin_available_slots",
        nonce: config.nonce,
        staff_id: staffId,
        service_id: serviceId,
        date: selectedDate
      });

      try {
        const response = await fetch(config.ajaxUrl, {
          method: "POST",
          credentials: "same-origin",
          headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
          body: body.toString()
        });
        const result = await response.json();
        if (currentRequest !== requestId) {
          return;
        }
        if (!response.ok || !result.success) {
          throw new Error(result && result.data && result.data.message ? result.data.message : "امکان دریافت زمان‌های آزاد وجود ندارد.");
        }

        time.innerHTML = "";
        const placeholder = document.createElement("option");
        placeholder.value = "";
        placeholder.textContent = "انتخاب ساعت";
        time.appendChild(placeholder);

        const slots = Array.isArray(result.data.slots) ? result.data.slots : [];
        slots.forEach(function (slot) {
          const option = document.createElement("option");
          option.value = slot.slice(11, 16);
          option.textContent = slot.slice(11, 16);
          time.appendChild(option);
        });

        time.disabled = slots.length === 0;
        submit.disabled = true;
        status.textContent = slots.length
          ? slots.length + " زمان قابل انتخاب پیدا شد. ظرفیت هنگام ثبت نهایی دوباره بررسی می‌شود."
          : "برای این تاریخ و خدمت زمان آزادی پیدا نشد.";
      } catch (error) {
        if (currentRequest !== requestId) {
          return;
        }
        resetTimes(error && error.message ? error.message : "خطا در دریافت زمان‌های آزاد.");
      }
    }

    [staff, service, date].forEach(function (field) {
      field.addEventListener("change", loadTimes);
    });
    time.addEventListener("change", function () {
      submit.disabled = !time.value;
    });

    resetTimes("برای مشاهده زمان‌های آزاد، متخصص، خدمت و تاریخ را انتخاب کنید.");
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initParisanBookingAdmin);
  } else {
    initParisanBookingAdmin();
  }
})();
