(function ($) {
	function canUseMedia() {
		return window.wp && wp.media;
	}

	function previewUrl(att) {
		if (att && att.sizes) {
			if (att.sizes.medium) return att.sizes.medium.url;
			if (att.sizes.large) return att.sizes.large.url;
			if (att.sizes.thumbnail) return att.sizes.thumbnail.url;
		}
		return att && att.url ? att.url : "";
	}

	function thumbUrl(att) {
		if (att && att.sizes && att.sizes.thumbnail) {
			return att.sizes.thumbnail.url;
		}
		return previewUrl(att);
	}

	function pickImage(onSelect) {
		if (!canUseMedia()) {
			alert("No se ha cargado el selector de medios. Recarga la página.");
			return;
		}
		var frame = wp.media({
			title: "Selecciona una imagen",
			multiple: false,
			library: { type: "image" },
		});
		frame.on("select", function () {
			var att = frame.state().get("selection").first().toJSON();
			onSelect(att);
		});
		frame.open();
	}

	function pickImages(onSelect) {
		if (!canUseMedia()) {
			alert("No se ha cargado el selector de medios. Recarga la página.");
			return;
		}
		var frame = wp.media({
			title: "Selecciona imágenes",
			multiple: true,
			library: { type: "image" },
		});
		frame.on("select", function () {
			var selection = frame.state().get("selection");
			var atts = [];
			selection.each(function (model) {
				atts.push(model.toJSON());
			});
			onSelect(atts);
		});
		frame.open();
	}

	function renumberTopLeft() {
		$("#am_top_left_list > li").each(function (i) {
			var $li = $(this);
			$li.attr("data-index", i);
			$li.find("input,select,textarea").each(function () {
				var $el = $(this);
				var name = $el.attr("name");
				if (!name) return;
				name = name.replace(/am_top_left\[[0-9]+\]/g, "am_top_left[" + i + "]");
				$el.attr("name", name);
			});
		});
	}

	function renumberPubli() {
		$("#publi_gallery_list .publi-item").each(function (i) {
			var $li = $(this);
			$li.attr("data-index", i);
			$li.find("input,select,textarea").each(function () {
				var $el = $(this);
				var name = $el.attr("name");
				if (!name) return;
				name = name.replace(/publi_gallery\[[0-9]+\]/g, "publi_gallery[" + i + "]");
				$el.attr("name", name);
			});
		});
	}

	function ensureSortableTopLeft() {
		var $list = $("#am_top_left_list");
		if (!$list.length || !$.fn.sortable) return;
		if ($list.data("am-sortable")) return;
		$list
			.data("am-sortable", true)
			.sortable({
				items: "> li",
				axis: "y",
				handle: ".am-gallery-handle",
				cancel: "input,textarea,button,select,label,a",
				stop: function () {
					renumberTopLeft();
				},
			});
	}

	function ensureSortablePubli() {
		var $list = $("#publi_gallery_list");
		if (!$list.length || !$.fn.sortable) return;
		if ($list.data("am-sortable")) return;
		$list
			.data("am-sortable", true)
			.sortable({
				items: "> li",
				axis: "y",
				handle: ".publi-handle",
				cancel: "input,textarea,button,select,label,a",
				stop: function () {
					renumberPubli();
				},
			});
	}

	$(function () {
		ensureSortableTopLeft();
		ensureSortablePubli();
	});

	$(document).on("click", "#am_top_left_add", function (e) {
		e.preventDefault();
		pickImages(function (atts) {
			if (!atts || !atts.length) return;
			var nextIndex = 0;
			$("#am_top_left_list > li").each(function () {
				var idx = parseInt($(this).attr("data-index") || "0", 10);
				if (idx >= nextIndex) nextIndex = idx + 1;
			});
			atts.forEach(function (att) {
				var idx = nextIndex++;
				var $li = $(
					'<li class="am-gallery-item" data-index="' +
						idx +
						'">' +
						'<div class="am-gallery-row">' +
						'<span class="dashicons dashicons-move am-gallery-handle" aria-hidden="true"></span>' +
						'<div class="am-thumb am-top-left-preview"><img src="' +
						thumbUrl(att) +
						'" alt=""></div>' +
						'<div class="am-actions"><button type="button" class="button am-top-left-pick">Cambiar</button></div>' +
						'<button type="button" class="button-link-delete am-top-left-remove am-gallery-remove">Quitar</button>' +
						"</div>" +
						'<div class="am-gallery-meta">' +
						'<input type="hidden" name="am_top_left[' +
						idx +
						'][id]" value="' +
						att.id +
						'">' +
						'<div class="am-field"><label>Enlace</label><input type="url" class="regular-text" name="am_top_left[' +
						idx +
						'][url]" value="" placeholder="https://..."></div>' +
						'<label><input type="checkbox" name="am_top_left[' +
						idx +
						'][new_tab]" value="1"> Abrir en nueva pestaña</label>' +
						'<div class="am-actions" style="gap:12px;">' +
						'<div class="am-field" style="margin-top:0;min-width:160px;"><label>Inicio</label><input type="date" name="am_top_left[' +
						idx +
						'][start]" value=""></div>' +
						'<div class="am-field" style="margin-top:0;min-width:160px;"><label>Fin</label><input type="date" name="am_top_left[' +
						idx +
						'][end]" value=""></div>' +
						"</div>" +
						"</div>" +
						"</li>"
				);
				$("#am_top_left_list").append($li);
			});
			renumberTopLeft();
			ensureSortableTopLeft();
		});
	});

	$(document).on("click", "#am_top_left_list .am-top-left-remove", function (e) {
		e.preventDefault();
		$(this).closest("li").remove();
		renumberTopLeft();
	});

	$(document).on("click", "#am_top_left_list .am-top-left-pick", function (e) {
		e.preventDefault();
		var $li = $(this).closest("li");
		pickImage(function (att) {
			$li.find('input[type="hidden"][name*="[id]"]').val(att.id);
			$li.find(".am-top-left-preview").html('<img src="' + thumbUrl(att) + '" alt="">');
		});
	});

	$(document).on("click", "#news_rigor_pick", function (e) {
		e.preventDefault();
		pickImage(function (att) {
			$("#news_rigor_image_id").val(att.id);
			$("#news_rigor_preview").html('<img src="' + previewUrl(att) + '" alt="">');
			$("#news_rigor_clear").prop("disabled", false);
			$("#news_rigor_pick").text("Cambiar imagen");
		});
	});

	$(document).on("click", "#news_rigor_clear", function (e) {
		e.preventDefault();
		$("#news_rigor_image_id").val("");
		$("#news_rigor_preview").empty();
		$("#news_rigor_clear").prop("disabled", true);
		$("#news_rigor_pick").text("Elegir imagen");
	});

	$(document).on("click", "#publi_gallery_add", function (e) {
		e.preventDefault();
		pickImages(function (atts) {
			if (!atts || !atts.length) return;
			var nextIndex = 0;
			$("#publi_gallery_list .publi-item").each(function () {
				var idx = parseInt($(this).attr("data-index") || "0", 10);
				if (idx >= nextIndex) nextIndex = idx + 1;
			});
			atts.forEach(function (att) {
				var idx = nextIndex++;
				var $li = $(
					'<li class="publi-item am-gallery-item" data-index="' +
						idx +
						'">' +
						'<div class="am-gallery-row">' +
						'<span class="dashicons dashicons-move am-gallery-handle publi-handle" aria-hidden="true"></span>' +
						'<div class="publi-preview am-thumb"><img src="' +
						thumbUrl(att) +
						'" alt=""></div>' +
						'<div class="am-actions"><button type="button" class="button publi-pick">Cambiar</button></div>' +
						'<button type="button" class="button-link-delete publi-remove am-gallery-remove">Quitar</button>' +
						"</div>" +
						'<div class="am-gallery-meta">' +
						'<input type="hidden" name="publi_gallery[' +
						idx +
						'][id]" value="' +
						att.id +
						'">' +
						'<div class="am-field"><label>Enlace</label><input type="url" class="regular-text" name="publi_gallery[' +
						idx +
						'][url]" value="" placeholder="https://..."></div>' +
						'<label><input type="checkbox" name="publi_gallery[' +
						idx +
						'][new_tab]" value="1"> Abrir en nueva pestaña</label>' +
						"</div>" +
						"</li>"
				);
				$("#publi_gallery_list").append($li);
			});
			renumberPubli();
			ensureSortablePubli();
		});
	});

	$(document).on("click", "#publi_gallery_list .publi-remove", function (e) {
		e.preventDefault();
		$(this).closest("li").remove();
		renumberPubli();
	});

	$(document).on("click", "#publi_gallery_list .publi-pick", function (e) {
		e.preventDefault();
		var $li = $(this).closest("li");
		pickImage(function (att) {
			$li.find('input[type="hidden"][name*="[id]"]').val(att.id);
			$li.find(".publi-preview").html('<img src="' + thumbUrl(att) + '" alt="">');
		});
	});
})(jQuery);

