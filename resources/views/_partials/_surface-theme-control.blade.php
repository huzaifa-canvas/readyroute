{{--
  The "Background" swatches, inserted into the template's own customizer panel
  directly under Primary Color so the two read as one set of controls.

  Appended from here rather than edited into template-customizer.js, which is
  a vendored file a theme update would overwrite. Sizes match the template's
  own colour options (50 x 46) so the two rows sit on the same rhythm.
--}}
<style>
  .rr-surface-grid {
    display: grid;
    /* Four across, so eight options make two even rows instead of
       seven-and-a-lonely-one. */
    grid-template-columns: repeat(4, 1fr);
    gap: .5rem;
  }

  .rr-surface-swatch {
    display: block;
    inline-size: 100%;
    block-size: 46px;
    padding: 0;
    position: relative;
    overflow: hidden;
    border: 1px solid var(--bs-border-color);
    border-radius: .375rem;
    cursor: pointer;
    background: none;
    transition: border-color .15s ease, box-shadow .15s ease;
  }

  .rr-surface-swatch:hover { border-color: var(--bs-primary); }

  .rr-surface-swatch[aria-pressed="true"] {
    border-color: var(--bs-primary);
    box-shadow: 0 0 0 2px rgba(var(--bs-primary-rgb), .25);
  }

  .rr-surface-swatch .rr-page { position: absolute; inset: 0; }

  .rr-surface-swatch .rr-card {
    position: absolute;
    inset: 5px 6px;
    border-radius: .25rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: .625rem;
    font-weight: 700;
    line-height: 1;
  }

  .rr-surface-name {
    display: block;
    margin-block-start: .25rem;
    font-size: .6875rem;
    text-align: center;
    color: var(--bs-secondary-color);
  }
</style>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    const surfaces = window.readyRouteSurfaces;
    const cookie   = window.readyRouteSurfaceCookie;

    if (!surfaces) return;

    function save(key) {
      // A year, matching how the template stores the primary colour.
      document.cookie = cookie + '=' + encodeURIComponent(key) +
        ';path=/;max-age=' + (365 * 24 * 60 * 60) + ';SameSite=Lax';
    }

    function build() {
      const colorSection = document.querySelector('.template-customizer-color');

      // The panel is built asynchronously; nothing to attach to yet.
      if (!colorSection || document.getElementById('rr-surface-control')) return false;

      const current = window.readyRouteReadCookie(cookie) || 'default';

      // Borrow the heading from the section above, so the label matches the
      // panel's own styling even after a theme update changes it.
      const sourceHeading = colorSection.querySelector('h5, h6, .customizer-heading, label');

      const section = document.createElement('div');
      section.id = 'rr-surface-control';
      section.className = colorSection.className.replace('template-customizer-color', 'template-customizer-surface');

      const heading = sourceHeading
        ? sourceHeading.cloneNode(false)
        : document.createElement('h6');

      heading.textContent = 'Background';
      heading.removeAttribute('for');
      section.appendChild(heading);

      const grid = document.createElement('div');
      grid.className = 'rr-surface-grid';

      Object.keys(surfaces).forEach(function (key) {
        const surface = surfaces[key];

        const cell = document.createElement('div');

        const swatch = document.createElement('button');
        swatch.type = 'button';
        swatch.className = 'rr-surface-swatch';
        swatch.title = surface.label;
        swatch.setAttribute('aria-label', surface.label + ' background');
        swatch.setAttribute('aria-pressed', key === current ? 'true' : 'false');
        swatch.dataset.surface = key;

        // A miniature of the real thing: the page colour behind, a card in
        // front, and sample text in the colour that would actually be used —
        // so the readability of a choice is visible before making it.
        swatch.innerHTML =
          '<span class="rr-page" style="background:' + surface.page + '"></span>' +
          '<span class="rr-card" style="background:' + surface.card + ';color:' + surface.text + '">Aa</span>';

        const name = document.createElement('span');
        name.className = 'rr-surface-name';
        name.textContent = surface.label;

        swatch.addEventListener('click', function () {
          window.readyRouteApplySurface(key);
          save(key);

          grid.querySelectorAll('.rr-surface-swatch').forEach(function (other) {
            other.setAttribute('aria-pressed', 'false');
          });

          swatch.setAttribute('aria-pressed', 'true');
        });

        cell.appendChild(swatch);
        cell.appendChild(name);
        grid.appendChild(cell);
      });

      section.appendChild(grid);

      // Straight after Primary Color, before Theme.
      colorSection.insertAdjacentElement('afterend', section);

      return true;
    }

    /**
     * Reset the panel in place, instead of reloading the page.
     *
     * The template's own reset clears its storage and then calls
     * location.reload(), which is a full navigation: the page goes white and
     * comes back, and anything half-typed on it is gone. Resetting is a
     * preference change like every other control in this panel, so it should
     * behave like one.
     *
     * The click is caught in the capture phase, which runs before the
     * listener the template attached to the button, so the reload never
     * happens — and template-customizer.js stays untouched, since it is a
     * vendored file a theme update would overwrite.
     *
     * The reset then works by driving the panel's own controls rather than
     * reimplementing what each one does. Every control is a radio (or, for
     * semi-dark, a switch), so setting it to the default and firing `change`
     * runs exactly the code path a person clicking it would.
     */
    function resetInPlace() {
      const tc    = window.templateCustomizer;
      const panel = document.getElementById('template-customizer');

      // The background is ours, and the template's reset never knew about it:
      // before this, a reset left the chosen background in place.
      document.cookie = cookie + '=;path=/;max-age=0;SameSite=Lax';
      window.readyRouteApplySurface('default');

      document.querySelectorAll('.rr-surface-swatch').forEach(function (swatch) {
        swatch.setAttribute('aria-pressed', swatch.dataset.surface === 'default' ? 'true' : 'false');
      });

      if (!tc || !panel) return;

      const defaults = tc.settings;

      const wanted = {
        colorRadioIcon:     defaults.defaultPrimaryColor,
        customRadioIcon:    defaults.defaultTheme,
        skinRadios:         defaults.defaultSkin && defaults.defaultSkin.name,
        layoutsRadios:      defaults.defaultMenuCollapsed ? 'collapsed' : 'expanded',
        navbarOptionRadios: defaults.defaultNavbarType,
        contentRadioIcon:   defaults.defaultContentLayout,
        directionRadioIcon: defaults.defaultTextDir ? 'rtl' : 'ltr'
      };

      Object.keys(wanted).forEach(function (name) {
        const value = wanted[name];
        if (!value) return;

        const radios = panel.querySelectorAll('input[name="' + name + '"]');
        if (!radios.length) return;

        // Colours are compared without case, since the panel spells them in
        // upper case and the configured default may not.
        const target = [...radios].find(function (radio) {
          return String(radio.value).toLowerCase() === String(value).toLowerCase();
        });

        // Already on its default, so there is nothing to put back. Skipping
        // also matters for direction, which the template handles with a
        // reload of its own — a reset should never trigger that needlessly.
        if (!target || target.checked) return;

        radios.forEach(function (radio) { radio.checked = false; });
        target.checked = true;
        target.dispatchEvent(new Event('change', { bubbles: true }));
      });

      // Semi-dark is a switch rather than a radio group.
      const semiDark = panel.querySelector('.template-customizer-semi-dark-switch');

      if (semiDark && semiDark.checked !== !!defaults.defaultSemiDark) {
        semiDark.checked = !!defaults.defaultSemiDark;
        semiDark.dispatchEvent(new Event('change', { bubbles: true }));
      }

      // Last, not first: each change above writes its new value to storage as
      // it goes, so the clear has to come after them. It also takes the dot
      // off the reset button, which is what says "something is customised".
      tc.clearLocalStorage();
    }

    document.addEventListener('click', function (event) {
      const button = event.target.closest('.template-customizer-reset-btn');
      if (!button) return;

      event.preventDefault();
      event.stopImmediatePropagation();

      resetInPlace();
    }, true);

    if (build()) return;

    // The customizer renders after its own script runs, so wait for the panel
    // rather than guessing at a delay.
    const observer = new MutationObserver(function () {
      if (build()) observer.disconnect();
    });

    observer.observe(document.body, { childList: true, subtree: true });

    // Stop watching if the panel never appears (it is disabled on some layouts).
    setTimeout(function () { observer.disconnect(); }, 10000);
  });
</script>
