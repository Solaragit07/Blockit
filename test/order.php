<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Start an Order • EMC-2 Construction Supply</title>
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet" />
<style>
  :root{
    --brand:#d63737;
    --brand-deep:#7a1919;
    --gold:#d4ad36;
    --ink:#0f172a;
    --muted:#64748b;
    --card:#ffffff;
    --line:#e5e7eb;
  }
  *{box-sizing:border-box}
  html{scroll-behavior:smooth}
  body{
    margin:0;
    font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,"Helvetica Neue",Arial,"Noto Sans",sans-serif;
    color:var(--ink);
    background:#fff;
  }

  header{
    position:sticky;top:0;z-index:30;
    background:#ffffffee;backdrop-filter:saturate(160%) blur(8px);
    border-bottom:1px solid var(--line);
  }
  .container{max-width:1200px;margin:0 auto;padding:0 20px}
  .nav{display:flex;align-items:center;justify-content:space-between;padding:14px 0}
  .brand{display:flex;align-items:center;gap:12px;font-weight:900;text-decoration:none;color:var(--ink)}
  .dot{height:28px;width:28px;border-radius:8px;background:radial-gradient(120% 120% at 0% 0%, #fda4af 0%, var(--brand) 55%, var(--brand-deep) 100%)}
  .back{font-weight:700;text-decoration:none;color:var(--brand-deep);border:1px solid var(--line);border-radius:10px;padding:8px 12px;background:#fff}
  .back:hover{background:#fff5f5}

  .hero-wrap{background:linear-gradient(135deg,var(--brand) 0%,var(--brand-deep) 60%);color:#fff}
  .hero{padding:36px 0}
  .hero h1{margin:0;font-size:clamp(26px,4.5vw,42px)}
  .hero p{margin:.25rem 0 0;opacity:.95}
  .gold{color:var(--gold)}

  .toolbar{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:16px 0;border-bottom:1px solid var(--line)}
  .crumb{font-size:14px;color:#94a3b8}
  .count{font-weight:600}
  .sort{display:flex;align-items:center;gap:8px}
  select{appearance:none;border:1px solid var(--line);border-radius:10px;padding:10px 12px;background:#fff;font-weight:600}

  /* MAIN LAYOUT: SIDEBAR LEFT */
  main{display:grid;grid-template-columns:320px 1fr;gap:20px;padding:20px 0}

  /* SIDEBAR LEFT */
  aside{position:sticky;top:92px;align-self:start}
  .panel{background:#fff;border:1px solid var(--line);border-radius:16px;overflow:hidden;margin-bottom:14px}
  .panel h3{margin:0;padding:14px 14px;border-bottom:1px solid var(--line);font-size:16px}
  .panel .body{padding:14px}
  .cats a{display:block;padding:10px 10px;border-radius:10px;text-decoration:none;color:var(--ink);font-weight:700;border:1px solid transparent}
  .cats a + a{margin-top:6px}
  .cats a:hover{border-color:#f1d38a;background:#fffaf0}
  .check{display:flex;align-items:center;gap:10px;margin:8px 0}
  .range{margin:10px 0 0}
  .range .vals{display:flex;gap:8px;margin-top:8px}
  .range input[type="number"]{width:100%;border:1px solid var(--line);border-radius:10px;padding:8px 10px}
  .apply{margin-top:12px;width:100%;border:1px solid var(--line);border-radius:10px;padding:10px 12px;font-weight:800;background:#fff}
  .apply:hover{background:#fff5f5}
  .muted{color:var(--muted)}

  /* PRODUCT GRID (RIGHT SIDE) */
  .grid{display:grid;grid-template-columns:repeat(12,1fr);gap:16px}
  .product{grid-column:span 4;background:var(--card);border:1px solid #ececec;border-radius:16px;overflow:hidden;box-shadow:0 6px 16px rgba(0,0,0,.05)}
  .product img{width:100%;height:190px;object-fit:cover;display:block}
  .pbody{padding:14px}
  .ptitle{font-weight:800;margin:0 0 6px}
  .pmeta{font-size:12px;color:var(--muted);display:flex;align-items:center;gap:6px}
  .price{margin-top:10px;font-weight:900}
  .price .old{color:#9ca3af;text-decoration:line-through;font-weight:600;margin-left:8px}

  /* Responsive */
  @media (max-width: 1024px){ .product{grid-column:span 6} }
  @media (max-width: 820px){
    main{grid-template-columns:1fr}
    aside{position:static}
    .product{grid-column:span 6}
  }
  @media (max-width: 540px){ .product{grid-column:span 12} }
</style>
</head>
<body>

<header>
  <div class="container nav">
    <a class="brand" href="index.html">
      <span class="dot"></span>
      <span>EMC-2 Construction Supply</span>
    </a>
    <a class="back" href="index.php">← Back to Home</a>
  </div>
</header>

<div class="hero-wrap">
  <div class="container hero">
    <h1>Start an Order <span class="gold">— Select Products</span></h1>
    <p>Filter by category, availability, and price. Sort results the way you like.</p>
  </div>
</div>

<div class="container">
  <div class="toolbar">
    <div>
      <div class="crumb">Home › All</div>
      <div class="count">19 products</div>
    </div>
    <div class="sort">
      <label for="sort">Sort by:</label>
      <select id="sort">
        <option>Best selling</option>
        <option>Featured</option>
        <option>Alphabetically, A–Z</option>
        <option>Alphabetically, Z–A</option>
        <option>Price, low to high</option>
        <option>Price, high to low</option>
        <option>Date, new to old</option>
        <option>Date, old to new</option>
      </select>
    </div>
  </div>

  <main>
    <!-- LEFT SIDEBAR -->
    <aside>
      <div class="panel">
        <h3>Categories</h3>
        <div class="body cats">
          <a href="#">Hardware</a>
          <a href="#">Metal</a>
          <a href="#">Paint</a>
          <a href="#">Wood</a>
          <a href="#">Chemical</a>
          <a href="#">PVC</a>
          <a href="#">Roofing</a>
          <a href="#">Door</a>
          <a href="#">Water</a>
          <a href="#">Gravel • Sand • Tiles</a>
        </div>
      </div>

      <div class="panel">
        <h3>Availability</h3>
        <div class="body">
          <label class="check"><input type="checkbox" /> In stock</label>
          <label class="check"><input type="checkbox" /> Out of stock</label>
        </div>
      </div>

      <div class="panel">
        <h3>Price</h3>
        <div class="body range">
          <input type="range" min="0" max="5000" step="10" value="0" />
          <input type="range" min="0" max="5000" step="10" value="2500" />
          <div class="vals">
            <input type="number" min="0" max="5000" value="0" />
            <input type="number" min="0" max="5000" value="2500" />
          </div>
          <button class="apply">Apply</button>
          <!-- <p class="muted">*Static UI only; link to data later.</p> -->
        </div>
      </div>
    </aside>

    <!-- RIGHT PRODUCT GRID -->
    <section>
      <div class="grid">
        <article class="product">
          <img src="https://images.unsplash.com/photo-1503602642458-232111445657?q=80&w=1200&auto=format&fit=crop">
          <div class="pbody">
            <h4 class="ptitle">Premium Set</h4>
            <div class="pmeta">⭐ 4.8 · 377 reviews</div>
            <div class="price">₱1,295.00 <span class="old">₱1,900.00</span></div>
          </div>
        </article>

        <article class="product">
          <img src="https://images.unsplash.com/photo-1516383607781-913a19294fd1?q=80&w=1200&auto=format&fit=crop">
          <div class="pbody">
            <h4 class="ptitle">Body & Face Soap</h4>
            <div class="pmeta">⭐ 4.7 · 60 reviews</div>
            <div class="price">₱276.00 <span class="old">₱545.00</span></div>
          </div>
        </article>

        <article class="product">
          <img src="https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?q=80&w=1200&auto=format&fit=crop">
          <div class="pbody">
            <h4 class="ptitle">Niacinamide Serum</h4>
            <div class="pmeta">⭐ 4.9 · 23 reviews</div>
            <div class="price">₱599.00 <span class="old">₱1,099.00</span></div>
          </div>
        </article>
      </div>
    </section>
  </main>
</div>

<script>
  // Mobile filter toggle
  const toggle = document.getElementById('filterToggle');
  const sidebar = document.getElementById('sidebar');
  if (toggle) toggle.addEventListener('click', () => {
    sidebar.style.display = (getComputedStyle(sidebar).display === 'none') ? 'block' : 'none';
  });

  // Price range <-> number fields sync (UI only)
  const minRange = document.getElementById('minRange');
  const maxRange = document.getElementById('maxRange');
  const minVal   = document.getElementById('minVal');
  const maxVal   = document.getElementById('maxVal');
  const clamp = () => {
    const a = Math.min(+minRange.value, +maxRange.value);
    const b = Math.max(+minRange.value, +maxRange.value);
    minRange.value = a; maxRange.value = b; minVal.value = a; maxVal.value = b;
  };
  [minRange,maxRange].forEach(el => el.addEventListener('input', clamp));
  [minVal,maxVal].forEach(el => el.addEventListener('change', () => {
    minRange.value = Math.max(0, Math.min(+minVal.value, +maxVal.value));
    maxRange.value = Math.max(+minVal.value, +maxVal.value);
    clamp();
  }));
  document.getElementById('applyBtn').addEventListener('click', () => {
    alert(`Apply price: ₱${minVal.value} – ₱${maxVal.value}\n(Connect to your real filtering later.)`);
  });
</script>
</body>
</html>
