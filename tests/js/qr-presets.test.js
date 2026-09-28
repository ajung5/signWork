import test from 'node:test';
import assert from 'node:assert/strict';
import { readPresets, writePresets, validSize, sizeLabel, units } from '../../resources/js/qr-presets.js';
const memory = () => {
    const data = new Map();
    return { getItem: key => data.get(key) ?? null, setItem: (key, value) => data.set(key, value) };
};
test('3 by 2 cm and unit conversion stay valid', () => {
    assert.equal(validSize(3 * units.cm, 2 * units.cm), true);
    assert.equal(sizeLabel(85.04, 56.69), '3 × 2 cm');
    assert.equal(validSize(71, 54), false);
    assert.equal(validSize(72, 53), false);
    assert.equal(validSize(401, 100), false);
    assert.equal(validSize(NaN, 100), false);
});
test('presets survive reload and remain scoped by user', () => {
    const storage = memory();
    const preset = {name: 'Custom 1', width: 85.04, height: 56.69};
    assert.equal(writePresets(storage, 10, [preset]), true);
    assert.deepEqual(readPresets(storage, 10), [preset]);
    assert.deepEqual(readPresets(storage, 11), []);
    assert.equal(writePresets(storage, 10, []), true);
    assert.deepEqual(readPresets(storage, 10), []);
});
test('blocked storage and corrupt data do not break the editor', () => {
    assert.deepEqual(readPresets(null, 1), []);
    assert.equal(writePresets(null, 1, []), false);
    assert.deepEqual(readPresets({getItem: () => '{bad json'}, 1), []);
    assert.deepEqual(readPresets({getItem: () => '{}'}, 1), []);
    const storage = memory();
    writePresets(storage, 1, [{name:'bad', width:0, height:5}, null]);
    assert.deepEqual(readPresets(storage, 1), []);
});
