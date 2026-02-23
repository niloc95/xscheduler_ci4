<?php
// Shared Service form fields partial
// Expects: $service (optional, defaults to empty array), $categories, $providers, $linkedProviders (optional)
$service = $service ?? [];
$linkedProviders = $linkedProviders ?? [];
?>
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <label class="form-label">Name</label>
        <input type="text" name="name" value="<?= esc($service['name'] ?? '') ?>" required class="form-input" />
    </div>
    <div>
        <label class="form-label">Duration (min)</label>
        <input type="number" name="duration_min" value="<?= (int)($service['duration_min'] ?? 0) ?>" min="1" required class="form-input" />
    </div>
    <div>
        <label class="form-label">Price</label>
        <div class="mt-1 relative rounded-lg shadow-sm">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <span class="text-gray-500 dark:text-gray-400 sm:text-sm">
                    <?php helper('currency'); echo get_app_currency_symbol(); ?>
                </span>
            </div>
            <input type="number" step="0.01" name="price" value="<?= esc($service['price'] ?? '') ?>" 
                   class="form-input pl-8" 
                   placeholder="0.00" />
        </div>
    </div>
    <div>
        <label class="form-label">Category</label>
        <select name="category_id" id="categorySelect" class="form-select">
            <option value="">Uncategorized</option>
            <?php foreach ($categories as $c): ?>
                <option value="<?= (int)$c['id'] ?>" <?= (isset($service['category_id']) && (int)$service['category_id'] === (int)$c['id']) ? 'selected' : '' ?>><?= esc($c['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="md:col-span-2">
        <label class="form-label">Description</label>
        <textarea name="description" rows="3" class="form-input"><?= esc($service['description'] ?? '') ?></textarea>
    </div>
    <div class="md:col-span-2">
        <label class="form-label">Providers</label>
        <div class="mt-1">
            <select multiple name="provider_ids[]" class="form-select min-h-[120px]">
                <?php foreach ($providers as $p): ?>
                    <option value="<?= (int)$p['id'] ?>" <?= in_array((int)$p['id'], $linkedProviders, true) ? 'selected' : '' ?>><?= esc($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Only users with role = provider are listed.</p>
        </div>
    </div>
    <div class="flex items-center space-x-2 md:col-span-2">
        <input type="hidden" name="active" value="0" />
        <input id="activeCheckbox" type="checkbox" name="active" value="1" <?= empty($service) || !array_key_exists('active', $service) || $service['active'] ? 'checked' : '' ?> class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
        <label for="activeCheckbox" class="text-sm text-gray-700 dark:text-gray-300">Active</label>
    </div>
</div>
