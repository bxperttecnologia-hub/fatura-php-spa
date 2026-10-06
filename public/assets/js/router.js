// ==========================
// ROTAS
// ==========================
const routes = {
  // =========================
  // DASHBOARD
  // =========================
  "/": "views/dashboard.php",
  // =========================
  // CLIENTES / EMPRESAS
  // =========================
  "/contacts": "views/contacts.php",
  "/contacts/create": "views/register_contact.php",

  // =========================
  // PRODUTOS / ITENS
  // =========================
  "/items": "views/items.php",

  // =========================
  // PROFORMAS
  // =========================
  "/proformas/create": "views/create_proforma.php",
  "/proformas/list": "views/list_proformas.php",

  // =========================
  // FACTURAS
  // =========================
  "/invoices/create": "views/create_invoices.php",
  "/invoices/list": "views/list_invoices.php",

  // =========================
  // STOCK / COMPRAS
  // =========================
  "/stock": "views/stock.php",
  "/purchases": "views/purchases.php",

  // =========================
  // RH (RECURSOS HUMANOS)
  // =========================
  "/employees": "views/employees.php",
  "/ponto": "views/ponto.php",
  "/vacations": "views/vacations.php",
  "/positions": "views/positions.php",
  "/payroll": "views/payroll.php",

  // =========================
  // AJUDA
  // =========================
  "/help": "views/help.php",

  // =========================
  // PLANO / SUBSCRIÇÃO
  // =========================
  "/subscription": "views/subscription.php",
};

// Cache
const pageCache = {};
let controller = null;

// ==========================
// LOAD ROUTE
// ==========================
async function loadRoute(path, addToHistory = true) {
  const app = document.getElementById("app");

  const route = routes[path] || routes["/"];

  try {
    // Cancela request anterior
    if (controller) controller.abort();
    controller = new AbortController();

    // Loading UI
    app.innerHTML = `
      <div class="text-center p-5">
        <div class="spinner-border"></div>
      </div>
    `;

    let html;

    if (pageCache[path]) {
      html = pageCache[path];
    } else {
      const res = await fetch(route, {
        signal: controller.signal,
      });
      html = await res.text();
      pageCache[path] = html;
    }

    app.innerHTML = html;

    // Atualiza URL
    if (addToHistory) {
      history.pushState({}, "", path);
    }

    // Executa scripts da view (IMPORTANTE)
    executeScripts(app);

    //  CHAMA O APP.JS
    initPage(path);
  } catch (err) {
    if (err.name !== "AbortError") {
      app.innerHTML = "<h4>Erro ao carregar</h4>";
    }
  }
}

// ==========================
// EXECUTA SCRIPTS DINÂMICOS
// ==========================
function executeScripts(container) {
  const scripts = container.querySelectorAll("script");

  scripts.forEach((oldScript) => {
    const newScript = document.createElement("script");

    [...oldScript.attributes].forEach((attr) => {
      newScript.setAttribute(attr.name, attr.value);
    });

    newScript.textContent = oldScript.textContent;

    oldScript.parentNode.replaceChild(newScript, oldScript);
  });
}

// ==========================
// NAVEGAÇÃO (LINKS)
// ==========================
document.addEventListener("click", (e) => {
  const link = e.target.closest("a[data-link]");

  if (link) {
    e.preventDefault();
    loadRoute(link.getAttribute("href"));
  }
});

// ==========================
// BACK / FORWARD
// ==========================
window.addEventListener("popstate", () => {
  loadRoute(location.pathname, false);
});

// ==========================
// INIT
// ==========================
window.addEventListener("load", () => {
  loadRoute(location.pathname, false);
});
