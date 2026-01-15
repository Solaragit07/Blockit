<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>EMC-2 Construction Supply • Build Now, Pay Later</title>

  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet" />

  <style>
    :root{
      --brand: #d63737;        /* primary red */
      --brand-deep: #7a1919;   /* deep red */
      --gold: #d4ad36;         /* accent gold */
      --ink: #111827;          /* text */
      --muted: #6b7280;        /* secondary text */
      --card: #ffffff;         /* surfaces */
    }

    *{ box-sizing: border-box; }
    html{ scroll-behavior: smooth; }
    body{
      margin: 0;
      font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, "Helvetica Neue", Arial, "Noto Sans", sans-serif;
      color: var(--ink);
      background: #fff;
    }

    /* Header / Nav */
    header{
      position: sticky; top: 0; z-index: 40;
      background: #ffffffee;
      backdrop-filter: saturate(160%) blur(8px);
      border-bottom: 1px solid #f1f1f1;
    }
    .container{ max-width: 1200px; margin: 0 auto; padding: 0 20px; }
    .nav{ display: flex; align-items: center; justify-content: space-between; padding: 14px 0; }
    .brand{ display: flex; align-items: center; gap: 12px; font-weight: 900; letter-spacing: .2px; text-decoration: none; color: var(--ink); }
    .dot{
      height: 28px; width: 28px; border-radius: 8px;
      background: radial-gradient(120% 120% at 0% 0%, #fda4af 0%, var(--brand) 55%, var(--brand-deep) 100%);
      box-shadow: 0 6px 18px rgba(214,55,55,.35);
    }
    nav ul{ display: flex; gap: 22px; list-style: none; margin: 0; padding: 0; }
    nav a{ display: inline-block; padding: 10px 12px; border-radius: 10px; text-decoration: none; color: #111827; font-weight: 700; }
    nav a:hover{ background: rgba(214,55,55,.08); }

    /* Mobile menu button (hidden on desktop) */
    .menu-btn{
      display: none;
      border: 1px solid #e5e7eb;
      border-radius: 10px;
      padding: 8px 10px;
      background: #fff;
      font-weight: 600;
    }

    /* Mobile-only dropdown (hidden on desktop by default) */
    #mobileMenu{
      display: none;
      position: static;
      border: 0;
      border-radius: 12px;
      padding: 8px;
      background: #fff;
      box-shadow: none;
      margin-top: 6px;
    }
    #mobileMenu a{ display: block; padding: 12px 10px; border-radius: 10px; color: #0f172a; text-decoration: none; }
    #mobileMenu a + a{ margin-top: 4px; }
    #mobileMenu a:hover{ background: rgba(214,55,55,.08); }

    /* Hero */
    .hero-wrap{
      color:#fff;
      background: linear-gradient(135deg, var(--brand) 0%, var(--brand-deep) 60%); /* keep red background */
    }
    .hero{
      display: grid; grid-template-columns: 1.2fr .8fr; gap: 24px;
      align-items: center; padding: 64px 0;
    }
    .pill{
      display: inline-flex; gap: 10px; align-items: center;
      background: rgba(0,0,0,.2);
      border: 1px solid rgba(255,255,255,.25);
      color: #fff; padding: 6px 12px; border-radius: 999px;
      font-weight: 800; letter-spacing: .3px;
    }
    .hero h1{ font-size: clamp(36px,6vw,64px); line-height: 1.05; margin: 10px 0 16px; }
    .hero p{ font-size: clamp(16px,2.4vw,18px); opacity: .95; max-width: 680px; }
    .gold{ color: var(--gold); } /* yellow accent for part of the title */

    .cta{ display:flex; gap:12px; flex-wrap:wrap; margin-top:22px; }
    .btn{ display:inline-flex; align-items:center; gap:10px; border-radius:12px; padding:12px 16px; text-decoration:none; font-weight:800; border:1px solid transparent; }
    .btn.primary{ background:#fff; color:var(--brand-deep); border-color:#ffffff33; box-shadow:0 14px 30px rgba(0,0,0,.15); }
    .btn.ghost{ background:transparent; color:#fff; border:1px solid #ffffff66; }

    .hero-media{ border-radius:20px; overflow:hidden; box-shadow:0 16px 40px rgba(0,0,0,.35); border:2px solid #ffffff22; }
    .hero-media img{ width:100%; height:100%; display:block; object-fit:cover; }

    /* Sections */
    section{ padding:70px 0; }
    h2{ font-size: clamp(24px,3vw,36px); margin: 0 0 18px; }
    .grid{ display:grid; grid-template-columns: repeat(12,1fr); gap:20px; }
    .card{ background: var(--card); border: 1px solid #eaeaea; border-radius: 18px; padding: 22px; box-shadow: 0 6px 18px rgba(15,23,42,.06); }
    .subtle{ color: var(--muted); }
    .list{ padding-left: 1rem; }
    .list li{ margin: 6px 0; }
    .badge{ display:inline-block; background:#fff5e1; border:1px solid #f7e0a3; color:#7a5a14; border-radius:999px; padding:6px 10px; font-weight:700; }

    /* Gallery */
    .gallery{ display:grid; grid-template-columns: repeat(auto-fit, minmax(240px,1fr)); gap:16px; }
    .shot{ width:100%; height:auto; aspect-ratio:4/3; border-radius:14px; border:1px solid #eee; object-fit:cover; box-shadow:0 8px 20px rgba(0,0,0,.06); }

    /* Footer */
    footer{ padding:40px 0; border-top:1px solid #f1f1f1; background:#fff; }
    .socials{ display:flex; gap:10px; }
    .socials a{ display:inline-flex; align-items:center; justify-content:center; height:38px; width:38px; border-radius:10px; border:1px solid #e2e8f0; background:#fff; color:var(--brand); }

    /* ---------- Mobile styles ---------- */
    @media (max-width: 880px){
      /* desktop links hidden; show mobile menu (as requested) */
      nav ul{ display:none; }
      .menu-btn{ display:none; } /* button hidden since menu is visible by default on phones */
      #mobileMenu{ display:block; }

      /* Layout fixes */
      .hero{ grid-template-columns: 1fr; padding: 40px 0; }
      .hero h1{ font-size: 40px; }
      .hero p{ font-size: 16px; }
      .hero .cta .btn{ width: 100%; justify-content: center; }
      .hero-media{ margin-top: 18px; }

      section{ padding: 48px 0; }
      .grid{ grid-template-columns: 1fr; }               /* no multi-column on phones */
      .grid > *{ grid-column: 1 / -1 !important; }       /* force full width */
    }

    /* Extra small phones */
    @media (max-width: 380px){
      .pill{ font-size: 12px; }
      .hero h1{ font-size: 32px; }
    }
  </style>
</head>

<body>
  <!-- Header / Navigation -->
  <header>
    <div class="container nav">
      <a class="brand" href="#home" aria-label="EMC-2 Construction Supply">
        <span class="dot" aria-hidden="true"></span>
        <span>EMC-2 Construction Supply</span>
      </a>

      <nav aria-label="Primary">
        <ul>
          <li><a href="#company-profile">Company Profile</a></li>
          <li><a href="#services">Services</a></li>
          <li><a href="#products">Products</a></li>
          <li><a href="#branches">Branches</a></li>
          <li><a href="#project-portfolio">Project Portfolio</a></li>
          <li><a href="#shop" class="btn primary" style="margin-top:-8px;">Shop Now</a></li>
        </ul>
      </nav>

      <button class="menu-btn" id="menuBtn" aria-expanded="false" aria-controls="mobileMenu">Menu ▾</button>
    </div>

    <!-- Visible on phones, hidden on desktop -->
    <div id="mobileMenu" class="container" role="dialog" aria-label="Mobile navigation">
      <a href="#company-profile" onclick="closeMenu()">Company Profile</a>
      <a href="#services" onclick="closeMenu()">Services</a>
      <a href="#products" onclick="closeMenu()">Products</a>
      <a href="#branches" onclick="closeMenu()">Branches</a>
      <a href="#project-portfolio" onclick="closeMenu()">Project Portfolio</a>
      <a href="#shop" class="btn primary" style="margin-top:8px;">Shop Now</a>
    </div>
  </header>

  <!-- Hero -->
  <div class="hero-wrap" id="home">
    <div class="container hero">
      <div>
        <span class="pill">DIRECT CONSTRUCTION SUPPLIER</span>
        <h1>Build Now, <span class="gold">Pay Later</span></h1>
        <p>Open Monday to Sunday, 7AM–5PM. We build, we design, we supply. Flexible payment options available for qualified customers around Laguna, Batangas, Quezon & nearby provinces.</p>
        <div class="cta">
          <a class="btn primary" href="#shop" aria-label="Shop now">🛒 Shop Now</a>
          <a class="btn ghost" href="#branches" aria-label="Find a branch">📍 Find a Branch</a>
        </div>
        <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap">
          <span class="badge">Free Quotation</span>
          <span class="badge">Free Deliveries</span>
          <span class="badge">Customer Support</span>
        </div>
      </div>
      <div class="hero-media">
        <img alt="Construction planning" src="https://images.unsplash.com/photo-1517245386807-bb43f82c33c4?q=80&w=1400&auto=format&fit=crop" />
      </div>
    </div>
  </div>

  <!-- Company Profile -->
  <section id="company-profile">
    <div class="container">
      <h2>Company Profile</h2>
      <div class="grid">
        <div class="card" style="grid-column: span 7">
          <h3>About</h3>
          <p>EMC-2 Construction Supply is your trusted neighborhood supplier for construction materials, tools & equipment, and finishing interiors. We pride ourselves on fast service, reliable deliveries, and a friendly team ready to help with any project—big or small.</p>
          <div class="grid" style="margin-top:12px">
            <div class="card" style="grid-column: span 6">
              <strong>Vision</strong>
              <p class="subtle">To be the most dependable hardware partner in Laguna and nearby areas.</p>
            </div>
            <div class="card" style="grid-column: span 6">
              <strong>Mission</strong>
              <p class="subtle">Deliver quality products and services at fair prices with genuine customer care.</p>
            </div>
          </div>
        </div>

        <div class="card" style="grid-column: span 5">
          <h3>Working Hours</h3>
          <p class="subtle">Mon–Sun: 7:00 AM – 5:00 PM</p>
          <div class="card" style="background:#fff5f5;border:1px solid #f6c2c2">
            <strong>Walk in and get your voucher!</strong>
            <p class="subtle">Available for customers who reach the minimum purchase amount.</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Services -->
  <section id="services">
    <div class="container">
      <h2>Services</h2>
      <div class="grid">
        <div class="card" style="grid-column: span 4">
          <h3>Free Quotation</h3>
          <p class="subtle">Send your bill of materials and we’ll prepare a fast, accurate quote.</p>
        </div>
        <div class="card" style="grid-column: span 4">
          <h3>Free Deliveries</h3>
          <p class="subtle">Enjoy hassle-free drop-offs within our serviceable areas.</p>
        </div>
        <div class="card" style="grid-column: span 4">
          <h3>Construction Services</h3>
          <p class="subtle">From minor repairs to full builds—our team can do it.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- Products -->
  <section id="products">
    <div class="container">
      <h2>Products</h2>
      <div class="grid">
        <div class="card" style="grid-column: span 3">
          <h3>Hardware Supplies</h3>
          <ul class="list subtle">
            <li>Nails, fasteners, adhesives</li>
            <li>Cement, sand, aggregates</li>
            <li>Pipes & fittings</li>
          </ul>
        </div>
        <div class="card" style="grid-column: span 3">
          <h3>Tools & Equipment</h3>
          <ul class="list subtle">
            <li>Power tools</li>
            <li>Hand tools</li>
            <li>Safety gear</li>
          </ul>
        </div>
        <div class="card" style="grid-column: span 3">
          <h3>Finishing Interiors</h3>
          <ul class="list subtle">
            <li>Paints & primers</li>
            <li>Tiles & grout</li>
            <li>Lighting & switches</li>
          </ul>
        </div>
        <div class="card" style="grid-column: span 3">
          <h3>And More</h3>
          <p class="subtle">Ask our team for availability—we’ll help you find what you need.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- Branches -->
  <section id="branches">
    <div class="container">
      <h2>Branches</h2>
      <div class="grid">
        <div class="card" style="grid-column: span 6">
          <strong>Visit Us</strong>
          <ul class="list">
            <li>Duhat (Main)</li>
            <li>Oogong</li>
            <li>Patimbao</li>
            <li>Liliw</li>
            <li>Masapang</li>
            <li>Calauan</li>
          </ul>
          <a class="btn" style="border-color:#e5e7eb" href="#map">▶ How to get to our branch?</a>
        </div>
        <div class="card" style="grid-column: span 6">
          <img alt="Branch exterior" class="shot" src="https://images.unsplash.com/photo-1469474968028-56623f02e42e?q=80&w=1200&auto=format&fit=crop" />
        </div>
      </div>
    </div>
  </section>

  <!-- Project Portfolio -->
  <section id="project-portfolio">
    <div class="container">
      <h2>Customer Project Highlights</h2>
      <p class="subtle">A peek at recent builds and banner/photo projects completed with EMC-2 supplies and services.</p>
      <div class="gallery" aria-label="Project gallery">
        <img class="shot" alt="Project 1" src="https://images.unsplash.com/photo-1504307651254-35680f356dfd?q=80&w=1200&auto=format&fit=crop" />
        <img class="shot" alt="Project 2" src="https://images.unsplash.com/photo-1482192596544-9eb780fc7f66?q=80&w=1200&auto=format&fit=crop" />
        <img class="shot" alt="Project 3" src="https://images.unsplash.com/photo-1503387762-592deb58ef4e?q=80&w=1200&auto=format&fit=crop" />
        <img class="shot" alt="Project 4" src="https://images.unsplash.com/photo-1469474968028-56623f02e42e?q=80&w=1200&auto=format&fit=crop" />
      </div>
    </div>
  </section>

  <!-- Shop CTA -->
  <section id="shop">
    <div class="container">
      <div class="card" style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;border-left:6px solid var(--brand)">
        <h2 style="margin:0">Shop Now!</h2>
        <div class="cta" style="margin:0">
          <a class="btn primary" href="order.php" aria-label="Start order">Start an Order</a>
          <a class="btn ghost" style="color:var(--brand)" href="#company-profile" aria-label="Learn more">Learn More</a>
        </div>
      </div>
    </div>
  </section>

  <!-- Footer -->
  <footer>
    <div class="container" style="display:grid;gap:20px;grid-template-columns:1.5fr 1fr;align-items:center">
      <div style="display:flex;align-items:center;gap:12px">
        <div style="height:44px;width:44px;border-radius:12px;background:radial-gradient(120% 120% at 0% 0%, #f9caca 0%, var(--brand) 55%, var(--brand-deep) 100%);box-shadow:0 6px 18px rgba(214,55,55,.4)"></div>
        <div>
          <strong>Customer Service / Customer Support</strong>
          <p class="subtle" style="margin:.25rem 0 0">Need assistance? Message us on social or visit your nearest branch.</p>
        </div>
      </div>
      <div style="justify-self:end">
        <div class="socials">
          <a href="#" aria-label="Facebook">f</a>
          <a href="#" aria-label="Instagram">◎</a>
          <a href="#" aria-label="Shopee">🛍️</a>
          <a href="#" aria-label="TikTok">♬</a>
        </div>
      </div>
    </div>
  </footer>

  <script>
    /* If you ever want tap-to-toggle on phones instead of always-open,
       remove the `display:block` from #mobileMenu in the 880px media query
       and use this button handler. */
    const menuBtn = document.getElementById('menuBtn');
    const mobileMenu = document.getElementById('mobileMenu');
    function closeMenu(){ mobileMenu.style.display='none'; menuBtn.setAttribute('aria-expanded','false'); }
    menuBtn?.addEventListener('click', () => {
      const open = getComputedStyle(mobileMenu).display !== 'none';
      mobileMenu.style.display = open ? 'none' : 'block';
      menuBtn.setAttribute('aria-expanded', String(!open));
    });
    window.addEventListener('keydown', (e)=>{ if(e.key==='Escape') closeMenu(); });
  </script>
</body>
</html>
