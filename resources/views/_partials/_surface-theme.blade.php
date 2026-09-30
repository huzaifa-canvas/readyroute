{{--
  Background ("Secondary Colour") support.

  Sits next to the template's Primary Color control and changes the page and
  card surfaces rather than the accent. The options are fixed pairs of
  background and text colour, not a free picker, because a background chosen on
  its own is how a panel ends up with grey text on a pale ground. Every preset
  here clears WCAG AA (4.5:1) for both body and muted text, on the page and on
  a card.

  This half runs in <head> so the saved choice is painted with the first frame
  instead of flashing the default first.
--}}
<script>
  (function () {
    // Kept in step with the list in _surface-theme-control.blade.php.
    window.readyRouteSurfaces = {
      'default':  { label: 'Default',  page: '#f8f7fa', card: '#ffffff', text: '#5d596c', muted: '#6d6b77', border: '#dbdade', dark: false },
      'warm':     { label: 'Warm',     page: '#faf6f0', card: '#fffdfa', text: '#5a5148', muted: '#6b6155', border: '#e6dfd4', dark: false },
      'cool':     { label: 'Cool',     page: '#f1f5fb', card: '#ffffff', text: '#4f5a69', muted: '#5f6b7a', border: '#d8e0ea', dark: false },
      'mint':     { label: 'Mint',     page: '#f0f7f2', card: '#ffffff', text: '#4c5f53', muted: '#5c6f63', border: '#d6e5db', dark: false },
      'rose':     { label: 'Rose',     page: '#fdf3f4', card: '#fffafa', text: '#6b545a', muted: '#7a636a', border: '#ecd9dc', dark: false },
      'slate':    { label: 'Slate',    page: '#eceff3', card: '#ffffff', text: '#4a5260', muted: '#5a6270', border: '#d5dae1', dark: false },
      'graphite': { label: 'Graphite', page: '#22252f', card: '#2b2f3b', text: '#d8dae0', muted: '#a9adb8', border: '#3a3f4d', dark: true  },
      'midnight': { label: 'Midnight', page: '#1a1d29', card: '#232734', text: '#d5d8e0', muted: '#a5a9b6', border: '#31364a', dark: true  }
    };

    window.readyRouteSurfaceCookie = 'rr-surface';

    window.readyRouteReadCookie = function (name) {
      const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
      return match ? decodeURIComponent(match[2]) : null;
    };

    /**
     * Write the surface as CSS variables.
     *
     * The template's own dark mode also sets these, so the rules are given the
     * same specificity it uses and injected after its stylesheet — otherwise
     * switching to Dark would quietly undo the choice.
     */
    window.readyRouteApplySurface = function (key) {
      const surface = window.readyRouteSurfaces[key];

      let sheet = document.getElementById('rr-surface-css');

      if (!surface || key === 'default') {
        if (sheet) sheet.remove();
        document.documentElement.removeAttribute('data-rr-surface');
        return;
      }

      if (!sheet) {
        sheet = document.createElement('style');
        sheet.id = 'rr-surface-css';
        document.head.appendChild(sheet);
      }

      sheet.textContent = `
        :root, [data-bs-theme="light"], [data-bs-theme="dark"] {
          --bs-body-bg: ${surface.page};
          --bs-paper-bg: ${surface.card};
          --bs-card-bg: ${surface.card};
          --bs-body-color: ${surface.text};
          --bs-secondary-color: ${surface.muted};
          --bs-border-color: ${surface.border};
          --bs-heading-color: ${surface.text};
        }
        body { background-color: ${surface.page}; color: ${surface.text}; }
        .card, .dropdown-menu, .modal-content, .offcanvas { background-color: ${surface.card}; }
        .text-heading, .text-body, h1, h2, h3, h4, h5, h6 { color: ${surface.text} !important; }
        .text-muted, .text-body-secondary { color: ${surface.muted} !important; }
        .table { --bs-table-bg: ${surface.card}; --bs-table-color: ${surface.text}; }
      `;

      // Lets anything else on the page react to a dark surface.
      document.documentElement.setAttribute('data-rr-surface', surface.dark ? 'dark' : 'light');
    };

    const saved = window.readyRouteReadCookie(window.readyRouteSurfaceCookie);

    if (saved) {
      window.readyRouteApplySurface(saved);
    }
  })();
</script>
