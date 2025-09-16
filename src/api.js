const API_BASE = 'http://localhost/emmaapi/v1/api.php';

function buildUrl(endpoint, params = {}) {
  const url = new URL(API_BASE);
  url.searchParams.set('endpoint', endpoint.replace(/^\/+|\/+$/g, ''));
  for (const [k, v] of Object.entries(params)) {
    url.searchParams.append(k, String(v));
  }
  return url.toString();
}

export async function getProducts(params = {}) {
  const res = await fetch(buildUrl('products', params));
  if (!res.ok) throw new Error('Failed to fetch products');
  return res.json();
}

export async function getCategories() {
  const res = await fetch(buildUrl('categories'));
  if (!res.ok) throw new Error('Failed to fetch categories');
  return res.json();
}

export async function getReviews(productId) {
  const res = await fetch(buildUrl('reviews', { productId }));
  if (!res.ok) throw new Error('Failed to fetch reviews');
  return res.json();
}

export async function createReview(review) {
  const res = await fetch(buildUrl('reviews'), {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(review),
  });
  if (!res.ok) throw new Error('Failed to create review');
  return res.json();
}

export async function getOrders() {
  const res = await fetch(buildUrl('orders'));
  if (!res.ok) throw new Error('Failed to fetch orders');
  return res.json();
}

