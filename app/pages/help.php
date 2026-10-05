<!DOCTYPE html>
<html lang="pt">

<head>
    <meta charset="UTF-8">
    <title>FAQ</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">

    <link rel="stylesheet" href="style.css">
</head>
<style>
    body {
        background: #e6eff7;
        font-family: 'Segoe UI', sans-serif;
    }

    /* CARD */
    .faq-card {
        background: #fff;
        border-radius: 16px;
        padding: 40px;
        box-shadow: 0 20px 50px rgba(0, 0, 0, 0.08);
    }

    /* BADGE */
    .badge-soft {
        background: #eef6ff;
        color: #0d6efd;
        padding: 6px 12px;
        border-radius: 999px;
        font-size: 12px;
    }

    /* SEARCH */
    .search-input {
        border-radius: 999px;
        padding: 12px 20px;
        border: 1px solid #e5e7eb;
    }

    .search-input:focus {
        border-color: #0d6efd;
        box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.1);
    }

    /* ASIDE */
    .faq-aside {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .aside-item {
        padding: 10px 15px;
        border-radius: 999px;
        color: #6b7280;
        cursor: pointer;
        transition: 0.2s;
    }

    .aside-item.active {
        border: 1px solid #007abd;
        color: #fff;
        font-weight: 500;
        background: #007abd;
    }

    /* FAQ ITEM */
    .faq-item {
        background: #f9fafb;
        border-radius: 10px;
        padding: 15px;
        margin-bottom: 12px;
        cursor: pointer;
        transition: 0.2s;
    }

    .faq-item:hover {
        background: #f1f5f9;
    }

    /* QUESTION */
    .faq-question {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-weight: 500;
    }

    /* ANSWER */
    .faq-answer {
        display: none;
        margin-top: 10px;
        color: #6c757d;
        font-size: 14px;
    }

    .faq-item.active .faq-answer {
        display: block;
    }

    .logo{
        width: 120px;
    }
</style>

<body>

    <div class="container my-5">
        <header class="mb-4">
            <img src="./assets/img/logo//BXpert2.png" alt="" class="logo">
        </header>

        <div class="faq-card">

            <!-- HEADER -->
            <div class="row mb-4 align-items-center">
                <div class="col-md-6">
                    <span class="badge-soft">/ FAQs</span>
                    <h2 class="mt-2">Perguntas frequentes</h2>
                    <p class="text-muted small">
                        Aqui está tudo o que você precisa saber para começar, gerenciar sua conta e solucionar os problemas mais frequentes.
                    </p>
                </div>
            </div>

            <!-- BUSCA -->
            <div class="mb-4">
                <input
                    type="text"
                    id="faqSearch"
                    class="form-control search-input"
                    placeholder="Buscar perguntas...">
            </div>

            <div class="row">

                <!-- ASIDE -->
                <div class="col-md-3">
                    <div class="faq-aside">
                        <div class="aside-item active" data-filter="all">Geral</div>
                        <div class="aside-item" data-filter="security">Conta & segurança</div>
                        <div class="aside-item" data-filter="tools">Recursos & ferramentas</div>
                    </div>
                </div>

                <!-- CONTENT -->
                <div class="col-md-9">

                    <!-- GENERAL -->
                    <div class="faq-item active" data-category="general">
                        <div class="faq-question">
                            Para que serve esta plataforma?
                            <span class="material-icons-round">remove</span>
                        </div>
                        <div class="faq-answer">Platform helps manage work efficiently.</div>
                    </div>

                    <div class="faq-item" data-category="general">
                        <div class="faq-question">
                            Como eu gerencio minha conta?
                            <span class="material-icons-round">add</span>
                        </div>
                        <div class="faq-answer">Go to settings.</div>
                    </div>

                    <!-- SECURITY -->
                    <div class="faq-item" data-category="security">
                        <div class="faq-question">
                            Como eu redefino minha senha?
                            <span class="material-icons-round">add</span>
                        </div>
                        <div class="faq-answer">Clique em esqueci a senha.</div>
                    </div>

                    <div class="faq-item" data-category="security">
                        <div class="faq-question">
                            Os meus dados são seguros?
                            <span class="material-icons-round">add</span>
                        </div>
                        <div class="faq-answer">Sim, criptografados.</div>
                    </div>

                    <!-- TOOLS -->
                    <div class="faq-item" data-category="tools">
                        <div class="faq-question">
                            Quais ferramentas estão disponíveis?
                            <span class="material-icons-round">add</span>
                        </div>
                        <div class="faq-answer">Vendas, Stock & Recursos Humanos, relatórios, análise de dados.</div>
                    </div>

                </div>
            </div>

        </div>

    </div>

    <script>
        const faqItems = document.querySelectorAll(".faq-item");
        const searchInput = document.getElementById("faqSearch");
        const asideItems = document.querySelectorAll(".aside-item");

        let currentFilter = "all";

        // ===== FILTRAR =====
        function filterFAQs() {
            const searchValue = searchInput.value.toLowerCase();

            faqItems.forEach(item => {
                const category = item.dataset.category;
                const text = item.innerText.toLowerCase();

                const matchCategory = currentFilter === "all" || category === currentFilter;
                const matchSearch = text.includes(searchValue);

                if (matchCategory && matchSearch) {
                    item.style.display = "block";
                } else {
                    item.style.display = "none";
                }
            });
        }

        // ===== ASIDE CLICK =====
        asideItems.forEach(btn => {
            btn.addEventListener("click", () => {

                asideItems.forEach(b => b.classList.remove("active"));
                btn.classList.add("active");

                currentFilter = btn.dataset.filter;

                filterFAQs();
            });
        });

        // ===== SEARCH =====
        searchInput.addEventListener("input", filterFAQs);


        // ===== ACCORDION =====
        faqItems.forEach(item => {
            item.addEventListener("click", () => {

                const isActive = item.classList.contains("active");

                faqItems.forEach(i => {
                    i.classList.remove("active");
                    i.querySelector("span").textContent = "add";
                });

                if (!isActive) {
                    item.classList.add("active");
                    item.querySelector("span").textContent = "remove";
                }
            });
        });
    </script>
</body>

</html>