import assert from 'node:assert/strict';
import test from 'node:test';

import { getAvatarInitials, getDisplayName } from '../../resources/js/utils/avatar.js';

test('getDisplayName prefers full name and falls back to first/last', () => {
    assert.equal(getDisplayName({ name: 'Dr Ada Lovelace' }, 'Unknown'), 'Dr Ada Lovelace');
    assert.equal(getDisplayName({ first_name: 'Ada', last_name: 'Lovelace' }, 'Unknown'), 'Ada Lovelace');
    assert.equal(getDisplayName({}, 'Unknown'), 'Unknown');
});

test('getAvatarInitials follows canonical normalization rules', () => {
    const cases = [
        { name: 'Ana Silva', expected: 'AS' },
        { name: 'Dr. Ana Silva PhD', expected: 'AS' },
        { name: 'Jean', expected: 'JE' },
        { name: '  ', expected: 'U' },
        { name: null, expected: 'U' },
        { name: 'Mr John', expected: 'JO' },
        { name: 'Maria de Souza', expected: 'MS' },
    ];

    for (const row of cases) {
        assert.equal(getAvatarInitials(row.name, { defaultInitial: 'U' }), row.expected);
    }
});

test('getAvatarInitials supports context-specific defaults', () => {
    assert.equal(getAvatarInitials('', { defaultInitial: 'C' }), 'C');
    assert.equal(getAvatarInitials(null, { defaultInitial: '?' }), '?');
});
