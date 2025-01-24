/**
 * TCL Builder Sections Manager
 * Handles section import/export functionality
 */

(function ($) {
    'use strict';

    const SectionsManager = {
        init() {
            this.bindEvents();
        },

        bindEvents() {
            $('.export-sections').on('click', this.handleExport.bind(this));
            $('.import-sections').on('click', this.handleImportClick.bind(this));
            $('.sections-import-file').on('change', this.handleImportFile.bind(this));
        },

        handleExport(e) {
            const button = $(e.currentTarget);
            const postId = button.data('post-id');

            button.prop('disabled', true);

            $.ajax({
                url: tclBuilderSectionsManager.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'tcl_builder_export_sections',
                    nonce: tclBuilderSectionsManager.nonce,
                    post_id: postId
                },
                success: (response) => {
                    if (!response.success) {
                        alert(response.data.message || tclBuilderSectionsManager.strings.error);
                        return;
                    }

                    // Create and trigger download
                    const filename = `tcl-builder-sections-${postId}-${new Date().toISOString().slice(0, 10)}.json`;
                    const blob = new Blob([JSON.stringify(response.data, null, 2)], { type: 'application/json' });
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');

                    a.style.display = 'none';
                    a.href = url;
                    a.download = filename;

                    document.body.appendChild(a);
                    a.click();

                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(a);

                    alert(tclBuilderSectionsManager.strings.exportSuccess);
                },
                error: () => {
                    alert(tclBuilderSectionsManager.strings.error);
                },
                complete: () => {
                    button.prop('disabled', false);
                }
            });
        },

        handleImportClick(e) {
            const button = $(e.currentTarget);
            const fileInput = button.siblings('.sections-import-file');
            fileInput.trigger('click');
        },

        handleImportFile(e) {
            const fileInput = $(e.currentTarget);
            const file = fileInput[0].files[0];
            const postId = fileInput.siblings('.import-sections').data('post-id');

            if (!file) {
                console.log('No file selected');
                return;
            }

            // Reset file input immediately
            fileInput.val('');

            if (!confirm(tclBuilderSectionsManager.strings.confirmImport)) {
                console.log('Import cancelled by user');
                return;
            }

            const reader = new FileReader();

            reader.onload = (e) => {
                try {
                    console.log('File read successfully');
                    const sections = JSON.parse(e.target.result);
                    this.importSections(postId, sections);
                } catch (error) {
                    console.error('Error parsing JSON:', error);
                    alert(tclBuilderSectionsManager.strings.error);
                }
            };

            reader.onerror = () => {
                console.error('Error reading file');
                alert(tclBuilderSectionsManager.strings.error);
            };

            console.log('Reading file:', file.name);
            reader.readAsText(file);
        },

        importSections(postId, sections) {
            console.log('Starting import process for post:', postId);

            // Validate sections data
            if (!Array.isArray(sections)) {
                console.error('Invalid sections data format');
                alert(tclBuilderSectionsManager.strings.error);
                return;
            }

            // Process sections for shadow DOM compatibility
            const processedSections = sections.map(section => {
                const newId = Date.now() + Math.floor(Math.random() * 1000);
                const shadowRootId = `shadow-root-${newId}`;
                
                // Handle HTML content type sections
                if (section.type === 'html' && section.content) {
                    const content = typeof section.content === 'string' ? 
                        JSON.parse(section.content) : section.content;
                        
                    // Process JS if present and needs shadow DOM
                    if (content.js) {
                        // Check for bypass flag
                        const bypassShadowDOM = content.js.includes('// @bypass-shadow-dom');
                        if (!bypassShadowDOM) {
                            const needsShadowDOM = TCLBuilder.Modal.validateShadowDOMJS(content.js).length > 0;
                            if (needsShadowDOM || section.shadowDOM) {
                                content.js = TCLBuilder.Modal.rewriteForShadowDOM(content.js);
                                content.shadowDOM = true;
                            }
                        }
                    }
                    
                    return {
                        ...section,
                        id: newId,
                        shadowRootId,
                        content: content,
                        imported: true,
                        importTimestamp: Date.now()
                    };
                }
                
                // Return non-HTML sections unchanged except for new ID
                return {
                    ...section,
                    id: newId,
                    imported: true,
                    importTimestamp: Date.now()
                };
            });

            const button = $(`.import-sections[data-post-id="${postId}"]`);
            button.prop('disabled', true);

            // Prepare data object for AJAX request
            const data = {
                action: 'tcl_builder_import_sections',
                nonce: tclBuilderSectionsManager.nonce,
                post_id: postId,
                sections: JSON.stringify({
                    sections: processedSections,
                    version: tclBuilderSectionsManager.version || '1.0.0'
                })
            };

            console.log('Sending AJAX request with data:', data);

            $.ajax({
                url: tclBuilderSectionsManager.ajaxUrl,
                type: 'POST',
                data: data,
                dataType: 'json',
                success: (response) => {
                    console.log('AJAX response:', response);
                    if (!response.success) {
                        console.error('Import failed:', response.data);
                        alert(response.data.message || tclBuilderSectionsManager.strings.error);
                        return;
                    }
                    alert(tclBuilderSectionsManager.strings.importSuccess);
                    window.location.reload();
                },
                error: (jqXHR, textStatus, errorThrown) => {
                    console.error('AJAX error:', textStatus, errorThrown);
                    alert(tclBuilderSectionsManager.strings.error);
                },
                complete: () => {
                    button.prop('disabled', false);
                }
            });
        }
    };

    $(document).ready(() => {
        SectionsManager.init();
    });

})(jQuery);
