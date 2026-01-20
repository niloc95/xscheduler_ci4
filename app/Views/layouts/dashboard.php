<?php
/**
 * Dashboard layout consolidating common summary, actions, and content regions.
 *
 * Expected child sections:
 * - page_title (optional): Overrides page title attribute.
 * - page_subtitle (optional): Overrides page subtitle attribute.
 * - dashboard_intro (optional): Introductory block shown above stats/actions.
 * - dashboard_stats (optional): Stat cards. Wrap content only; grid handled here.
 * - dashboard_stats_class (optional): Custom CSS classes for stats grid wrapper.
 * - dashboard_actions (optional): Primary/secondary action buttons rendered to the right.
 * - dashboard_filters (optional): Filter controls rendered below stats/actions.
 * - dashboard_tabs (optional): Tab list rendered above filters.
 * - dashboard_content_top (optional): Extra block rendered before main body.
 * - dashboard_content (required): Main body content for the page.
 */
?>

<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<?php
    $pageTitleSection = trim($this->renderSection('page_title'));
    $resolvedTitle = $pageTitleSection !== '' ? $pageTitleSection : ($pageTitle ?? 'Dashboard');

    $pageSubtitleSection = trim($this->renderSection('page_subtitle'));
    $resolvedSubtitle = $pageSubtitleSection !== '' ? $pageSubtitleSection : ($pageSubtitle ?? '');

    $introContent = trim($this->renderSection('dashboard_intro'));
    $statsContent = trim($this->renderSection('dashboard_stats'));
    $statsClassSection = trim($this->renderSection('dashboard_stats_class'));
    $statsWrapperClass = $statsClassSection !== '' ? $statsClassSection : 'grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6';

    $actionsContent = trim($this->renderSection('dashboard_actions'));
    $filtersContent = trim($this->renderSection('dashboard_filters'));
    $tabsContent = trim($this->renderSection('dashboard_tabs'));
    $contentTop = trim($this->renderSection('dashboard_content_top'));
    $bodyContent = $this->renderSection('dashboard_content');
?>
<div class="main-content"
     data-page-title="<?= esc($resolvedTitle) ?>"
     <?= $resolvedSubtitle !== '' ? 'data-page-subtitle="' . esc($resolvedSubtitle) . '"' : '' ?>>
    <?php if ($introContent !== ''): ?>
    <div class="mb-6" data-dashboard-intro>
        <?= $introContent ?>
    </div>
    <?php endif; ?>

    <?php if ($statsContent !== '' || $actionsContent !== ''): ?>
    <div class="space-y-4 mb-6" data-dashboard-summary>
        <?php if ($statsContent !== ''): ?>
        <div class="<?= esc($statsWrapperClass) ?>" data-dashboard-stats>
            <?= $statsContent ?>
        </div>
        <?php endif; ?>

        <?php if ($actionsContent !== ''): ?>
        <div class="flex justify-end" data-dashboard-actions>
            <div class="flex flex-wrap gap-3">
                <?= $actionsContent ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($tabsContent !== '' || $filtersContent !== ''): ?>
    <div class="space-y-4 mb-6" data-dashboard-controls>
        <?php if ($tabsContent !== ''): ?>
        <div class="border-b border-gray-200 dark:border-gray-700" data-dashboard-tabs>
            <?= $tabsContent ?>
        </div>
        <?php endif; ?>

        <?php if ($filtersContent !== ''): ?>
        <div data-dashboard-filters>
            <?= $filtersContent ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($contentTop !== ''): ?>
    <div class="mb-6" data-dashboard-content-top>
        <?= $contentTop ?>
    </div>
    <?php endif; ?>

    <div class="space-y-6" data-dashboard-content>
        <?= $bodyContent ?>
    </div>
</div>
<?= $this->endSection() ?>
