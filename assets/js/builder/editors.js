/**
 * TCL Builder Editors Module
 * Handles CodeMirror editor initialization and management
 */

(function ($) {
    'use strict';

    // Ensure TCLBuilder exists
    if (typeof window.TCLBuilder === 'undefined') {
        console.error('TCLBuilder not found. Editors module initialization failed.');
        return;
    }

    // Extend existing TCLBuilder.Editors
    $.extend(TCLBuilder.Editors, {
        init() {
            this.initializeCodeMirror();
        },

        initializeCodeMirror() {
            if (!window.wp?.codeEditor) {
                console.warn('CodeMirror not available');
                return;
            }

            try {
                const sharedSettings = {
                    lineNumbers: true,
                    lineWrapping: true,
                    autoCloseBrackets: true,
                    matchBrackets: true,
                    indentUnit: 4,
                    tabSize: 4,
                    indentWithTabs: false,
                    smartIndent: true,
                    theme: 'dracula',
                    extraKeys: {
                        "Tab": "indentMore",
                        "Shift-Tab": "indentLess",
                        "Ctrl-Q": function (cm) {
                            cm.foldCode(cm.getCursor());
                        }
                    },
                    viewportMargin: Infinity,
                    autoRefresh: true,
                    minHeight: 150,
                    scrollbarStyle: "native",
                    foldGutter: true,
                    gutters: ["CodeMirror-linenumbers", "CodeMirror-foldgutter"],
                    foldOptions: {
                        widget: "..."
                    }
                };

                // Initialize editors with error handling
                TCLBuilder.Core.editors.html = wp.codeEditor.initialize(
                    $('.html-editor'),
                    {
                        ...tclBuilderCodeMirror.codeEditor.html,
                        codemirror: {
                            ...tclBuilderCodeMirror.codeEditor.html.codemirror,
                            ...sharedSettings,
                            mode: 'text/html',
                            autoCloseTags: true
                        }
                    }
                );

                TCLBuilder.Core.editors.css = wp.codeEditor.initialize(
                    $('.css-editor'),
                    {
                        ...tclBuilderCodeMirror.codeEditor.css,
                        codemirror: {
                            ...tclBuilderCodeMirror.codeEditor.css.codemirror,
                            ...sharedSettings,
                            mode: 'text/css'
                        }
                    }
                );

                // Add error recovery and enhanced event handling
                [TCLBuilder.Core.editors.html, TCLBuilder.Core.editors.css].forEach(editor => {
                    if (editor?.codemirror) {
                        const cm = editor.codemirror;

                        // Error handling
                        cm.on('error', (instance, error) => {
                            console.warn('CodeMirror error:', error);
                            instance.setValue(instance.getValue()); // Reset the editor
                        });

                        // Handle resize with improved debouncing and error handling
                        let resizeTimeout;
                        let isRefreshing = false;
                        const resizeObserver = new ResizeObserver((entries) => {
                            // Skip if already refreshing to prevent loops
                            if (isRefreshing) return;

                            // Cancel existing refresh request
                            if (resizeTimeout) {
                                window.cancelAnimationFrame(resizeTimeout);
                            }

                            // Schedule new refresh
                            resizeTimeout = window.requestAnimationFrame(() => {
                                try {
                                    isRefreshing = true;
                                    // Get current size before refresh
                                    const wrapper = cm.getWrapperElement();
                                    const currentHeight = wrapper.offsetHeight;
                                    const currentWidth = wrapper.offsetWidth;

                                    // Only refresh if size actually changed
                                    const entry = entries[0];
                                    const newHeight = entry.contentRect.height;
                                    const newWidth = entry.contentRect.width;

                                    if (currentHeight !== newHeight || currentWidth !== newWidth) {
                                        cm.refresh();
                                    }
                                } catch (e) {
                                    console.warn('Editor refresh skipped:', e);
                                } finally {
                                    isRefreshing = false;
                                }
                            });
                        });

                        // Observe the wrapper element with specific box model
                        const wrapper = cm.getWrapperElement();
                        resizeObserver.observe(wrapper, { box: 'border-box' });

                        // Clean up observer when editor is destroyed
                        cm.on('destroy', () => {
                            resizeObserver.disconnect();
                        });

                        // Handle editor focus with improved debouncing
                        let focusTimeout;
                        let isFocusRefreshing = false;
                        cm.on('focus', () => {
                            // Skip if already refreshing
                            if (isFocusRefreshing) return;

                            if (focusTimeout) {
                                window.cancelAnimationFrame(focusTimeout);
                            }

                            focusTimeout = window.requestAnimationFrame(() => {
                                try {
                                    isFocusRefreshing = true;
                                    cm.refresh();
                                } catch (e) {
                                    console.warn('Editor focus refresh skipped:', e);
                                } finally {
                                    isFocusRefreshing = false;
                                }
                            });
                        });

                        // Handle content changes with improved debouncing and size calculation
                        let changeTimeout;
                        let isChanging = false;
                        cm.on('change', () => {
                            // Skip if already processing changes
                            if (isChanging) return;

                            if (changeTimeout) {
                                window.cancelAnimationFrame(changeTimeout);
                            }

                            changeTimeout = window.requestAnimationFrame(() => {
                                try {
                                    isChanging = true;
                                    // Calculate new size
                                    const totalLines = cm.lineCount();
                                    const lineHeight = cm.defaultTextHeight();
                                    const currentHeight = wrapper.offsetHeight;
                                    const newHeight = Math.min(totalLines * lineHeight + 50, 500);

                                    // Only update if height changed
                                    if (currentHeight !== newHeight) {
                                        wrapper.style.minHeight = newHeight + 'px';
                                        // Use a separate frame for refresh to avoid layout thrashing
                                        requestAnimationFrame(() => {
                                            cm.refresh();
                                        });
                                    }
                                } catch (e) {
                                    console.warn('Editor size adjustment skipped:', e);
                                } finally {
                                    isChanging = false;
                                }
                            });
                        });

                        // Handle viewport changes with debouncing
                        let viewportTimeout;
                        let isViewportChanging = false;
                        cm.on('viewportChange', () => {
                            // Skip if already processing viewport change
                            if (isViewportChanging) return;

                            if (viewportTimeout) {
                                window.cancelAnimationFrame(viewportTimeout);
                            }

                            viewportTimeout = window.requestAnimationFrame(() => {
                                try {
                                    isViewportChanging = true;
                                    cm.refresh();
                                } catch (e) {
                                    console.warn('Viewport change refresh skipped:', e);
                                } finally {
                                    isViewportChanging = false;
                                }
                            });
                        });

                        // Initialize with reasonable size
                        setTimeout(() => {
                            cm.refresh();
                            const wrapper = cm.getWrapperElement();
                            wrapper.style.minHeight = '200px';
                        }, 100);
                    }
                });

                // Publish initialization event
                TCLBuilder.Events.publish('editors:initialized');

            } catch (error) {
                console.error('Error initializing editors:', error);
            }
        },

        formatCSS(css) {
            if (!css) return '';

            try {
                let formatted = '';
                let indentLevel = 0;
                const indentString = '    '; // 4 spaces for indentation

                // Remove multiple spaces and normalize newlines
                css = css.replace(/[\n\r]/g, '')
                    .replace(/\s+/g, ' ')
                    .replace(/\/\*.*?\*\//g, match => `\n${match}\n`) // Preserve comments
                    .replace(/}/g, '}\n')
                    .replace(/{/g, '{\n')
                    .replace(/;(?![^(]*\))/g, ';\n') // Don't add newline if inside parentheses
                    .replace(/\n\s*\n/g, '\n')
                    .trim();

                // Process each line
                const lines = css.split('\n');

                lines.forEach(line => {
                    line = line.trim();

                    if (!line) return;

                    // Handle closing braces
                    if (line.includes('}')) {
                        indentLevel = Math.max(0, indentLevel - 1);
                    }

                    // Add line with proper indentation
                    if (line.length > 0) {
                        formatted += indentString.repeat(indentLevel) + line + '\n';
                    }

                    // Handle opening braces
                    if (line.includes('{')) {
                        indentLevel++;
                    }
                });

                // Special handling for media queries and keyframes
                formatted = formatted
                    .replace(/@media[^{]+{(\n*)(\s*)/, '@media $1$2') // Clean up media query formatting
                    .replace(/@keyframes[^{]+{(\n*)(\s*)/, '@keyframes $1$2') // Clean up keyframe formatting
                    .replace(/}\s*}/g, '}\n}'); // Fix double closing brace formatting

                // Log any potential issues
                if (formatted.split('{').length !== formatted.split('}').length) {
                    console.warn('TCLBuilder: Possible mismatched braces in CSS formatting');
                }

                return formatted.trim();
            } catch (error) {
                console.error('TCLBuilder: Error formatting CSS:', error);
                // Return cleaned but unformatted CSS as fallback
                return css.replace(/\s+/g, ' ').trim();
            }
        }
    });

})(jQuery);
