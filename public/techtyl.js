// Uebertraegt die vom Server gepruefte Design-Auswahl als data-Attribute auf <html>,
// damit das Stylesheet Variante, Navigation und Anordnung erkennt.
(function () {
  var source = document.getElementById('techtyl-attributes');
  if (!source) return;
  var attributes;
  try {
    attributes = JSON.parse(source.textContent || '');
  } catch (error) {
    return;
  }
  if (!attributes || typeof attributes !== 'object') return;
  Object.keys(attributes).forEach(function (key) {
    if (/^data-techtyl-[a-z]+$/.test(key) && /^[a-z]+$/.test(String(attributes[key]))) {
      document.documentElement.setAttribute(key, String(attributes[key]));
    }
  });
})();
