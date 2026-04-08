(function () {
  'use strict';

  document.addEventListener('alpine:init', () => {
    Alpine.data('commandPalette', () => ({
      open: false,
      query: '',
      commands: [],
      filtered: [],
      selectedIndex: 0,
      commandsUrl: '/game/commands',

      init() {
        this.fetchCommands();

        document.addEventListener('keydown', (e) => {
          if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
            e.preventDefault();
            this.toggle();
          }
          if (e.key === 'Escape' && this.open) {
            this.close();
          }
        });

        // Refresh commands when room changes
        document.addEventListener('sse:room_changed', () => {
          this.fetchCommands();
        });

        // Refresh commands when player state changes
        document.addEventListener('sse:state_changed', () => {
          this.fetchCommands();
        });
      },

      toggle() {
        this.open = !this.open;
        if (this.open) {
          this.query = '';
          this.selectedIndex = 0;
          this.filter();
          this.$nextTick(() => this.$refs.searchInput?.focus());
        }
      },

      close() {
        this.open = false;
        this.query = '';
      },

      async fetchCommands() {
        try {
          const res = await fetch(this.commandsUrl);
          if (res.ok) {
            this.commands = await res.json();
            this.filter();
          }
        } catch (e) {
          console.error('[CommandPalette] Failed to fetch commands', e);
        }
      },

      filter() {
        if (this.query === '') {
          this.filtered = this.commands;
        } else {
          const q = this.query.toLowerCase();
          this.filtered = this.commands.filter(
            (cmd) => cmd.label.toLowerCase().includes(q)
          );
        }
        this.selectedIndex = Math.min(this.selectedIndex, Math.max(0, this.filtered.length - 1));
      },

      navigate(direction) {
        if (direction === 'up' && this.selectedIndex > 0) {
          this.selectedIndex--;
        } else if (direction === 'down' && this.selectedIndex < this.filtered.length - 1) {
          this.selectedIndex++;
        }
      },

      execute(command) {
        this.close();
        if (command && command.action) {
          // Actions starting with / are navigation
          // Future: support other action types (panel toggle, etc.)
          window.location.href = command.action;
        }
      },

      executeSelected() {
        const cmd = this.filtered[this.selectedIndex];
        if (cmd) {
          this.execute(cmd);
        }
      },
    }));
  });
})();
