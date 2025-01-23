/**
 * TCL Builder Sections Module
 * Handles section management functionality
 */

(function () {
    'use strict';

    // Extend existing TCLBuilder.Sections
    Object.assign(TCLBuilder.Sections, {
        jsQueue: new Map(),

        initShadowJS(sectionId, jsContent) {
            // Enhanced shadow DOM initialization with better scoping
            return `
                try {
                    const shadowRoot = this.getRootNode();
                    // Provide scoped query helpers
                    const $ = selector => shadowRoot.querySelector(selector);
                    const $$ = selector => shadowRoot.querySelectorAll(selector);
                    
                    // Add safe window access
                    const safeWindow = {
                        setTimeout,
                        setInterval,
                        clearTimeout,
                        clearInterval,
                        requestAnimationFrame,
                        cancelAnimationFrame
                    };
                    
                    // Add safe document replacement
                    const safeDocument = {
                        createElement: (tag) => {
                            const el = document.createElement(tag);
                            shadowRoot.appendChild(el);
                            return el;
                        }
                    };
                    
                    // Execute in strict mode with safe context
                    (function() {
                        'use strict';
                        // Provide safe globals
                        const window = safeWindow;
                        const document = safeDocument;
                        ${jsContent}
                    })();
                } catch (error) {
                    console.error('Shadow DOM JS Error in section ${sectionId}:', error);
                }
            `;
        },

        init() {
            this.loadSections();
            this.bindEvents();
        },

        loadSections() {
            if (!tclBuilderData?.sections) {
                TCLBuilder.Core.sections = [];
                return;
            }

            try {
                // Normalize input to handle both string and array formats
                let rawSections = tclBuilderData.sections;

                // If it's a string (legacy format or from API), try to parse it
                if (typeof rawSections === 'string') {
                    try {
                        // Handle potential escaped JSON
                        rawSections = rawSections.replace(/\\(.)/g, '$1'); // Unescape any escaped characters
                        rawSections = JSON.parse(rawSections);
                    } catch (parseError) {
                        console.error('TCLBuilder: Error parsing sections JSON:', parseError);
                        rawSections = [];
                    }
                }

                // Ensure we have an array to work with
                if (!Array.isArray(rawSections)) {
                    console.warn('TCLBuilder: Sections data is not an array, resetting to empty array');
                    rawSections = [];
                }

                // Validate and sanitize sections with detailed error handling
                TCLBuilder.Core.sections = rawSections.reduce((validSections, section) => {
                    try {
                        if (!section || typeof section !== 'object') {
                            console.warn('TCLBuilder: Invalid section data, skipping:', section);
                            return validSections;
                        }

                        const validatedSection = {
                            id: parseInt(section.id) || Date.now() + Math.floor(Math.random() * 1000),
                            type: ['html', 'shortcode'].includes(section.type) ? section.type : 'html',
                            title: TCLBuilder.Utils.sanitize(section.title || 'Untitled Section'),
                            designation: section.designation || 'library',
                            shadow_context: !!section.shadow_context,
                            content: section.type === 'html' ? {
                                html: typeof section.content?.html === 'string' ? section.content.html : '',
                                css: typeof section.content?.css === 'string' ? section.content.css : '',
                                js: typeof section.content?.js === 'string' ? section.content.js : ''
                            } : (typeof section.content === 'string' ? section.content : '')
                        };

                        validSections.push(validatedSection);
                        return validSections;
                    } catch (sectionError) {
                        console.error('TCLBuilder: Error processing section:', sectionError, section);
                        return validSections;
                    }
                }, []);

            } catch (error) {
                console.error('TCLBuilder: Error parsing sections:', error);
                TCLBuilder.Core.sections = [];
            }

            this.renderSections();
        },

        renderSections() {
            const container = jQuery('.content-sections');

            container.empty();

            // Add top actions
            container.append(`
                <div class="sections-actions">
                    <button type="button" class="button add-section-btn-top">
                        <i data-lucide="plus-circle"></i>
                        Add Section
                    </button>
                    <button class="button import-sections-btn">
                        <i data-lucide="upload"></i>
                        Import
                    </button>
                    <button class="button add-test-section-btn">
                        <i data-lucide="beaker"></i>
                        Add Test Section
                    </button>
                </div>
            `);

            // Add sections
            TCLBuilder.Core.sections.forEach(section => {
                container.append(this.createSectionHTML(section));
            });

            // Initialize Shadow DOM and execute queued JS
            this.jsQueue.forEach((jsContent, shadowRootId) => {
                const container = document.getElementById(shadowRootId);
                if (container) {
                    const shadowRoot = container.attachShadow({ mode: 'open' });
                    const script = document.createElement('script');
                    script.textContent = jsContent;
                    shadowRoot.appendChild(script);
                }
            });
            this.jsQueue.clear();

            // Add bottom dashed button
            container.append(`
                <button class="add-section-btn">
                    <i data-lucide="plus-circle"></i>
                    Add New Section
                </button>
            `);

            if (window.lucide) {
                lucide.createIcons();
            }

            // Publish sections rendered event
            TCLBuilder.Events.publish('sections:rendered');
        },

        formatPreviewContent(content, type) {
            try {
                if (!content) return '';

                if (type === 'html') {
                    if (typeof content !== 'object') {
                        console.warn('TCLBuilder: Invalid HTML section content format');
                        return 'Invalid content format';
                    }

                    // Analyze content
                    const htmlStats = this.analyzeHTML(content.html || '');
                    const cssStats = this.analyzeCSS(content.css || '');
                    const jsStats = this.analyzeJS(content.js || '');

                    return `
                        <div class="preview-summary">
                            <div class="preview-stat html-stat">
                                <i data-lucide="code-2"></i>
                                <span>${htmlStats.elementCount}</span>
                                ${htmlStats.keyElements ? `<span class="key-items">${htmlStats.keyElements}</span>` : ''}
                            </div>
                            ${cssStats.ruleCount ? `
                            <div class="preview-stat css-stat">
                                <i data-lucide="palette"></i>
                                <span>${cssStats.ruleCount}</span>
                                ${cssStats.keyProperties ? `<span class="key-items">${cssStats.keyProperties}</span>` : ''}
                            </div>
                            ` : ''}
                            ${jsStats.functionCount ? `
                            <div class="preview-stat js-stat">
                                <i data-lucide="file-code"></i>
                                <span>${jsStats.functionCount}</span>
                                ${jsStats.keyFeatures ? `<span class="key-items">${jsStats.keyFeatures}</span>` : ''}
                            </div>
                            ` : ''}
                        </div>
                    `;
                } else {
                    // Analyze shortcode content
                    const shortcodeStats = this.analyzeShortcode(String(content));

                    return `
                        <div class="preview-summary">
                            <div class="preview-stat shortcode-stat">
                                <i data-lucide="braces"></i>
                                <span>${shortcodeStats.name}</span>
                                ${shortcodeStats.attributes ? `<span class="key-items">${shortcodeStats.attributes}</span>` : ''}
                            </div>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('TCLBuilder: Error formatting preview content:', error);
                return 'Error generating preview';
            }
        },

        analyzeHTML(html) {
            try {
                // Strip PHP tags before analysis
                const cleanHtml = html.replace(/<\?php[\s\S]*?\?>/g, '');

                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = cleanHtml;

                // Count elements
                const elementCount = tempDiv.getElementsByTagName('*').length;

                // Get unique tag names (up to 2)
                const tags = new Set(Array.from(tempDiv.getElementsByTagName('*')).map(el => el.tagName.toLowerCase()));
                const keyElements = Array.from(tags).slice(0, 2).join(', ');

                return {
                    elementCount,
                    keyElements
                };
            } catch (error) {
                console.error('TCLBuilder: Error analyzing HTML:', error);
                return { elementCount: 0, keyElements: '' };
            }
        },

        analyzeCSS(css) {
            try {
                // Count CSS rules
                const ruleCount = (css.match(/{/g) || []).length;

                // Extract key properties (color, background, etc.)
                const keyProps = new Set();
                const importantProps = ['color', 'background', 'font', 'margin', 'padding'];

                importantProps.forEach(prop => {
                    if (css.includes(prop)) keyProps.add(prop);
                });

                const keyProperties = Array.from(keyProps).slice(0, 2).join(', ');

                return {
                    ruleCount,
                    keyProperties
                };
            } catch (error) {
                console.error('TCLBuilder: Error analyzing CSS:', error);
                return { ruleCount: 0, keyProperties: '' };
            }
        },

        analyzeJS(js) {
            try {
                if (!js) return { functionCount: 0, keyFeatures: '' };

                // Count function declarations and expressions
                const functionCount = (js.match(/function\s+\w+\s*\(|\(\s*\)\s*=>|\w+\s*:\s*function/g) || []).length;

                // Detect key features
                const features = new Set();
                if (js.includes('addEventListener')) features.add('events');
                if (js.includes('querySelector')) features.add('DOM');
                if (js.includes('fetch(')) features.add('ajax');
                if (js.includes('jQuery') || js.includes('$')) features.add('jQuery');
                if (js.includes('new Promise')) features.add('async');

                const keyFeatures = Array.from(features).slice(0, 2).join(', ');

                return {
                    functionCount,
                    keyFeatures
                };
            } catch (error) {
                console.error('TCLBuilder: Error analyzing JavaScript:', error);
                return { functionCount: 0, keyFeatures: '' };
            }
        },

        analyzeShortcode(content) {
            try {
                // Extract shortcode name
                const nameMatch = content.match(/\[(\w+)/);
                const name = nameMatch ? nameMatch[1] : 'shortcode';

                // Extract attributes
                const attrMatch = content.match(/(\w+)="[^"]*"/g);
                const attributes = attrMatch ?
                    attrMatch.slice(0, 3).map(attr => attr.split('=')[0]).join(', ') : '';

                return {
                    name,
                    attributes
                };
            } catch (error) {
                console.error('TCLBuilder: Error analyzing shortcode:', error);
                return { name: 'shortcode', attributes: '' };
            }
        },

        createSectionHTML(section) {
            try {
                if (!section || !section.id) {
                    console.error('TCLBuilder: Invalid section data:', section);
                    return '';
                }

                const shadowRootId = `shadow-root-${section.id}-${Date.now()}`;
                const sanitizedSection = {
                    id: parseInt(section.id),
                    type: TCLBuilder.Utils.sanitize(section.type || 'html'),
                    title: TCLBuilder.Utils.sanitize(section.title || 'Untitled Section'),
                };

                // Process JS for shadow DOM if needed
                if (section.type === 'html' && section.content?.js) {
                    if (section.shadow_context) {
                        this.jsQueue.set(shadowRootId, this.initShadowJS(section.id, section.content.js));
                    } else {
                        // Queue regular JS if shadow_context is false
                        this.jsQueue.set(shadowRootId, section.content.js);
                    }
                }


                const previewContent = this.formatPreviewContent(section.content, section.type);

                // Log section rendering
                console.log('TCLBuilder: Rendering section:', {
                    id: sanitizedSection.id,
                    type: sanitizedSection.type,
                    hasContent: !!section.content
                });

                return `
                    <div class="section-container" 
                         data-id="${sanitizedSection.id}" 
                         data-type="${sanitizedSection.type}"
                         data-designation="${section.designation || 'library'}"
                         data-shadow-context="${!!section.shadow_context}"
                         data-shadow-root-id="${shadowRootId}"
                         data-timestamp="${Date.now()}">
                        <div class="section-header">
                            <div class="drag-handle" aria-label="Drag to reorder">
                                <i data-lucide="move"></i>
                            </div>
                            <div class="designation-selector">
                                <i data-lucide="${(section.designation || 'library') === 'store' ? 'shopping-bag' :
                        (section.designation || 'library') === 'code' ? 'code-2' :
                            'library'
                    }"></i>
                                <select aria-label="Section designation">
                                    <option value="store" ${(section.designation || 'library') === 'store' ? 'selected' : ''}>Store</option>
                                    <option value="library" ${(section.designation || 'library') === 'library' ? 'selected' : ''}>Library</option>
                                    <option value="code" ${(section.designation || 'library') === 'code' ? 'selected' : ''}>Code</option>
                                </select>
                            </div>
                            <h2 class="section-title">${sanitizedSection.title}</h2>
                            <div class="header-actions">
                                <button class="action-btn" aria-label="Edit section">
                                    <i data-lucide="pencil"></i>
                                </button>
                                <button class="action-btn" aria-label="Export section">
                                    <i data-lucide="download"></i>
                                </button>
                                <button class="action-btn" aria-label="Delete section">
                                    <i data-lucide="trash-2"></i>
                                </button>
                            </div>
                        </div>
                        <div class="section-content">
                            <div class="content-block ${sanitizedSection.type}-block">
                                <i data-lucide="${sanitizedSection.type === 'html' ? 'code-2' : 'braces'}"></i>
                                <div class="preview-content shadow-root-container" id="${shadowRootId}">
                                    ${previewContent || 'Empty section'}
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            } catch (error) {
                console.error('TCLBuilder: Error creating section HTML:', error);
                return `
                    <div class="section-container error">
                        <div class="error-message">Error rendering section</div>
                    </div>
                `;
            }
        },

        deleteSection(sectionId) {
            sectionId = parseInt(sectionId);
            TCLBuilder.Core.sections = TCLBuilder.Core.sections.filter(s => parseInt(s.id) !== sectionId);
            this.renderSections();
            TCLBuilder.WordPress.save();
        },

        history: {
            undoStack: [],
            redoStack: [],
            maxSize: 50
        },

        pushToHistory(sections) {
            this.history.undoStack.push(JSON.stringify(sections));
            if (this.history.undoStack.length > this.history.maxSize) {
                this.history.undoStack.shift();
            }
            // Clear redo stack when new action is performed
            this.history.redoStack = [];
        },

        undo() {
            if (this.history.undoStack.length > 0) {
                // Save current state to redo stack
                this.history.redoStack.push(JSON.stringify(TCLBuilder.Core.sections));
                // Pop and apply previous state
                const previousState = JSON.parse(this.history.undoStack.pop());
                TCLBuilder.Core.sections = previousState;
                this.renderSections();
                return TCLBuilder.WordPress.save();
            }
            return Promise.resolve();
        },

        redo() {
            if (this.history.redoStack.length > 0) {
                // Save current state to undo stack
                this.history.undoStack.push(JSON.stringify(TCLBuilder.Core.sections));
                // Pop and apply next state
                const nextState = JSON.parse(this.history.redoStack.pop());
                TCLBuilder.Core.sections = nextState;
                this.renderSections();
                return TCLBuilder.WordPress.save();
            }
            return Promise.resolve();
        },

        updateOrder() {
            // Store current state in history before updating
            this.pushToHistory([...TCLBuilder.Core.sections]);

            // Get new order of section IDs
            const newOrder = jQuery('.section-container').map(function () {
                return parseInt(jQuery(this).data('id'));
            }).get();

            // Validate order integrity
            if (!this.validateOrder(newOrder)) {
                return Promise.reject(new Error('Invalid section order'));
            }

            // Optimize order update by only sending IDs
            const postId = jQuery('#post_ID').val();

            return new Promise((resolve, reject) => {
                TCLBuilder.Logger.info('Updating section order', {
                    postId,
                    newOrder,
                    sectionCount: newOrder.length
                });

                // Get full section data including all properties
                const sectionsData = TCLBuilder.Core.sections.map(section => {
                    const container = jQuery(`.section-container[data-id="${section.id}"]`);
                    const currentDesignation = container.data('designation') || 'library';

                    return {
                        ...section,
                        designation: currentDesignation
                    };
                });

                jQuery.ajax({
                    url: tclBuilderData.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'tcl_builder_reorder_sections',
                        nonce: tclBuilderData.nonce,
                        post_id: postId,
                        order: newOrder,
                        sections: JSON.stringify(sectionsData)
                    },
                    success: (response) => {
                        if (!response.success) {
                            reject(new Error(response.data.message || 'Failed to update order'));
                            return;
                        }

                        // Update local sections with server response
                        TCLBuilder.Core.sections = response.data.sections;

                        // Update Shadow DOM order if on frontend
                        if (document.querySelector('.tcl-builder-content')) {
                            this.updateShadowDOMOrder();
                        }

                        // Update last saved state
                        TCLBuilder.WordPress.lastSavedState = TCLBuilder.WordPress.getCurrentState();

                        TCLBuilder.Events.publish('sections:reordered', response.data);
                        resolve(response.data);
                    },
                    error: (xhr, status, error) => {
                        TCLBuilder.Logger.error('Order update failed', {
                            status,
                            error,
                            response: xhr.responseText
                        });
                        reject(new Error(`Network error while updating order: ${error}`));
                    }
                });
            });
        },

        validateOrder(newOrder) {
            // Ensure all sections are accounted for
            const currentIds = TCLBuilder.Core.sections.map(s => s.id).sort();
            const newIds = [...newOrder].sort();

            if (currentIds.length !== newIds.length) {
                TCLBuilder.Logger.error('Section count mismatch', {
                    current: currentIds,
                    new: newIds
                });
                return false;
            }

            // Ensure no duplicate IDs
            const uniqueIds = new Set(newOrder);
            if (uniqueIds.size !== newOrder.length) {
                TCLBuilder.Logger.error('Duplicate section IDs found', {
                    order: newOrder
                });
                return false;
            }

            return true;
        },

        updateShadowDOMOrder() {
            const content = document.querySelector('.tcl-builder-content');
            if (!content) return;

            const sections = Array.from(content.children);
            const container = content.cloneNode(false);

            // Reorder sections according to Core.sections order
            TCLBuilder.Core.sections.forEach(section => {
                const sectionElement = sections.find(el =>
                    el.getAttribute('data-section-id') === String(section.id)
                );
                if (sectionElement) {
                    container.appendChild(sectionElement.cloneNode(true));
                }
            });

            // Replace content with reordered version
            content.parentNode.replaceChild(container, content);

            // Reinitialize any necessary frontend functionality
            if (window.lucide) {
                window.lucide.createIcons();
            }
        },




        handleExport(e) {
            e.preventDefault();
            const section = jQuery(e.currentTarget).closest('.section-container');
            const sectionId = section.data('id');

            // Find the section data
            const sectionData = TCLBuilder.Core.sections.find(s => parseInt(s.id) === parseInt(sectionId));
            if (!sectionData) {
                TCLBuilder.Events.publish('notification:error', {
                    message: 'Section not found'
                });
                return;
            }

            // Prepare export data
            const exportData = {
                version: tclBuilderData.version,
                timestamp: new Date().toISOString(),
                sections: [sectionData]
            };

            // Create and trigger download
            const filename = `tcl-builder-section-${sectionId}-${new Date().toISOString().slice(0, 10)}.json`;
            const blob = new Blob([JSON.stringify(exportData, null, 2)], { type: 'application/json' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');

            a.style.display = 'none';
            a.href = url;
            a.download = filename;

            document.body.appendChild(a);
            a.click();

            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);

            TCLBuilder.Events.publish('notification:success', {
                message: 'Section exported successfully'
            });
        },

        handleImportClick(e) {
            e.preventDefault();
            jQuery('.sections-import-file').trigger('click');
        },

        handleImportFile(e) {
            const file = e.target.files[0];
            if (!file) {
                console.log('No file selected');
                return;
            }

            console.log('Reading file:', file.name);
            const reader = new FileReader();
            reader.onload = (event) => {
                try {
                    console.log('File content:', event.target.result.substring(0, 100) + '...');
                    const importData = JSON.parse(event.target.result);
                    this.importSections(importData);
                } catch (error) {
                    console.error('Import error:', error);
                    TCLBuilder.Events.publish('notification:error', {
                        message: 'Invalid import file format'
                    });
                }
            };
            reader.onerror = (error) => {
                console.error('FileReader error:', error);
                TCLBuilder.Events.publish('notification:error', {
                    message: 'Error reading file'
                });
            };
            reader.readAsText(file);
            e.target.value = ''; // Reset file input
        },



        importSections(importData) {
            try {
                // Validate import data using the new utility
                if (!TCLBuilder.Utils.validateImport(importData)) {
                    throw new Error('Invalid import data');
                }

                // Process each imported section
                const processedSections = importData.sections.map(section => ({
                    id: Date.now() + Math.floor(Math.random() * 1000),
                    type: section.type,
                    title: section.title || 'Imported Section',
                    designation: section.designation || 'library',
                    content: section.content
                }));

                // Append new sections to existing ones instead of replacing
                TCLBuilder.Core.sections = [...TCLBuilder.Core.sections, ...processedSections];
                
                // Render and save
                this.renderSections();
                TCLBuilder.WordPress.save().then(() => {
                    TCLBuilder.Events.publish('notification:success', {
                        message: 'Sections imported successfully'
                    });
                }).catch(error => {
                    console.error('Save error:', error);
                    TCLBuilder.Events.publish('notification:error', {
                        message: 'Failed to save imported sections'
                    });
                });

            } catch (error) {
                console.error('Import error:', error);
                TCLBuilder.Events.publish('notification:error', {
                    message: 'Failed to import sections: ' + error.message
                });
            }
        },


        addTestSection() {
            const testSection = {
                id: Date.now() + Math.floor(Math.random() * 1000),
                type: 'html',
                title: 'Test Shadow DOM Section',
                designation: 'library',
                content: {
                    html: `
                        <button id="test-button">Click Me</button>
                        <div id="test-result">Initial Text</div>
                    `,
                    css: `
                        #test-button { padding: 10px; }
                        #test-result { margin-top: 10px; }
                    `,
                    js: `
                        document.getElementById('test-button').addEventListener('click', function() {
                            document.getElementById('test-result').textContent = 'It works!';
                        });
                    `
                }
            };

            TCLBuilder.Core.sections.push(testSection);
            this.renderSections();
            TCLBuilder.WordPress.save();
        },

        bindEvents() {
            // Add hidden file input for imports
            if (!jQuery('.sections-import-file').length) {
                jQuery('.content-sections').append(`
                    <input type="file" class="sections-import-file" style="display: none;" accept="application/json">
                `);
            }

            // Import/Export and Add Section events
            jQuery(document).off('click', '.action-btn[aria-label="Export section"]').on('click', '.action-btn[aria-label="Export section"]', this.handleExport.bind(this));
            jQuery(document).off('click', '.import-sections-btn').on('click', '.import-sections-btn', (e) => {
                e.preventDefault();
                e.stopPropagation();
                const fileInput = jQuery('.sections-import-file');
                if (fileInput.length) {
                    fileInput.trigger('click');
                } else {
                    console.error('Import file input not found');
                }
            });
            jQuery(document).off('change', '.sections-import-file').on('change', '.sections-import-file', (e) => {
                console.log('File selected:', e.target.files[0]);
                this.handleImportFile(e);
            });
            jQuery(document).on('click', '.add-section-btn-top', (e) => {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                TCLBuilder.Modal.open('editor');
                return false;
            });

            jQuery(document).on('click', '.add-section-btn', () => {
                TCLBuilder.Modal.open('editor');
            });

            // Add test section button
            jQuery(document).on('click', '.add-test-section-btn', () => {
                this.addTestSection();
            });

            // Handle designation changes
            jQuery(document).on('change', '.designation-selector select', (e) => {
                const section = jQuery(e.target).closest('.section-container');
                const sectionId = parseInt(section.data('id'));
                const newDesignation = e.target.value;

                // Update data attribute
                section.attr('data-designation', newDesignation);

                // Update section data in Core.sections
                const sectionData = TCLBuilder.Core.sections.find(s => s.id === sectionId);
                if (sectionData) {
                    sectionData.designation = newDesignation;
                    TCLBuilder.WordPress.save();
                }
            });

            // Delete section buttons
            jQuery(document).on('click', '.action-btn[aria-label="Delete section"]', (e) => {
                e.preventDefault();
                const section = jQuery(e.currentTarget).closest('.section-container');
                const sectionId = section.data('id');
                if (confirm('Are you sure you want to delete this section?')) {
                    this.deleteSection(sectionId);
                }
            });

            // Edit section buttons
            jQuery(document).on('click', '.action-btn[aria-label="Edit section"]', (e) => {
                e.preventDefault();
                const section = jQuery(e.currentTarget).closest('.section-container');
                const type = section.data('type');
                const sectionId = section.data('id');
                TCLBuilder.Modal.open(type === 'html' ? 'editor' : 'shortcode', sectionId);
            });

            // Keyboard shortcuts for undo/redo
            jQuery(document).on('keydown', (e) => {
                if ((e.metaKey || e.ctrlKey) && !e.shiftKey && e.key === 'z') {
                    e.preventDefault();
                    this.undo();
                } else if ((e.metaKey || e.ctrlKey) && e.shiftKey && e.key === 'z') {
                    e.preventDefault();
                    this.redo();
                }
            });
        }
    });
})();
