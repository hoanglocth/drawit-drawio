(function($) {
    'use strict';
    
    // Establish the DrawIt namespace
    window.DrawIt = window.DrawIt || {};
    
    // Common utilities for all DrawIt scripts
    DrawIt.utils = DrawIt.utils || {
        // Configuration
        config: {
            plugin: {
                slug: "drawit",
                name: "DrawIt"
            }
        },
        
        // Get element by ID with plugin prefix
        getElement: function(id) {
            return document.getElementById(DrawIt.utils.config.plugin.slug + "-" + id);
        },
        
        // Get storage key
        getStorageKey: function(type, customId) {
            var post_id = "";
            var img_id = customId || "new";
            
            try {
                var postIdElem = DrawIt.utils.getElement('post-id');
                if (postIdElem) {
                    post_id = postIdElem.value;
                } else {
                    // Try to get post ID from URL if element not available
                    var postIdMatch = new RegExp("[?&]post=([^&#]*)").exec(window.location.href);
                    post_id = postIdMatch !== null && postIdMatch.length > 1 ? postIdMatch[1] : 0;
                }
                
                var imgIdElem = DrawIt.utils.getElement('img-id');
                if (imgIdElem) {
                    img_id = imgIdElem.value || img_id;
                }
            } catch (e) {
                // Silently handle error
            }
            
            if (type === 'draft') {
                return DrawIt.utils.config.plugin.slug + "-draft-" + post_id + "-" + img_id;
            } else if (type === 'settings') {
                return DrawIt.utils.config.plugin.slug + "-editor-settings";
            }
            
            return "";
        },
        
        // Parse image information from HTML
        parseImageInfo: function(selectedCode) {
            var imageInfo = {
                title: "",
                id: ""
            };

            try {
                if (!selectedCode) return imageInfo;
                
                // Add <span></span> around this copy of selection for ease of jQuery parsing
                var jCode = $("<span>" + selectedCode + "</span>").find("img").first();
                if (jCode.length === 0) return imageInfo;

                // Get title if provided
                var diagTitle = jCode.attr("title");
                if (typeof diagTitle !== typeof undefined && diagTitle !== false) {
                    imageInfo.title = "&title=" + encodeURIComponent(diagTitle);
                }

                // Get image ID from class if available
                var imgClass = jCode.attr("class");
                if (typeof imgClass !== typeof undefined && imgClass !== false) {
                    var imgClassSplit = imgClass.split(" ");
                    for (var i = 0; i < imgClassSplit.length; i++) {
                        var elem = imgClassSplit[i];
                        if (elem.indexOf("wp-image-") === 0) {
                            var idParts = elem.split("-");
                            if (idParts.length >= 3) {
                                imageInfo.id = "&img_id=" + idParts[2];
                            }
                        }
                    }
                }
            } catch (err) {
                // Silently handle error
            }

            return imageInfo;
        },
        
        // Get current post ID from URL
        getPostId: function() {
            var postIdMatch = new RegExp("[?&]post=([^&#]*)").exec(window.location.href);
            return postIdMatch !== null && postIdMatch.length > 1 ? postIdMatch[1] : 0;
        },
        
        // Style the thickbox for better editor display
        styleThickbox: function(baseUrl) {
            var pluginImgPath = baseUrl || '/wp-content/plugins/' + DrawIt.utils.config.plugin.slug;
            
            $("#TB_window").css({
                "min-width": "90%",
                left: "calc(-1 * (" + $("#TB_window").css("margin-left") + ") + 5%)",
                background: 'url("' + pluginImgPath + '/img/wpspin-2x.gif") no-repeat center center #fff'
            });
            
            $("#TB_window > iframe").css({
                "min-width": "100%"
            });
        },

        // Get editor URL with parameters
        getEditorUrl: function(imageInfo) {
            var postId = DrawIt.utils.getPostId();
            return "media-upload.php?referer=" + DrawIt.utils.config.plugin.slug + 
                   "&type=" + DrawIt.utils.config.plugin.slug + 
                   "&post_id=" + postId + 
                   (imageInfo.title || "") + 
                   (imageInfo.id || "") + 
                   "&TB_iframe=true";
        },
        
        // Show status message
        showStatus: function(message, color) {
            var statusDiv = document.querySelector("." + DrawIt.utils.config.plugin.slug + "-editor-status");
            if (!statusDiv) {
                statusDiv = document.createElement("div");
                statusDiv.className = DrawIt.utils.config.plugin.slug + "-editor-status";
                var container = document.querySelector("." + DrawIt.utils.config.plugin.slug + "-form-title-block");
                if (container) {
                    container.appendChild(statusDiv);
                }
            }
            statusDiv.innerHTML = message;
            statusDiv.style.color = color || "blue";
            statusDiv.style.display = "block";
        },

        // Hide status message
        hideStatus: function() {
            var statusDiv = document.querySelector("." + DrawIt.utils.config.plugin.slug + "-editor-status");
            if (statusDiv) {
                statusDiv.style.display = "none";
            }
        },
        
        // Show loading mask
        showMask: function() {
            var mask = DrawIt.utils.getElement('editor-mask');
            if (mask) {
                mask.style.display = "block";
            }
        },
        
        // Hide loading mask
        hideMask: function() {
            var mask = DrawIt.utils.getElement('editor-mask');
            if (mask) {
                mask.style.display = "none";
            }
        },

        // Update draft info display
        updateDraftInfo: function(time) {
            var draftInfo = DrawIt.utils.getElement('draft-info');
            if (draftInfo) {
                draftInfo.innerHTML = "Draft auto-saved at " + time;
                draftInfo.style.display = "block";
            }
        },

        // Clear all storage data for current diagram
        clearStorage: function() {
            try {
                localStorage.removeItem(DrawIt.utils.getStorageKey('draft'));
                localStorage.removeItem(DrawIt.utils.getStorageKey('settings'));
                
                // Clear other potential formats
                var post_id = DrawIt.utils.getPostId();
                var img_id_elem = DrawIt.utils.getElement('img-id');
                var img_id = img_id_elem ? img_id_elem.value : "";
                var slug = DrawIt.utils.config.plugin.slug;
                
                localStorage.removeItem(slug + "-" + post_id + "-" + img_id);
                localStorage.removeItem(slug + "_" + post_id + "_" + img_id);
                localStorage.removeItem(slug + "-draft-" + post_id);
            } catch (e) {
                // Silently handle error
            }
        }
    };
    
})(jQuery);
