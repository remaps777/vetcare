import * as bootstrap from 'bootstrap';
import $ from 'jquery';
import DataTable from 'datatables.net-bs5';
import 'datatables.net-responsive-bs5';

window.$ = window.jQuery = $;
window.bootstrap = bootstrap;
DataTable.use($, 'jq');
DataTable.use(bootstrap, 'bootstrap');
window.DataTable = DataTable;

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-numeric-only]').forEach((field) => {
        const maxLength = Number(field.dataset.maxlength || field.maxLength || 0);
        const sanitizeNumericValue = (value) => {
            let sanitized = value.replace(/\D/g, '');
            if (maxLength > 0) {
                sanitized = sanitized.slice(0, maxLength);
            }

            return sanitized;
        };

        const enforce = () => {
            const nextValue = sanitizeNumericValue(field.value ?? '');
            if (field.value !== nextValue) {
                field.value = nextValue;
            }
        };

        field.setAttribute('inputmode', 'numeric');
        field.setAttribute('pattern', '[0-9]*');
        field.addEventListener('keydown', (event) => {
            const allowedKeys = ['Backspace', 'Delete', 'Tab', 'ArrowLeft', 'ArrowRight', 'Home', 'End', 'Escape'];
            if (event.key && !/\d/.test(event.key) && !allowedKeys.includes(event.key)) {
                event.preventDefault();
            }
        });
        field.addEventListener('paste', (event) => {
            const pasteValue = event.clipboardData?.getData('text') ?? window.clipboardData?.getData('text') ?? '';
            if (!pasteValue) return;
            event.preventDefault();
            field.value = sanitizeNumericValue(pasteValue);
            field.dispatchEvent(new Event('input', { bubbles: true }));
        });
        field.addEventListener('input', enforce);
        enforce();
    });

    document.querySelectorAll('[data-datatable]').forEach((table) => {
        const columnCount = table.querySelectorAll('thead tr:first-child th').length;
        table.querySelectorAll('tbody tr').forEach((row) => {
            const cells = row.querySelectorAll(':scope > td, :scope > th');
            if (cells.length === columnCount) {
                return;
            }

            row.replaceChildren(...Array.from({ length: columnCount }, () => document.createElement('td')));
        });

        new DataTable(table, {
            responsive: true,
            paging: false,
            info: false,
            language: {
                emptyTable: 'No hay registros disponibles.',
                search: 'Buscar:',
                zeroRecords: 'No se encontraron coincidencias.',
            },
        });
    });
});

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[title]').forEach((element) => {
        new bootstrap.Tooltip(element);
    });

    document.querySelectorAll('[data-inventory-form]').forEach((form) => {
        const type = form.querySelector('[name="item_type"]');
        const item = form.querySelector('[name="item_id"]');
        const filterItems = () => {
            item.value = '';
            item.querySelectorAll('option[data-item-type]').forEach((option) => {
                option.hidden = option.dataset.itemType !== type.value;
            });

        };
        type?.addEventListener('change', filterItems);
        filterItems();
    });

    document.querySelectorAll('[data-bs-target="#inventoryEntryModal"], [data-bs-target="#inventoryExitModal"]').forEach((button) => {
        button.addEventListener('click', () => {
            const modal = document.querySelector(button.dataset.bsTarget);
            const type = modal?.querySelector('[name="item_type"]');
            const item = modal?.querySelector('[name="item_id"]');
            if (!type || !item) return;
            type.value = button.dataset.itemType;
            type.dispatchEvent(new Event('change'));
            item.value = button.dataset.itemId;
        });
    });

    const sendToStoreModal = document.getElementById('sendToStoreModal');
    sendToStoreModal?.addEventListener('show.bs.modal', (event) => {
        const button = event.relatedTarget;
        const form = sendToStoreModal.querySelector('[data-send-to-store-form]');
        form.elements.item_type.value = button.dataset.itemType;
        form.elements.item_id.value = button.dataset.itemId;
        form.elements.quantity.max = button.dataset.clinicStock;
        form.elements.quantity.value = '';
        sendToStoreModal.querySelector('[data-send-item-name]').textContent = button.dataset.itemName;
        sendToStoreModal.querySelector('[data-send-clinic-stock]').textContent = button.dataset.clinicStock;
    });

    const cashierForm = document.querySelector('[data-cashier-form]');
    if (cashierForm) {
        const cart = new Map();
        const cartElement = cashierForm.querySelector('[data-cart]');
        const totalElement = cashierForm.querySelector('[data-total]');
        const totalButtonElement = cashierForm.querySelector('[data-total-button]');
        const submitButton = cashierForm.querySelector('[data-cashier-submit]');
        const addProduct = (button) => {
            const id = button.dataset.id;
            const current = cart.get(id);
            const quantity = current ? current.quantity + 1 : 1;
            const stock = Number(button.dataset.stock);
            if (quantity > stock) return;
            cart.set(id, { id, name: button.dataset.name, price: Number(button.dataset.price), quantity, stock });
            renderCart();
        };
        const renderCart = () => {
            cartElement.innerHTML = '';
            let total = 0;
            cashierForm.querySelectorAll('[data-product-quantity]').forEach((quantity) => {
                quantity.textContent = '0';
            });
            cart.forEach((item) => {
                const subtotal = item.price * item.quantity;
                total += subtotal;
                const row = document.createElement('div');
                row.className = 'border-bottom py-2';
                row.innerHTML = `<div class="d-flex justify-content-between"><strong>${item.name}</strong><span>S/ ${(subtotal / 100).toFixed(2)}</span></div><div class="d-flex align-items-center gap-2 mt-1"><button type="button" class="btn btn-sm btn-outline-secondary" data-cart-minus="${item.id}">−</button><span>${item.quantity}</span><button type="button" class="btn btn-sm btn-outline-secondary" data-cart-plus="${item.id}">+</button><button type="button" class="btn btn-sm btn-outline-danger ms-auto" data-cart-remove="${item.id}"><i class="bi bi-trash"></i></button></div>`;
                cartElement.appendChild(row);
                const id = document.createElement('input');
                id.type = 'hidden';
                id.name = `items[${item.id}][product_id]`;
                id.value = item.id;
                const quantity = document.createElement('input');
                quantity.type = 'hidden';
                quantity.name = `items[${item.id}][quantity]`;
                quantity.value = item.quantity;
                cartElement.append(id, quantity);
            });
            if (cart.size === 0) cartElement.innerHTML = '<p class="text-muted">Agrega productos para iniciar la venta.</p>';
            totalElement.textContent = `S/ ${(total / 100).toFixed(2)}`;
            if (totalButtonElement) totalButtonElement.textContent = `S/ ${(total / 100).toFixed(2)}`;
            if (submitButton) submitButton.disabled = cart.size === 0;
            cart.forEach((item) => {
                const quantity = cashierForm.querySelector(`[data-product-quantity="${item.id}"]`);
                if (quantity) quantity.textContent = item.quantity;
            });
        };
        cashierForm.querySelectorAll('[data-product]').forEach((button) => {
            button.addEventListener('click', () => addProduct(button));
        });
        cashierForm.querySelectorAll('[data-product-add]').forEach((button) => {
            button.addEventListener('click', () => {
                const product = cashierForm.querySelector(`[data-product][data-id="${button.dataset.productAdd}"]`);
                if (product) addProduct(product);
            });
        });
        cashierForm.querySelectorAll('[data-product-minus]').forEach((button) => {
            button.addEventListener('click', () => {
                const item = cart.get(button.dataset.productMinus);
                if (!item) return;
                if (item.quantity === 1) cart.delete(button.dataset.productMinus);
                else item.quantity -= 1;
                renderCart();
            });
        });
        cartElement.addEventListener('click', (event) => {
            const control = event.target.closest('[data-cart-minus], [data-cart-plus], [data-cart-remove]');
            const id = control?.dataset.cartMinus || control?.dataset.cartPlus || control?.dataset.cartRemove;
            if (!id || !cart.has(id)) return;
            const item = cart.get(id);
            if (control.hasAttribute('data-cart-remove') || (control.hasAttribute('data-cart-minus') && item.quantity === 1)) cart.delete(id);
            else if (control.hasAttribute('data-cart-minus')) item.quantity -= 1;
            else if (item.quantity < item.stock) item.quantity += 1;
            renderCart();
        });
    }

    document.querySelectorAll('[data-catalog-form]').forEach((form) => {
        const modal = form.closest('.modal');
        modal.addEventListener('show.bs.modal', (event) => {
            const record = event.relatedTarget?.dataset.product || event.relatedTarget?.dataset.medication;
            const data = record ? JSON.parse(record) : null;
            form.reset();
            form.action = data ? `${form.dataset.updateBase}/${data.id}` : form.dataset.createAction;
            form.querySelector('[data-method-field]').value = data ? 'PATCH' : 'POST';
            modal.querySelector('[data-catalog-title]').textContent = data
                ? `Editar ${event.relatedTarget.dataset.product ? 'producto' : 'medicamento'}`
                : `Nuevo ${event.target.id === 'productModal' ? 'producto' : 'medicamento'}`;
            if (!data) return;
            Object.entries(data).forEach(([key, value]) => {
                const field = form.elements[key];
                if (field && value !== null) field.value = key === 'sale_price_cents'
                    ? (Number(value) / 100).toFixed(2)
                    : key === 'purchase_price_cents'
                        ? (Number(value) / 100).toFixed(2)
                        : value;
            });
        });

    });

    const userSearch = document.querySelector('[data-user-search]');
    userSearch?.addEventListener('input', () => {
        const query = userSearch.value.trim().toLocaleLowerCase();
        document.querySelectorAll('[data-user-row]').forEach((row) => {
            row.classList.toggle('d-none', query !== '' && !row.dataset.search.includes(query));
        });
    });

    const editModal = document.getElementById('editUserModal');
    editModal?.addEventListener('show.bs.modal', (event) => {
        const button = event.relatedTarget;
        const form = editModal.querySelector('[data-user-edit-form]');
        form.action = button.dataset.userUpdate;
        form.elements.name.value = button.dataset.userName;
        form.elements.email.value = button.dataset.userEmail;
        form.elements.phone.value = button.dataset.userPhone;
        form.elements.profile_id.value = button.dataset.userProfileId;
        form.elements.is_active.value = button.dataset.userActive;
    });

    const profileModal = document.getElementById('profileUserModal');
    profileModal?.addEventListener('show.bs.modal', (event) => {
        const button = event.relatedTarget;
        const form = profileModal.querySelector('[data-profile-form]');
        form.action = button.dataset.profileAction;
        profileModal.querySelector('[data-modal-user-name]').textContent = button.dataset.userName;
        profileModal.querySelector('[data-modal-current-profile]').textContent = button.dataset.userProfile;
    });

    const toggleModal = document.getElementById('toggleUserModal');
    toggleModal?.addEventListener('show.bs.modal', (event) => {
        const button = event.relatedTarget;
        const active = button.dataset.userActive === '1';
        const form = toggleModal.querySelector('[data-toggle-form]');
        form.action = button.dataset.toggleAction;
        toggleModal.querySelector('[data-toggle-message]').textContent = active
            ? `¿Desactivar a ${button.dataset.userName}? El usuario no podrá iniciar sesión hasta ser activado nuevamente.`
            : `¿Activar a ${button.dataset.userName}?`;
        toggleModal.querySelector('[data-toggle-submit]').textContent = active ? 'Desactivar' : 'Activar';
    });

    const permissionsModal = document.getElementById('permissionsUserModal');
    permissionsModal?.addEventListener('show.bs.modal', async (event) => {
        const button = event.relatedTarget;
        const list = permissionsModal.querySelector('[data-permissions-list]');
        const error = permissionsModal.querySelector('[data-permissions-error]');
        const form = permissionsModal.querySelector('[data-permissions-form]');
        form.action = button.dataset.permissionsSave;
        permissionsModal.querySelector('[data-permissions-user]').textContent = button.dataset.userName;
        list.innerHTML = '<div class="text-muted">Cargando permisos...</div>';
        error.classList.add('d-none');

        const response = await fetch(button.dataset.permissionsUrl, { headers: { Accept: 'application/json' } });
        if (!response.ok) {
            error.textContent = 'No se pudieron cargar los permisos.';
            error.classList.remove('d-none');
            return;
        }
        const data = await response.json();
        list.innerHTML = '';
        Object.entries(data.permissions).forEach(([module, permissions]) => {
            const column = document.createElement('div');
            column.className = 'col-md-6';
            const card = document.createElement('div');
            card.className = 'card h-100 border';
            card.innerHTML = `<div class="card-header bg-light fw-semibold">${module}</div>`;
            const body = document.createElement('div');
            body.className = 'card-body';
            permissions.forEach((permission) => {
                const wrapper = document.createElement('div');
                wrapper.className = 'form-check mb-2';
                wrapper.innerHTML = `<input class="form-check-input" type="checkbox" name="permissions[]" value="${permission.id}" id="permission-${permission.id}" ${permission.effective ? 'checked' : ''}><label class="form-check-label" for="permission-${permission.id}">${permission.name}</label>`;
                body.appendChild(wrapper);
            });
            card.appendChild(body);
            column.appendChild(card);
            list.appendChild(column);
        });
    });

    const passwordModal = document.getElementById('passwordUserModal');
    passwordModal?.addEventListener('show.bs.modal', (event) => {
        const button = event.relatedTarget;
        if (!button) return;
        const form = passwordModal.querySelector('[data-password-form]');
        form.action = button.dataset.passwordAction;
        passwordModal.querySelector('[data-password-user]').textContent = button.dataset.userName;
        passwordModal.querySelector('[data-password-confirm]').classList.remove('d-none');
        passwordModal.querySelector('[data-password-result]').classList.add('d-none');
        passwordModal.querySelector('[data-password-submit]').classList.remove('d-none');
    });
    passwordModal?.querySelector('[data-copy-password]')?.addEventListener('click', async () => {
        const password = passwordModal.querySelector('[data-temporary-password]').value;
        await navigator.clipboard.writeText(password);
    });
});

const sidebar = document.getElementById('appSidebar');
const toggle = document.getElementById('sidebarToggle');
const backdrop = document.getElementById('sidebarBackdrop');
const close = document.getElementById('sidebarClose');

if (sidebar && toggle && backdrop && close) {
    const mobile = window.matchMedia('(max-width: 991.98px)');
    const setOpen = (open) => {
        sidebar.classList.toggle('show', open);
        backdrop.classList.toggle('show', open);
        toggle.setAttribute('aria-expanded', String(open));
        sidebar.inert = mobile.matches && !open;
        document.body.classList.toggle('sidebar-open', mobile.matches && open);
    };
    toggle.addEventListener('click', () => {
        setOpen(true);
        close.focus();
    });
    const closeMenu = () => {
        setOpen(false);
        toggle.focus();
    };
    close.addEventListener('click', closeMenu);
    backdrop.addEventListener('click', closeMenu);
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && sidebar.classList.contains('show')) closeMenu();
    });
    mobile.addEventListener('change', () => setOpen(false));
    setOpen(false);
}

/* ── Confirmed-mutation modal forms ─────────────────────────────────────────
   Any <form data-confirmed-form> goes through a two-step flow:
   1. POST with _preview=1  → server validates & returns { confirmation_token, summary }
   2. POST with confirmation_token → server commits the change
   Errors surface in the [data-form-errors] alert inside the same modal.
   ────────────────────────────────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('select[data-dependent-on]').forEach((dependent) => {
        const source = document.getElementById(dependent.dataset.dependentOn);
        if (!source) return;

        const filterOptions = () => {
            const selectedSpecies = source.value;
            let selectedVisible = false;
            dependent.querySelectorAll('option[data-filter-value]').forEach((option) => {
                const visible = !selectedSpecies || option.dataset.filterValue === selectedSpecies;
                option.hidden = !visible;
                option.disabled = !visible;
                if (option.selected && visible) selectedVisible = true;
            });
            if (!selectedVisible) dependent.value = '';
            dependent.disabled = !selectedSpecies;
        };

        source.addEventListener('change', filterOptions);
        filterOptions();
    });

    document.querySelectorAll('[data-autocomplete-input]').forEach((input) => {
        const hidden = document.getElementById(input.dataset.hiddenTarget);
        const menu = document.getElementById(input.dataset.autocompleteMenu);
        if (!hidden || !menu) return;

        const options = [...menu.querySelectorAll('[data-autocomplete-option]')];
        const normalize = (value) => value.trim().toLocaleLowerCase();
        const setOpen = (open) => {
            menu.classList.toggle('show', open);
            input.setAttribute('aria-expanded', String(open));
        };
        const syncValue = (value = input.value) => {
            const option = options.find((item) => normalize(item.dataset.label) === normalize(value));
            hidden.value = option?.dataset.value ?? '';
            hidden.dispatchEvent(new Event('change', { bubbles: true }));
        };
        const filterOptions = () => {
            const query = normalize(input.value);
            let hasVisibleOptions = false;
            options.forEach((option) => {
                const visible = !query || normalize(option.dataset.label).includes(query);
                option.classList.toggle('d-none', !visible);
                hasVisibleOptions ||= visible;
            });
            setOpen(hasVisibleOptions);
        };

        input.addEventListener('focus', filterOptions);
        input.addEventListener('input', () => {
            syncValue();
            filterOptions();
        });
        input.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') setOpen(false);
        });
        options.forEach((option) => {
            option.addEventListener('click', () => {
                input.value = option.dataset.label;
                hidden.value = option.dataset.value;
                hidden.dispatchEvent(new Event('change', { bubbles: true }));
                setOpen(false);
            });
        });

        syncValue();
        document.addEventListener('click', (event) => {
            if (!input.closest('[data-autocomplete]')?.contains(event.target)) setOpen(false);
        });
    });

    document.querySelectorAll('[data-confirmed-form]').forEach(form => {
        const modal    = form.closest('.modal');
        const errBox   = form.querySelector('[data-form-errors]');
        const editArea = form.querySelector('[data-edit-fields]');
        const confArea = form.querySelector('[data-confirmation]');
        const summary  = form.querySelector('[data-summary]');
        const saveBtn  = form.querySelector('[data-save-button]');
        const backBtn  = form.querySelector('[data-back-to-edit]');
        let token      = null;

        const showError = (msg) => {
            errBox.textContent = msg;
            errBox.classList.remove('d-none');
        };
        const clearError = () => errBox.classList.add('d-none');

        const showEdit = () => {
            editArea.classList.remove('d-none');
            confArea.classList.add('d-none');
            backBtn.classList.add('d-none');
            saveBtn.textContent = 'Revisar y continuar';
            token = null;
            clearError();
        };

        const showConfirm = (data) => {
            token = data.confirmation_token;
            summary.innerHTML = '';
            Object.entries(data.summary).forEach(([k, v]) => {
                const term = document.createElement('dt');
                term.className = 'col-sm-5 text-secondary';
                term.textContent = k;
                const description = document.createElement('dd');
                description.className = 'col-sm-7 fw-semibold';
                description.textContent = v ?? '—';
                summary.append(term, description);
            });
            editArea.classList.add('d-none');
            confArea.classList.remove('d-none');
            backBtn.classList.remove('d-none');
            saveBtn.textContent = 'Confirmar y guardar';
            clearError();
        };

        backBtn?.addEventListener('click', showEdit);
        modal?.addEventListener('hidden.bs.modal', showEdit);

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            clearError();

            const invalidAutocomplete = [...form.querySelectorAll('[data-autocomplete-input]')]
                .find((input) => input.required && !document.getElementById(input.dataset.hiddenTarget)?.value);
            if (invalidAutocomplete) {
                showError('Selecciona un propietario registrado de la lista para continuar.');
                invalidAutocomplete.focus();
                return;
            }

            saveBtn.disabled = true;

            try {
                const fd = new FormData(form);
                if (!token) {
                    fd.set('_preview', '1');
                } else {
                    fd.set('confirmation_token', token);
                    fd.delete('_preview');
                }

                const res = await fetch(form.action, {
                    method: form.method || 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    body: fd,
                });

                const json = await res.json().catch(() => ({ message: 'Respuesta inesperada del servidor.' }));

                if (!res.ok) {
                    if (res.status === 422 && json.errors) {
                        showError(Object.values(json.errors).flat().join(' '));
                    } else {
                        showError(json.message || `Error ${res.status}. Inténtalo de nuevo.`);
                    }
                    return;
                }

                if (json.confirmation_token) {
                    showConfirm(json);
                } else {
                    window.location.reload();
                }
            } catch {
                showError('No se pudo conectar con el servidor. Verifica tu conexión.');
            } finally {
                saveBtn.disabled = false;
            }
        });
    });
});


document.querySelectorAll('[data-worker-fields]').forEach((container) => {
    const select = container.querySelector('[data-worker-profile]');
    const fields = container.querySelector('[data-doctor-fields]');
    const update = () => {
        const doctor = select.selectedOptions[0]?.dataset.profileCode === 'DOCTOR';
        fields.hidden = !doctor;
        fields.disabled = !doctor;
    };
    select.addEventListener('change', update);
    update();
});
document.querySelectorAll('[data-application-decision]').forEach((modal) => {
    modal.addEventListener('show.bs.modal', (event) => {
        modal.querySelector('[data-decision-action]').value = event.relatedTarget.dataset.reviewAction;
        modal.querySelectorAll('[data-decision-label]').forEach((label) => { label.textContent = event.relatedTarget.dataset.reviewLabel; });
    });
});
document.querySelectorAll('[data-reopen-modal]').forEach((modal) => bootstrap.Modal.getOrCreateInstance(modal).show());
