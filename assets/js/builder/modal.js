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

            this.lastActiveElement = document.activeElement;

            modal.find('input, textarea').val('');
            modal.removeData('section-id');

            modalDialog.attr({
                'role': 'dialog',
                'aria-modal': 'true',
                'aria-labelledby': `${type}-modal-title`
            });

            jQuery('body > *').not(modal).not(modal.parents()).each(function() {
                if (!jQuery(this).attr('inert')) {
                    jQuery(this).attr('inert', '');
                }
            });

            if (type === 'editor' && 
                TCLBuilder.Core.editors.html?.codemirror && 
                TCLBuilder.Core.editors.css?.codemirror && 
                TCLBuilder.Core.editors.js?.codemirror) {
                TCLBuilder.Core.editors.html.codemirror.setValue('');
                TCLBuilder.Core.editors.css.codemirror.setValue('');
                TCLBuilder.Core.editors.js.codemirror.setValue('');
                
                setTimeout(() => {
                    TCLBuilder.Core.editors.html.codemirror.refresh();
                    TCLBuilder.Core.editors.css.codemirror.refresh();
                    TCLBuilder.Core.editors.js.codemirror.refresh();
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

            setTimeout(() => {
                const firstFocusable = modal.find('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])').first();
                if (firstFocusable.length) {
                    firstFocusable.focus();
                }
            }, 100);

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
                let htmlContent = '';
                let cssContent = '';
                let jsContent = '';

                if (section.content) {
                    if (typeof section.content === 'object') {
                        htmlContent = section.content.html || '';
                        cssContent = section.content.css || '';
                        jsContent = section.content.js || '';
                    } else if (typeof section.content === 'string') {
                        try {
                            const parsed = JSON.parse(section.content);
                            htmlContent = parsed.html || '';
                            cssContent = parsed.css || '';
                            jsContent = parsed.js || '';
                        } catch (e) {
                            htmlContent = section.content;
                        }
                    }
                }

                if (TCLBuilder.Core.editors.html?.codemirror && 
                    TCLBuilder.Core.editors.css?.codemirror && 
                    TCLBuilder.Core.editors.js?.codemirror) {
                    TCLBuilder.Core.editors.html.codemirror.setValue(htmlContent);
                    TCLBuilder.Core.editors.css.codemirror.setValue(cssContent);
                    TCLBuilder.Core.editors.js.codemirror.setValue(jsContent);
                    
                    setTimeout(() => {
                        TCLBuilder.Core.editors.html.codemirror.refresh();
                        TCLBuilder.Core.editors.css.codemirror.refresh();
                        TCLBuilder.Core.editors.js.codemirror.refresh();
                        
                        TCLBuilder.Core.editors.html.codemirror.getDoc().markClean();
                        TCLBuilder.Core.editors.css.codemirror.getDoc().markClean();
                        TCLBuilder.Core.editors.js.codemirror.getDoc().markClean();

                        TCLBuilder.Core.editors.html.codemirror.setSize(null, 'auto');
                        TCLBuilder.Core.editors.css.codemirror.setSize(null, 'auto');
                        TCLBuilder.Core.editors.js.codemirror.setSize(null, 'auto');
                    }, 100);
                }
            } else if (type === 'shortcode') {
                let shortcodeContent = '';
                if (section.content) {
                    shortcodeContent = typeof section.content === 'string'
                        ? section.content
                        : (typeof section.content === 'object' ? JSON.stringify(section.content) : '');
                }
                modal.find('.shortcode-input').val(shortcodeContent);
            }

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
                    if (!TCLBuilder.Core.editors.html?.codemirror || 
                        !TCLBuilder.Core.editors.css?.codemirror || 
                        !TCLBuilder.Core.editors.js?.codemirror) {
                        throw new Error('Code editors not initialized');
                    }

                    const htmlContent = TCLBuilder.Core.editors.html.codemirror.getValue();
                    const cssContent = TCLBuilder.Core.editors.css.codemirror.getValue();
                    const jsContent = TCLBuilder.Core.editors.js.codemirror.getValue();

                    // Process JS for shadow DOM if it contains DOM operations
                    const needsShadowDOM = this.validateShadowDOMJS(jsContent).length > 0;
                    const processedJS = needsShadowDOM ? this.rewriteForShadowDOM(jsContent) : jsContent;

                    content = {
                        html: htmlContent.trim(),
                        css: cssContent.trim(),
                        js: processedJS.trim(),
                        shadowDOM: needsShadowDOM
                    };

                    if (content.html && !TCLBuilder.Utils.validateHTML(content.html)) {
                        validationErrors.push('Invalid HTML structure. Basic HTML structure validation failed.');
                    }

                    if (content.css) {
                        const openBraces = (content.css.match(/{/g) || []).length;
                        const closeBraces = (content.css.match(/}/g) || []).length;
                        if (openBraces !== closeBraces) {
                            validationErrors.push('CSS has mismatched braces. Please check your CSS syntax.');
                        }
                    }

                    if (content.js) {
                        try {
                            new Function(content.js);
                        } catch (e) {
                            validationErrors.push('JavaScript syntax error: ' + e.message);
                        }

                        // Only validate Shadow DOM if not explicitly disabled
                        const bypassShadowDOM = content.js.includes('// @bypass-shadow-dom');
                        if (!bypassShadowDOM) {
                            const shadowDOMViolations = this.validateShadowDOMJS(content.js);
                            if (shadowDOMViolations.length > 0) {
                                validationErrors.push('Shadow DOM compatibility issues:\n' + shadowDOMViolations.join('\n'));
                            }
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

        handleImport() {
            const fileInput = jQuery('.sections-import-file');
            if (!fileInput.length) {
                jQuery('.content-sections').append(`
                    <input type="file" class="sections-import-file" style="display: none;" accept="application/json">
                `);
            }
            jQuery('.sections-import-file').trigger('click');
        },

        validateShadowDOMJS(js) {
            const violations = [];
            
            // Check for direct DOM access patterns
            const domViolations = {
                'document.querySelector': '• Use this.shadowRoot.querySelector instead of document.querySelector',
                'document.getElementById': '• Use this.shadowRoot.querySelector("#id") instead of getElementById',
                'document.getElementsBy': '• Use this.shadowRoot.querySelectorAll instead of getElementsBy*',
                'document.body': '• Avoid document.body access, use this.shadowRoot',
                'window.document': '• Avoid window.document access, use this.shadowRoot',
                'document.createElement': '• Use this.shadowRoot.appendChild with template elements',
                'document.addEventListener': '• Use this.shadowRoot.addEventListener for events'
            };

            Object.entries(domViolations).forEach(([pattern, message]) => {
                if (js.includes(pattern)) {
                    violations.push(message);
                }
            });

            // Check for global scope pollution
            if (/\bwindow\.[a-zA-Z_$][a-zA-Z0-9_$]*\s*=/.test(js)) {
                violations.push('• Avoid adding properties to window object');
            }

            return violations;
        },

        rewriteForShadowDOM(js) {
            // Check if shadow DOM is bypassed
            if (js.includes('// @bypass-shadow-dom')) {
                return js; // Return original code without wrapping
            }
            
            // Replace direct DOM queries with shadow root context
            let shadowJS = js
                .replace(/document\.querySelector\(/g, 'this.shadowRoot.querySelector(')
                .replace(/document\.getElementById\(['"](.*?)['"]\)/g, 'this.shadowRoot.querySelector("#$1")')
                .replace(/document\.getElementsByClassName\(['"](.*?)['"]\)/g, 'this.shadowRoot.querySelectorAll(".$1")')
                .replace(/document\.getElementsByTagName\(/g, 'this.shadowRoot.querySelectorAll(')
                .replace(/document\.body/g, 'this.shadowRoot')
                .replace(/window\.document/g, 'this.shadowRoot');

            return `
class SectionComponent extends HTMLElement {
    constructor() {
        super();
        this.attachShadow({mode: 'open'});
    }
    
    connectedCallback() {
        ${shadowJS}
    }
}
customElements.define('section-component-${Date.now()}', SectionComponent);`;
        },

        bindEvents() {
            // Add section buttons
            jQuery('.add-section-btn').on('click', (e) => {
                e.preventDefault();
                this.open('main');
            });

            // Add import button handling
            jQuery('.import-sections-btn').off('click').on('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.handleImport();
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
