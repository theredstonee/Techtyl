// Techtyl, Admin-Uebersicht auf /admin. Der Wrapper gibt den Block versteckt am Seitenende aus;
// dieses Skript setzt ihn an den Anfang des Inhalts, blendet die dadurch ersetzte Panel-Box
// "System Information" (Update-Hinweis steckt im Block) und die vier Link-Knoepfe aus, setzt die
// Balkenbreiten und zaehlt die Kennzahlen in der Bewegungsstufe "voll" kurz hoch.
// Ohne Skript bleibt die Panel-Seite unveraendert. Es wird kein HTML erzeugt, nur Text und Attribute.
(function () {
  /* ---------- Card editor ("Karten anpassen") ----------
     Without this script every arrow button posts the form and the server moves the card.
     With it, arrows and dragging reorder the list in place; "Save" posts the new order. */
  function setupCustomize(block) {
    var panel = block.querySelector('.techtyl-ov-customize');
    var list = panel && panel.querySelector('.techtyl-ov-cardlist');
    if (!list) {
      return;
    }
    var form = list.closest('form');
    var live = panel.querySelector('[data-live]');
    var unsaved = panel.querySelector('.techtyl-ov-unsaved');
    var movedText = list.getAttribute('data-moved-text') || '';

    if (panel.open && window.location.hash === '#techtyl-ov-customize') {
      panel.scrollIntoView({ block: 'start' });
    }

    function rows() {
      return Array.prototype.slice.call(list.children);
    }

    function refresh(row) {
      var all = rows();
      all.forEach(function (item, index) {
        var up = item.querySelector('[data-direction="up"]');
        var down = item.querySelector('[data-direction="down"]');
        if (up) {
          up.disabled = index === 0;
        }
        if (down) {
          down.disabled = index === all.length - 1;
        }
      });
      if (unsaved) {
        unsaved.hidden = false;
      }
      if (row && live) {
        live.textContent = movedText
          .replace(':title', row.getAttribute('data-title') || '')
          .replace(':position', String(all.indexOf(row) + 1))
          .replace(':total', String(all.length));
      }
    }

    list.addEventListener('click', function (event) {
      var button = event.target.closest('.techtyl-ov-move');
      if (!button || !list.contains(button)) {
        return;
      }
      event.preventDefault();
      var row = button.closest('.techtyl-ov-cardrow');
      var up = button.getAttribute('data-direction') === 'up';
      var sibling = up ? row.previousElementSibling : row.nextElementSibling;
      if (!sibling) {
        return;
      }
      list.insertBefore(row, up ? sibling : sibling.nextElementSibling);
      refresh(row);
      // Keep the focus on the same arrow; at the edge move it to the other one.
      var same = row.querySelector('[data-direction="' + (up ? 'up' : 'down') + '"]');
      var other = row.querySelector('[data-direction="' + (up ? 'down' : 'up') + '"]');
      (same && !same.disabled ? same : other).focus();
    });

    list.addEventListener('change', function () {
      if (unsaved) {
        unsaved.hidden = false;
      }
    });

    form.addEventListener('click', function (event) {
      var remove = event.target.closest('.techtyl-ov-remove');
      if (remove && !window.confirm(remove.getAttribute('data-confirm') || '')) {
        event.preventDefault();
      }
    });

    /* Dragging by the handle (mouse and pen); the arrows stay the keyboard way. */
    var dragged = null;
    rows().forEach(function (row) {
      var grip = row.querySelector('.techtyl-ov-grip');
      if (!grip) {
        return;
      }
      grip.addEventListener('pointerdown', function () {
        row.setAttribute('draggable', 'true');
      });
      row.addEventListener('dragstart', function (event) {
        dragged = row;
        row.classList.add('is-dragging');
        if (event.dataTransfer) {
          event.dataTransfer.effectAllowed = 'move';
          event.dataTransfer.setData('text/plain', row.getAttribute('data-id') || '');
        }
      });
      row.addEventListener('dragend', function () {
        row.classList.remove('is-dragging');
        row.removeAttribute('draggable');
        if (dragged) {
          refresh(dragged);
        }
        dragged = null;
      });
    });
    list.addEventListener('dragover', function (event) {
      if (!dragged) {
        return;
      }
      event.preventDefault();
      var target = event.target.closest('.techtyl-ov-cardrow');
      if (!target || target === dragged) {
        return;
      }
      var box = target.getBoundingClientRect();
      var after = event.clientY > box.top + box.height / 2;
      list.insertBefore(dragged, after ? target.nextElementSibling : target);
    });
    list.addEventListener('drop', function (event) {
      if (dragged) {
        event.preventDefault();
      }
    });
  }

  var block = document.getElementById('techtyl-overview');
  var content = document.querySelector('.content-wrapper > section.content');
  if (!block || !content) {
    return;
  }

  try {
    /* ---------- Einsetzen ---------- */
    var anchor = null;
    Array.prototype.forEach.call(content.children, function (row) {
      if (!row.classList.contains('row')) {
        return;
      }
      var info = row.querySelector(':scope > .col-xs-12 > .box > .box-header .box-title');
      var links = row.querySelector(':scope > .text-center > a > .btn');
      if (info || links) {
        if (!anchor) {
          anchor = row;
        }
        row.hidden = true;
        row.setAttribute('data-techtyl-ov-replaced', '');
      }
    });
    content.insertBefore(block, anchor);
    block.hidden = false;

    var heading = document.querySelector('.content-header > h1');
    var title = block.getAttribute('data-title');
    var subtitle = block.getAttribute('data-subtitle');
    if (heading && title && heading.firstChild && heading.firstChild.nodeType === 3) {
      heading.firstChild.textContent = title;
      var small = heading.querySelector('small');
      if (small && subtitle) {
        small.textContent = subtitle;
      }
    }

    /* ---------- Balken ---------- */
    Array.prototype.forEach.call(block.querySelectorAll('[data-p]'), function (meter) {
      var p = parseFloat(meter.getAttribute('data-p'));
      if (!isFinite(p)) {
        p = 0;
      }
      meter.style.setProperty('--techtyl-ov-p', String(Math.max(0, Math.min(100, p)) / 100));
    });
    // Anteile im gestapelten Balken (Server nach Zustand)
    Array.prototype.forEach.call(block.querySelectorAll('[data-share]'), function (part) {
      var share = parseFloat(part.getAttribute('data-share'));
      part.style.flexGrow = String(isFinite(share) && share > 0 ? share : 0);
    });

    setupCustomize(block);

    /* ---------- Hochzaehlen (nur Stufe "voll") ---------- */
    var motion = document.documentElement.getAttribute('data-techtyl-motion');
    if (!motion) {
      var source = document.getElementById('techtyl-attributes');
      try {
        motion = source ? JSON.parse(source.textContent || '{}')['data-techtyl-motion'] : null;
      } catch (error) {
        motion = null;
      }
    }
    var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if ((motion || 'voll') !== 'voll' || reduced || !window.requestAnimationFrame || !window.Intl) {
      return;
    }

    // Same number format as the server (en-US or de-DE), so the last frame equals the printed text.
    var intl = block.getAttribute('data-intl') === 'de-DE' ? 'de-DE' : 'en-US';
    Array.prototype.forEach.call(block.querySelectorAll('[data-count]'), function (node, index) {
      var target = parseFloat(node.getAttribute('data-count'));
      var decimals = parseInt(node.getAttribute('data-decimals'), 10) || 0;
      var final = node.textContent;
      if (!isFinite(target) || target <= 0) {
        return;
      }
      var format = new Intl.NumberFormat(intl, { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
      var duration = 700;
      var delay = 120 + index * 60;
      var start = null;
      node.textContent = format.format(0);
      function step(now) {
        if (start === null) {
          start = now + delay;
        }
        var t = Math.min(1, Math.max(0, (now - start) / duration));
        var eased = 1 - Math.pow(1 - t, 3);
        if (t >= 1) {
          node.textContent = final;
          return;
        }
        node.textContent = format.format(target * eased);
        window.requestAnimationFrame(step);
      }
      window.requestAnimationFrame(step);
    });
  } catch (error) {
    // Im Zweifel die Panel-Seite unveraendert lassen.
    Array.prototype.forEach.call(content.querySelectorAll('[data-techtyl-ov-replaced]'), function (row) {
      row.hidden = false;
    });
    block.hidden = true;
  }
})();
