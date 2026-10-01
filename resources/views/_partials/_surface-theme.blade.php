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
     * "#rrggbb" into the "r, g, b" triplet form.
     *
     * Several surfaces are painted as rgba() over a *-rgb variable rather than
     * the colour itself: the translucent navbar, and the blurred band the
     * fixed header lays over the top of the page. Setting only the hex
     * variables left those two reading the stock light values, which is where
     * the pale strip across the top of a dark background came from.
     */
    function rgbParts(hex) {
      const h = hex.replace('#', '');
      const full = h.length === 3 ? h[0] + h[0] + h[1] + h[1] + h[2] + h[2] : h;

      return [
        parseInt(full.slice(0, 2), 16),
        parseInt(full.slice(2, 4), 16),
        parseInt(full.slice(4, 6), 16)
      ].join(', ');
    }

    /**
     * The template's grey ramp is a fixed list of literals per theme, so on a
     * dark background every disabled input, striped row and .bg-light block
     * stayed pale. Rebuilding the ramp as the text colour mixed into the card
     * keeps those greys on whichever ground is actually in use.
     */
    const GRAY_STEPS = {
      25: 3, 50: 5, 75: 7, 100: 10, 200: 16, 300: 24,
      400: 34, 500: 46, 600: 58, 700: 70, 800: 82, 900: 92
    };

    function grayRamp(surface) {
      return Object.keys(GRAY_STEPS).map(function (step) {
        return '--bs-gray-' + step + ': color-mix(in srgb, ' + surface.text + ' ' +
               GRAY_STEPS[step] + '%, ' + surface.card + ');';
      }).join('\n          ');
    }

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

      // On a dark ground the template's hover and active tints have to mix
      // towards light; its base colour is black, which would darken them.
      const base = surface.dark ? '#ffffff' : '#000000';

      sheet.textContent = `
        :root, [data-bs-theme="light"], [data-bs-theme="dark"],
        [data-semidark-menu="true"] {
          color-scheme: ${surface.dark ? 'dark' : 'light'};

          --bs-body-bg: ${surface.page};
          --bs-body-bg-rgb: ${rgbParts(surface.page)};
          --bs-paper-bg: ${surface.card};
          --bs-paper-bg-rgb: ${rgbParts(surface.card)};
          --bs-card-bg: ${surface.card};

          --bs-body-color: ${surface.text};
          --bs-body-color-rgb: ${rgbParts(surface.text)};
          --bs-heading-color: ${surface.text};
          --bs-emphasis-color: ${surface.text};
          --bs-secondary-color: ${surface.muted};
          --bs-border-color: ${surface.border};

          --bs-base-color: ${base};
          --bs-base-color-rgb: ${rgbParts(base)};

          --bs-secondary-bg: color-mix(in srgb, ${surface.text} 6%, ${surface.card});
          --bs-tertiary-bg: color-mix(in srgb, ${surface.text} 4%, ${surface.card});


          ${grayRamp(surface)}

          /* The sidebar, navbar and footer carry their own variable set. The
             menu colour is the one that bites: it is derived from the light
             theme's heading colour, so on a dark background the menu went
             dark-on-dark unless it is overridden here. */
          --bs-menu-bg: ${surface.card};
          --bs-menu-bg-rgb: ${rgbParts(surface.card)};
          --bs-menu-color: ${surface.text};
          --bs-menu-color-rgb: ${rgbParts(surface.text)};
          --bs-menu-hover-color: ${surface.text};
          --bs-menu-sub-active-color: ${surface.text};
          --bs-menu-divider-color: ${surface.border};
          --bs-navbar-bg: ${surface.card};
          --bs-navbar-border-color: ${surface.border};
          --bs-footer-bg: ${surface.card};
        }

        body { background-color: ${surface.page}; color: ${surface.text}; }

        .card, .dropdown-menu, .modal-content, .offcanvas, .popover, .list-group-item {
          background-color: ${surface.card};
        }

        /* Tooltips and popovers.
           The template pins the tooltip chip to a fixed dark colour but takes
           its text from --bs-paper-bg, which only works while the card is
           near-white: on a dark background that is dark text on a dark chip.
           These variables have to be set on .tooltip itself rather than on
           :root, because Bootstrap declares them there and a local
           declaration beats an inherited one. On a dark background the chip
           is lifted off the card so it still reads as a chip. */
        .tooltip {
          --bs-tooltip-bg: ${surface.dark
            ? 'color-mix(in srgb, ' + surface.text + ' 18%, ' + surface.card + ')'
            : '#2f2b3d'};
          --bs-tooltip-color: ${surface.dark ? surface.text : '#ffffff'};
          --bs-tooltip-opacity: 1;
        }

        /* The chat screen paints its panel from its own --bs-chat-bg, which
           the template only defines for its light and dark themes — so on
           these backgrounds the conversation stayed a pale slab in the middle
           of a dark page. Set on .app-chat rather than :root because that is
           where the template's dark theme declares it, and an equally specific
           rule later in the sheet is what beats it. The page colour keeps the
           panel recessed from the card, which is the relationship the template
           gives it. */
        .app-chat,
        [data-bs-theme="dark"] .app-chat,
        [data-bs-theme="light"] .app-chat { --bs-chat-bg: ${surface.page}; }

        .popover {
          --bs-popover-bg: ${surface.card};
          --bs-popover-header-bg: color-mix(in srgb, ${surface.text} 6%, ${surface.card});
          --bs-popover-header-color: ${surface.text};
          --bs-popover-body-color: ${surface.text};
          --bs-popover-border-color: ${surface.border};
        }

        .text-heading, .text-body, h1, h2, h3, h4, h5, h6 { color: ${surface.text} !important; }
        .text-muted, .text-body-secondary { color: ${surface.muted} !important; }
        .table { --bs-table-bg: ${surface.card}; --bs-table-color: ${surface.text}; }

        /* Literal-colour utilities, which would otherwise punch a pale hole in
           a dark page. The coloured variants (.bg-primary and friends) are
           left alone: they carry their own ground. */
        .bg-white, .bg-body, .bg-body-tertiary, .bg-light { background-color: ${surface.card} !important; }
        .text-dark, .text-black { color: ${surface.text} !important; }
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
