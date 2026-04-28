const ACTIVE_BUTTON_CLASSES = ['bg-blue-600', 'text-white', 'shadow-sm'];
const INACTIVE_BUTTON_CLASSES = ['bg-gray-100', 'text-gray-600', 'hover:bg-gray-200', 'dark:bg-gray-700', 'dark:text-gray-300', 'dark:hover:bg-gray-600'];

function setButtonState(button, isActive) {
    ACTIVE_BUTTON_CLASSES.forEach((className) => button.classList.toggle(className, isActive));
    INACTIVE_BUTTON_CLASSES.forEach((className) => button.classList.toggle(className, !isActive));
    button.setAttribute('aria-selected', isActive ? 'true' : 'false');
}

function activateProfileTab(container, target, updateHash = true) {
    const panels = Array.from(container.querySelectorAll('[data-profile-tab-panel]'));
    const buttons = Array.from(container.querySelectorAll('[data-profile-tab-button]'));
    const targetPanel = panels.find((panel) => panel.dataset.profileTabPanel === target);

    if (!targetPanel) {
        return;
    }

    container.dataset.defaultTab = target;

    panels.forEach((panel) => {
        panel.classList.toggle('hidden', panel.dataset.profileTabPanel !== target);
    });

    buttons.forEach((button) => {
        setButtonState(button, button.dataset.profileTabButton === target);
    });

    if (updateHash && window.history?.replaceState) {
        window.history.replaceState(null, '', `#${target}`);
    }
}

export function initProfilePage(root = document) {
    const containers = root.querySelectorAll('[data-profile-page]');

    containers.forEach((container) => {
        if (container.dataset.profilePageInitialized === 'true') {
            return;
        }

        container.dataset.profilePageInitialized = 'true';

        const buttons = Array.from(container.querySelectorAll('[data-profile-tab-button]'));
        const externalTriggers = Array.from(container.querySelectorAll('[data-profile-tab-trigger]'));
        const imageTrigger = container.querySelector('[data-profile-image-trigger]');
        const imageInput = container.querySelector('[data-profile-image-input]');
        const imageForm = container.querySelector('[data-profile-image-form]');
        const formsSection = buttons[0]?.closest('section') ?? null;

        buttons.forEach((button) => {
            button.addEventListener('click', () => {
                activateProfileTab(container, button.dataset.profileTabButton);
            });
        });

        externalTriggers.forEach((trigger) => {
            trigger.addEventListener('click', () => {
                activateProfileTab(container, trigger.dataset.profileTabTrigger);
                formsSection?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        });

        imageTrigger?.addEventListener('click', () => {
            imageInput?.click();
        });

        imageInput?.addEventListener('change', () => {
            if (imageInput.files?.length) {
                imageForm?.submit();
            }
        });

        const hashTab = window.location.hash ? window.location.hash.slice(1) : '';
        const defaultTab = container.dataset.defaultTab || 'profile';
        const initialTab = buttons.some((button) => button.dataset.profileTabButton === hashTab)
            ? hashTab
            : defaultTab;

        activateProfileTab(container, initialTab, false);
    });
}