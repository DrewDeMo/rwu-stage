(function() {
	'use strict';

	const TCLBuilderFrontend = {
		init() {
			this.initializeSections();
		},

		initializeSections() {
			const sections = document.querySelectorAll('.tcl-builder-section');
			sections.forEach(section => {
				try {
					this.initializeSection(section);
				} catch (error) {
					console.error('Error initializing section:', error);
				}
			});
		},

		initializeSection(section) {
			const sectionId = section.getAttribute('data-section-id');
			const sectionHost = section.querySelector('.tcl-builder-section-host');
			
			if (!sectionHost || !sectionHost.shadowRoot) {
				console.warn('Shadow root not found for section:', sectionId);
				return;
			}

			const shadowRoot = sectionHost.shadowRoot;
			const sectionData = window.tclBuilderSections?.[sectionId];

			if (!sectionData) {
				console.warn('Section data not found:', sectionId);
				return;
			}

			if (sectionData.type === 'html' && sectionData.content?.js) {
				this.initializeSectionJS(shadowRoot, sectionData.content.js, sectionId);
			}
		},

		waitForElements(root, selectors) {
            return new Promise((resolve, reject) => {
                // Filter out empty or invalid selectors
                const validSelectors = selectors.filter(selector => 
                    typeof selector === 'string' && selector.trim().length > 0
                );

                if (validSelectors.length === 0) {
                    resolve([]);
                    return;
                }

                // First check if elements already exist
                const elements = validSelectors.map(selector => root.querySelector(selector));
                if (elements.every(el => el !== null)) {
                    resolve(elements);
                    return;
                }

                let timeoutId;
                const observer = new MutationObserver(() => {
                    const elements = validSelectors.map(selector => root.querySelector(selector));
                    if (elements.every(el => el !== null)) {
                        observer.disconnect();
                        clearTimeout(timeoutId);
                        resolve(elements);
                    }
                });

                observer.observe(root, {
                    childList: true,
                    subtree: true,
                    attributes: true
                });

                // Set timeout to prevent infinite waiting
                timeoutId = setTimeout(() => {
                    observer.disconnect();
                    const elements = validSelectors.map(selector => root.querySelector(selector));
                    const missingSelectors = validSelectors.filter((selector, index) => elements[index] === null);
                    console.warn(`[Section] Timed out waiting for elements:`, missingSelectors);
                    resolve(elements); // Resolve with whatever elements we have
                }, 2000); // 2 second timeout
            });
        },

initializeSectionJS(shadowRoot, js, sectionId) {
	requestAnimationFrame(() => {
		try {
			// Transform document-level DOM queries to shadow root context
			const transformedJs = js
				.replace(/document\.getElementById\(['"](.*?)['"]\)/g, '$(\'#$1\')')
				.replace(/document\.querySelector\(['"](.*?)['"]\)/g, '$(\'$1\')')
				.replace(/document\.getElementsByClassName\(['"](.*?)['"]\)/g, '$$(\'.$1\')')
				.replace(/document\.getElementsByTagName\(['"](.*?)['"]\)/g, '$$(\'"$1\')')
				.replace(/document\.querySelectorAll\(['"](.*?)['"]\)/g, '$$(\'"$1\')')
				.replace(/document\.createElement\(['"](.*?)['"]\)/g, 'root.ownerDocument.createElement(\'$1\')');

			const selectorRegex = /[#.]?[\w-]+/g;
			const matches = transformedJs.match(selectorRegex) || [];
			const selectors = [...new Set(matches.filter(s => s && !s.match(/^(function|return|const|let|var|new|try|catch)$/)))];
			selectors.push('.section-content');

			// Enhanced safe element accessor with detailed error tracking
			const $ = selector => {
				const el = shadowRoot.querySelector(selector);
				if (!el) {
					console.warn(`[Section ${sectionId}] Element not found: ${selector}`);
					return new Proxy({}, {
						get: (target, prop) => {
							if (prop === 'style') return new Proxy({}, {
								get: () => () => {},
								set: () => true
							});
							if (prop === 'classList') return {
								add: () => {},
								remove: () => {},
								toggle: () => false,
								contains: () => false
							};
							if (prop === 'addEventListener' || prop === 'removeEventListener') {
								return () => {
									console.warn(`[Section ${sectionId}] Attempted to add/remove event listener on non-existent element: ${selector}`);
								};
							}
							if (typeof prop === 'string' && prop.startsWith('on')) return null;
							return () => null;
						},
						set: () => true
					});
				}
				return el;
			};

			const $$ = selector => Array.from(shadowRoot.querySelectorAll(selector) || []);
			
			let jQuery;
			if (window.jQuery) {
				jQuery = selector => window.jQuery(shadowRoot).find(selector);
				jQuery.fn = window.jQuery.fn;
			}

			this.waitForElements(shadowRoot, selectors).then(() => {
				try {
					const wrappedJs = `
						try {
							${transformedJs}
						} catch (error) {
							console.error('[Section ${sectionId}] Runtime error:', error.message);
							throw error;
						}
					`;
					const fn = new Function('$', '$$', 'jQuery', 'root', wrappedJs);
					fn.call(shadowRoot, $, $$, jQuery, shadowRoot);
				} catch (error) {
					console.error(`[Section ${sectionId}] JavaScript Error:`, {
						message: error.message,
						stack: error.stack,
						selectors: selectors.join(', ')
					});
				}
			}).catch(error => {
				console.error(`[Section ${sectionId}] Element initialization error:`, error);
			});
		} catch (error) {
			console.error(`[Section ${sectionId}] Setup error:`, error);
		}
	});
}
	};

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', () => TCLBuilderFrontend.init());
	} else {
		TCLBuilderFrontend.init();
	}
})();