/**
 * TCL Builder Events Module
 * Handles pub/sub event system
 */

(function($) {
    'use strict';

    // Ensure TCLBuilder exists
    if (typeof window.TCLBuilder === 'undefined') {
        console.error('TCLBuilder not found. Events module initialization failed.');
        return;
    }

    // Extend existing TCLBuilder.Events
    $.extend(TCLBuilder.Events, {
        subscribers: {},

        subscribe(event, callback) {
            if (!this.subscribers[event]) {
                this.subscribers[event] = [];
            }
            this.subscribers[event].push(callback);

            // Return unsubscribe function
            return () => {
                this.subscribers[event] = this.subscribers[event].filter(cb => cb !== callback);
            };
        },

        publish(event, data) {
            if (!this.subscribers[event]) return;
            this.subscribers[event].forEach(callback => {
                try {
                    callback(data);
                } catch (error) {
                    console.error(`Error in event subscriber for ${event}:`, error);
                }
            });
        },

        unsubscribeAll(event) {
            if (event) {
                delete this.subscribers[event];
            } else {
                this.subscribers = {};
            }
        }
    });

})(jQuery);
