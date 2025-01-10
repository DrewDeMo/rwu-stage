/**
 * TCL Builder Modal Module
 * Handles modal management functionality
 */

(function() {
    'use strict';

    // Extend existing TCLBuilder.Modal
    Object.assign(TCLBuilder.Modal, {
        init() {
            this.bindEvents();
        },

        lastActiveElement: null,

        open(type, sectionId = null) {
            const modal = jQuery(`.modal-overlay[data-modal="${type}"]`);
            const modalDialog = modal.find('.modal-dialog');

            // Store last active element for focus restoration
            this.lastActiveElement = document.activeElement;

            // Reset modal state
            modal.find('input, textarea').val('');
            modal.removeData('section-id');

            // Set proper ARIA attributes
            modalDialog.attr({
                'role': 'dialog',
                'aria-modal': 'true',
                'aria-labelledby': `${type}-modal-title`
            });

            // Make other content inert, excluding the modal and its parents
            jQuery('body > *').not(modal).not(modal.parents()).each(function() {
                if (!jQuery(this).attr('inert')) {
                    jQuery(this).attr('inert', '');
                }
            });

            // Refresh CodeMirror instances if this is the editor modal
            if (type === 'editor' && TCLBuilder.Core.editors.html?.codemirror && TCLBuilder.Core.editors.css?.codemirror) {
                // Clear editor content
                TCLBuilder.Core.editors.html.codemirror.setValue('');
                TCLBuilder.Core.editors.css.codemirror.setValue('');
                
                // Refresh editors
                setTimeout(() => {
                    TCLBuilder.Core.editors.html.codemirror.refresh();
                    TCLBuilder.Core.editors.css.codemirror.refresh();
                }, 100);
            }

            if (sectionId) {
                modal.data('section-id', parseInt(sectionId));
                this.loadSectionData(type, parseInt(sectionId));
            }

            this.close(TCLBuilder.Core.activeModal);
            modal.addClass('active');
            TCLBuilder.Core.activeModal = type;
            jQuery('body').css('overflow', 'hidden');

            // Focus first focusable element
            setTimeout(() => {
                const firstFocusable = modal.find('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])').first();
                if (firstFocusable.length) {
                    firstFocusable.focus();
                }
            }, 100);

            // Publish modal opened event
            TCLBuilder.Events.publish('modal:opened', { type, sectionId });
        },

        close(type) {
            if (!type) return;
            const modal = jQuery(`.modal-overlay[data-modal="${type}"]`);

            // Remove inert from other content, excluding the modal and its parents
            jQuery('body > *').not(modal).not(modal.parents()).removeAttr('inert');

            modal.removeClass('active');
            if (type === TCLBuilder.Core.activeModal) {
                TCLBuilder.Core.activeModal = null;
                jQuery('body').css('overflow', '');

                // Restore focus to last active element
                if (this.lastActiveElement) {
                    this.lastActiveElement.focus();
                    this.lastActiveElement = null;
                }
            }

            // Publish modal closed event
            TCLBuilder.Events.publish('modal:closed', { type });
        },

        loadSectionData(type, sectionId) {
            const section = TCLBuilder.Core.sections.find(s => s.id === parseInt(sectionId));
            if (!section) return;

            const modal = jQuery(`.modal-overlay[data-modal="${type}"]`);
            modal.data('section-id', sectionId);
            modal.find('.title-input').val(section.title || '');

            if (type === 'editor') {
                // For HTML sections, ensure we have the correct content structure
                let htmlContent = '';
                let cssContent = '';

                if (section.content) {
                    if (typeof section.content === 'object') {
                        htmlContent = section.content.html || '';
                        cssContent = section.content.css || '';
                    } else if (typeof section.content === 'string') {
                        // Handle case where content might be stored as string
                        try {
                            const parsed = JSON.parse(section.content);
                            htmlContent = parsed.html || '';
                            cssContent = parsed.css || '';
                        } catch (e) {
                            htmlContent = section.content;
                        }
                    }
                }

                if (TCLBuilder.Core.editors.html?.codemirror && TCLBuilder.Core.editors.css?.codemirror) {
                    TCLBuilder.Core.editors.html.codemirror.setValue(htmlContent);
                    TCLBuilder.Core.editors.css.codemirror.setValue(cssContent);
                    
                // Refresh editors
                setTimeout(() => {
                    TCLBuilder.Core.editors.html.codemirror.refresh();
                    TCLBuilder.Core.editors.css.codemirror.refresh();
                    
                    // Just mark clean without height adjustment
                    TCLBuilder.Core.editors.html.codemirror.getDoc().markClean();
                    TCLBuilder.Core.editors.css.codemirror.getDoc().markClean();

                    // Set editor size to auto
                    TCLBuilder.Core.editors.html.codemirror.setSize(null, 'auto');
                    TCLBuilder.Core.editors.css.codemirror.setSize(null, 'auto');
                }, 100);
                }
            } else if (type === 'shortcode') {
                // For shortcode sections, ensure we have a string
                let shortcodeContent = '';

                if (section.content) {
                    shortcodeContent = typeof section.content === 'string'
                        ? section.content
                        : (typeof section.content === 'object' ? JSON.stringify(section.content) : '');
                }

                modal.find('.shortcode-input').val(shortcodeContent);
            }

            // Trigger input events to ensure any listeners are notified
            modal.find('input, textarea').trigger('input');
        },

        saveSection(type) {
            const modal = jQuery(`.modal-overlay[data-modal="${type}"]`);
            const existingId = modal.data('section-id');

            try {
                // Validate inputs
                if (!type) {
                    throw new Error('Section type is required');
                }

                // Get and validate content based on type
                let content;
                let validationErrors = [];

                if (type === 'editor') {
                    if (!TCLBuilder.Core.editors.html?.codemirror || !TCLBuilder.Core.editors.css?.codemirror) {
                        throw new Error('Code editors not initialized');
                    }

                    const htmlContent = TCLBuilder.Core.editors.html.codemirror.getValue();
                    const cssContent = TCLBuilder.Core.editors.css.codemirror.getValue();

                    // Basic content validation
                    content = {
                        html: htmlContent.trim(),
                        css: cssContent.trim()
                    };

                    // Only validate non-empty content
                    if (content.html && !TCLBuilder.Utils.validateHTML(content.html)) {
                        validationErrors.push('Invalid HTML structure. Basic HTML structure validation failed.');
                    }

                    if (content.css) {
                        // Only check for matching braces in CSS
                        const openBraces = (content.css.match(/{/g) || []).length;
                        const closeBraces = (content.css.match(/}/g) || []).length;
                        if (openBraces !== closeBraces) {
                            validationErrors.push('CSS has mismatched braces. Please check your CSS syntax.');
                        }
                    }
                } else {
                    const shortcodeInput = modal.find('.shortcode-input');
                    if (!shortcodeInput.length) {
                        throw new Error('Shortcode input not found');
                    }
                    
                    const shortcodeContent = shortcodeInput.val().trim();
                    if (!shortcodeContent) {
                        validationErrors.push('Shortcode content cannot be empty');
                    } else if (!/^\[[\w-]+(?:\s+[\w-]+="[^"]*")*\]/.test(shortcodeContent)) {
                        validationErrors.push('Invalid shortcode format');
                    }
                    
                    content = shortcodeContent;
                }

                // If there are validation errors, throw them
                if (validationErrors.length > 0) {
                    throw new Error('Validation errors:\n' + validationErrors.join('\n'));
                }

                // Get and validate title
                const title = modal.find('.title-input').val().trim() || 'Untitled Section';
                if (title.length > 100) {
                    throw new Error('Section title is too long (maximum 100 characters)');
                }

                // Create section data object
                const sectionData = {
                    id: existingId ? parseInt(existingId) : Date.now(),
                    type: type === 'editor' ? 'html' : 'shortcode',
                    title: TCLBuilder.Utils.sanitize(title),
                    content: content,
                    lastModified: new Date().toISOString()
                };

                // Validate complete section data
                if (!TCLBuilder.Utils.validate(sectionData)) {
                    throw new Error('Section validation failed. Please check the console for details.');
                }

                // Log section save attempt
                console.log('TCLBuilder: Saving section:', {
                    id: sectionData.id,
                    type: sectionData.type,
                    title: sectionData.title,
                    isUpdate: !!existingId
                });

                // Update sections array
                if (existingId) {
                    const index = TCLBuilder.Core.sections.findIndex(s => parseInt(s.id) === parseInt(existingId));
                    if (index === -1) {
                        throw new Error('Section not found');
                    }
                    TCLBuilder.Core.sections[index] = sectionData;
                } else {
                    TCLBuilder.Core.sections.push(sectionData);
                }

                // Update UI and save
                TCLBuilder.Sections.renderSections();
                this.close(type);
                TCLBuilder.WordPress.save();

                // Publish section saved event with additional metadata
                TCLBuilder.Events.publish('section:saved', {
                    ...sectionData,
                    validationPassed: true,
                    timestamp: new Date().toISOString()
                });

            } catch (error) {
                console.error('TCLBuilder: Error saving section:', error);
                
                // Log detailed error information
                console.error('TCLBuilder: Save operation details:', {
                    type,
                    existingId,
                    content: type === 'editor' ? {
                        html: TCLBuilder.Core.editors.html?.codemirror?.getValue()?.length + ' characters',
                        css: TCLBuilder.Core.editors.css?.codemirror?.getValue()?.length + ' characters'
                    } : modal.find('.shortcode-input').val()?.length + ' characters',
                    error: {
                        message: error.message,
                        stack: error.stack
                    }
                });

                // Show user-friendly error message
                const errorMessage = error.message.includes('Validation errors') 
                    ? error.message 
                    : 'There was an error saving the section. Please check the following:\n\n' +
                      '• Ensure all required fields are filled out\n' +
                      '• Check HTML and CSS syntax\n' +
                      '• Remove any unsafe HTML elements\n' +
                      '• Verify shortcode format is correct';

                alert(errorMessage);

                // Publish error event
                TCLBuilder.Events.publish('section:error', {
                    type: 'save_error',
                    message: error.message,
                    timestamp: new Date().toISOString()
                });
            }
        },

        bindEvents() {
            // Add section buttons
            jQuery('.add-section-btn').on('click', (e) => {
                e.preventDefault();
                this.open('main');
            });

            // Section type selection
            jQuery('.section-option').on('click', (e) => {
                e.preventDefault();
                const type = jQuery(e.currentTarget).data('type');
                this.close('main');
                this.open(type === 'html' ? 'editor' : 'shortcode');
            });

            // Close modal buttons
            jQuery('.modal-close, .secondary-btn').on('click', (e) => {
                e.preventDefault();
                this.close(jQuery(e.currentTarget).closest('.modal-overlay').data('modal'));
            });

            // Close on overlay click
            jQuery('.modal-overlay').on('click', (e) => {
                if (e.target === e.currentTarget) {
                    e.preventDefault();
                    this.close(jQuery(e.currentTarget).data('modal'));
                }
            });

            // Save section buttons
            jQuery('.primary-btn').on('click', (e) => {
                e.preventDefault();
                const modal = jQuery(e.currentTarget).closest('.modal-overlay');
                const modalType = modal.data('modal');
                this.saveSection(modalType);
            });

            // Handle keyboard events
            jQuery(document).on('keydown', (e) => {
                const modal = jQuery(`.modal-overlay[data-modal="${TCLBuilder.Core.activeModal}"]`);
                
                // Close on escape key
                if (e.key === 'Escape' && TCLBuilder.Core.activeModal) {
                    e.preventDefault();
                    this.close(TCLBuilder.Core.activeModal);
                    return;
                }
                
                // If no modal is active or modal doesn't contain focus, don't trap
                if (!TCLBuilder.Core.activeModal || !modal.length || !modal.find(':focus').length) {
                    return;
                }

                // Get all focusable elements
                const focusableElements = modal.find('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
                const firstFocusable = focusableElements.first();
                const lastFocusable = focusableElements.last();

                // Handle Tab key
                if (e.key === 'Tab') {
                    if (e.shiftKey) {
                        // If shift + tab and focus is on first element, move to last
                        if (document.activeElement === firstFocusable[0]) {
                            e.preventDefault();
                            lastFocusable.focus();
                        }
                    } else {
                        // If tab and focus is on last element, move to first
                        if (document.activeElement === lastFocusable[0]) {
                            e.preventDefault();
                            firstFocusable.focus();
                        }
                    }
                }
            });
        }
    });
})();
