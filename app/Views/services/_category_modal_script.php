<script>
(function() {
    function initServiceCategoryModal() {
        const form = document.getElementById('createServiceForm') || document.getElementById('editServiceForm');
        const categorySelect = document.getElementById('categorySelect');
        const categoryModal = document.getElementById('categoryModal');
        const openCategoryModal = document.getElementById('openCategoryModal');
        const cancelCategoryModal = document.getElementById('cancelCategoryModal');
        const createCategoryForm = document.getElementById('createCategoryForm');

        if (!form || !categoryModal || !openCategoryModal || !createCategoryForm) {
            return;
        }

        const showModal = () => {
            categoryModal.classList.remove('hidden');
            categoryModal.classList.add('flex');
            const nameInput = createCategoryForm.querySelector('input[name="name"]');
            if (nameInput) {
                window.setTimeout(() => nameInput.focus(), 0);
            }
        };

        const hideModal = () => {
            categoryModal.classList.add('hidden');
            categoryModal.classList.remove('flex');
        };

        if (openCategoryModal.dataset.modalBound !== 'true') {
            openCategoryModal.dataset.modalBound = 'true';
            openCategoryModal.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                showModal();
            });
        }

        if (cancelCategoryModal && cancelCategoryModal.dataset.modalBound !== 'true') {
            cancelCategoryModal.dataset.modalBound = 'true';
            cancelCategoryModal.addEventListener('click', (event) => {
                event.preventDefault();
                hideModal();
            });
        }

        if (categoryModal.dataset.modalBound !== 'true') {
            categoryModal.dataset.modalBound = 'true';
            categoryModal.addEventListener('click', (event) => {
                if (event.target === categoryModal) {
                    hideModal();
                }
            });
        }

        if (document.body.dataset.serviceCategoryEscapeBound !== 'true') {
            document.body.dataset.serviceCategoryEscapeBound = 'true';
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    const modal = document.getElementById('categoryModal');
                    if (modal && !modal.classList.contains('hidden')) {
                        modal.classList.add('hidden');
                        modal.classList.remove('flex');
                    }
                }
            });
        }

        if (createCategoryForm.dataset.modalBound !== 'true') {
            createCategoryForm.dataset.modalBound = 'true';
            createCategoryForm.addEventListener('submit', async (event) => {
                event.preventDefault();

                const action = createCategoryForm.action;
                const formData = new FormData(createCategoryForm);

                try {
                    const response = await fetch(action, {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                        },
                        body: formData,
                    });

                    const text = await response.text();
                    let data = null;

                    try {
                        data = JSON.parse(text);
                    } catch (error) {
                        throw new Error(text || 'Could not create category');
                    }

                    if (!response.ok || !data || !data.success) {
                        const message = data?.message || 'Could not create category';
                        throw new Error(message);
                    }

                    if (categorySelect && data.id) {
                        let option = categorySelect.querySelector(`option[value="${String(data.id)}"]`);
                        if (!option) {
                            option = document.createElement('option');
                            option.value = String(data.id);
                            option.textContent = data.name || String(formData.get('name') || 'New Category');
                            categorySelect.appendChild(option);
                        }
                        categorySelect.value = String(data.id);
                    }

                    createCategoryForm.reset();
                    const colorInput = createCategoryForm.querySelector('input[name="color"]');
                    if (colorInput) {
                        colorInput.value = '#3B82F6';
                    }
                    hideModal();
                    window.XSNotify?.toast({ type: 'success', message: data.message || 'Category created.' });
                } catch (error) {
                    window.XSNotify?.toast({ type: 'error', message: error.message || 'Could not create category' });
                }
            });
        }
    }

    if (typeof window.xsRegisterViewInit === 'function') {
        window.xsRegisterViewInit(initServiceCategoryModal);
    } else if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initServiceCategoryModal, { once: true });
    } else {
        initServiceCategoryModal();
    }
})();
</script>
