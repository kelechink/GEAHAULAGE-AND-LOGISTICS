(function () {
  var WHATSAPP_NUMBER = "2348162163235";

  var year = document.getElementById("year");
  if (year) year.textContent = new Date().getFullYear();

  var form = document.getElementById("quote");
  if (!form) return;
  var errorBox = document.getElementById("form-error");

  form.addEventListener("submit", function (event) {
    event.preventDefault();

    var required = ["cargo", "from", "to", "name"];
    var missing = [];
    required.forEach(function (id) {
      var el = form.elements[id];
      var empty = !el.value.trim();
      el.setAttribute("aria-invalid", empty ? "true" : "false");
      if (empty) missing.push(form.querySelector('label[for="' + id + '"]').textContent.trim());
    });

    if (missing.length) {
      errorBox.textContent = "Fill in: " + missing.join(", ") + ".";
      errorBox.hidden = false;
      form.elements[required.find(function (id) { return !form.elements[id].value.trim(); })].focus();
      return;
    }
    errorBox.hidden = true;

    var v = function (id) { return form.elements[id].value.trim(); };
    var lines = [
      "Hello GEA, I'd like a haulage quote.",
      "",
      "Cargo: " + v("cargo"),
      "From: " + v("from"),
      "To: " + v("to")
    ];
    if (v("weight")) lines.push("Weight: about " + v("weight") + " tonnes");
    if (v("date")) lines.push("Loading date: " + v("date"));
    lines.push("", "Name: " + v("name"));
    if (v("company")) lines.push("Company: " + v("company"));

    var url = "https://wa.me/" + WHATSAPP_NUMBER + "?text=" + encodeURIComponent(lines.join("\n"));
    window.open(url, "_blank", "noopener");
  });

  form.addEventListener("input", function (event) {
    if (event.target.getAttribute("aria-invalid") === "true" && event.target.value.trim()) {
      event.target.setAttribute("aria-invalid", "false");
    }
  });
})();
