(function($) {
    'use strict';

    // DrawIt namespace
    window.DrawIt = window.DrawIt || {};

    // Configuration
    DrawIt.config = {
        plugin: {
            slug: "drawit",
            name: "DrawIt"
        },
        editor: {
            url: "https://embed.diagrams.net/?embed=1&ui=atlas&spin=1&proto=json&configure=1",
            defaultHeight: "700px"
        },
        monitoring: {
            activityTimeout: 60000, // 60 seconds
            checkInterval: 15000,   // 15 seconds
            initDelay: 10000        // 10 seconds
        }
    };

    // State management
    DrawIt.state = {
        iframe: null,
        iframeInitialized: false,
        initAttempts: 0,
        maxInitAttempts: 3,
        editorActive: false,
        recoveryMode: false,
        lastActivityTime: 0,
        activityCheckInterval: null
    };

    // Editor management
    DrawIt.editor = {
        init: function() {
            DrawIt.state.iframe = DrawIt.utils.getElement('iframe');
            DrawIt.editor.reset();
            DrawIt.editor.setupEventListeners();
        },
        
        reset: function(forceRecoveryMode) {
            // Clear previous state
            DrawIt.state.iframeInitialized = false;
            DrawIt.state.editorActive = false;
            DrawIt.state.initAttempts = 0;
            
            // Clear activity monitoring
            if (DrawIt.state.activityCheckInterval) {
                clearInterval(DrawIt.state.activityCheckInterval);
                DrawIt.state.activityCheckInterval = null;
            }
            
            // Set recovery mode if needed
            if (typeof forceRecoveryMode !== 'undefined' && forceRecoveryMode) {
                DrawIt.state.recoveryMode = true;
            }
            
            // Create a unique URL to prevent caching
            var timestamp = new Date().getTime();
            var randomStr = Math.random().toString(36).substring(2, 15);
            var urlWithParams = DrawIt.config.editor.url + "&t=" + timestamp + "&r=" + randomStr;
            
            if (DrawIt.state.recoveryMode) {
                urlWithParams += "&recovery=1";
            }
            
            // Replace the iframe with a new one
            var container = DrawIt.state.iframe.parentNode;
            var oldIframe = DrawIt.state.iframe;
            
            var newIframe = document.createElement("iframe");
            newIframe.id = DrawIt.config.plugin.slug + "-iframe";
            newIframe.className = DrawIt.config.plugin.slug + "-editor-iframe";
            newIframe.style.width = "100%";
            newIframe.style.height = DrawIt.config.editor.defaultHeight;
            newIframe.style.border = "none";
            newIframe.src = "about:blank";
            
            container.replaceChild(newIframe, oldIframe);
            DrawIt.state.iframe = newIframe;
            
            // Show status message
            var statusMsg = DrawIt.state.recoveryMode ? 
                "Editor is restarting in recovery mode..." : 
                "Initializing editor...";
            
            DrawIt.utils.showStatus(statusMsg, DrawIt.state.recoveryMode ? "orange" : "blue");
            
            // Load the editor after a delay
            setTimeout(function() {
                DrawIt.state.iframe.src = urlWithParams;
                
                // Start activity monitoring
                setTimeout(function() {
                    DrawIt.editor.startActivityMonitor();
                }, DrawIt.config.monitoring.initDelay);
            }, 300);
            
            // Ensure UI controls exist
            DrawIt.editor.createControlButtons();
        },
        
        sendMessage: function(data) {
            if (DrawIt.state.iframe && DrawIt.state.iframe.contentWindow) {
                try {
                    DrawIt.state.iframe.contentWindow.postMessage(JSON.stringify(data), "*");
                    
                    // Update activity timestamp
                    if (data.action !== 'ping') {
                        DrawIt.editor.updateActivity();
                    }
                } catch (e) {
                    DrawIt.utils.showStatus("Error communicating with the editor. Try resetting.", "red");
                }
            }
        },
        
        saveCallback: function(xml) {
            DrawIt.utils.showMask();
            
            // Update hidden field with XML content
            var xmlField = DrawIt.utils.getElement('xml');
            if (xmlField) {
                xmlField.value = xml;
            }
            
            // Save settings to localStorage
            if (!DrawIt.state.recoveryMode) {
                try {
                    localStorage.setItem(DrawIt.utils.getStorageKey('settings'), JSON.stringify({
                        lastModified: new Date(),
                        settings: {
                            grid: (xml.indexOf('grid="1"') > -1) ? "1" : "0",
                            page: (xml.indexOf('page="1"') > -1) ? "1" : "0"
                        }
                    }));
                } catch (e) {
                    // Silently handle error
                }
            }
            
            // Export the diagram
            var formatElement = DrawIt.utils.getElement('type');
            var format = formatElement ? formatElement.value : 'png';
            
            var exportMsg = {
                action: "export",
                format: format,
                xml: xml,
                spin: "Saving diagram"
            };
            
            // Add special settings for SVG exports
            if (format.toLowerCase() === "svg") {
                exportMsg.theme = "light";
                exportMsg.border = 0;
                exportMsg.background = "#ffffff";
                exportMsg.transparent = true;
            }
            
            DrawIt.editor.sendMessage(exportMsg);
        },
        
        processExportedImage: function(imgType, imgData) {
            // Get image ID from URL if available
            var urlParams = new URLSearchParams(window.location.search);
            var img_id = urlParams.has('img_id') ? urlParams.get('img_id') : "";
            
            // Prepare form data
            var data = {
                action: "submit-form-" + DrawIt.config.plugin.slug,
                title: DrawIt.utils.getElement('title').value,
                nonce: DrawIt.utils.getElement('nonce').value,
                post_id: DrawIt.utils.getElement('post-id').value,
                xml: DrawIt.utils.getElement('xml').value,
                img_type: imgType,
                img_data: imgData,
                img_id: img_id
            };
            
            // Submit to WordPress
            $.post(ajaxurl, data, function(response) {
                try {
                    var resp = JSON.parse(response);
                    
                    if (resp["success"]) {
                        // Clear draft on successful save
                        localStorage.removeItem(DrawIt.utils.getStorageKey('draft'));
                        DrawIt.utils.hideMask();
                        
                        // Reset editor
                        DrawIt.state.recoveryMode = false;
                        DrawIt.editor.reset(false);
                        
                        // Send to WordPress editor
                        parent.window.send_to_editor(resp["html"]);
                    } else {
                        // Show error dialog
                        DrawIt.editor.sendMessage({
                            action: "dialog",
                            title: "Error",
                            message: resp["html"],
                            button: "OK",
                            modified: true
                        });
                    }
                } catch (e) {
                    alert("Error saving diagram: " + e.message);
                }
                
                DrawIt.utils.hideMask();
            }).fail(function(xhr, status, error) {
                DrawIt.utils.hideMask();
                alert("Error saving diagram. Please try again.");
            });
        },
        
        createControlButtons: function() {
            var container = document.querySelector("." + DrawIt.config.plugin.slug + "-form-title-block");
            var existingButton = DrawIt.utils.getElement('reset-button');
            
            if (!existingButton && container) {
                var buttonContainer = document.createElement("div");
                buttonContainer.className = DrawIt.config.plugin.slug + "-reset-container";
                buttonContainer.style.marginTop = "10px";
                
                // Reset button
                var resetButton = document.createElement("button");
                resetButton.id = DrawIt.config.plugin.slug + "-reset-button";
                resetButton.className = DrawIt.config.plugin.slug + "-reset-button";
                resetButton.innerHTML = "Reset Editor";
                resetButton.style.marginRight = "10px";
                resetButton.onclick = function() {
                    if (confirm("Reset the editor? Any unsaved changes will be lost.")) {
                        DrawIt.editor.reset(false);
                    }
                    return false;
                };
                
                // Recovery button
                var recoverButton = document.createElement("button");
                recoverButton.id = DrawIt.config.plugin.slug + "-recover-button";
                recoverButton.className = DrawIt.config.plugin.slug + "-recover-button";
                recoverButton.innerHTML = "Recovery Mode";
                recoverButton.onclick = function() {
                    if (confirm("Start in recovery mode? This will load a minimal editor without your saved settings.")) {
                        DrawIt.utils.clearStorage();
                        DrawIt.editor.reset(true);
                    }
                    return false;
                };
                
                // Clear data button
                var clearButton = document.createElement("button");
                clearButton.id = DrawIt.config.plugin.slug + "-clear-button";
                clearButton.className = DrawIt.config.plugin.slug + "-clear-button";
                clearButton.innerHTML = "Clear Data";
                clearButton.style.marginLeft = "10px";
                clearButton.onclick = function() {
                    if (confirm("Clear all saved diagram data? This can help if the editor is having problems.")) {
                        DrawIt.utils.clearStorage();
                        alert("Data cleared. You may now reset the editor.");
                    }
                    return false;
                };
                
                buttonContainer.appendChild(resetButton);
                buttonContainer.appendChild(recoverButton);
                buttonContainer.appendChild(clearButton);
                container.appendChild(buttonContainer);
            }
        },

        alertEditorStuck: function() {
            DrawIt.utils.showStatus("Editor may be stuck. Try using Reset or Recovery buttons below.", "red");
            
            var resetButton = DrawIt.utils.getElement('reset-button');
            if (resetButton) {
                resetButton.style.backgroundColor = "#ffcc00";
                resetButton.style.fontWeight = "bold";
            }
        },
        
        resetStuckWarning: function() {
            DrawIt.utils.hideStatus();
            
            var resetButton = DrawIt.utils.getElement('reset-button');
            if (resetButton && resetButton.style.backgroundColor === "rgb(255, 204, 0)") {
                resetButton.style.backgroundColor = "";
                resetButton.style.fontWeight = "";
            }
        },

        exitEditor: function() {
            // Clean up
            if (DrawIt.state.activityCheckInterval) {
                clearInterval(DrawIt.state.activityCheckInterval);
                DrawIt.state.activityCheckInterval = null;
            }
            
            // Reset and close
            DrawIt.editor.reset();
            parent.window.tb_remove();
        },
        
        updateActivity: function() {
            DrawIt.state.lastActivityTime = Date.now();
            DrawIt.state.editorActive = true;
            DrawIt.editor.resetStuckWarning();
        },
        
        startActivityMonitor: function() {
            DrawIt.state.lastActivityTime = Date.now();
            
            // Ping function to test editor responsiveness
            var pingEditor = function() {
                try {
                    DrawIt.editor.sendMessage({
                        action: 'ping',
                        timestamp: Date.now()
                    });
                } catch (e) {
                    // Silently handle error
                }
            };
            
            // Check activity periodically
            DrawIt.state.activityCheckInterval = setInterval(function() {
                var currentTime = Date.now();
                var timeSinceLastActivity = currentTime - DrawIt.state.lastActivityTime;
                
                // Alert if no activity for the timeout period
                if (DrawIt.state.editorActive && timeSinceLastActivity > DrawIt.config.monitoring.activityTimeout) {
                    DrawIt.editor.alertEditorStuck();
                    pingEditor();
                }
            }, DrawIt.config.monitoring.checkInterval);
        },
        
        setupEventListeners: function() {
            // Handle messages from iframe
            window.addEventListener("message", DrawIt.editor.handleMessage);
            
            // Save draft on page unload
            window.addEventListener("beforeunload", function(e) {
                if (!DrawIt.state.recoveryMode) {
                    var draftKey = DrawIt.utils.getStorageKey('draft');
                    var xmlContent = DrawIt.utils.getElement('xml').value;
                    
                    if (xmlContent && xmlContent.trim() !== "") {
                        try {
                            localStorage.setItem(draftKey, JSON.stringify({
                                lastModified: new Date(),
                                xml: xmlContent
                            }));
                        } catch (e) {
                            // Silently handle error
                        }
                    }
                }
            });
        },
        
        handleMessage: function(evt) {
            try {
                if (evt.data && typeof evt.data === "string" && evt.data.length > 0) {
                    var resp = JSON.parse(evt.data);
                    DrawIt.editor.updateActivity();
                    
                    // Handle the message based on event type
                    switch (resp.event) {
                        case "pong":
                            DrawIt.editor.updateActivity();
                            break;
                            
                        case "configure":
                            DrawIt.editor.handleConfigureEvent(resp);
                            break;
                            
                        case "init":
                            DrawIt.editor.handleInitEvent(resp);
                            break;
                            
                        case "load":
                            DrawIt.state.editorActive = true;
                            
                            if (DrawIt.state.recoveryMode) {
                                setTimeout(function() {
                                    DrawIt.editor.sendMessage({
                                        action: "dialog",
                                        title: "Recovery Mode",
                                        message: "Editor is in recovery mode with minimal settings. Save your diagram to return to normal mode.",
                                        button: "OK",
                                        modified: false
                                    });
                                }, 1000);
                            }
                            break;
                            
                        case "autosave":
                            DrawIt.editor.handleAutosaveEvent(resp);
                            break;
                            
                        case "save":
                            DrawIt.editor.saveCallback(resp.xml);
                            break;
                            
                        case "export":
                            DrawIt.editor.processExportedImage(resp.format, resp.data);
                            break;
                            
                        case "exit":
                            DrawIt.editor.handleExitEvent(resp);
                            break;
                            
                        case "error":
                            alert("Editor error: " + resp.message);
                            break;
                            
                        default:
                            break;
                    }
                }
            } catch (e) {
                // Silently handle error
            }
        },

        handleConfigureEvent: function(resp) {
            DrawIt.state.initAttempts++;
            
            // Prepare configuration
            var configSettings = {
                defaultFonts: [
                    "Helvetica",
                    "Arial",
                    "Tahoma", 
                    "Verdana",
                    "Times New Roman",
                ],
                defaultTheme: "light",
                defaultColorScheme: "default",
                darkColor: "#000000",
                lightColor: "#ffffff",
            };
            
            // Adjust for recovery mode
            if (DrawIt.state.recoveryMode) {
                configSettings.ui = "min";
                configSettings.plugins = [];
                configSettings.preset = "minimal";
            } 
            // Try to use saved settings
            else {
                try {
                    var savedSettings = localStorage.getItem(DrawIt.utils.getStorageKey('settings'));
                    if (savedSettings) {
                        var parsedSettings = JSON.parse(savedSettings);
                        if (parsedSettings && parsedSettings.settings) {
                            window.drawit_saved_settings = parsedSettings.settings;
                        }
                    }
                } catch (e) {
                    // Silently handle error
                }
            }
            
            // Send configuration to the editor
            DrawIt.editor.sendMessage({
                action: "configure",
                config: configSettings
            });
        },
        
        handleInitEvent: function(resp) {
            DrawIt.state.iframeInitialized = true;
            DrawIt.state.editorActive = true;
            
            // Update UI
            if (DrawIt.state.recoveryMode) {
                DrawIt.utils.showStatus("Editor loaded in recovery mode", "green");
            } else {
                DrawIt.utils.hideStatus();
            }
            
            // Load diagram content
            var xmlField = DrawIt.utils.getElement('xml');
            var xml_content = "";
            
            // Handle content based on mode
            if (DrawIt.state.recoveryMode) {
                if (xmlField && xmlField.value !== "") {
                    xml_content = xmlField.value
                        .replace(/grid="[^"]*"/g, 'grid="1"')
                        .replace(/page="[^"]*"/g, 'page="1"');
                }
            } else {
                xml_content = DrawIt.editor.loadDiagramContent(xmlField);
            }
            
            // Send load message
            DrawIt.editor.sendMessage({
                action: "load",
                autosave: 1,
                xml: xml_content,
                noSaveBtn: false,
                noExitBtn: false,
                modified: false
            });
        },
        
        loadDiagramContent: function(xmlField) {
            var xml_content = "";
            var draftKey = DrawIt.utils.getStorageKey('draft');
            var draft = null;
            
            // Try to load draft
            try {
                var draftData = localStorage.getItem(draftKey);
                if (draftData) {
                    draft = JSON.parse(draftData);
                }
            } catch (e) {
                // Silently handle error
            }
            
            // Process draft if available
            if (draft !== null) {
                try {
                    var lastModified = new Date(draft.lastModified);
                    var formattedDate = lastModified.toLocaleString();
                    
                    if (confirm("A draft of this diagram from " + formattedDate + " was found. Would you like to continue editing?")) {
                        xml_content = draft.xml;
                        
                        // Indicate unsaved changes
                        setTimeout(function() {
                            DrawIt.editor.sendMessage({
                                action: 'status',
                                modified: true
                            });
                        }, 1000);
                    } else {
                        // Use original content
                        localStorage.removeItem(draftKey);
                        
                        if (xmlField && xmlField.value !== "") {
                            xml_content = xmlField.value;
                        }
                    }
                } catch (e) {
                    localStorage.removeItem(draftKey);
                    
                    if (xmlField && xmlField.value !== "") {
                        xml_content = xmlField.value;
                    }
                }
            } else if (xmlField && xmlField.value !== "") {
                xml_content = xmlField.value;
            }
            
            // Apply saved settings if available
            if (window.drawit_saved_settings && xml_content) {
                try {
                    var settings = window.drawit_saved_settings;
                    
                    if (settings.grid && xml_content.indexOf('grid=') > -1) {
                        xml_content = xml_content.replace(/grid="[^"]*"/g, 'grid="' + settings.grid + '"');
                    }
                    if (settings.page && xml_content.indexOf('page=') > -1) {
                        xml_content = xml_content.replace(/page="[^"]*"/g, 'page="' + settings.page + '"');
                    }
                } catch (e) {
                    // Silently handle error
                }
            }
            
            return xml_content;
        },
        
        handleAutosaveEvent: function(resp) {
            if (!DrawIt.state.recoveryMode) {
                try {
                    localStorage.setItem(DrawIt.utils.getStorageKey('draft'), JSON.stringify({
                        lastModified: new Date(),
                        xml: resp.xml
                    }));
                    
                    DrawIt.utils.updateDraftInfo(new Date().toLocaleTimeString());
                } catch (e) {
                    // Silently handle error
                }
            }
        },
        
        handleExitEvent: function(resp) {
            if (!DrawIt.state.recoveryMode) {
                if (confirm("Would you like to save a draft of this diagram?")) {
                    try {
                        localStorage.setItem(DrawIt.utils.getStorageKey('draft'), JSON.stringify({
                            lastModified: new Date(),
                            xml: resp.xml || DrawIt.utils.getElement('xml').value
                        }));
                    } catch (e) {
                        // Silently handle error
                    }
                } else {
                    localStorage.removeItem(DrawIt.utils.getStorageKey('draft'));
                }
            }
            
            DrawIt.editor.exitEditor();
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        DrawIt.editor.init();
    });

})(jQuery);
