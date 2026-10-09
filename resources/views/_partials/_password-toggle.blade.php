{{--
  Makes the whole eye box clickable.

  The template attaches its toggle listener to the <i>, not to the box around
  it, so only the 20px glyph responds — about 70% of the visible button does
  nothing. People aim at the box, not the glyph, and conclude the eye is
  broken.

  A CSS-only fix does not work here: the icon is painted through mask-image,
  and a mask clips its own pseudo-elements out of hit testing too, so an
  ::after stretched over the box is never the thing that gets clicked.

  So the box forwards the click to the icon and lets the template's own
  handler do the work — nothing here needs to know how the toggle is
  implemented, which keeps it working if the template changes.
--}}
<script>
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.form-password-toggle .input-group-text').forEach(function (box) {
      box.addEventListener('click', function (event) {
        // A click that already landed on the icon is the template's to
        // handle; forwarding it as well would toggle twice and look dead.
        if (event.target.closest('i')) return;

        box.querySelector('i')?.click();
      });
    });
  });
</script>
