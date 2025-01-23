/**
 * TCL Builder Events Module
 * Handles pub/sub event system with Shadow DOM support
 */

(function($) {
    'use strict';

    if (typeof window.TCLBuilder === 'undefined') {
        console.error('[ShadowDOM] TCLBuilder not found. Events module initialization failed.');
        return;
    }

    $.extend(TCLBuilder.Events, {
        subscribers: {},
        shadowContexts: new Map(),

        subscribe(event, callback, context = null) {
            if (!this.subscribers[event]) {
                this.subscribers[event] = [];
            }
            const subscriber = { callback, context };
            this.subscribers[event].push(subscriber);

            return () => {
                this.subscribers[event] = this.subscribers[event].filter(s => s !== subscriber);
            };
        },

        publish(event, data, context = null) {
            if (!this.subscribers[event]) return;
            
            const prefix = event.startsWith('shadow:') ? '[ShadowDOM] ' : '';
            console.debug(`${prefix}Publishing event: ${event}`);

            this.subscribers[event].forEach(subscriber => {
                try {
                    if (!context || subscriber.context === context) {
                        subscriber.callback(data);
                    }
                } catch (error) {
                    console.error(`${prefix}Error in event subscriber for ${event}:`, error);
                }
            });
        },

        registerShadowContext(rootId, shadowRoot) {
            this.shadowContexts.set(rootId, shadowRoot);
            this.publish('shadow:context:created', { rootId, shadowRoot });
        },

        unregisterShadowContext(rootId) {
            this.shadowContexts.delete(rootId);
            this.publish('shadow:context:destroyed', { rootId });
        },

        unsubscribeAll(event) {
            if (event) {
                delete this.subscribers[event];
            } else {
                this.subscribers = {};
                this.shadowContexts.clear();
            }
        }
    });

})(jQuery);
