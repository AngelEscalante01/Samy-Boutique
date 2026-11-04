import test from 'node:test'
import assert from 'node:assert/strict'
import { savedPhotoScopes, togglePhotoScope, photoAvailable } from '../../resources/js/Components/Products/photo-scopes.js'

test('legacy, general and shared saved photos retain their associations', () => {
  assert.deepEqual(savedPhotoScopes({ product_variant_id: 7 }), ['v_7'])
  assert.deepEqual(savedPhotoScopes({ product_variant_id: 7, variant_ids: [] }), ['all'])
  assert.deepEqual(savedPhotoScopes({ product_variant_id: 7, variant_ids: [7, 8] }), ['v_7', 'v_8'])
})
test('a shared photo stays visible until its last associated variant is unavailable', () => {
  const variants = [{ client_key: 'v_7', stock: 0 }, { client_key: 'v_8', stock: 1 }]
  assert.equal(photoAvailable(['v_7'], variants), false)
  assert.equal(photoAvailable(['v_7', 'v_8'], variants), true)
  assert.equal(photoAvailable(['all'], variants), true)
  assert.equal(photoAvailable(['v_archived'], variants), false)
  variants[1].stock = 0
  assert.equal(photoAvailable(['v_7', 'v_8'], variants), false)
  assert.equal(photoAvailable(['all'], variants), false)
})
test('choosing a variant replaces general scope and clearing the last choice requires an explicit new scope', () => {
  const shared = togglePhotoScope(togglePhotoScope(['all'], 'n_red', true), 'n_blue', true)
  assert.deepEqual(shared, ['n_red', 'n_blue'])
  assert.deepEqual(togglePhotoScope(shared, 'n_red', false), ['n_blue'])
  assert.deepEqual(togglePhotoScope(['n_red'], 'n_red', false), [])
  assert.deepEqual(togglePhotoScope(shared, 'all', true), ['all'])
})
