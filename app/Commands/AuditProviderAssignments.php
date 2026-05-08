<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class AuditProviderAssignments extends BaseCommand
{
    protected $group = 'audit';

    protected $name = 'audit:provider-assignments';

    protected $description = 'Audit provider-service and provider-location assignments used by dashboard cards.';

    protected $usage = 'audit:provider-assignments [serviceNameFilter]';

    protected $arguments = [
        'serviceNameFilter' => 'Optional case-insensitive service name filter for focused checks.',
    ];

    /**
     * @param array<int, string> $params
     */
    public function run(array $params)
    {
        $serviceNameFilter = trim((string) ($params[0] ?? ''));

        $db = \Config\Database::connect();
        $usersTable = $db->prefixTable('users');
        $userRolesTable = $db->prefixTable('user_roles');
        $providerServicesTable = $db->prefixTable('providers_services');
        $servicesTable = $db->prefixTable('services');
        $locationsTable = $db->prefixTable('locations');

        CLI::newLine();
        CLI::write('Provider Assignment Integrity Audit', 'yellow');
        CLI::write('===================================', 'yellow');

        // Provider roster (authoritative role membership)
        $providers = $db->table($usersTable . ' u')
            ->select('u.id, u.name')
            ->join($userRolesTable . ' ur', 'ur.user_id = u.id', 'inner')
            ->where('ur.role', 'provider')
            ->orderBy('u.name', 'ASC')
            ->get()
            ->getResultArray();

        if (empty($providers)) {
            CLI::write('No providers found via xs_user_roles.', 'red');
            CLI::newLine();
            return;
        }

        $providerIds = array_map(static fn(array $row): int => (int) $row['id'], $providers);

        // Provider -> services map
        $providerServiceRows = $db->table($providerServicesTable . ' ps')
            ->select('ps.provider_id, s.id as service_id, s.name as service_name, s.active')
            ->join($servicesTable . ' s', 's.id = ps.service_id', 'inner')
            ->whereIn('ps.provider_id', $providerIds)
            ->orderBy('ps.provider_id', 'ASC')
            ->orderBy('s.name', 'ASC')
            ->get()
            ->getResultArray();

        // Provider -> locations map
        $providerLocationRows = [];
        $locationsTableExists = method_exists($db, 'tableExists') ? ($db->tableExists('locations') || $db->tableExists($locationsTable)) : true;
        if ($locationsTableExists) {
            $providerLocationRows = $db->table($locationsTable)
                ->select('provider_id, id as location_id, name as location_name, is_active')
                ->whereIn('provider_id', $providerIds)
                ->orderBy('provider_id', 'ASC')
                ->orderBy('name', 'ASC')
                ->get()
                ->getResultArray();
        }

        $servicesByProvider = [];
        foreach ($providerServiceRows as $row) {
            $providerId = (int) $row['provider_id'];
            $servicesByProvider[$providerId][] = [
                'id' => (int) $row['service_id'],
                'name' => (string) $row['service_name'],
                'active' => (int) ($row['active'] ?? 1) === 1,
            ];
        }

        $locationsByProvider = [];
        foreach ($providerLocationRows as $row) {
            $providerId = (int) $row['provider_id'];
            $locationsByProvider[$providerId][] = [
                'id' => (int) $row['location_id'],
                'name' => (string) $row['location_name'],
                'active' => (int) ($row['is_active'] ?? 1) === 1,
            ];
        }

        CLI::newLine();
        CLI::write('Provider -> Services / Locations', 'cyan');

        $providersMissingServices = [];
        $providersMissingLocations = [];

        foreach ($providers as $provider) {
            $providerId = (int) $provider['id'];
            $providerName = (string) $provider['name'];

            $providerServices = $servicesByProvider[$providerId] ?? [];
            $providerLocations = $locationsByProvider[$providerId] ?? [];

            if (empty($providerServices)) {
                $providersMissingServices[] = sprintf('%d:%s', $providerId, $providerName);
            }

            if ($locationsTableExists && empty($providerLocations)) {
                $providersMissingLocations[] = sprintf('%d:%s', $providerId, $providerName);
            }

            $serviceNames = array_map(static fn(array $s): string => $s['name'] . ($s['active'] ? '' : ' [inactive]'), $providerServices);
            $locationNames = array_map(static fn(array $l): string => $l['name'] . ($l['active'] ? '' : ' [inactive]'), $providerLocations);

            CLI::write(sprintf(
                '- #%d %s | services=%d | locations=%d',
                $providerId,
                $providerName,
                count($providerServices),
                count($providerLocations)
            ));

            CLI::write('  services: ' . (empty($serviceNames) ? '(none)' : implode(', ', $serviceNames)), 'light_gray');
            CLI::write('  locations: ' . (empty($locationNames) ? '(none)' : implode(', ', $locationNames)), 'light_gray');
        }

        // Service -> providers summary
        $serviceProviderRows = $db->table($servicesTable . ' s')
            ->select('s.id as service_id, s.name as service_name, s.active, ps.provider_id, u.name as provider_name')
            ->join($providerServicesTable . ' ps', 'ps.service_id = s.id', 'left')
            ->join($usersTable . ' u', 'u.id = ps.provider_id', 'left')
            ->orderBy('s.name', 'ASC')
            ->get()
            ->getResultArray();

        $providersByService = [];
        foreach ($serviceProviderRows as $row) {
            $serviceId = (int) $row['service_id'];
            if (!isset($providersByService[$serviceId])) {
                $providersByService[$serviceId] = [
                    'name' => (string) $row['service_name'],
                    'active' => (int) ($row['active'] ?? 1) === 1,
                    'providers' => [],
                ];
            }

            $providerId = $row['provider_id'] !== null ? (int) $row['provider_id'] : null;
            if ($providerId !== null && $providerId > 0) {
                $providersByService[$serviceId]['providers'][] = [
                    'id' => $providerId,
                    'name' => (string) ($row['provider_name'] ?? ('Provider ' . $providerId)),
                ];
            }
        }

        CLI::newLine();
        CLI::write('Service -> Providers', 'cyan');

        $servicesWithoutProviders = [];
        $filteredHitCount = 0;

        foreach ($providersByService as $serviceId => $service) {
            $serviceName = $service['name'];
            if ($serviceNameFilter !== '' && stripos($serviceName, $serviceNameFilter) === false) {
                continue;
            }

            $filteredHitCount++;
            $providerList = $service['providers'];
            if (empty($providerList)) {
                $servicesWithoutProviders[] = sprintf('%d:%s', $serviceId, $serviceName);
            }

            $providerText = empty($providerList)
                ? '(none)'
                : implode(', ', array_map(static fn(array $p): string => sprintf('#%d %s', $p['id'], $p['name']), $providerList));

            CLI::write(sprintf(
                '- #%d %s%s -> %s',
                $serviceId,
                $serviceName,
                $service['active'] ? '' : ' [inactive]',
                $providerText
            ));
        }

        if ($serviceNameFilter !== '' && $filteredHitCount === 0) {
            CLI::newLine();
            CLI::write('No services matched filter: ' . $serviceNameFilter, 'yellow');
        }

        CLI::newLine();
        CLI::write('Findings', 'yellow');
        CLI::write('--------', 'yellow');
        CLI::write('Providers missing services: ' . (empty($providersMissingServices) ? '0' : count($providersMissingServices)));
        if (!empty($providersMissingServices)) {
            CLI::write('  ' . implode('; ', $providersMissingServices), 'red');
        }

        if ($locationsTableExists) {
            CLI::write('Providers missing locations: ' . (empty($providersMissingLocations) ? '0' : count($providersMissingLocations)));
            if (!empty($providersMissingLocations)) {
                CLI::write('  ' . implode('; ', $providersMissingLocations), 'red');
            }
        }

        CLI::write('Services without providers: ' . (empty($servicesWithoutProviders) ? '0' : count($servicesWithoutProviders)));
        if (!empty($servicesWithoutProviders)) {
            CLI::write('  ' . implode('; ', $servicesWithoutProviders), 'red');
        }

        CLI::newLine();
        CLI::write('Done.', 'green');
    }
}
