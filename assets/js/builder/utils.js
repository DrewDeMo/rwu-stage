/**
 * TCL Builder Utils Module
 * Contains utility functions used across the builder
 */

(function($) {
    'use strict';

    // Ensure TCLBuilder exists
    if (typeof window.TCLBuilder === 'undefined') {
        console.error('TCLBuilder not found. Utils module initialization failed.');
        return;
    }

    // Extend existing TCLBuilder.Utils
    $.extend(TCLBuilder.Utils, {
        sanitize(input) {
            if (typeof input !== 'string') return '';
            // HTML sanitization disabled to allow raw HTML/CSS
            return input;
        },

        validateHTML(html) {
            if (typeof html !== 'string') return true; // Allow empty content
            if (!html.trim()) return true; // Allow empty string
            
            try {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                
                // Only check for major parsing errors
                const parserErrors = doc.getElementsByTagName('parsererror');
                if (parserErrors.length > 0) {
                    console.warn('TCLBuilder: HTML parsing warning:', parserErrors[0].textContent);
                    return false;
                }

                return true;
            } catch (error) {
                console.error('TCLBuilder: HTML validation error:', error);
                return false;
            }
        },

        validateCSS(css) {
            if (typeof css !== 'string') return true; // Allow empty content
            if (!css.trim()) return true; // Allow empty string
            
            try {
                // Only check for matching braces
                const braceCount = (css.match(/{/g) || []).length;
                const closingBraceCount = (css.match(/}/g) || []).length;
                
                if (braceCount !== closingBraceCount) {
                    console.warn('TCLBuilder: Mismatched braces in CSS');
                    return false;
                }

                return true;
            } catch (error) {
                console.error('TCLBuilder: CSS validation error:', error);
                return false;
            }
        },

        validate(data) {
            if (!data || typeof data !== 'object') {
                console.error('TCLBuilder: Invalid data object');
                return false;
            }
            
            // Only validate ID and type as required
            if (!data.id || !data.type) {
                console.error('TCLBuilder: Missing required fields (id or type)');
                return false;
            }

            // Set defaults for optional fields
            data.title = data.title || 'Untitled Section';

            // Ensure content structure exists
            if (data.type === 'html') {
                if (!data.content || typeof data.content !== 'object') {
                    data.content = { html: '', css: '', js: '' };
                }
                if (!data.content.html) data.content.html = '';
                if (!data.content.css) data.content.css = '';
                if (!data.content.js) data.content.js = '';
            } else if (data.type === 'shortcode') {
                if (typeof data.content !== 'string') {
                    data.content = '';
                }
            }

            return true;
        },

        debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        },

        validateImport(importData) {
            try {
                // Check basic structure
                if (!importData || typeof importData !== 'object') {
                    throw new Error('Invalid import data format');
                }

                // Validate required fields
                if (!importData.version || !importData.sections || !Array.isArray(importData.sections)) {
                    throw new Error('Missing required import fields');
                }

                // Validate each section
                importData.sections.forEach((section, index) => {
                    if (!section.type || !section.content) {
                        throw new Error(`Invalid section data at index ${index}`);
                    }

                    if (section.type === 'html') {
                        if (typeof section.content !== 'object' || 
                            typeof section.content.html !== 'string' || 
                            typeof section.content.css !== 'string' || 
                            typeof section.content.js !== 'string') {
                            throw new Error(`Invalid HTML section content at index ${index}`);
                        }
                    } else if (section.type === 'shortcode') {
                        if (typeof section.content !== 'string') {
                            throw new Error(`Invalid shortcode content at index ${index}`);
                        }
                    }
                });

                return true;
            } catch (error) {
                console.error('Import validation error:', error);
                return false;
            }
        }
    });

})(jQuery);
