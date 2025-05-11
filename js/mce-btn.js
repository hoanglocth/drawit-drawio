(function ($) {
	"use strict";

	// Use the common DrawIt namespace
	window.DrawIt = window.DrawIt || {};

	// TinyMCE (visual editor) button integration
	DrawIt.TinyMCE = {
		// Initialize button in the visual editor
		init: function () {
			var slug = DrawIt.utils.config.plugin.slug;
			var name = DrawIt.utils.config.plugin.name;

			// Register the TinyMCE plugin
			tinymce.PluginManager.add(slug + "_mce_button", function (editor, url) {
				// Add CSS for the button
				editor.on("init", function () {
					var cssURL = url + "/../css/" + slug + "-mce.css";

					if (document.createStyleSheet) {
						document.createStyleSheet(cssURL);
					} else {
						var cssLink = editor.dom.create("link", {
							rel: "stylesheet",
							href: cssURL,
						});
						document.getElementsByTagName("head")[0].appendChild(cssLink);
					}
				});

				// Add the button to TinyMCE
				editor.addButton(slug + "_mce_button", {
					tooltip: name + " Diagram",
					icon: slug,
					onclick: function () {
						var selectedCode = tinymce.activeEditor.selection.getContent();

						// If shortcode is detected, use its info, otherwise try parsing as image
						var imageInfo =
							DrawIt.utils.parseShortcode(selectedCode, slug) ||
							DrawIt.utils.parseImageInfo(selectedCode);
						var editorUrl = DrawIt.utils.getEditorUrl(imageInfo);

						// Fix base URL by removing '/js' suffix
						var baseUrl = url + "/../";

						tb_show("Draw a diagram", editorUrl, false);
						DrawIt.utils.styleThickbox(baseUrl);
					},
				});
			});
		},
	};

	// Initialize when document is ready
	$(document).ready(function () {
		DrawIt.TinyMCE.init();
	});
})(jQuery);
