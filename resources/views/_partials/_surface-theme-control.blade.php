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
