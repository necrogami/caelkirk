(function () {
  'use strict';

  const ShillaSSE = {
    source: null,
    playerChannel: null,

    connect(url) {
      if (this.source) {
        this.source.close();
      }

      this.source = new EventSource(url);

      this.source.onopen = () => {
        console.log('[SSE] Connected');
      };

      this.source.onerror = () => {
        if (this.source.readyState === EventSource.CLOSED) {
          console.log('[SSE] Connection closed');
        }
      };

      // Listen on global channel
      this.source.addEventListener('global', (e) => {
        this.dispatch(JSON.parse(e.data));
      });

      // Listen on player-specific channel
      if (this.playerChannel) {
        this.source.addEventListener(this.playerChannel, (e) => {
          this.dispatch(JSON.parse(e.data));
        });
      }
    },

    dispatch(data) {
      if (data.type) {
        document.dispatchEvent(
          new CustomEvent('sse:' + data.type, { detail: data })
        );
      }
    },

    disconnect() {
      if (this.source) {
        this.source.close();
        this.source = null;
      }
    },

    setPlayerChannel(channel) {
      this.playerChannel = channel;
    },
  };

  window.ShillaSSE = ShillaSSE;

  window.addEventListener('beforeunload', () => {
    ShillaSSE.disconnect();
  });
})();
