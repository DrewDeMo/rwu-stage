/**
 * TCL Builder
 * Main builder initialization
 */

(function ($) {
    'use strict';

    // Define global TCLBuilder object with namespaces
    if (typeof window.TCLBuilder === 'undefined') {
        window.TCLBuilder = {
            Core: {},
            Sections: {},
            Editors: {},
            Modal: {},
            DragDrop: {},
            WordPress: {},
            Utils: {},
            Events: {},
            DID: {},  // Add DID namespace
            Logger: {
                log: function (message, level = 'info', context = {}) {
                    // Add timestamp to context
                    context.timestamp = new Date().toISOString();
                    context.url = window.location.href;

                    // Log to console
                    const logFn = level === 'error' ? console.error :
                        level === 'warning' ? console.warn :
                            console.log;
                    logFn(`[${level.toUpperCase()}] ${message}`, context);

                    // Send to PHP logger via AJAX
                    $.ajax({
                        url: tclBuilderData.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'tcl_builder_log',
                            nonce: tclBuilderData.nonce,
                            message: message,
                            level: level,
                            context: JSON.stringify(context)
                        }
                    });
                },
                error: function (message, context = {}) {
                    this.log(message, 'error', context);
                },
                warning: function (message, context = {}) {
                    this.log(message, 'warning', context);
                },
                info: function (message, context = {}) {
                    this.log(message, 'info', context);
                },
                debug: function (message, context = {}) {
                    this.log(message, 'debug', context);
                }
            }
        };
    }

    // Global error handler
    window.onerror = function (msg, url, lineNo, columnNo, error) {
        TCLBuilder.Logger.error('JavaScript Error', {
            message: msg,
            url: url,
            line: lineNo,
            column: columnNo,
            stack: error ? error.stack : 'no stack'
        });
        return false;
    };

    // Monitor AJAX errors
    $(document).ajaxError(function (event, jqXHR, settings, error) {
        TCLBuilder.Logger.error('AJAX Error', {
            status: jqXHR.status,
            statusText: jqXHR.statusText,
            responseText: jqXHR.responseText,
            url: settings.url,
            type: settings.type,
            error: error
        });
    });

    // Initialize on document ready
    $(document).ready(() => {
        // Initialize the builder
        if (TCLBuilder.Core && TCLBuilder.Core.init) {
            TCLBuilder.Logger.info('Initializing TCL Builder');

            // Monitor content changes
            $(document).on('change', '.code-editor', function () {
                const editorType = $(this).hasClass('html-editor') ? 'HTML' : 'CSS';
                const content = $(this).val();
                TCLBuilder.Logger.debug(`${editorType} content changed`, {
                    type: editorType,
                    length: content.length,
                    preview: content.substring(0, 100) // First 100 chars
                });
            });

            // Monitor section operations
            $(document).on('click', '.add-section-btn, .delete-section-btn', function () {
                const action = $(this).hasClass('add-section-btn') ? 'add' : 'delete';
                TCLBuilder.Logger.info(`Section ${action} initiated`, {
                    action: action,
                    button_class: this.className,
                    section_count: $('.tcl-builder-section').length
                });
            });

            // Monitor save operations
            $(document).on('click', '.primary-btn', function () {
                if ($(this).closest('.modal-footer').length) {
                    TCLBuilder.Logger.info('Saving section changes', {
                        modal_type: $(this).closest('.modal-overlay').data('modal'),
                        has_html: $('.html-editor').val().length > 0,
                        has_css: $('.css-editor').val().length > 0
                    });
                }
            });

            // Add error notification container if not exists
            if (!$('.tcl-builder-notifications').length) {
                $('body').append('<div class="tcl-builder-notifications"></div>');
            }

            // Handle save errors
            TCLBuilder.Events.subscribe('wordpress:saveError', (error) => {
                TCLBuilder.Logger.error('Save operation failed', error);

                // Show error notification
                const notification = $(`
                    <div class="tcl-builder-notification error">
                        <div class="notification-content">
                            <strong>Error Saving Content</strong>
                            <p>${error.message || 'An error occurred while saving. Please try again.'}</p>
                        </div>
                        <button class="close-notification">&times;</button>
                    </div>
                `).hide();

                $('.tcl-builder-notifications').append(notification);
                notification.slideDown();

                // Auto-hide after 5 seconds
                setTimeout(() => {
                    notification.slideUp(() => notification.remove());
                }, 5000);

                // Allow manual close
                notification.find('.close-notification').on('click', () => {
                    notification.slideUp(() => notification.remove());
                });
            });

            // Initialize the builder and DID module
            TCLBuilder.Core.init();

            // Initialize DID module if available
            if (TCLBuilder.DID && TCLBuilder.DID.init) {
                TCLBuilder.Logger.info('Initializing DID module');
                TCLBuilder.DID.init();
            }

            // Add notification styles
            $('<style>')
                .text(`
                    .tcl-builder-notifications {
                        position: fixed;
                        top: 32px;
                        right: 20px;
                        z-index: 999999;
                        width: 300px;
                    }
                    .tcl-builder-notification {
                        background: white;
                        border-left: 4px solid #dc3232;
                        box-shadow: 0 2px 5px rgba(0,0,0,0.2);
                        margin-bottom: 10px;
                        padding: 15px;
                        position: relative;
                        border-radius: 4px;
                    }
                    .tcl-builder-notification.error {
                        border-left-color: #dc3232;
                    }
                    .tcl-builder-notification .notification-content {
                        padding-right: 20px;
                    }
                    .tcl-builder-notification strong {
                        display: block;
                        margin-bottom: 5px;
                    }
                    .tcl-builder-notification p {
                        margin: 0;
                        font-size: 13px;
                    }
                    .tcl-builder-notification .close-notification {
                        position: absolute;
                        top: 10px;
                        right: 10px;
                        border: none;
                        background: none;
                        cursor: pointer;
                        font-size: 18px;
                        color: #666;
                        padding: 0;
                        width: 20px;
                        height: 20px;
                        line-height: 20px;
                    }
                    .tcl-builder-notification .close-notification:hover {
                        color: #dc3232;
                    }
                `)
                .appendTo('head');
        } else {
            TCLBuilder.Logger.error('TCL Builder modules not loaded correctly');
        }
    });

})(jQuery);
