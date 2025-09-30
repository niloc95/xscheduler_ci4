<?php
// Shared Services Create/Edit view
// Expects: $action_url (string), $data (array), $categories (array), $providers (array), optional $linkedProviders (array)
?>
<?= $this->extend('layouts/dashboard') ?>

<?= $this->section('sidebar') ?>
	<?= $this->include('components/unified-sidebar', ['current_page' => 'services']) ?>
<?= $this->endSection() ?>

<?php
	$isEdit = !empty($data) && isset($data['id']);
	$title = $isEdit ? 'Edit Service' : 'Create Service';
	$subtitle = $isEdit ? 'Update service details' : 'Add a new service offering';
?>

<?= $this->section('page_title') ?><?= esc($title) ?><?= $this->endSection() ?>
<?= $this->section('page_subtitle') ?><?= esc($subtitle) ?><?= $this->endSection() ?>

<?= $this->section('dashboard_content_top') ?>
	<a href="<?= base_url('/services') ?>" class="inline-flex items-center text-sm text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white">
		<span class="material-symbols-outlined mr-1">arrow_back</span>
		Back to Services
	</a>
<?= $this->endSection() ?>

<?= $this->section('dashboard_content') ?>
	<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
		<div class="lg:col-span-2">
			<form action="<?= esc($action_url) ?>" method="post" class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
				<?= csrf_field() ?>
				<?php if ($isEdit): ?>
					<input type="hidden" name="service_id" value="<?= (int)$data['id'] ?>">
				<?php endif; ?>

				<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
					<div class="mb-4">
						<label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Name</label>
						<input type="text" name="name" value="<?= esc($data['name'] ?? '') ?>" required class="mt-1 block w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white" />
					</div>
					<div class="mb-4">
						<label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Duration (min)</label>
						<input type="number" name="duration_min" value="<?= (int)($data['duration_min'] ?? 0) ?>" min="1" required class="mt-1 block w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white" />
					</div>
					<div class="mb-4">
						<label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Price</label>
						<input type="number" step="0.01" name="price" value="<?= esc($data['price'] ?? '') ?>" class="mt-1 block w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white" />
					</div>
					<div class="mb-4">
						<label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Category</label>
						<select name="category_id" id="categorySelect" class="mt-1 block w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
							<option value="">Uncategorized</option>
							<?php foreach ($categories as $c): ?>
								<option value="<?= (int)$c['id'] ?>" <?= (isset($data['category_id']) && (int)$data['category_id'] === (int)$c['id']) ? 'selected' : '' ?>><?= esc($c['name']) ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="md:col-span-2 mb-4">
						<label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
						<textarea name="description" rows="3" class="mt-1 block w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white"><?= esc($data['description'] ?? '') ?></textarea>
					</div>
					<div class="md:col-span-2 mb-4">
						<label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Providers</label>
						<select multiple name="provider_ids[]" class="mt-1 block w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white min-h-[120px]">
							<?php $selectedProviders = $linkedProviders ?? []; ?>
							<?php foreach ($providers as $p): ?>
								<option value="<?= (int)$p['id'] ?>" <?= in_array((int)$p['id'], $selectedProviders ?? [], true) ? 'selected' : '' ?>><?= esc($p['name']) ?></option>
							<?php endforeach; ?>
						</select>
						<p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Only users with role = provider are listed.</p>
					</div>
					<div class="flex items-center space-x-2 md:col-span-2 mb-2">
						<input type="hidden" name="active" value="0" />
						<input id="activeCheckbox" type="checkbox" name="active" value="1" <?= !isset($data['active']) || $data['active'] ? 'checked' : '' ?> class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
						<label for="activeCheckbox" class="text-sm text-gray-700 dark:text-gray-300">Active</label>
					</div>
				</div>

				<div class="mt-6 flex justify-end gap-3">
					<a href="<?= base_url('/services') ?>" class="px-4 py-2 bg-white text-gray-700 border border-gray-300 rounded-md hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600">Cancel</a>
					<button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
						<span class="material-symbols-outlined mr-2">save</span>
						<?= $isEdit ? 'Save Changes' : 'Save Service' ?>
					</button>
				</div>
			</form>
		</div>

		<div class="lg:col-span-1">
			<div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
				<h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Tips</h3>
				<ul class="list-disc text-sm text-gray-600 dark:text-gray-300 ml-5 space-y-1">
					<li>Providers list only shows users with role = provider.</li>
					<li>You can manage categories on the Categories page.</li>
				</ul>
			</div>
		</div>
	</div>
<?= $this->endSection() ?>

