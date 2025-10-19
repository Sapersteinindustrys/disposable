(function () {
    function initNavigation() {
        var container = document.getElementById('pc-admin-panels');
        if (!container) {
            return;
        }

        var navButtons = document.querySelectorAll('.pc-admin-nav__link[data-target]');
        var panels = container.querySelectorAll('.pc-admin-panel');

        if (!navButtons.length || !panels.length) {
            return;
        }

        function activatePanel(targetId, sourceButton) {
            if (!targetId) {
                return;
            }

            navButtons.forEach(function (btn) {
                btn.classList.toggle('is-active', btn === sourceButton || btn.getAttribute('data-target') === targetId);
            });

            panels.forEach(function (panel) {
                var isTarget = panel.id === targetId;
                panel.classList.toggle('is-active', isTarget);
                if (isTarget && typeof panel.focus === 'function') {
                    try {
                        panel.focus();
                    } catch (err) {
                        // Ignore focus errors if focus is not supported.
                    }
                }
            });
        }

        navButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                activatePanel(button.getAttribute('data-target'), button);
            });
        });

        var dashboard = document.querySelector('.pc-admin-dashboard');
        var initialPanel = dashboard ? dashboard.getAttribute('data-pc-active-panel') : '';
        var initialButton = null;

        if (initialPanel) {
            navButtons.forEach(function (button) {
                if (button.getAttribute('data-target') === initialPanel) {
                    initialButton = button;
                }
            });
        }

        if (!initialButton && navButtons.length) {
            initialButton = navButtons[0];
        }

        if (initialButton) {
            activatePanel(initialButton.getAttribute('data-target'), initialButton);
        }
    }

    document.addEventListener('DOMContentLoaded', initNavigation);
})();
