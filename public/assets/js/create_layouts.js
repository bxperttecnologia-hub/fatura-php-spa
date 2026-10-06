const init = () => {
    const sidebar = document.getElementById('sidebar');
    const mainEl = document.querySelector('.main-wrapper');
    const headerEl = document.querySelector('.app-navbar');
    const toggleBtns = document.querySelectorAll('.sidebar-toggle-btn');
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');

    if (!sidebar) return;

    let overlay = document.querySelector('.overlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.className = 'overlay';
        document.body.appendChild(overlay);
    }

    function applySidebarState(collapsed) {
        sidebar.classList.toggle('collapsed', collapsed);
        mainEl?.classList.toggle('collapsed', collapsed);
        headerEl?.classList.toggle('collapsed', collapsed);

        const icon = toggleBtn.forEach(element => {
            element?.querySelector('i');
        });

        if (icon) {
            icon.setAttribute('data-lucide', collapsed ? 'panel-left-open' : 'panel-left-close');
            lucide.createIcons();
        }
    }

    const savedState = localStorage.getItem('sidebarCollapsed') === 'true';
    applySidebarState(savedState);

    toggleBtns.forEach(element => {
        element?.addEventListener('click', function (e) {
            e.stopPropagation();
            const isCollapsed = sidebar.classList.contains('collapsed');
            applySidebarState(!isCollapsed);
            localStorage.setItem('sidebarCollapsed', !isCollapsed);
        });
    });
    

    function openMobileMenu() {
        sidebar.classList.add('mobile-active');
        overlay.classList.add('show');
    }

    function closeMobileMenu() {
        sidebar.classList.remove('mobile-active');
        overlay.classList.remove('show');
    }

    mobileMenuBtn?.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        if (sidebar.classList.contains('mobile-active')) {
            closeMobileMenu();
        } else {
            openMobileMenu();
        }
    });

    overlay.addEventListener('click', closeMobileMenu);

    window.addEventListener('resize', function () {
        if (window.innerWidth > 992) {
            closeMobileMenu();
        }
    });

    // Expõe pro spa-router.js poder fechar o menu mobile
    // depois de trocar de página
    window.__closeMobileMenu = closeMobileMenu;
}

document.addEventListener('DOMContentLoaded', function () {
    init();

    // Marca o item ativo do menu na carga inicial da página
    if (window.SpaRouter) {
        window.SpaRouter.highlightActiveLink();
    }
});