<?= $this->extend('components/layout') ?>

<?= $this->section('sidebar') ?>
    <?= $this->include('components/unified-sidebar', ['current_page' => 'schedule']) ?>
<?= $this->endSection() ?>

<?= $this->section('header_title') ?>Schedule<?= $this->endSection() ?>

<?= $this->section('head') ?>
    <!-- Load SPA variant calendar module for custom scheduler page -->
    <script type="module" src="<?= base_url('build/assets/calendar-custom.js') ?>"></script>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="main-content" data-page-title="Schedule" data-page-subtitle="Modern calendar with filters and quick booking">
    <div class="max-w-7xl mx-auto">
        <!-- Filters -->
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 md:p-6 shadow-sm">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label for="provider" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Provider</label>
                    <select id="provider" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        <option value="">All providers</option>
                        <?php foreach (($providers ?? []) as $p): ?>
                            <option value="<?= esc($p['id']) ?>"><?= esc($p['name'] ?? ($p['email'] ?? ('Provider #'.$p['id']))) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="service" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Service</label>
                    <select id="service" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        <option value="">All services</option>
                        <?php foreach (($services ?? []) as $s): ?>
                            <option value="<?= esc($s['id']) ?>"><?= esc($s['name'] ?? ('Service #'.$s['id'])) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Date</label>
                    <input id="date" type="date" value="<?= esc(date('Y-m-d')) ?>" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100" />
                </div>
                <div class="flex items-end">
                    <button id="load" type="button" class="w-full md:w-auto inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-medium">
                        <span class="material-symbols-outlined text-base">refresh</span>
                        Load Slots
                    </button>
                </div>
            </div>
        </div>

        <!-- Slots -->
        <div class="mt-6 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 md:p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Available Slots</h2>
            <div id="slots" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3"></div>
            <div id="slots-empty" class="hidden text-sm text-gray-500 dark:text-gray-400">Use the filters above and click "Load Slots" to see availability.</div>
        </div>

        <!-- Calendar placeholder (optional; wired by global calendar module) -->
        <div class="mt-6 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 md:p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Calendar</h2>
            <div id="calendarRoot" class="min-h-[480px]"></div>
        </div>
    </div>
</div>

<script type="module">
    const el = {
        provider: document.getElementById('provider'),
        service: document.getElementById('service'),
        date: document.getElementById('date'),
        load: document.getElementById('load'),
        slots: document.getElementById('slots'),
        empty: document.getElementById('slots-empty'),
    };

    function setEmpty(msg){
        if (el.empty) {
            el.empty.textContent = msg || 'No available slots.';
            el.empty.classList.remove('hidden');
        }
    }

    function clearEmpty(){
        if (el.empty) el.empty.classList.add('hidden');
    }

    async function fetchSlots(){
        if (!el.slots) return;
        el.slots.innerHTML = '';
        clearEmpty();
        const params = new URLSearchParams();
        if (el.provider && el.provider.value) params.set('provider_id', el.provider.value);
        if (el.service && el.service.value) params.set('service_id', el.service.value);
        if (el.date && el.date.value) params.set('date', el.date.value);

        if (!params.has('provider_id') || !params.has('service_id')) {
            setEmpty('Select a provider and a service to view available slots.');
            return;
        }

        try {
            const res = await fetch('<?= base_url('api/slots') ?>?' + params.toString());
            if (!res.ok) throw new Error('Failed to load slots');
            const data = await res.json();
            const slots = Array.isArray(data.slots) ? data.slots : [];
            if (!slots.length) {
                setEmpty(`No available slots for ${data.date || el.date?.value || ''}.`);
                return;
            }
            const frag = document.createDocumentFragment();
            slots.forEach(s => {
                const card = document.createElement('div');
                card.className = 'p-3 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 flex items-center justify-between';
                const time = document.createElement('div');
                time.className = 'text-sm font-medium text-gray-900 dark:text-gray-100';
                time.textContent = `${s.start} – ${s.end}`;
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'px-3 py-1.5 rounded-md text-white bg-blue-600 hover:bg-blue-700 text-sm';
                btn.textContent = 'Book';
                btn.addEventListener('click', () => {
                    if (window.XSNotify?.show) {
                        window.XSNotify.show({ title: 'Booking (demo)', message: `Requested ${s.start} – ${s.end}.`, type: 'info', autoClose: true, duration: 1800 });
                    } else {
                        alert(`Requested ${s.start} – ${s.end}`);
                    }
                });
                card.append(time, btn);
                frag.appendChild(card);
            });
            el.slots.appendChild(frag);
        } catch (e) {
            setEmpty('Failed to load slots.');
        }
    }

    el.load?.addEventListener('click', fetchSlots);
    // Auto-run if provider and service are preselected
    if (el.provider?.value && el.service?.value) fetchSlots();
    // Re-wire after SPA navigations
    document.addEventListener('spa:navigated', () => {
        el.load?.removeEventListener?.('click', fetchSlots);
        document.getElementById('load')?.addEventListener('click', fetchSlots);
    });
</script>
<?= $this->endSection() ?>

