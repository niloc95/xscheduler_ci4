<?php

namespace App\Services;

use App\Models\BusinessIntegrationModel;
use App\Models\BusinessNotificationRuleModel;
use App\Models\MessageTemplateModel;

class NotificationBusinessOptionsService
{
    public function getOptions(int $selectedBusinessId): array
    {
        return array_map(
            static fn(int $businessId): array => [
                'id' => $businessId,
                'label' => 'Business ' . $businessId,
            ],
            $this->getBusinessIds($selectedBusinessId)
        );
    }

    /**
     * @return int[]
     */
    public function getBusinessIds(int $selectedBusinessId): array
    {
        $businessIds = [$selectedBusinessId];

        foreach ($this->configurationModels() as $model) {
            $businessIds = array_merge($businessIds, $this->extractIds($model));
        }

        $businessIds = array_values(array_unique(array_filter(array_map('intval', $businessIds), static fn(int $id): bool => $id > 0)));
        sort($businessIds);

        return $businessIds === [] ? [max(1, $selectedBusinessId)] : $businessIds;
    }

    /**
     * @return array<int, object>
     */
    private function configurationModels(): array
    {
        return [
            new BusinessNotificationRuleModel(),
            new BusinessIntegrationModel(),
            new MessageTemplateModel(),
        ];
    }

    /**
     * @return int[]
     */
    private function extractIds(object $model): array
    {
        try {
            $rows = $model->builder()
                ->select('business_id')
                ->groupBy('business_id')
                ->orderBy('business_id', 'ASC')
                ->get()
                ->getResultArray();
        } catch (\Throwable $e) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn(mixed $value): int => (int) $value,
            array_column($rows, 'business_id')
        ), static fn(int $id): bool => $id > 0));
    }
}