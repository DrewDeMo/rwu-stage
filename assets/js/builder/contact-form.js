/**
 * TCL Builder Contact Form Handler
 */
(function ($) {
    'use strict';

    const ContactForm = {
        init() {
            this.input = document.getElementById('contact_form_shortcode');
            this.saveButton = document.querySelector('.save-contact-form-btn');
            this.copyButton = document.querySelector('.copy-shortcode-btn');

            if (!this.input || !this.saveButton || !this.copyButton) {
                return;
            }

            this.bindEvents();
        },

        bindEvents() {
            this.saveButton.addEventListener('click', () => this.save());
            this.copyButton.addEventListener('click', () => this.copyShortcode());

            // Save on Enter key
            this.input.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.save();
                }
            });
        },

        async save() {
            const shortcode = this.input.value.trim();

            if (!shortcode) {
                if (window.TCLBuilder && TCLBuilder.notify) {
                    TCLBuilder.notify('Please enter a Contact Form 7 shortcode', 'error');
                }
                return;
            }

            // Show loading state
            const originalText = this.saveButton.innerHTML;
            this.saveButton.innerHTML = '<span class="dashicons dashicons-update-alt"></span> Saving...';
            this.saveButton.disabled = true;

            try {
                const response = await fetch(ajaxurl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'tcl_builder_save_contact_form',
                        nonce: window.tclBuilderData.nonce,
                        post_id: document.getElementById('post_ID').value,
                        shortcode: shortcode
                    })
                });

                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.data.message || 'Failed to save contact form shortcode');
                }

                // Show success state
                this.saveButton.innerHTML = '<span class="dashicons dashicons-yes"></span> Saved';

                // Reset button after delay
                setTimeout(() => {
                    this.saveButton.disabled = false;
                    this.saveButton.innerHTML = originalText;
                }, 2000);

                if (window.TCLBuilder && TCLBuilder.notify) {
                    TCLBuilder.notify('Contact form shortcode saved successfully', 'success');
                }

            } catch (error) {
                console.error('Error saving contact form shortcode:', error);

                // Show error state
                this.saveButton.innerHTML = '<span class="dashicons dashicons-warning"></span> Error';

                // Reset button after delay
                setTimeout(() => {
                    this.saveButton.disabled = false;
                    this.saveButton.innerHTML = originalText;
                }, 2000);

                if (window.TCLBuilder && TCLBuilder.notify) {
                    TCLBuilder.notify(error.message, 'error');
                }
            }
        },

        copyShortcode() {
            const shortcode = '[contact_form]';

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
                    TCLBuilder.notify('Failed to copy shortcode', 'error');
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
                    TCLBuilder.notify('Failed to copy shortcode', 'error');
                }
            } finally {
                document.body.removeChild(textarea);
            }
        },

        showCopySuccess() {
            const originalHTML = this.copyButton.innerHTML;
            this.copyButton.innerHTML = '<span class="dashicons dashicons-yes"></span>';

            setTimeout(() => {
                this.copyButton.innerHTML = originalHTML;
            }, 2000);

            if (window.TCLBuilder && TCLBuilder.notify) {
                TCLBuilder.notify('Shortcode copied to clipboard', 'success');
            }
        }
    };

    // Initialize when document is ready
    $(document).ready(() => {
        if (typeof window.TCLBuilder === 'undefined') {
            window.TCLBuilder = {};
        }
        window.TCLBuilder.ContactForm = ContactForm;
        ContactForm.init();
    });

})(jQuery);
