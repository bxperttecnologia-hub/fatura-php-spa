(function () {
  "use strict";

  const app = {
    user: null,
    currency: null,
    currencies: [],
    ready: null,
    hydratePage: null,
  };

  const hydratePage = (user) => {
    if (!user) return;

    for (const field of document.querySelectorAll("[data-app-user-field]")) {
      const value = user[field.dataset.appUserField];
      if (value == null) continue;

      if (field.dataset.appUserAttribute) {
        field.setAttribute(field.dataset.appUserAttribute, String(value));
      } else if (field instanceof HTMLInputElement || field instanceof HTMLSelectElement) {
        field.value = String(value);
      } else if (field instanceof HTMLImageElement && field.dataset.appUserField === "image") {
        field.src = `/assets/img/profiles/${encodeURIComponent(String(value))}?t=${Date.now()}`;
      } else {
        field.textContent = String(value);
      }
    }

    const ids = {
      id_company: user.company_id,
      company_id: user.company_id,
      user_id: user.id,
    };
    for (const [id, value] of Object.entries(ids)) {
      const field = document.getElementById(id);
      if (field && value != null) field.value = String(value);
    }

    const now = new Date();
    const localDate = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, "0")}-${String(now.getDate()).padStart(2, "0")}`;
    for (const field of document.querySelectorAll("[data-default-date]")) {
      if (field.value) continue;
      field.value = field.dataset.defaultDate === "month"
        ? localDate.slice(0, 7)
        : field.dataset.defaultDate === "year"
          ? String(now.getFullYear())
          : localDate;
    }
    for (const field of document.querySelectorAll("[data-current-date]")) {
      field.textContent = now.toLocaleDateString("pt-PT");
    }

    for (const field of document.querySelectorAll("[data-app-user-image]")) {
      if (user.image) {
        field.src = `/assets/img/profiles/${encodeURIComponent(String(user.image))}?t=${Date.now()}`;
      }
    }
  };

  const hydrateCurrencySelects = (user) => {
    if (!user || !app.currencies.length) return;

    for (const select of document.querySelectorAll("select[data-currency-options]")) {
      const selectedIso = select.dataset.selectedCurrency
        || app.currency?.currency
        || user.currency
        || "AOA";
      select.replaceChildren(...app.currencies.map((currency) => {
        const option = document.createElement("option");
        option.value = currency.iso_code;
        option.textContent = `${currency.currency} (${currency.iso_code})`;
        option.selected = currency.iso_code === selectedIso;
        return option;
      }));
    }
  };

  const pageReady = new Promise((resolve) => {
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", resolve, { once: true });
    } else {
      resolve();
    }
  });

  app.ready = fetch("/api/v1/auth/me", {
    credentials: "same-origin",
    headers: { Accept: "application/json" },
  })
    .then((response) => {
      if (response.status === 401) return null;
      if (!response.ok) {
        throw new Error(`Falha ao carregar o utilizador (${response.status}).`);
      }
      return response.json();
    })
    .then((response) => {
      app.user = response?.data?.user ?? null;
      return pageReady.then(() => {
        app.hydratePage();
        return app.user;
      });
    })
    .catch((error) => {
      console.error("Não foi possível inicializar o contexto da aplicação.", error);
      return null;
    });
  app.hydratePage = () => {
    hydratePage(app.user);
    hydrateCurrencySelects(app.user);
  };

  app.currencyReady = app.ready.then(async (user) => {
    if (!user) return null;
    await pageReady;
    const [currentResponse, currenciesResponse] = await Promise.all([
      fetch("/api/v1/metadata/currency", {
        credentials: "same-origin",
        headers: { Accept: "application/json" },
      }),
      fetch("/api/v1/metadata/currencies", {
        credentials: "same-origin",
        headers: { Accept: "application/json" },
      }),
    ]);
    if (!currentResponse.ok && currentResponse.status !== 404) {
      throw new Error(`Falha ao carregar a moeda da empresa (${currentResponse.status}).`);
    }
    if (!currenciesResponse.ok) {
      throw new Error(`Falha ao carregar as moedas (${currenciesResponse.status}).`);
    }
    app.currency = currentResponse.status === 404 ? null : await currentResponse.json();
    app.currencies = await currenciesResponse.json();
    hydrateCurrencySelects(user);
    return app.currency;
  }).catch((error) => {
    console.error("Não foi possível carregar a moeda da empresa.", error);
    for (const select of document.querySelectorAll("select[data-currency-options]")) {
      select.replaceChildren(new Option("Erro ao carregar moedas", "", true, true));
      select.options[0].disabled = true;
    }
    return null;
  });

  app.subscriptionReady = app.ready.then(async (user) => {
    if (!user) return null;
    const response = await fetch("/api/v1/subscription/overview", {
      credentials: "same-origin",
      headers: { Accept: "application/json" },
    });
    if (!response.ok) {
      throw new Error(`Falha ao carregar o plano da empresa (${response.status}).`);
    }
    const payload = await response.json();
    app.subscription = payload?.data ?? null;
    return app.subscription;
  }).catch((error) => {
    console.error("Não foi possível carregar o plano da empresa.", error);
    return null;
  });

  window.APP = app;
})();
