(function($) {
    'use strict';

    // Use the common DrawIt namespace
    window.DrawIt = window.DrawIt || {};

    // QuickTags (text editor) button integration
    DrawIt.QuickTags = {
        // Initialize button in the text editor
        init: function() {
            var slug = DrawIt.utils.config.plugin.slug;
            var name = DrawIt.utils.config.plugin.name;
            
            // Add button to QuickTags toolbar
            QTags.addButton(slug, name, function(el, canvas) {
                var selectedCode = "";
                canvas.focus();

                // Get selected content
                if (document.selection) {
                    selectedCode = document.selection.createRange().text;
                } else {
                    selectedCode = canvas.value.substring(
                        canvas.selectionStart, 
                        canvas.selectionEnd
                    );
                }

                var imageInfo = DrawIt.utils.parseImageInfo(selectedCode);
                var editorUrl = DrawIt.utils.getEditorUrl(imageInfo);

                tb_show("Draw a diagram", editorUrl, false);
                DrawIt.utils.styleThickbox();
                
                return false;
            });
        }
    };

    // Initialize when document is ready and QuickTags is available
    $(document).ready(function() {
        if (typeof QTags !== 'undefined') {
            DrawIt.QuickTags.init();
        }
    });

})(jQuery);
