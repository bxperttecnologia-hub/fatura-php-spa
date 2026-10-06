const urlParams = new URLSearchParams(window.location.search);

// Atualiza os parâmetros da URL dinamicamente
const updateUrlParams = (paramKey, paramValue) => {
  const currentUrl = new URL(window.location.href);
  currentUrl.searchParams.set(paramKey, paramValue);
  window.history.pushState({}, "", currentUrl.href);
};

// Capitaliza uma string
const capitalize = (str) => str.charAt(0).toUpperCase() + str.slice(1);

// Define página base e constrói URL de conteúdo
let page = (urlParams.get("page") || "profile").replace(/\.html$/i, "");
let currentPageIndex = parseInt(urlParams.get("currentPage")) || 0;
let url = `/${page}.php`;

// Carrega o conteúdo da página dinamicamente
const loadContent = async (url, pageName, index = 0, label = pageName) => {
  // const setUrls = () => {};
  const contentArea = document.getElementById("app");
  // Loader temporário
  contentArea.innerHTML = `
    <div class="skeleton-loader" style="height: 300px; margin-bottom: 15px;"></div>
    <div class="skeleton-loader" style="height: 250px; margin-bottom: 15px;"></div>
    <div class="skeleton-loader" style="height: 150px; margin-bottom: 15px;"></div>
    <div class="skeleton-loader" style="height: 100px;"></div>
  `;

  await fetch(url)
    .then((res) => res.text())
    .then((html) => {
      contentArea.innerHTML = html;

      updateUrlParams("page", pageName.toLowerCase());
      updateUrlParams("currentPage", index);

      executeScripts(contentArea)
    })
    .catch((err) => {
      console.error("Erro ao carregar conteúdo:", err);
      contentArea.innerHTML = "<p>Erro ao carregar conteúdo.</p>";
    });

  activateMenu(index);
};

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

const activateMenu = () => {
  document.addEventListener("click", (e) => {
    const link = e.target.closest("a[data-link]");

    if (link) {
      e.preventDefault();
      loadRoute(link.getAttribute("href"));
    }
  });
};

// Inicialização ao carregar o DOM
document.addEventListener("DOMContentLoaded", () => {
  // Mapeamento de URLs específicas para conteúdos
  const routes = [
    {
      match: "/",
      path: "views/dashboard.php",
      title: "Painel de controlo",
    },
    {
      match: "/contacts",
      path: "views/contacts.php",
      title: "Contactos",
    },
    {
      match: "/contacts/create",
      path: "views/register_contact.php",
      title: "registrar contactos",
    },
    {
      match: "/items",
      path: "views/items.php",
      title: "items",
    },
    {
      match: "/proformas/create",
      path: "views/create_proforma.php",
      title: "proformas",
    },
    {
      match: "/proformas/list",
      path: "views/list_proformas.php",
      title: "proformas list",
    },
    {
      match: "/invoices/create",
      path: "views/create_invoices.php",
      title: "",
    },
    {
      match: "/invoices/list",
      path: "views/list_invoices.php",
      title: "invoices list",
    },
    {
      match: "/stock",
      path: "views/stock.php",
      title: "stock",
    },
    {
      match: "/purchases",
      path: "views/purchases.php",
      title: "purchases",
    },
    {
      match: "/employees",
      path: "views/employees.php",
      title: "employees",
    },
    {
      match: "/ponto",
      path: "views/ponto.php",
      title: "ponto",
    },
    {
      match: "/vacations",
      path: "views/vacations.php",
      title: "vacations",
    },
    {
      match: "/positions",
      path: "views/positions.php",
      title: "positions",
    },
    {
      match: "/payroll",
      path: "views/payroll.php",
      title: "payroll",
    },
    {
      match: "/help",
      path: "views/help.php",
      title: "help",
    },
    {
      match: "/subscription",
      path: "views/subscription.php",
      title: "subscription",
    },
  ];

  const matchedRoute = routes.find((route) => url.includes(route.match));

  if (matchedRoute) {
    loadContent(matchedRoute.path, page, currentPageIndex, matchedRoute.title);
  } else {
    // Carrega conteúdo padrão
    loadContent(url, page, currentPageIndex);
  }

  checkUserSession(); // Habilite se necessário
});
