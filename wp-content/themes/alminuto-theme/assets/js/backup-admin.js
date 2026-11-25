(function () {
	var unitSelect = document.getElementById("am-sched-unit");
	var rowWeekday = document.getElementById("am-row-weekday");
	var rowMonthday = document.getElementById("am-row-monthday");

	function syncUnitRows() {
		if (!unitSelect) return;
		var val = unitSelect.value;
		if (rowWeekday) rowWeekday.style.display = val === "weeks" ? "" : "none";
		if (rowMonthday) rowMonthday.style.display = val === "months" ? "" : "none";
	}

	if (unitSelect) {
		unitSelect.addEventListener("change", syncUnitRows);
		syncUnitRows();
	}

	var copyBtn = document.getElementById("am-copy-redirect");
	var uriEl = document.getElementById("am-redirect-uri");
	if (copyBtn && uriEl && navigator.clipboard) {
		copyBtn.addEventListener("click", function () {
			navigator.clipboard.writeText(uriEl.textContent || "").then(function () {
				var orig = copyBtn.textContent;
				copyBtn.textContent = "Copiada";
				setTimeout(function () {
					copyBtn.textContent = orig;
				}, 1500);
			});
		});
	}

	var runForm = document.getElementById("am-backup-run-now-form");
	if (runForm) {
		runForm.addEventListener("submit", function (e) {
			var ok = window.confirm("¿Ejecutar una copia de seguridad ahora y subirla a Google Drive?");
			if (!ok) {
				e.preventDefault();
				return;
			}
			var btn = runForm.querySelector("button[type=submit]");
			if (btn) {
				btn.disabled = true;
				btn.textContent = "Ejecutando copia...";
			}
		});
	}

	// Visual monthly calendar
	var calGrid = document.getElementById("am-cal-grid");
	var calTitle = document.getElementById("am-cal-title");
	var prevBtn = document.getElementById("am-cal-prev");
	var nextBtn = document.getElementById("am-cal-next");
	var payload = window.alminutoBackupCalendar || { today: "", scheduled: {}, history: {} };

	if (calGrid && calTitle) {
		var monthNames = [
			"Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio",
			"Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"
		];
		var dowNames = ["Lun", "Mar", "Mié", "Jue", "Vie", "Sáb", "Dom"];

		var parts = (payload.today || "").split("-");
		var viewYear = parts.length === 3 ? parseInt(parts[0], 10) : new Date().getFullYear();
		var viewMonth = parts.length === 3 ? parseInt(parts[1], 10) - 1 : new Date().getMonth();

		function pad(n) {
			return n < 10 ? "0" + n : String(n);
		}

		function renderCalendar() {
			calTitle.textContent = monthNames[viewMonth] + " " + viewYear;
			calGrid.innerHTML = "";

			dowNames.forEach(function (d) {
				var el = document.createElement("div");
				el.className = "am-cal-dow";
				el.textContent = d;
				calGrid.appendChild(el);
			});

			var firstDay = new Date(viewYear, viewMonth, 1);
			var startDow = (firstDay.getDay() + 6) % 7; // Monday = 0
			var daysInMonth = new Date(viewYear, viewMonth + 1, 0).getDate();

			for (var i = 0; i < startDow; i++) {
				var empty = document.createElement("div");
				empty.className = "am-cal-day am-cal-day--empty";
				calGrid.appendChild(empty);
			}

			for (var day = 1; day <= daysInMonth; day++) {
				var dateKey = viewYear + "-" + pad(viewMonth + 1) + "-" + pad(day);
				var cell = document.createElement("div");
				cell.className = "am-cal-day" + (dateKey === payload.today ? " am-cal-day--today" : "");

				var numEl = document.createElement("div");
				numEl.className = "am-cal-day__num";
				numEl.textContent = String(day);
				cell.appendChild(numEl);

				var dots = document.createElement("div");
				dots.className = "am-cal-dots";

				var hist = payload.history && payload.history[dateKey] ? payload.history[dateKey] : [];
				hist.forEach(function (item) {
					var pill = document.createElement("span");
					pill.className = "am-cal-pill " + (item.status === "success" ? "am-cal-pill--ok" : "am-cal-pill--err");
					pill.textContent = item.time;
					pill.title = (item.status === "success" ? "Correcta" : "Error") + " (" + item.size + ")";
					dots.appendChild(pill);
				});

				var sched = payload.scheduled && payload.scheduled[dateKey] ? payload.scheduled[dateKey] : [];
				sched.slice(0, 3).forEach(function (t) {
					var pill = document.createElement("span");
					pill.className = "am-cal-pill am-cal-pill--sched";
					pill.textContent = t;
					pill.title = "Programada a las " + t;
					dots.appendChild(pill);
				});
				if (sched.length > 3) {
					var more = document.createElement("span");
					more.className = "am-cal-pill am-cal-pill--sched";
					more.textContent = "+" + (sched.length - 3);
					dots.appendChild(more);
				}

				cell.appendChild(dots);
				calGrid.appendChild(cell);
			}
		}

		if (prevBtn) {
			prevBtn.addEventListener("click", function () {
				viewMonth--;
				if (viewMonth < 0) {
					viewMonth = 11;
					viewYear--;
				}
				renderCalendar();
			});
		}

		if (nextBtn) {
			nextBtn.addEventListener("click", function () {
				viewMonth++;
				if (viewMonth > 11) {
					viewMonth = 0;
					viewYear++;
				}
				renderCalendar();
			});
		}

		renderCalendar();
	}
})();
