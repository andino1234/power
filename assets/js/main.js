/**
 * main.js – Dashboard Mystery Shopper
 */

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var container = document.querySelector('.dashboard-container');
        var iframe = container ? container.querySelector('iframe') : null;

        if (iframe) {
            var overlay = document.createElement('div');
            overlay.setAttribute('id', 'iframe-loader');
            overlay.innerHTML =
                '<div class="loader-inner">' +
                '  <div class="loader-spinner"></div>' +
                '  <p>Cargando dashboard&hellip;</p>' +
                '</div>';
            overlay.style.cssText =
                'position:absolute;inset:0;background:rgba(244,247,252,0.9);' +
                'display:flex;align-items:center;justify-content:center;z-index:10;' +
                'border-radius:6px;transition:opacity 0.4s ease;';

            container.style.position = 'relative';
            container.appendChild(overlay);

            var style = document.createElement('style');
            style.textContent =
                '#iframe-loader .loader-inner{text-align:center;color:#0069E1;font-size:.9rem;}' +
                '#iframe-loader .loader-spinner{width:42px;height:42px;border:4px solid #d1dff5;' +
                'border-top-color:#0069E1;border-radius:50%;animation:spin .8s linear infinite;margin:0 auto 10px;}' +
                '@keyframes spin{to{transform:rotate(360deg)}}';
            document.head.appendChild(style);

            iframe.addEventListener('load', function () {
                overlay.style.opacity = '0';
                setTimeout(function () {
                    if (overlay.parentNode) overlay.parentNode.removeChild(overlay);
                }, 400);
            });

            var timeout = setTimeout(function () {
                if (overlay.parentNode) {
                    overlay.innerHTML =
                        '<div class="loader-inner" style="color:#E97132;">' +
                        '  <p>⚠ El dashboard tardó demasiado en cargar.<br>' +
                        '  Verifica tu conexión o recarga la página.</p>' +
                        '</div>';
                }
            }, 15000);

            iframe.addEventListener('load', function () { clearTimeout(timeout); });
        }

        initUsersModal();
        initAvatarPreviews();
        initEditMetaSync();
    });

    function initUsersModal() {
        var modals = Array.prototype.slice.call(document.querySelectorAll('[data-modal-root]'));
        if (!modals.length) return;

        var body = document.body;
        var editForm = document.getElementById('user-edit-form');

        function getModalById(id) {
            return id ? document.getElementById(id) : null;
        }

        function setModalVisibility(modal, show) {
            if (!modal) return;
            modal.classList.toggle('is-visible', !!show);
            modal.setAttribute('aria-hidden', show ? 'false' : 'true');
            body.classList.toggle('modal-open', modals.some(function (item) {
                return item.classList.contains('is-visible');
            }));
        }

        function closeAllModals() {
            modals.forEach(function (modal) {
                setModalVisibility(modal, false);
            });
        }

        function setField(name, value) {
            if (!editForm) return;
            var field = editForm.querySelector('[data-edit-field="' + name + '"]');
            if (!field) return;
            field.value = value == null ? '' : value;
        }

        function updateEditPreview(payload) {
            if (!editForm) return;

            var preview = editForm.querySelector('[data-edit-avatar-preview]');
            var namePreview = editForm.querySelector('[data-edit-name-preview]');
            var metaPreview = editForm.querySelector('[data-edit-meta-preview]');
            var fullName = payload.full_name || 'Usuario';
            var username = payload.username || 'usuario';
            var roleName = payload.role_name || 'viewer';
            var avatarPath = payload.avatar_path || '';
            var placeholder = fullName.trim() ? fullName.trim().charAt(0).toUpperCase() : 'U';

            if (namePreview) namePreview.textContent = fullName;
            if (metaPreview) metaPreview.textContent = username + ' · ' + roleName;
            if (!preview) return;

            if (avatarPath) {
                if (preview.tagName !== 'IMG') {
                    var img = document.createElement('img');
                    img.className = 'edit-preview-avatar avatar-live-preview';
                    img.setAttribute('data-edit-avatar-preview', 'true');
                    preview.parentNode.replaceChild(img, preview);
                    preview = img;
                }
                preview.src = avatarPath;
                preview.alt = 'Avatar actual';
                preview.classList.remove('placeholder');
                preview.removeAttribute('data-placeholder');
                preview.textContent = '';
            } else {
                if (preview.tagName === 'IMG') {
                    var div = document.createElement('div');
                    div.className = 'edit-preview-avatar avatar-live-preview placeholder';
                    div.setAttribute('data-edit-avatar-preview', 'true');
                    preview.parentNode.replaceChild(div, preview);
                    preview = div;
                }
                preview.classList.add('placeholder');
                preview.setAttribute('data-placeholder', placeholder);
                preview.textContent = placeholder;
            }
        }

        function updateActionModal(modal, trigger) {
            if (!modal || !trigger) return;

            var userId = trigger.getAttribute('data-action-user-id') || '';
            var fullName = trigger.getAttribute('data-action-name') || 'Usuario';
            var username = trigger.getAttribute('data-action-username') || 'usuario';
            var title = trigger.getAttribute('data-action-title') || 'Confirmar acción';
            var description = trigger.getAttribute('data-action-description') || '';
            var note = trigger.getAttribute('data-action-note') || '';
            var submitLabel = trigger.getAttribute('data-action-submit-label') || 'Confirmar';
            var submitClass = trigger.getAttribute('data-action-submit-class') || '';

            modal.querySelectorAll('[data-action-user-id-field]').forEach(function (field) {
                field.value = userId;
            });
            modal.querySelectorAll('[data-action-modal-title]').forEach(function (node) {
                node.textContent = title;
            });
            modal.querySelectorAll('[data-action-modal-description]').forEach(function (node) {
                node.textContent = description;
            });
            modal.querySelectorAll('[data-action-modal-note]').forEach(function (node) {
                node.textContent = note;
            });
            modal.querySelectorAll('[data-action-user-name]').forEach(function (node) {
                node.textContent = fullName;
            });
            modal.querySelectorAll('[data-action-user-meta]').forEach(function (node) {
                node.textContent = '@' + username;
            });
            modal.querySelectorAll('[data-action-submit]').forEach(function (button) {
                button.textContent = submitLabel;
                button.className = 'table-action-link users-confirm-submit';
                if (submitClass) {
                    submitClass.split(/\s+/).forEach(function (className) {
                        if (className) button.classList.add(className);
                    });
                }
            });
        }

        document.querySelectorAll('[data-modal-open]').forEach(function (trigger) {
            trigger.addEventListener('click', function (event) {
                var targetId = trigger.getAttribute('data-modal-open');
                var modal = getModalById(targetId);
                if (!modal) return;
                if (event) event.preventDefault();

                if (trigger.classList.contains('js-open-user-modal')) {
                    var payload = {
                        user_id: trigger.getAttribute('data-user-id') || '',
                        full_name: trigger.getAttribute('data-full-name') || '',
                        email: trigger.getAttribute('data-email') || '',
                        username: trigger.getAttribute('data-username') || '',
                        role_name: trigger.getAttribute('data-role-name') || 'viewer',
                        is_active: trigger.getAttribute('data-is-active') || '1',
                        avatar_path: trigger.getAttribute('data-avatar-path') || ''
                    };

                    setField('user_id', payload.user_id);
                    setField('full_name', payload.full_name);
                    setField('email', payload.email);
                    setField('username', payload.username);
                    setField('role_name', payload.role_name);
                    setField('is_active', payload.is_active);
                    var avatarInput = editForm ? editForm.querySelector('input[name="avatar"]') : null;
                    if (avatarInput) avatarInput.value = '';
                    updateEditPreview(payload);
                }

                if (trigger.classList.contains('js-open-action-modal')) {
                    updateActionModal(modal, trigger);
                }

                closeAllModals();
                setModalVisibility(modal, true);
            });
        });

        modals.forEach(function (modal) {
            modal.querySelectorAll('[data-modal-close]').forEach(function (element) {
                element.addEventListener('click', function () {
                    setModalVisibility(modal, false);
                });
            });
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeAllModals();
            }
        });

        if (window.usersCreateModalAutoOpen) {
            setModalVisibility(getModalById('user-create-modal'), true);
        }
        if (window.usersEditModalAutoOpen) {
            setModalVisibility(getModalById('user-edit-modal'), true);
        }
    }

    function initAvatarPreviews() {
        document.querySelectorAll('[data-avatar-input]').forEach(function (input) {
            input.addEventListener('change', function () {
                var targetSelector = input.getAttribute('data-preview-target');
                var preview = targetSelector ? document.querySelector(targetSelector) : null;
                if (!preview) return;

                var files = input.files || [];
                var file = files[0];
                var placeholderSource = input.getAttribute('data-placeholder-source');
                var sourceInput = placeholderSource ? document.querySelector(placeholderSource) : null;
                var placeholder = sourceInput && sourceInput.value.trim()
                    ? sourceInput.value.trim().charAt(0).toUpperCase()
                    : (preview.getAttribute('data-placeholder') || 'U');

                if (!file) {
                    resetPreview(preview, placeholder);
                    return;
                }

                var reader = new FileReader();
                reader.onload = function (event) {
                    if (preview.tagName === 'IMG') {
                        preview.src = event.target.result;
                        preview.classList.remove('placeholder');
                        preview.textContent = '';
                    } else {
                        var img = document.createElement('img');
                        img.className = preview.className.replace('placeholder', '').trim();
                        img.classList.add('avatar-live-preview');
                        if (preview.hasAttribute('data-avatar-preview')) img.setAttribute('data-avatar-preview', 'true');
                        if (preview.hasAttribute('data-edit-avatar-preview')) img.setAttribute('data-edit-avatar-preview', 'true');
                        img.src = event.target.result;
                        img.alt = 'Previsualización de avatar';
                        preview.parentNode.replaceChild(img, preview);
                    }
                };
                reader.readAsDataURL(file);
            });
        });
    }

    function resetPreview(preview, placeholder) {
        if (preview.tagName === 'IMG') {
            var div = document.createElement('div');
            div.className = preview.className + ' placeholder';
            if (preview.hasAttribute('data-avatar-preview')) div.setAttribute('data-avatar-preview', 'true');
            if (preview.hasAttribute('data-edit-avatar-preview')) div.setAttribute('data-edit-avatar-preview', 'true');
            div.setAttribute('data-placeholder', placeholder || 'U');
            div.textContent = placeholder || 'U';
            preview.parentNode.replaceChild(div, preview);
            return;
        }

        preview.classList.add('placeholder');
        preview.setAttribute('data-placeholder', placeholder || 'U');
        preview.textContent = placeholder || 'U';
    }

    function initEditMetaSync() {
        var form = document.getElementById('user-edit-form');
        if (!form) return;

        var nameInput = form.querySelector('input[name="full_name"]');
        var usernameInput = form.querySelector('input[name="username"]');
        var roleSelect = form.querySelector('select[name="role_name"]');
        var namePreview = form.querySelector('[data-edit-name-preview]');
        var metaPreview = form.querySelector('[data-edit-meta-preview]');

        function sync() {
            var fullName = nameInput && nameInput.value.trim() ? nameInput.value.trim() : 'Usuario';
            var username = usernameInput && usernameInput.value.trim() ? usernameInput.value.trim() : 'usuario';
            var role = roleSelect && roleSelect.value ? roleSelect.value : 'viewer';
            var avatarPreview = form.querySelector('[data-edit-avatar-preview]');
            if (namePreview) namePreview.textContent = fullName;
            if (metaPreview) metaPreview.textContent = username + ' · ' + role;
            if (avatarPreview && avatarPreview.classList.contains('placeholder')) {
                avatarPreview.textContent = fullName.charAt(0).toUpperCase();
                avatarPreview.setAttribute('data-placeholder', fullName.charAt(0).toUpperCase());
            }
        }

        [nameInput, usernameInput, roleSelect].forEach(function (field) {
            if (!field) return;
            field.addEventListener('input', sync);
            field.addEventListener('change', sync);
        });
    }
})();
