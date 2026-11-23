// Techtyl Designer, Vorschau: setzt den Pfad der Panel-App (z. B. /server/1a2b3c4d), bevor sie
// startet. Laeuft nur im Rahmen des Designers derselben Herkunft und nur mit erlaubten Pfaden.
(function () {
  var script = document.currentScript;
  var path = script && script.getAttribute('data-path');
  if (!path || !/^\/(?:server\/[a-f0-9]{8}|auth\/login)?$/.test(path)) return;
  try {
    if (window.parent === window || window.parent.location.origin !== window.location.origin) return;
  } catch (error) {
    return;
  }
  window.history.replaceState(null, '', path);
})();
