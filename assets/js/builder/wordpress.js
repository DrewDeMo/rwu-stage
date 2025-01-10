/**
 * TCL Builder WordPress Module
 * Handles WordPress integration functionality
 */

(function () {
    'use strict';

    // Set shared sections base URL
    tclBuilderData.sharedSectionsBaseUrl = 'https://tclmore.windowworlddeals.com/';

    // Extend existing TCLBuilder.WordPress
    Object.assign(TCLBuilder.WordPress, {
        init() {
            this.bindEvents();
            this.setupHeartbeat();
            this.lastSavedState = this.getCurrentState();
            this.setupAutosave();
        },

        getCurrentState() {
            return JSON.stringify(this.prepareSectionsData(TCLBuilder.Core.sections));
        },

        hasUnsavedChanges() {
            return this.getCurrentState() !== this.lastSavedState;
        },

        setupHeartbeat() {
            // Add our data to heartbeat-send
            jQuery(document).on('heartbeat-send', (e, data) => {
                if (this.hasUnsavedChanges()) {
                    data.tcl_builder_sections = this.getCurrentState();
                }
            });

            // Handle heartbeat-tick
            jQuery(document).on('heartbeat-tick', (e, data) => {
                if (data.tcl_builder_revision) {
                    this.lastSavedState = this.getCurrentState();
                    TCLBuilder.Events.publish('wordpress:autosaved', data.tcl_builder_revision);
                }
            });
        },

        setupAutosave() {
            let autosaveTimer = null;
            const AUTOSAVE_INTERVAL = 60000; // 1 minute

            // Watch for changes in sections
            TCLBuilder.Events.subscribe('section:updated', () => {
                if (autosaveTimer) {
                    clearTimeout(autosaveTimer);
                }

                // Show unsaved changes indicator
                if (this.hasUnsavedChanges()) {
                    jQuery('.tcl-builder-container').addClass('has-unsaved-changes');
                }

                // Set new autosave timer
                autosaveTimer = setTimeout(() => {
                    if (this.hasUnsavedChanges()) {
                        this.save(true); // true indicates this is an autosave
                    }
                }, AUTOSAVE_INTERVAL);
            });

            // Handle beforeunload
            window.addEventListener('beforeunload', (e) => {
                if (this.hasUnsavedChanges()) {
                    e.preventDefault();
                    e.returnValue = '';
                }
            });
        },

        validateSection(section) {
            if (!section || typeof section !== 'object') return false;
            if (!section.type || !section.content) return false;

            // Validate designation is a valid value
            const validDesignations = ['store', 'library', 'code'];
            if (section.designation && !validDesignations.includes(section.designation)) {
                console.warn('Invalid designation:', section.designation);
                section.designation = 'library'; // Reset to default if invalid
            }

            if (section.type === 'html') {
                return typeof section.content === 'object' &&
                    'html' in section.content &&
                    'css' in section.content;
            }

            return typeof section.content === 'string';
        },

        prepareSectionsData(sections) {
            return sections.map(section => {
                const preparedSection = {
                    id: parseInt(section.id) || Date.now(),
                    type: section.type,
                    title: section.title || 'Untitled Section',
                    designation: section.designation || 'library'
                };

                if (section.type === 'html') {
                    preparedSection.content = {
                        html: section.content.html || '',
                        css: section.content.css || ''
                    };
                } else {
                    preparedSection.content = section.content || '';
                }

                return preparedSection;
            });
        },

        save(isAutosave = false) {
            return new Promise((resolve, reject) => {
                try {
                    const postId = jQuery('#post_ID').val();
                    if (!postId) {
                        throw new Error('No post ID found');
                    }

                    // Check for ongoing autosave
                    if (wp.autosave && wp.autosave.server.postChanged()) {
                        return new Promise((innerResolve) => {
                            setTimeout(() => {
                                this.save(isAutosave).then(innerResolve).catch(reject);
                            }, 100);
                        });
                    }

                    // Validate sections array exists
                    if (!Array.isArray(TCLBuilder.Core.sections)) {
                        throw new Error('Sections data is not properly initialized');
                    }

                    // Validate sections before saving
                    const invalidSections = TCLBuilder.Core.sections.filter(
                        section => !this.validateSection(section)
                    );

                    if (invalidSections.length > 0) {
                        console.error('Invalid sections found:', invalidSections);
                        throw new Error('Some sections have invalid structure');
                    }

                    // Force WordPress to recognize content change
                    if (typeof window.tinymce !== 'undefined' && window.tinymce.get('content')) {
                        window.tinymce.get('content').save();
                    }

                    const contentField = jQuery('#content');
                    if (contentField.length) {
                        const currentContent = contentField.val() || '';
                        contentField.val(currentContent.trim() + ' ');
                    }

                    // Show appropriate loading state
                    const container = jQuery('.tcl-builder-container');
                    container.addClass('is-loading');
                    if (isAutosave) {
                        container.addClass('is-autosaving');
                    }

                    // Prepare sections data with validation
                    const preparedSections = this.prepareSectionsData(TCLBuilder.Core.sections);
                    if (!preparedSections || !preparedSections.length) {
                        throw new Error('Failed to prepare sections data');
                    }

                    // Trigger WordPress change event
                    contentField.trigger('change');

                    // Log the data being sent
                    console.log('Saving sections:', preparedSections);

                    // Ensure proper JSON encoding of sections data
                    const sectionsJson = JSON.stringify(preparedSections, null, 2);
                    if (!sectionsJson) {
                        throw new Error('Failed to encode sections data');
                    }

                    // Debug nonce value and ajax URL
                    console.log('Current nonce:', tclBuilderData.nonce);
                    console.log('AJAX URL:', tclBuilderData.ajaxUrl);

                    if (!tclBuilderData.nonce || typeof tclBuilderData.nonce !== 'string') {
                        console.error('Invalid nonce:', tclBuilderData.nonce);
                        return Promise.reject(new Error('Security verification failed. Please refresh the page and try again.'));
                    }


                    // Create a new promise to handle nonce refresh
                    jQuery.ajax({
                        url: tclBuilderData.ajaxUrl,
                        method: 'POST',
                        headers: {
                            'X-WP-Nonce': tclBuilderData.nonce,
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        xhrFields: {
                            withCredentials: true
                        },
                        beforeSend: function (xhr) {
                            // Verify nonce again before sending
                            if (!tclBuilderData.nonce || typeof tclBuilderData.nonce !== 'string') {
                                console.error('Invalid nonce in beforeSend:', tclBuilderData.nonce);
                                xhr.abort();
                                return false;
                            }
                            return true;
                        },
                        error: function (xhr, status, error) {
                            // Handle 403 Forbidden (invalid nonce)
                            if (xhr.status === 403) {
                                console.error('Invalid nonce detected, refreshing page...');
                                window.location.reload();
                                return;
                            }

                            const errorMessage = xhr.responseJSON?.data?.message || 'Network error while saving sections';
                            console.error(errorMessage, { xhr, status, error });
                            TCLBuilder.Events.publish('wordpress:saveError', {
                                message: errorMessage,
                                xhr,
                                status,
                                error
                            });
                        },
                        data: {
                            action: 'tcl_builder_save_sections',
                            post_id: postId,
                            sections: sectionsJson,
                            _ajax_nonce: tclBuilderData.nonce
                        },
                        success: (response) => {
                            if (!response || !response.success) {
                                const errorMessage = response?.data?.message || 'Unknown error occurred';
                                console.error('Server error saving sections:', errorMessage);
                                TCLBuilder.Events.publish('wordpress:saveError', {
                                    message: errorMessage,
                                    details: response?.data
                                });
                                reject(new Error(errorMessage));
                                return;
                            }

                            // Update last saved state
                            this.lastSavedState = this.getCurrentState();

                            // Remove loading state
                            const container = jQuery('.tcl-builder-container');
                            container.removeClass('is-loading');
                            if (isAutosave) {
                                container.removeClass('is-autosaving');
                            }

                            // Trigger WordPress to recognize the change for revisions
                            jQuery(document).trigger('autosave-enable-buttons');

                            // Resolve with response data
                            resolve(response.data);
                        },
                        complete: () => {
                            const container = jQuery('.tcl-builder-container');
                            container.removeClass('is-loading');
                            if (isAutosave) {
                                container.removeClass('is-autosaving');
                            }
                        },
                    });
                } catch (error) {
                    console.error('Error in save operation:', error);
                    TCLBuilder.Events.publish('wordpress:saveError', {
                        message: error.message || 'Error saving sections. Please try again.',
                        error
                    });
                    return Promise.reject(error);
                }
            });
        },

        bindEvents() {
            // Handle post update
            jQuery('#publish, #save-post').on('click', () => {
                this.save(false);
            });

            // Add revision indicator to admin bar
            const adminBar = jQuery('#wp-admin-bar-top-secondary');
            if (adminBar.length) {
                adminBar.append(`
                    <div id="tcl-builder-status" class="tcl-builder-status">
                        <span class="status-indicator"></span>
                        <span class="status-text"></span>
                    </div>
                `);
            }

            // Update status indicator on events
            TCLBuilder.Events.subscribe('section:updated', () => {
                this.updateStatusIndicator('unsaved');
            });

            TCLBuilder.Events.subscribe('wordpress:saved', () => {
                this.updateStatusIndicator('saved');
            });

            TCLBuilder.Events.subscribe('wordpress:autosaved', () => {
                this.updateStatusIndicator('autosaved');
            });
        },

        updateStatusIndicator(status) {
            const indicator = jQuery('#tcl-builder-status');
            if (!indicator.length) return;

            indicator.attr('data-status', status);
            const text = {
                unsaved: 'Unsaved Changes',
                saved: 'All Changes Saved',
                autosaved: 'Draft Saved'
            }[status];

            indicator.find('.status-text').text(text);
        }
    });
})();
