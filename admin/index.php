<?php
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html>
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Emma API Admin</title>
    <style>
      body { font-family: Arial, sans-serif; margin: 2rem; }
      code { background: #f4f4f4; padding: 0.15rem 0.35rem; border-radius: 4px; }
    </style>
  </head>
  <body>
    <h1>Emma API</h1>
    <p>API is running. Test endpoints from your Next.js app or directly via URLs like:</p>
    <ul>
      <li><code>http://localhost/emmaapi/v1/api.php?endpoint=products</code></li>
      <li><code>http://localhost/emmaapi/v1/api.php?endpoint=categories</code></li>
      <li><code>http://localhost/emmaapi/v1/api.php?endpoint=orders</code></li>
      <li><code>http://localhost/emmaapi/v1/api.php?endpoint=reviews&amp;productId=1</code></li>
      <li><code>POST http://localhost/emmaapi/v1/api.php?endpoint=auth</code> with JSON body <code>{"email":"admin@example.com","password":"password"}</code></li>
    </ul>
    <script type="module">
      import { getProducts, getCategories, getReviews } from '../src/api.js';
      async function demo() {
        try {
          const [products, categories, reviews] = await Promise.all([
            getProducts(),
            getCategories(),
            getReviews(1),
          ]);
          console.log('Products', products);
          console.log('Categories', categories);
          console.log('Reviews (product 1)', reviews);
        } catch (e) {
          console.error(e);
        }
      }
      demo();
    </script>
  </body>
</html>

