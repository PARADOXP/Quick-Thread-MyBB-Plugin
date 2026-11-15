/**
 * Quick Thread JavaScript
 * XenForo-style Quick Thread functionality for MyBB
 * 
 * @author PARADOX
 * @version 2.0
 */

var QuickThread = {
    forms: {},
    settings: {
        animationDuration: 300,
        highlightDuration: 3000,
        scrollBehavior: 'smooth',
        animationsEnabled: true
    },

    /**
     * Initialize Quick Thread for a specific forum
     */
    init: function (fid) {
        var wrapper = document.getElementById('quick-thread-' + fid);
        var formId = 'quickthread_form_' + fid;
        var form = document.getElementById(formId);

        if (!form) {
            return;
        }

        // Check animation setting from data attribute
        if (wrapper && wrapper.dataset.animate === 'false') {
            this.settings.animationDuration = 0;
        }

        this.forms[fid] = {
            fid: fid,
            form: form,
            titleInput: document.getElementById('qt_subject_' + fid),
            collapsed: form.querySelector('.qt-collapsed'),
            expanded: document.getElementById('qt_expanded_' + fid),
            loaded: true,
            expanded_state: false
        };

        this.bindEvents(fid);
    },

    /**
     * Bind event listeners
     */
    bindEvents: function (fid) {
        var self = this;
        var qt = this.forms[fid];

        if (!qt || !qt.titleInput) {
            return;
        }

        // Title input focus → Load and expand form
        qt.titleInput.addEventListener('focus', function () {
            self.expand(fid);
        });

        // Form submit → Check if AJAX mode is enabled
        qt.form.addEventListener('submit', function (e) {
            var isAjaxMode = qt.form.dataset.ajaxMode === 'true';
            
            if (isAjaxMode) {
                e.preventDefault();
                self.submit(fid);
            } else {
                // Normal submission - just add loading indicator
                var submitBtn = qt.form.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="qt-spinner-small"></span> Posting...';
                }
            }
        });
 
        // Event delegation for buttons (since they're loaded via AJAX)
        qt.form.addEventListener('click', function (e) {
            var target = e.target;

            // Tab buttons
            if (target.classList.contains('qt-tab')) {
                e.preventDefault();
                var tabName = target.dataset.tab;
                self.switchTab(fid, tabName);
                return;
            }

            // Poll checkbox - show/hide options
            if (target.classList.contains('qt-poll-checkbox') || target.closest('.qt-poll-checkbox')) {
                var checkbox = qt.expanded.querySelector('input[name="postpoll"]');
                var pollOptions = qt.expanded.querySelector('.qt-poll-options');
                if (checkbox && pollOptions) {
                    pollOptions.style.display = checkbox.checked ? '' : 'none';
                }
                return;
            }

            // Number spinner arrows
            var arrowBtn = target.closest('[data-numpoll-arrow]');
            if (arrowBtn) {
                var input = qt.expanded.querySelector('input[name="numpolloptions"]');
                if (input) {
                    var dir = arrowBtn.getAttribute('data-numpoll-arrow');
                    var min = parseInt(input.min, 10) || 2;
                    var max = parseInt(input.max, 10) || 10;
                    var val = parseInt(input.value, 10) || 2;
                    if (dir === 'up' && val < max) {
                        input.value = val + 1;
                    } else if (dir === 'down' && val > min) {
                        input.value = val - 1;
                    }
                }
                return;
            }

            // Cancel button
            if (target.classList.contains('qt-cancel') || target.closest('.qt-cancel')) {
                e.preventDefault();
                self.collapse(fid);
            }

            // More options button
            if (target.classList.contains('qt-more-options') || target.closest('.qt-more-options')) {
                e.preventDefault();
                var moreFid = target.dataset.fid || qt.fid;
                // Get current form data and redirect with it
                var subject = qt.titleInput ? qt.titleInput.value : '';
                var message = document.getElementById('qt_message_' + moreFid);
                var messageValue = message ? message.value : '';

                // Redirect to full newthread page
                var url = 'newthread.php?fid=' + moreFid;
                if (subject) {
                    url += '&subject=' + encodeURIComponent(subject);
                }
                if (messageValue) {
                    url += '&message=' + encodeURIComponent(messageValue);
                }
                window.location.href = url;
            }
        });
    },

    /**
     * Expand the quick thread form
     */
    expand: function (fid) {
        var qt = this.forms[fid];

        if (!qt) {
            return;
        }

        if (qt.expanded_state) {
            return;
        }

        // Show expanded section
        qt.expanded.style.display = 'block';
        qt.expanded_state = true;

        // Add class to collapsed section for styling
        if (qt.collapsed) {
            qt.collapsed.classList.add('qt-expanded-state');
        }

        // Animate expansion (if enabled)
        if (this.settings.animationDuration > 0) {
            setTimeout(function () {
                qt.expanded.classList.add('qt-visible');
            }, 10);
        } else {
            qt.expanded.classList.add('qt-visible');
        }
    },

    /**
     * Switch between tabs (Message / Poll)
     */
    switchTab: function (fid, tabName) {
        var qt = this.forms[fid];
        if (!qt || !qt.expanded) return;

        // Update tab buttons
        var tabs = qt.expanded.querySelectorAll('.qt-tab');
        tabs.forEach(function(tab) {
            if (tab.dataset.tab === tabName) {
                tab.classList.add('qt-tab-active');
            } else {
                tab.classList.remove('qt-tab-active');
            }
        });

        // Show/hide tab content
        var messageContent = qt.expanded.querySelector('.qt-tab-message');
        var pollContent = qt.expanded.querySelector('.qt-tab-poll');

        if (tabName === 'message') {
            if (messageContent) messageContent.style.display = 'block';
            if (pollContent) pollContent.style.display = 'none';
        } else if (tabName === 'poll') {
            if (messageContent) messageContent.style.display = 'none';
            if (pollContent) pollContent.style.display = 'block';
        }
    },

    /**
     * Collapse the quick thread form
     */
    collapse: function (fid) {
        var qt = this.forms[fid];
        var self = this;

        if (!qt) {
            return;
        }

        // Hide expanded section
        qt.expanded.classList.remove('qt-visible');

        if (this.settings.animationDuration > 0) {
            setTimeout(function () {
                qt.expanded.style.display = 'none';
                qt.expanded_state = false;
            }, this.settings.animationDuration);
        } else {
            qt.expanded.style.display = 'none';
            qt.expanded_state = false;
        }

        // Remove expanded state class
        if (qt.collapsed) {
            qt.collapsed.classList.remove('qt-expanded-state');
        }

        // Clear form
        qt.form.reset();

        // Clear title input
        if (qt.titleInput) {
            qt.titleInput.value = '';
        }

        // Clear any error messages
        var errors = qt.expanded.querySelectorAll('.qt-errors');
        errors.forEach(function (error) {
            error.remove();
        });
    },

    /**
     * Load form fields via AJAX
     */
    loadForm: function (fid) {
        var self = this;
        var qt = this.forms[fid];

        if (!qt) {
            return;
        }

        // Show loading state
        qt.expanded.innerHTML = '<div class="qt-loading-state"><span class="qt-spinner"></span> Loading editor...</div>';

        var xhr = new XMLHttpRequest();
        xhr.open('GET', 'xmlhttp.php?action=quickthread_load_form&fid=' + fid, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        xhr.onload = function () {
            if (xhr.status === 200) {
                // Check if response is JSON or HTML
                var contentType = xhr.getResponseHeader('content-type');
                if (contentType && contentType.indexOf('application/json') !== -1) {
                    // Response is JSON
                    try {
                        var response = JSON.parse(xhr.responseText);

                        if (response.success && response.html) {
                            qt.expanded.innerHTML = response.html;
                            qt.loaded = true;

                            // Initialize any MyCode editors, smilie inserters
                            self.initializeEditors(fid);

                            // Focus message field
                            setTimeout(function () {
                                var messageField = document.getElementById('qt_message_' + fid);
                                if (messageField) {
                                    messageField.focus();
                                }
                            }, 100);
                        } else if (response.error) {
                            qt.expanded.innerHTML = '<div class="qt-error">' + response.error + '</div>';
                        } else {
                            qt.expanded.innerHTML = '<div class="qt-error">Error loading form. Please refresh the page.</div>';
                        }
                    } catch (e) {
                        console.error('Error parsing JSON:', e);
                        qt.expanded.innerHTML = '<div class="qt-error">Error loading form. Please refresh the page.</div>';
                    }
                } else {
                    // Response is HTML (probably an error page)
                    console.error('Received HTML instead of JSON:', xhr.responseText.substring(0, 500));
                    qt.expanded.innerHTML = '<div class="qt-error">Error loading form. Please refresh the page.</div>';
                }
            } else {
                qt.expanded.innerHTML = '<div class="qt-error">Error loading form (Status: ' + xhr.status + ')</div>';
            }
        };

        xhr.onerror = function () {
            qt.expanded.innerHTML = '<div class="qt-error">Network error. Please check your connection.</div>';
        };

        xhr.send();
    },

    /**
     * Initialize editors (MyCode, smilies, etc.)
     */
    initializeEditors: function (fid) {
        var self = this;
        var qt = this.forms[fid];

        // Sync prefix selector from collapsed to expanded form
        var collapsedPrefix = qt.form.querySelector('.qt-prefix');
        var expandedPrefix = qt.expanded.querySelector('.qt-prefix');
        if (collapsedPrefix && expandedPrefix) {
            // Set the expanded prefix to match collapsed
            expandedPrefix.value = collapsedPrefix.value;
            // Sync changes from expanded back to collapsed
            expandedPrefix.addEventListener('change', function() {
                collapsedPrefix.value = this.value;
            });
        }

        // Execute any script tags in the expanded area (for SCEditor initialization)
        var scripts = qt.expanded.querySelectorAll('script');
        scripts.forEach(function(script) {
            if (script.src) {
                // External script - load it
                var newScript = document.createElement('script');
                newScript.src = script.src;
                newScript.async = false;
                script.parentNode.insertBefore(newScript, script);
                newScript.onload = function() {
                    // Try to initialize SCEditor after script loads
                    self.initSCEditor(fid);
                };
            } else {
                // Inline script - execute it directly
                try {
                    eval(script.textContent);
                } catch (e) {
                    console.error('Error executing inline script:', e);
                }
            }
        });

        // Also try to initialize SCEditor directly in case the above didn't work
        setTimeout(function() {
            self.initSCEditor(fid);
        }, 100);

        // Initialize clickable smilies if present
        var smilieLinks = document.querySelectorAll('#qt_message_' + fid + '_smilies a');
        if (smilieLinks.length > 0) {
            smilieLinks.forEach(function (link) {
                link.addEventListener('click', function (e) {
                    e.preventDefault();
                    var smilie = this.textContent || this.innerText;
                    var messageField = document.getElementById('qt_message_' + fid);
                    if (messageField) {
                        messageField.value += ' ' + smilie + ' ';
                        messageField.focus();
                    }
                });
            });
        }
    },

    /**
     * Initialize SCEditor for the textarea
     */
    initSCEditor: function(fid) {
        var textarea = document.getElementById('qt_message_' + fid);
        if (!textarea) {
            return;
        }

        // Check if SCEditor is already initialized on this textarea
        if (jQuery && jQuery.fn.sceditor) {
            var editorId = 'qt_message_' + fid;
            
            // Check if already initialized
            if (jQuery('#' + editorId).data('sceditor')) {
                return;
            }

            // Get editor options from global scope if available
            var editorOptions = window.opt_editor || {};
            
            // Initialize SCEditor
            jQuery('#' + editorId).sceditor(editorOptions);
            
            // Set global MyBBEditor reference
            window.MyBBEditor = jQuery('#' + editorId).sceditor('instance');
        }
    },

    /**
     * Submit the quick thread form via AJAX
     */
    submit: function (fid) {
        var self = this;
        var qt = this.forms[fid];

        if (!qt) {
            return;
        }

        // Show loading state
        qt.form.classList.add('qt-submitting');

        // Disable submit button
        var submitBtn = qt.form.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.dataset.originalText = submitBtn.textContent;
            submitBtn.innerHTML = '<span class="qt-spinner-small"></span> Posting...';
        }

        // Build form data
        var formData = new FormData(qt.form);
        
        // Get message from SCEditor if initialized
        if (window.MyBBEditor && typeof MyBBEditor !== 'undefined') {
            var editorContent = MyBBEditor.val();
            formData.set('message', editorContent);
        }

        // Use AJAX endpoint
        var ajaxUrl = 'xmlhttp.php?action=quickthread_submit&fid=' + fid;
        
        var xhr = new XMLHttpRequest();
        xhr.open('POST', ajaxUrl, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        xhr.onload = function () {
            qt.form.classList.remove('qt-submitting');

            // Re-enable submit button
            if (submitBtn) {
                submitBtn.disabled = false;
                if (submitBtn.dataset.originalText) {
                    submitBtn.textContent = submitBtn.dataset.originalText;
                }
            }

            if (xhr.status === 200) {
                try {
                    var response = JSON.parse(xhr.responseText);

                    if (response.errors && response.errors.length > 0) {
                        self.showErrors(fid, response.errors);
                    } else if (response.success) {
                        // Check for poll redirect
                        if (response.redirect) {
                            window.location.href = response.redirect;
                        } else {
                            self.onSuccess(fid, response);
                        }
                    } else if (response.error) {
                        self.showErrors(fid, [response.error]);
                    } else {
                        self.showErrors(fid, ['An unknown error occurred. Please try again.']);
                    }
                } catch (e) {
                    console.error('Error parsing response:', e);
                    // Fallback: reload page
                    window.location.reload();
                }
            } else {
                self.showErrors(fid, ['Server error (Status: ' + xhr.status + '). Please try again.']);
            }
        };

        xhr.onerror = function () {
            qt.form.classList.remove('qt-submitting');
            if (submitBtn) {
                submitBtn.disabled = false;
                if (submitBtn.dataset.originalText) {
                    submitBtn.textContent = submitBtn.dataset.originalText;
                }
            }
            self.showErrors(fid, ['Network error. Please check your connection.']);
        };

        xhr.send(formData);
    },

    /**
     * Handle successful thread creation
     */
    onSuccess: function (fid, data) {
        var self = this;
        var qt = this.forms[fid];

        // If we have a link, redirect to the thread
        if (data.link) {
            // Show success message briefly then redirect
            this.showMessage(data.message || 'Thread posted successfully!', 'success');
            
            setTimeout(function () {
                window.location.href = data.link;
            }, 1000);
        } else {
            // No link - just show message and reload
            this.showMessage(data.message || 'Thread posted successfully!', 'success');
            
            // Collapse the form
            setTimeout(function () {
                self.collapse(fid);
            }, 1000);
            
            // Reload after a delay
            setTimeout(function () {
                window.location.reload();
            }, 1500);
        }
    },

    /**
     * Insert new thread into the thread list
     */
    insertThread: function (html, tid) {
        // Find the thread list table body
        var threadList = document.querySelector('.tborder tbody');

        if (!threadList) {
            console.warn('Thread list not found');
            // Fallback: refresh page
            setTimeout(function () {
                window.location.reload();
            }, 1000);
            return;
        }

        // Find the header row (usually has class "thead")
        var headerRow = threadList.querySelector('.thead');

        if (!headerRow) {
            // Try to find first tr
            headerRow = threadList.querySelector('tr');
        }

        // Create temporary container
        var tempDiv = document.createElement('div');
        tempDiv.innerHTML = html.trim();
        var newThread = tempDiv.firstElementChild;

        if (!newThread) {
            console.warn('Could not parse thread HTML');
            return;
        }

        // Insert thread after header
        if (headerRow && headerRow.nextSibling) {
            threadList.insertBefore(newThread, headerRow.nextSibling);
        } else {
            threadList.appendChild(newThread);
        }

        // Highlight animation
        newThread.classList.add('qt-highlight');

        setTimeout(function () {
            newThread.classList.remove('qt-highlight');
        }, this.settings.highlightDuration);
    },

    /**
     * Show error messages
     */
    showErrors: function (fid, errors) {
        var qt = this.forms[fid];

        if (!qt) {
            return;
        }

        // Remove existing errors
        var existingErrors = qt.expanded.querySelectorAll('.qt-errors');
        existingErrors.forEach(function (el) {
            el.remove();
        });

        // Build error HTML
        var errorHtml = '<div class="qt-errors">';
        errors.forEach(function (error) {
            errorHtml += '<p class="qt-error-item">' + error + '</p>';
        });
        errorHtml += '</div>';

        // Insert at top of expanded section
        qt.expanded.insertAdjacentHTML('afterbegin', errorHtml);

        // Scroll to errors
        qt.expanded.scrollIntoView({
            behavior: this.settings.scrollBehavior,
            block: 'nearest'
        });
    },

    /**
     * Show success/info message
     */
    showMessage: function (message, type) {
        type = type || 'info';

        // Use MyBB's built-in notification if available
        if (typeof MyBB !== 'undefined' && typeof MyBB.prompt === 'function') {
            MyBB.prompt(message);
            return;
        }

        // Fallback: Create simple notification
        var notification = document.createElement('div');
        notification.className = 'qt-notification qt-notification-' + type;
        notification.textContent = message;
        notification.style.cssText = 'position:fixed;top:20px;right:20px;padding:15px 20px;background:#4caf50;color:#fff;border-radius:4px;box-shadow:0 2px 8px rgba(0,0,0,0.2);z-index:10000;';

        document.body.appendChild(notification);

        setTimeout(function () {
            notification.style.opacity = '0';
            notification.style.transition = 'opacity 0.3s';
            setTimeout(function () {
                document.body.removeChild(notification);
            }, 300);
        }, 3000);
    }
};

// ============================================================
// FORUM CHOOSER
// ============================================================

var QuickThreadChooser = {
    overlay: null,

    /**
     * Initialize the forum chooser
     */
    init: function () {
        this.overlay = document.getElementById('qt-chooser-overlay');

        if (!this.overlay) {
            return;
        }

        // Bind events
        var self = this;

        // Close on overlay click
        this.overlay.addEventListener('click', function (e) {
            if (e.target === self.overlay) {
                self.close();
            }
        });

        // Close on ESC key
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && self.overlay && self.overlay.style.display === 'flex') {
                self.close();
            }
        });

        // Close button
        var closeBtn = this.overlay.querySelector('.qt-chooser-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', function () {
                self.close();
            });
        }
    },

    /**
     * Open the chooser overlay
     */
    open: function () {
        if (!this.overlay) {
            this.overlay = document.getElementById('qt-chooser-overlay');
        }

        if (this.overlay) {
            this.overlay.style.display = 'flex';
            document.body.style.overflow = 'hidden';

            // Focus first forum link
            setTimeout(function () {
                var firstLink = document.querySelector('.qt-forum-item:not(.disabled) a');
                if (firstLink) {
                    firstLink.focus();
                }
            }, 100);
        }
    },

    /**
     * Close the chooser overlay
     */
    close: function () {
        if (this.overlay) {
            this.overlay.style.display = 'none';
            document.body.style.overflow = '';
        }
    }
};

// ============================================================
// AUTO-INITIALIZATION
// ============================================================

// Wait for DOM to be ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
        QuickThreadChooser.init();
    });
} else {
    QuickThreadChooser.init();
}
