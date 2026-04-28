<?php
// Shared Service form fields partial
// Expects: $service (optional, defaults to empty array), $categories, $providers, $linkedProviders (optional)
$service = $service ?? [];
$linkedProviders = $linkedProviders ?? [];

$oldProviderIds = old('provider_ids');
if ($oldProviderIds === null) {
    $oldProviderIds = old('provider_ids[]');
}

$selectedProviderIds = $oldProviderIds !== null ? (array) $oldProviderIds : (array) $linkedProviders;
$selectedProviderIds = array_values(array_unique(array_map('intval', array_filter($selectedProviderIds, static fn($id) => $id !== null && $id !== ''))));

$serviceName = old('name', $service['name'] ?? '');
$serviceDuration = old('duration_min', $service['duration_min'] ?? '');
$servicePrice = old('price', $service['price'] ?? '');
$serviceCategoryId = old('category_id', $service['category_id'] ?? '');
$serviceDescription = old('description', $service['description'] ?? '');
$serviceSlug = old('slug', $service['slug'] ?? '');

$incomingUnlock = old('unlock_slug');
$canUnlockSlug = isset($canUnlockSlug) ? (bool) $canUnlockSlug : false;
$slugLocked = isset($slugLocked) ? (bool) $slugLocked : (!empty($service) && trim((string) ($service['slug'] ?? '')) !== '');
$unlockRequested = $canUnlockSlug && ((string) $incomingUnlock === '1');
$slugReadOnly = $slugLocked && !$unlockRequested;

$oldActive = old('active');
if ($oldActive !== null) {
    $serviceIsActive = (string) $oldActive === '1';
} else {
    $serviceIsActive = empty($service) || !array_key_exists('active', $service) || (bool) $service['active'];
}
?>
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <label class="form-label">Name</label>
        <input type="text" name="name" value="<?= esc($serviceName) ?>" required class="form-input" />
    </div>
    <div>
        <label class="form-label">Duration (min)</label>
        <input type="number" name="duration_min" value="<?= esc((string) $serviceDuration) ?>" min="1" required class="form-input" />
    </div>
    <div>
        <label class="form-label">Slug</label>
        <input type="text"
               name="slug"
               value="<?= esc((string) $serviceSlug) ?>"
               pattern="[a-z0-9-]+"
               placeholder="e.g. haircut-deluxe"
               class="form-input"
               <?= $slugReadOnly ? 'readonly data-slug-input="locked"' : 'data-slug-input="editable"' ?> />
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
            Lowercase letters, numbers, and hyphens only.
        </p>
        <?php if ($slugLocked && !$canUnlockSlug): ?>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Slug is locked after publish.
            </p>
        <?php endif; ?>
        <?php if ($slugLocked && $canUnlockSlug): ?>
            <label class="mt-2 inline-flex items-center gap-2 text-xs text-gray-700 dark:text-gray-300">
                <input type="checkbox"
                       name="unlock_slug"
                       value="1"
                       data-slug-unlock
                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                       <?= $unlockRequested ? 'checked' : '' ?> />
                Unlock slug editing for this update (admin only)
            </label>
        <?php endif; ?>
    </div>
    <div>
        <label class="form-label">Price</label>
        <div class="mt-1 relative rounded-lg shadow-sm">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <span class="text-gray-500 dark:text-gray-400 sm:text-sm">
                    <?php helper('currency'); echo get_app_currency_symbol(); ?>
                </span>
            </div>
            <input type="number" step="0.01" name="price" value="<?= esc((string) $servicePrice) ?>" 
                   class="form-input pl-8" 
                   placeholder="0.00" />
        </div>
    </div>
    <div>
        <label class="form-label">Category</label>
        <select name="category_id" id="categorySelect" class="form-select">
            <option value="">Uncategorized</option>
            <?php foreach ($categories as $c): ?>
                <option value="<?= (int)$c['id'] ?>" <?= (string) $serviceCategoryId === (string) ((int) $c['id']) ? 'selected' : '' ?>><?= esc($c['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="md:col-span-2">
        <label class="form-label">Description</label>
        <textarea name="description" rows="3" class="form-input"><?= esc($serviceDescription) ?></textarea>
    </div>
    <fieldset class="md:col-span-2" data-provider-picker>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <legend class="form-label mb-0">Assigned Providers</legend>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Tick one or more providers for this service.</p>
            </div>
            <div class="flex items-center gap-2">
                <button type="button"
                        class="rounded-lg border border-gray-300 dark:border-gray-600 px-3 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                        data-provider-picker-action="select-all">
                    Select All
                </button>
                <button type="button"
                        class="rounded-lg border border-gray-300 dark:border-gray-600 px-3 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                        data-provider-picker-action="clear-all">
                    Clear All
                </button>
            </div>
        </div>

        <div class="mt-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800/50 overflow-hidden">
            <?php if (!empty($providers)): ?>
                <div class="max-h-72 overflow-y-auto p-3 grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <?php foreach ($providers as $p): ?>
                        <?php $providerId = (int) ($p['id'] ?? 0); ?>
                        <?php $providerName = (string) ($p['name'] ?? 'Provider'); ?>
                        <?php $isChecked = in_array($providerId, $selectedProviderIds, true); ?>
                        <label class="flex items-start gap-3 rounded-xl border px-3 py-3 cursor-pointer transition-colors <?= $isChecked ? 'border-primary-300 bg-primary-50/70 dark:border-primary-700 dark:bg-primary-900/20' : 'border-gray-200 dark:border-gray-700 hover:border-primary-200 dark:hover:border-primary-700 hover:bg-gray-50 dark:hover:bg-gray-800' ?>">
                            <input type="checkbox"
                                   name="provider_ids[]"
                                   value="<?= $providerId ?>"
                                   class="mt-0.5 h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                                   <?= $isChecked ? 'checked' : '' ?> />
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-semibold text-gray-900 dark:text-gray-100 truncate"><?= esc($providerName) ?></span>
                                  <span class="block text-xs text-gray-500 dark:text-gray-400"><?= esc($p['email'] ?? '') ?></span>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="p-4 text-sm text-gray-500 dark:text-gray-400">
                    No active providers available yet.
                </div>
            <?php endif; ?>
        </div>

        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
            <span data-provider-selection-count><?= count($selectedProviderIds) ?></span> provider(s) selected.
        </p>
    </fieldset>
    <div class="flex items-center space-x-2 md:col-span-2">
        <input type="hidden" name="active" value="0" />
        <input id="activeCheckbox" type="checkbox" name="active" value="1" <?= $serviceIsActive ? 'checked' : '' ?> class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
        <label for="activeCheckbox" class="text-sm text-gray-700 dark:text-gray-300">Active</label>
    </div>
</div>

<?php if ($slugLocked && $canUnlockSlug): ?>
<script>
    (function () {
        const unlock = document.querySelector('[data-slug-unlock]');
        const slugInput = document.querySelector('input[name="slug"]');
        if (!unlock || !slugInput) {
            return;
        }

        const refresh = function () {
            slugInput.readOnly = !unlock.checked;
        };

        unlock.addEventListener('change', refresh);
        refresh();
    })();
</script>
<?php endif; ?>
