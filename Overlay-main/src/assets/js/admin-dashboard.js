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

        navButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                var targetId = button.getAttribute('data-target');
                if (!targetId) {
                    return;
                }

                navButtons.forEach(function (btn) {
                    btn.classList.remove('is-active');
                });
                panels.forEach(function (panel) {
                    panel.classList.remove('is-active');
                });

                button.classList.add('is-active');

                var targetPanel = document.getElementById(targetId);
                if (targetPanel) {
                    targetPanel.classList.add('is-active');
                    if (typeof targetPanel.focus === 'function') {
                        try {
                            targetPanel.focus();
                        } catch (err) {
                            // Ignore focus errors in unsupported browsers.
                        }
                    }
                }
            });
        });
    }

    document.addEventListener('DOMContentLoaded', initNavigation);
})();
