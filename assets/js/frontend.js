(function() {
	'use strict';

	const TCLBuilderFrontend = {
		observers: new Map(),
		eventListeners: new Map(),

		init() {
			this.initializeSections();
			this.observeContentChanges();
		},

		observeContentChanges() {
			const contentObserver = new MutationObserver((mutations) => {
				mutations.forEach(mutation => {
					mutation.addedNodes.forEach(node => {
						if (node.classList?.contains('tcl-builder-section')) {
							this.initializeSection(node);
						}
					});
					mutation.removedNodes.forEach(node => {
						if (node.classList?.contains('tcl-builder-section')) {
							this.cleanupSection(node);
						}
					});
				});
			});

			contentObserver.observe(document.body, {
				childList: true,
				subtree: true
			});
		},

		cleanupSection(section) {
			const sectionId = section.getAttribute('data-section-id');
			
			// Cleanup observers
			if (this.observers.has(sectionId)) {
				this.observers.get(sectionId).forEach(observer => observer.disconnect());
				this.observers.delete(sectionId);
			}

			// Cleanup event listeners
			if (this.eventListeners.has(sectionId)) {
				this.eventListeners.get(sectionId).forEach(({element, type, fn}) => {
					try {
						element.removeEventListener(type, fn);
					} catch (error) {
						console.warn(`Failed to remove event listener: ${error.message}`);
					}
				});
				this.eventListeners.delete(sectionId);
			}

			// Clear any remaining references
			const sectionHost = section.querySelector('.tcl-builder-section-host');
			if (sectionHost && sectionHost.shadowRoot) {
				while (sectionHost.shadowRoot.firstChild) {
					sectionHost.shadowRoot.removeChild(sectionHost.shadowRoot.firstChild);
				}
			}
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
			if (!section || !section.classList) {
				console.warn('Invalid section element');
				return;
			}

			const sectionId = section.getAttribute('data-section-id');
			if (!sectionId) {
				console.warn('Section missing data-section-id');
				return;
			}

			const sectionHost = section.querySelector('.tcl-builder-section-host');
			if (!sectionHost) {
				console.warn('Section host not found:', sectionId);
				return;
			}

			// Get the actual ID from the host element
			const hostId = sectionHost.id;
			if (!hostId) {
				console.warn('Host element missing ID:', sectionId);
				return;
			}

			// Create a promise that resolves when the section is initialized
			const waitForInitialization = new Promise((resolve) => {
				const checkShadowRoot = () => {
					const shadowRoot = sectionHost.shadowRoot;
					if (shadowRoot && shadowRoot.querySelector('.section-content')) {
						resolve(shadowRoot);
						return true;
					}
					return false;
				};

				// Check immediately first
				if (checkShadowRoot()) {
					return;
				}

				// If not ready, observe changes
				const observer = new MutationObserver((mutations, obs) => {
					if (checkShadowRoot()) {
						obs.disconnect();
					}
				});

				observer.observe(sectionHost, { 
					childList: true, 
					subtree: true,
					attributes: true,
					attributeFilter: ['data-initialized']
				});

				// Cleanup after timeout
				setTimeout(() => {
					observer.disconnect();
					if (!checkShadowRoot()) {
						console.warn('Shadow root initialization timed out:', sectionId);
						resolve(null);
					}
				}, 5000);
			});

			// Initialize section once shadow DOM is ready
			waitForInitialization.then(shadowRoot => {
				if (!shadowRoot) return;

				const sectionData = window.tclBuilderSections?.[sectionId];
				if (!sectionData) {
					console.warn('Section data not found:', sectionId);
					return;
				}

				if (sectionData.type === 'html' && sectionData.content?.js) {
					// Wait for next frame to ensure DOM is ready
					requestAnimationFrame(() => {
						try {
							this.initializeSectionJS(shadowRoot, sectionData.content.js, sectionId);
						} catch (error) {
							console.error('Failed to initialize section JS:', error);
						}
					});
				}
			}).catch(error => {
				console.error('Section initialization failed:', error);
			});

		},

		waitForElements(root, selectors, sectionId) {
			return new Promise((resolve, reject) => {
				const validSelectors = selectors.filter(selector => 
					typeof selector === 'string' && selector.trim().length > 0
				);

				if (validSelectors.length === 0) {
					resolve([]);
					return;
				}

				const elements = validSelectors.map(selector => root.querySelector(selector));
				if (elements.every(el => el !== null)) {
					resolve(elements);
					return;
				}

				const observer = new MutationObserver(() => {
					const elements = validSelectors.map(selector => root.querySelector(selector));
					if (elements.every(el => el !== null)) {
						observer.disconnect();
						resolve(elements);
					}
				});

				observer.observe(root, {
					childList: true,
					subtree: true,
					attributes: true
				});

				// Store observer for cleanup
				if (!this.observers.has(sectionId)) {
					this.observers.set(sectionId, new Set());
				}
				this.observers.get(sectionId).add(observer);

				setTimeout(() => {
					observer.disconnect();
					const elements = validSelectors.map(selector => root.querySelector(selector));
					resolve(elements);
				}, 2000);
			});
		},

		trackEventListener(sectionId, element, type, fn) {
			if (!this.eventListeners.has(sectionId)) {
				this.eventListeners.set(sectionId, new Set());
			}
			this.eventListeners.get(sectionId).add({
				element,
				type,
				fn
			});
		},

		initializeSectionJS(shadowRoot, js, sectionId) {
            if (!shadowRoot || !shadowRoot.querySelector('.section-content')) {
                console.warn('[ShadowDOM] Invalid shadow root or content not ready');
                return;
            }

            // Only apply shadow DOM context if explicitly enabled
            const useShadowContext = shadowRoot.host.hasAttribute('data-shadow-context');
            
            // Create enhanced wrapper function with appropriate context
            const wrappedJs = `
                (function(shadowRoot) {
                    'use strict';
                    
                    // Provide scoped query helpers
                    const $ = selector => shadowRoot.querySelector(selector);
                    const $$ = selector => shadowRoot.querySelectorAll(selector);
                    
                    // Safe window access
                    const window = ${useShadowContext ? `{
                        setTimeout,
                        setInterval,
                        clearTimeout,
                        clearInterval,
                        requestAnimationFrame,
                        cancelAnimationFrame
                    }` : 'window'};
                    
                    // Safe document replacement
                    const document = ${useShadowContext ? `{
                        createElement: (tag) => {
                            const el = shadowRoot.ownerDocument.createElement(tag);
                            shadowRoot.appendChild(el);
                            return el;
                        },
                        querySelector: selector => shadowRoot.querySelector(selector),
                        querySelectorAll: selector => shadowRoot.querySelectorAll(selector),
                        getElementById: id => shadowRoot.querySelector('#' + CSS.escape(id)),
                        getElementsByClassName: className => shadowRoot.querySelectorAll('.' + className),
                        addEventListener: (type, fn, options) => {
                            const element = shadowRoot.querySelector(type.startsWith('#') ? type : '.section-content');
                            if (!element) {
                                console.warn('[ShadowDOM] Target element not found for event listener');
                                return;
                            }
                            const wrappedFn = (e) => {
                                e.stopPropagation();
                                fn.call(element, e);
                            };
                            element.addEventListener(type, wrappedFn, options);
                            TCLBuilderFrontend.trackEventListener('${sectionId}', element, type, wrappedFn);
                        }
                    }` : 'document'};

                    try {
                        ${js}
                    } catch (error) {
                        console.error('[ShadowDOM] Section ${sectionId} JS execution error:', error);
                    }
                })(this);`;

            // Execute in shadow root context
            requestAnimationFrame(() => {
                try {
                    const fn = new Function('root', wrappedJs);
                    fn.call(shadowRoot, shadowRoot);
                } catch (error) {
                    console.error(`[ShadowDOM] Section ${sectionId} JS execution error:`, error);
                }
            });
        },


		createSafeProxy() {
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
					return () => this.createSafeProxy();
				}
			});
		},

		wrapElement(el, sectionId) {
			const wrapper = new Proxy(el, {
				get: (target, prop) => {
					if (prop === 'addEventListener') {
						return (type, fn) => {
							const wrappedFn = (e) => {
								e.stopPropagation();
								fn.call(target, e);
							};
							target.addEventListener(type, wrappedFn);
							
							if (!this.eventListeners.has(sectionId)) {
								this.eventListeners.set(sectionId, new Set());
							}
							this.eventListeners.get(sectionId).add({
								element: target,
								type,
								fn: wrappedFn
							});
						};
					}
					return target[prop];
				}
			});
			return wrapper;
		},

		wrapjQuery($el, sectionId) {
			const originalOn = $el.on;
			$el.on = function(types, selector, data, fn) {
				if (typeof selector === 'function') {
					fn = selector;
					selector = undefined;
				}
				const wrappedFn = function(e) {
					e.stopPropagation();
					return fn.apply(this, arguments);
				};
				
				if (!TCLBuilderFrontend.eventListeners.has(sectionId)) {
					TCLBuilderFrontend.eventListeners.set(sectionId, new Set());
				}
				
				TCLBuilderFrontend.eventListeners.get(sectionId).add({
					element: this,
					type: types,
					fn: wrappedFn
				});
				
				return originalOn.call(this, types, selector, data, wrappedFn);
			};
			return $el;
		},



	};

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', () => TCLBuilderFrontend.init());
	} else {
		TCLBuilderFrontend.init();
	}
})();