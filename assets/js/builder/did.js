/**
 * TCL Builder DID Field Handler
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
            DID: {},
            Logger: {}
        };
    }

    // Extend TCLBuilder.DID namespace  
    Object.assign(TCLBuilder.DID, {
        init() {
            this.input = document.getElementById('campaign_did');
            this.saveButton = document.querySelector('.save-did-btn');
            this.copyButton = document.querySelector('.copy-shortcode-btn');
            this.postId = document.querySelector('#post_ID').value;

            if (!this.input || !this.saveButton || !this.copyButton || !this.postId) {
                console.error('Required DID field elements not found');
                return;
            }

            // Initialize with existing DID if available
            if (window.TCLBuilder && TCLBuilder.data && TCLBuilder.data.campaignDid) {
                this.input.value = TCLBuilder.data.campaignDid;
            }

            this.initializeEventListeners();
            this.initializeLucideIcons();
        },

        initializeEventListeners() {
            // Save DID when button is clicked
            this.saveButton.addEventListener('click', () => this.saveDID());

            // Save DID when Enter is pressed in input
            this.input.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.saveDID();
                }
            });

            // Copy shortcode when copy button is clicked
            this.copyButton.addEventListener('click', () => this.copyShortcode());

            // Format phone number as user types
            this.input.addEventListener('input', (e) => this.formatPhoneNumber(e));

            // Add to TCLBuilder events
            if (window.TCLBuilder && TCLBuilder.events) {
                TCLBuilder.events.on('section:html:updated', () => {
                    // Re-initialize Lucide icons in case new DID shortcodes were added
                    if (window.lucide) {
                        window.lucide.createIcons();
                    }
                });
            }
        },

        initializeLucideIcons() {
            // Check if Lucide is loaded and initialized
            if (typeof window.lucide === 'undefined' || window.lucide === null) {
                console.error('Lucide library not loaded or initialized');
                if (window.TCLBuilder && TCLBuilder.Logger) {
                    TCLBuilder.Logger.error('Lucide library not available', {
                        lucide: window.lucide,
                        timestamp: new Date().toISOString()
                    });
                }
                return;
            }

            try {
                // Get the DID field container
                const didField = document.querySelector('.tcl-builder-did-field');
                if (!didField) {
                    console.warn('DID field container not found');
                    return;
                }

                // Double check Lucide exists and has createIcons method
                if (!window.lucide || typeof window.lucide.createIcons !== 'function') {
                    console.error('Lucide.createIcons is not available');
                    if (window.TCLBuilder && TCLBuilder.Logger) {
                        TCLBuilder.Logger.error('Lucide.createIcons is not available', {
                            lucide: window.lucide,
                            timestamp: new Date().toISOString()
                        });
                    }
                    return;
                }

                // Initialize Lucide icons in the DID field
                const icons = window.lucide.createIcons(didField);

                // Verify icons were created
                if (!icons || icons.length === 0) {
                    console.warn('No Lucide icons found to initialize');
                    if (window.TCLBuilder && TCLBuilder.Logger) {
                        TCLBuilder.Logger.warn('No Lucide icons initialized', {
                            didField: didField,
                            timestamp: new Date().toISOString()
                        });
                    }
                }
            } catch (error) {
                console.error('Error initializing Lucide icons:', error);
                if (window.TCLBuilder && TCLBuilder.Logger) {
                    TCLBuilder.Logger.error('Lucide initialization failed', {
                        error: error.message,
                        stack: error.stack,
                        timestamp: new Date().toISOString()
                    });
                }
            }
        },

        async saveDID() {
            try {
                if (!this.input || !this.saveButton) {
                    console.error('DID field elements not found');
                    return;
                }

                const did = this.input.value.trim();

                // Validate DID format
                if (!/^\d{3}-\d{3}-\d{4}$/.test(did)) {
                    throw new Error('Please enter a valid phone number in XXX-XXX-XXXX format');
                }

                // Show loading state
                this.saveButton.disabled = true;
                this.saveButton.innerHTML = `
                    <i data-lucide="loader-2" class="animate-spin"></i>
                    ${wp.i18n.__('Saving...', 'tcl-builder')}
                `;

                // Log attempt
                if (window.TCLBuilder && TCLBuilder.Logger) {
                    TCLBuilder.Logger.info('Attempting to save DID', { did });
                }

                const response = await fetch(ajaxurl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'tcl_builder_save_did',
                        nonce: window.tclBuilderData.nonce,
                        post_id: this.postId,
                        did: did
                    })
                });

                const data = await response.json();

                if (!data.success) {
                    // Provide more specific error message
                    const errorMessage = data.data?.message ||
                        (data.data?.code === 'invalid_nonce' ? 'Session expired. Please refresh the page and try again.' :
                            'Failed to save DID. Please check your connection and try again.');
                    throw new Error(errorMessage);
                }

                // Show success state
                this.saveButton.innerHTML = `
                    <i data-lucide="check"></i>
                    ${wp.i18n.__('Saved', 'tcl-builder')}
                `;

                // Reset button after delay
                setTimeout(() => {
                    this.saveButton.disabled = false;
                    this.saveButton.innerHTML = `
                        <i data-lucide="save"></i>
                        ${wp.i18n.__('Save', 'tcl-builder')}
                    `;
                    lucide.createIcons(); // Refresh icons
                }, 2000);

            } catch (error) {
                // Only show error state if there's actually an error
                if (error.message && !error.message.includes('Failed to save DID')) {
                    // Log the full error details for debugging
                    if (window.TCLBuilder && TCLBuilder.Logger) {
                        TCLBuilder.Logger.error('Error saving DID:', {
                            message: error.message,
                            stack: error.stack,
                            timestamp: new Date().toISOString()
                        });
                    }
                    // Show error state
                    this.saveButton.innerHTML = `
                        <i data-lucide="alert-circle"></i>
                        ${wp.i18n.__('Error', 'tcl-builder')}
                    `;

                    // Reset button after delay
                    setTimeout(() => {
                        this.saveButton.disabled = false;
                        this.saveButton.innerHTML = `
                            <i data-lucide="save"></i>
                            ${wp.i18n.__('Save', 'tcl-builder')}
                        `;
                        lucide.createIcons(); // Refresh icons
                    }, 2000);
                }
            }
        },

        copyShortcode() {
            const shortcode = '[campaign_did]';

            // Use modern Clipboard API with fallback
            try {
                if (navigator.clipboard && window.isSecureContext) {
                    // Modern approach
                    navigator.clipboard.writeText(shortcode).then(() => {
                        this.showCopySuccess();
                    }).catch(() => {
                        // Fallback to legacy approach if clipboard API fails
                        this.legacyCopy(shortcode);
                    });
                } else {
                    // Legacy approach for older browsers
                    this.legacyCopy(shortcode);
                }
            } catch (error) {
                console.error('Copy failed:', error);
                if (window.TCLBuilder && TCLBuilder.notify) {
                    TCLBuilder.notify(
                        wp.i18n.__('Failed to copy shortcode', 'tcl-builder'),
                        'error'
                    );
                }
            }
        },

        legacyCopy(text) {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.setAttribute('readonly', '');
            textarea.style.position = 'absolute';
            textarea.style.left = '-9999px';
            document.body.appendChild(textarea);

            try {
                textarea.select();
                document.execCommand('copy');
                this.showCopySuccess();
            } catch (error) {
                console.error('Legacy copy failed:', error);
                if (window.TCLBuilder && TCLBuilder.notify) {
                    TCLBuilder.notify(
                        wp.i18n.__('Failed to copy shortcode', 'tcl-builder'),
                        'error'
                    );
                }
            } finally {
                document.body.removeChild(textarea);
            }
        },

        showCopySuccess() {
            const originalHTML = this.copyButton.innerHTML;
            this.copyButton.innerHTML = '<i data-lucide="check"></i>';
            lucide.createIcons();

            setTimeout(() => {
                this.copyButton.innerHTML = originalHTML;
                lucide.createIcons();
            }, 2000);

            if (window.TCLBuilder && TCLBuilder.notify) {
                TCLBuilder.notify(
                    wp.i18n.__('Shortcode copied to clipboard', 'tcl-builder'),
                    'success'
                );
            }
        },

        formatPhoneNumber(e) {
            // Get input value and remove all non-numeric characters
            let input = e.target.value.replace(/\D/g, '');

            // Limit to 10 digits
            input = input.substring(0, 10);

            // Format the number as XXX-XXX-XXXX
            let formatted = input;
            if (input.length >= 3) {
                formatted = input.slice(0, 3) + (input.length > 3 ? '-' : '');
                if (input.length >= 6) {
                    formatted += input.slice(3, 6) + (input.length > 6 ? '-' : '');
                    if (input.length >= 7) {
                        formatted += input.slice(6);
                    }
                } else {
                    formatted += input.slice(3);
                }
            }

            // Update input value
            e.target.value = formatted;
        }
    });
})(jQuery);
