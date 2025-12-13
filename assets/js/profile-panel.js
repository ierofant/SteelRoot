(() => {
    // Mobile FAB visibility to avoid desktop flicker/duplication
    const mobileFab = document.querySelector('.mobile-user-fab');
    const toggleFab = () => {
        if (!mobileFab) return;
        const isMobile = window.matchMedia('(max-width: 820px)').matches;
        if (isMobile) {
            mobileFab.classList.add('show');
            mobileFab.removeAttribute('hidden');
        } else {
            mobileFab.classList.remove('show');
            mobileFab.setAttribute('hidden', 'hidden');
        }
    };
    toggleFab();
    window.addEventListener('resize', toggleFab);

    const triggers = document.querySelectorAll('#openProfilePanel, .profile-btn[data-avatar]');
    if (!triggers.length) return;

    let panel = null;
    let backdrop = null;

    const buildPanel = (data) => {
        const name = data.name || 'User';
        const email = data.email || '';
        const avatar = data.avatar || '/assets/img/avatar-placeholder.png';
        const profile = data.profile || '/profile';
        const logout = data.logout || '/logout';
        const token = data.token || '';

        if (!backdrop) {
            backdrop = document.createElement('div');
            backdrop.id = 'profilePanelBackdrop';
            backdrop.className = 'profile-panel-backdrop';
            document.body.appendChild(backdrop);
        }
        if (!panel) {
            panel = document.createElement('div');
            panel.id = 'profilePanel';
            panel.className = 'profile-panel';
            document.body.appendChild(panel);
        }

        panel.innerHTML = `
            <button id="closeProfilePanel" class="profile-panel-close" aria-label="Close">×</button>
            <div class="profile-panel-header">
                <div class="profile-panel-avatar" style="background-image:url('${avatar}');"></div>
                <div class="profile-panel-meta">
                    <div class="profile-panel-name">${name}</div>
                    ${email ? `<div class="profile-panel-email">${email}</div>` : ''}
                </div>
            </div>
            <div class="profile-panel-actions">
                <a href="${profile}" class="profile-panel-btn">Профиль</a>
                <form method="POST" action="${logout}">
                    <input type="hidden" name="_token" value="${token}">
                    <button type="submit" class="profile-panel-btn ghost">Выйти</button>
                </form>
            </div>
        `;
    };

    const close = () => {
        panel?.classList.remove('active');
        backdrop?.classList.remove('active');
        document.body.classList.remove('profile-panel-open');
    };

    const open = (data) => {
        buildPanel(data);
        panel.classList.add('active');
        backdrop.classList.add('active');
        document.body.classList.add('profile-panel-open');
        const closeBtn = document.getElementById('closeProfilePanel');
        closeBtn?.addEventListener('click', close, { once: true });
        backdrop.onclick = close;
    };

    triggers.forEach((btn) => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const data = {
                name: btn.dataset.name,
                email: btn.dataset.email,
                avatar: btn.dataset.avatar,
                profile: btn.dataset.profile,
                logout: btn.dataset.logout,
                token: btn.dataset.token,
            };
            open(data);
        });
    });

    window.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') close();
    });
})();
