<?php
// Shared Service form fields partial
// Expects: $service (optional), $categories, $providers, $linkedProviders (optional)
?>
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Name</label>
        <input type="text" name="name" value="<?= esc($service['name'] ?? '') ?>" required class="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white" />
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Duration (min)</label>
        <input type="number" name="duration_min" value="<?= (int)($service['duration_min'] ?? '') ?>" min="1" required class="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white" />
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Price</label>
        <input type="number" step="0.01" name="price" value="<?= esc($service['price'] ?? '') ?>" class="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white" />
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Category</label>
        <select name="category_id" id="categorySelect" class="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
            <option value="">Uncategorized</option>
            <?php foreach ($categories as $c): ?>
                <option value="<?= (int)$c['id'] ?>" <?= (isset($service['category_id']) && (int)$service['category_id'] === (int)$c['id']) ? 'selected' : '' ?>><?= esc($c['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="md:col-span-2">
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
        <textarea name="description" rows="3" class="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white"><?= esc($service['description'] ?? '') ?></textarea>
    </div>
    <div class="md:col-span-2">
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Providers</label>
        <div class="mt-1">
            <select multiple name="provider_ids[]" class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white min-h-[120px]">
                <?php foreach ($providers as $p): ?>
                    <option value="<?= (int)$p['id'] ?>" <?= isset($linkedProviders) && in_array((int)$p['id'], $linkedProviders, true) ? 'selected' : '' ?>><?= esc($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Only users with role = provider are listed.</p>
        </div>
    </div>
    <div class="flex items-center space-x-2 md:col-span-2">
        <input type="hidden" name="active" value="0" />
        <input id="activeCheckbox" type="checkbox" name="active" value="1" <?= !isset($service) || !array_key_exists('active', $service) || $service['active'] ? 'checked' : '' ?> class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
        <label for="activeCheckbox" class="text-sm text-gray-700 dark:text-gray-300">Active</label>
    </div>
</div>
