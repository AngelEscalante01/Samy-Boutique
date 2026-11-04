export function savedPhotoScopes(image) {
  const ids = image.variant_ids ?? (image.product_variant_id ? [image.product_variant_id] : [])
  return ids.length ? ids.map(id => `v_${id}`) : ['all']
}

export function togglePhotoScope(scopes, key, checked) {
  if (key === 'all') return checked ? ['all'] : []
  const next = scopes.filter(value => value !== 'all' && value !== key)
  return checked ? [...next, key] : next
}

export function photoAvailable(scopes, variants) {
  return variants.some(variant => Number(variant.stock) > 0 && (scopes.includes('all') || scopes.includes(variant.client_key)))
}
