// Techtyl, kleine Helfer fuer die Seiten der Registrierung und Anmeldung.
// Alles hier ist Komfort: die Pruefungen selbst macht der Server. Es wird nie HTML aus
// Daten erzeugt, nur Text, Klassen und Attribute gesetzt.
(function () {
  'use strict';

  function all(selector, scope) {
    return Array.prototype.slice.call((scope || document).querySelectorAll(selector));
  }

  function busy(form, on) {
    all('button[type="submit"]', form).forEach(function (button) {
      button.disabled = on;
      button.classList.toggle('is-busy', on);
    });
  }

  /* Benutzername: gleich klein schreiben, so wie der Server ihn speichert */
  all('[data-ta-lower]').forEach(function (input) {
    input.addEventListener('input', function () {
      var lower = input.value.toLowerCase();
      if (lower !== input.value) {
        var at = input.selectionStart;
        input.value = lower;
        input.setSelectionRange(at, at);
      }
    });
  });

  /* Einladungscode: Grossbuchstaben in Fuenfergruppen */
  all('[data-ta-invite]').forEach(function (input) {
    input.addEventListener('input', function () {
      var raw = input.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 20);
      input.value = (raw.match(/.{1,5}/g) || []).join('-');
    });
  });

  /* Absenden: Knopf sperren, damit nichts doppelt abgeschickt wird */
  all('form[data-ta-form]').forEach(function (form) {
    form.addEventListener('submit', function () {
      busy(form, true);
    });
  });
  // Nach "Zurueck" im Browser sind die Knoepfe wieder frei
  window.addEventListener('pageshow', function (event) {
    if (event.persisted) {
      all('form[data-ta-form]').forEach(function (form) {
        busy(form, false);
      });
    }
  });

  /* Passwort: Laenge und Uebereinstimmung live anzeigen */
  all('form[data-ta-password]').forEach(function (form) {
    var min = parseInt(form.getAttribute('data-ta-password'), 10) || 12;
    var password = form.querySelector('input[name="password"]');
    var repeat = form.querySelector('input[name="password_confirmation"]');
    var meter = form.querySelector('[data-ta-meter]');
    var count = form.querySelector('[data-ta-count]');
    var ruleLength = form.querySelector('[data-ta-rule="length"]');
    var ruleMatch = form.querySelector('[data-ta-rule="match"]');
    var countText = form.getAttribute('data-ta-count-text') || '(:count / :min)';
    if (!password || !repeat) return;

    function update() {
      var length = password.value.length;
      var enough = length >= min;
      var same = length > 0 && password.value === repeat.value;
      if (meter) {
        meter.style.transform = 'scaleX(' + Math.min(1, length / min) + ')';
        meter.classList.toggle('is-full', enough);
      }
      if (count) count.textContent = length > 0 && !enough ? countText.replace(':count', String(length)).replace(':min', String(min)) : '';
      if (ruleLength) ruleLength.classList.toggle('is-met', enough);
      if (ruleMatch) ruleMatch.classList.toggle('is-met', same);
    }

    password.addEventListener('input', update);
    repeat.addEventListener('input', update);
    update();

    all('[data-ta-reveal]', form).forEach(function (toggle) {
      toggle.addEventListener('click', function () {
        var show = password.type === 'password';
        password.type = repeat.type = show ? 'text' : 'password';
        toggle.textContent = form.getAttribute(show ? 'data-ta-hide' : 'data-ta-show') || toggle.textContent;
        toggle.setAttribute('aria-pressed', show ? 'true' : 'false');
      });
    });
  });

  /* Erneut senden: kurze Wartezeit, passend zum Limit des Servers */
  all('form[data-ta-wait]').forEach(function (form) {
    var seconds = parseInt(form.getAttribute('data-ta-wait'), 10) || 60;
    var button = form.querySelector('button[type="submit"]');
    var text = form.querySelector('[data-ta-wait-text]');
    var number = form.querySelector('[data-ta-wait-seconds]');
    if (!button || !text || !number) return;

    button.disabled = true;
    text.hidden = false;
    number.textContent = String(seconds);
    var timer = window.setInterval(function () {
      seconds -= 1;
      number.textContent = String(Math.max(0, seconds));
      if (seconds <= 0) {
        window.clearInterval(timer);
        button.disabled = false;
        text.hidden = true;
      }
    }, 1000);
  });

  /* Zwei-Faktor-Seite: Code an den Checkpoint des Panels schicken */
  all('form[data-ta-2fa]').forEach(function (form) {
    var token = form.getAttribute('data-ta-2fa');
    var error = document.querySelector('[data-ta-2fa-error]');
    var codeField = form.querySelector('[data-ta-2fa-code]');
    var recoveryField = form.querySelector('[data-ta-2fa-recovery]');
    var code = form.querySelector('input[name="authentication_code"]');
    var recovery = form.querySelector('input[name="recovery_token"]');
    var toggle = form.querySelector('[data-ta-2fa-switch]');
    var sheet = document.querySelector('.ta-sheet');
    var useRecovery = false;
    var sending = false;

    function fail(message) {
      error.textContent = message;
      error.hidden = false;
      if (sheet) {
        sheet.classList.remove('is-shaking');
        void sheet.offsetWidth;
        sheet.classList.add('is-shaking');
      }
      busy(form, false);
      sending = false;
      var field = useRecovery ? recovery : code;
      field.value = '';
      field.focus();
    }

    function text(key) {
      return form.getAttribute('data-err-' + key) || 'Error';
    }

    function xsrf() {
      var match = /(?:^|;\s*)XSRF-TOKEN=([^;]+)/.exec(document.cookie);
      return match ? decodeURIComponent(match[1]) : '';
    }

    function send() {
      if (sending) return;
      var body = { confirmation_token: token };
      if (useRecovery) {
        if (!recovery.value.trim()) return recovery.focus();
        body.recovery_token = recovery.value.trim();
      } else {
        if (!/^\d{6}$/.test(code.value)) return code.focus();
        body.authentication_code = code.value;
      }
      sending = true;
      error.hidden = true;
      busy(form, true);

      fetch('/auth/login/checkpoint', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-XSRF-TOKEN': xsrf(),
        },
        body: JSON.stringify(body),
      })
        .then(function (response) {
          return response
            .json()
            .catch(function () {
              return null;
            })
            .then(function (data) {
              if (response.ok && data && data.data && data.data.complete) {
                var target = data.data.intended;
                window.location.assign(typeof target === 'string' && /^\/(?!\/)/.test(target) ? target : '/');
                return;
              }
              if (response.status === 429) return fail(text('many'));
              if (response.status === 419) return fail(text('session'));
              fail(text(useRecovery ? 'recovery' : 'code'));
            });
        })
        .catch(function () {
          fail(text('network'));
        });
    }

    form.addEventListener('submit', function (event) {
      event.preventDefault();
      send();
    });

    code.addEventListener('input', function () {
      code.value = code.value.replace(/\D/g, '').slice(0, 6);
      if (code.value.length === 6) send();
    });

    toggle.addEventListener('click', function () {
      useRecovery = !useRecovery;
      codeField.hidden = useRecovery;
      recoveryField.hidden = !useRecovery;
      error.hidden = true;
      var alt = toggle.getAttribute('data-alt');
      toggle.setAttribute('data-alt', toggle.textContent);
      toggle.textContent = alt;
      (useRecovery ? recovery : code).focus();
    });
  });
})();
