(function () {
  'use strict';

  // Toast notification system
  document.addEventListener('alpine:init', () => {
    Alpine.store('toasts', {
      items: [],
      add(message, type = 'info', duration = 4000) {
        const id = Date.now();
        this.items.push({ id, message, type });
        if (duration > 0) {
          setTimeout(() => this.remove(id), duration);
        }
      },
      remove(id) {
        this.items = this.items.filter(t => t.id !== id);
      },
    });
  });
})();
