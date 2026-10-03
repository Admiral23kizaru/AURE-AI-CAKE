<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

function storefront_escape(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function storefront_product_image(?string $picture): string
{
    $picture = trim((string) $picture);
    if ($picture === '') {
        return '/AI-CAKE/images.jpg';
    }

    $segments = array_filter(explode('/', str_replace('\\', '/', ltrim($picture, '/'))), 'strlen');
    return '/AI-CAKE/uploads/' . implode('/', array_map('rawurlencode', $segments));
}

function storefront_role_home(): string
{
    $role = current_user()['role'] ?? 'guest';
    return match ($role) {
        'admin' => '/AI-CAKE/admin/dashboard.php',
        'staff' => '/AI-CAKE/staff/dashboard.php',
        'customer' => '/AI-CAKE/account.php',
        default => '/AI-CAKE/login.php',
    };
}

function storefront_orders_url(): string
{
    if (!is_logged_in()) {
        return '/AI-CAKE/login.php';
    }

    return (current_user()['role'] ?? '') === 'customer'
        ? '/AI-CAKE/my_orders.php'
        : storefront_role_home();
}

function storefront_header(string $active = ''): void
{
    $loggedIn = is_logged_in();
    $accountLabel = $loggedIn && in_array(current_user()['role'] ?? '', ['staff', 'admin'], true)
        ? 'Dashboard'
        : ($loggedIn ? 'Account' : 'Log in');
    ?>
    <header class="storefront-header">
      <nav class="navbar navbar-expand-lg" aria-label="Main navigation">
        <div class="container storefront-container">
          <a class="storefront-brand" href="/AI-CAKE/" aria-label="Aure Sanchez House of Cakes home">
            <img src="/AI-CAKE/logo.png" width="152" height="48" alt="Aure Sanchez House of Cakes">
          </a>

          <button class="navbar-toggler storefront-menu-button" type="button" data-bs-toggle="offcanvas" data-bs-target="#storefrontMenu" aria-controls="storefrontMenu" aria-label="Open menu">
            <i class="bi bi-list" aria-hidden="true"></i>
          </button>

          <div class="d-none d-lg-flex align-items-center ms-5 flex-grow-1">
            <div class="navbar-nav storefront-nav-links">
              <a class="nav-link<?= $active === 'home' ? ' active' : '' ?>" href="/AI-CAKE/">Home</a>
              <a class="nav-link<?= $active === 'menu' ? ' active' : '' ?>" href="/AI-CAKE/view-all.php">Menu</a>
              <a class="nav-link<?= $active === 'orders' ? ' active' : '' ?>" href="<?= storefront_escape(storefront_orders_url()) ?>">My Orders</a>
            </div>
          </div>

          <div class="storefront-actions d-none d-lg-flex">
            <a class="icon-action" href="/AI-CAKE/search.php" aria-label="Search cakes" title="Search cakes"><i class="bi bi-search" aria-hidden="true"></i></a>
            <a class="account-action" href="<?= storefront_escape(storefront_role_home()) ?>"><i class="bi bi-person" aria-hidden="true"></i><span><?= storefront_escape($accountLabel) ?></span></a>
            <a class="icon-action cart-action" href="/AI-CAKE/cart.php" aria-label="Open cart" title="Open cart"><i class="bi bi-bag" aria-hidden="true"></i><span class="storefront-cart-count" id="storefrontCartCount" hidden>0</span></a>
          </div>
        </div>
      </nav>
    </header>

    <div class="offcanvas offcanvas-start storefront-offcanvas" tabindex="-1" id="storefrontMenu" aria-labelledby="storefrontMenuLabel">
      <div class="offcanvas-header">
        <a href="/AI-CAKE/" id="storefrontMenuLabel"><img src="/AI-CAKE/logo.png" width="150" alt="Aure Sanchez House of Cakes"></a>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close menu"></button>
      </div>
      <div class="offcanvas-body d-flex flex-column">
        <nav class="mobile-storefront-nav" aria-label="Mobile navigation">
          <a href="/AI-CAKE/">Home</a>
          <a href="/AI-CAKE/view-all.php">Menu</a>
          <a href="<?= storefront_escape(storefront_orders_url()) ?>">My Orders</a>
          <a href="/AI-CAKE/search.php">Search</a>
          <a href="<?= storefront_escape(storefront_role_home()) ?>"><?= storefront_escape($accountLabel) ?></a>
          <a href="/AI-CAKE/cart.php">Cart</a>
        </nav>
        <?php if (!$loggedIn): ?>
          <a class="btn storefront-primary-button mt-auto" href="/AI-CAKE/register.php">Create customer account</a>
        <?php else: ?>
          <form method="post" action="/AI-CAKE/logout.php" class="mt-auto"><?= csrf_field() ?><button class="btn storefront-secondary-button w-100">Log out</button></form>
        <?php endif; ?>
      </div>
    </div>
    <?php
}

function storefront_product_card(array $cake, bool $showNew = false): void
{
    $id = (int) ($cake['id'] ?? 0);
    $name = storefront_escape($cake['name'] ?? 'Cake');
    $image = storefront_escape(storefront_product_image($cake['picture'] ?? ''));
    $price = number_format((float) ($cake['price'] ?? 0), 2);
    ?>
    <article class="storefront-product-card">
      <a class="product-photo-link" href="/AI-CAKE/cake_size.php?id=<?= $id ?>" aria-label="View <?= $name ?>">
        <img src="<?= $image ?>" alt="<?= $name ?>" loading="lazy" width="640" height="640">
      </a>
      <div class="product-card-content">
        <div>
          <?php if ($showNew && !empty($cake['is_new'])): ?><span class="product-kicker">New</span><?php endif; ?>
          <h3><a href="/AI-CAKE/cake_size.php?id=<?= $id ?>"><?= $name ?></a></h3>
        </div>
        <div class="product-card-footer">
          <span class="product-price">₱<?= $price ?></span>
          <a class="product-select-link" href="/AI-CAKE/cake_size.php?id=<?= $id ?>" aria-label="Customize <?= $name ?>">Customize <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
        </div>
      </div>
    </article>
    <?php
}

function storefront_footer(): void
{
    ?>
    <footer class="storefront-footer">
      <div class="container storefront-container">
        <div class="footer-brand"><img src="/AI-CAKE/logo.png" width="142" alt="Aure Sanchez House of Cakes"><p>Cakes prepared for pickup, celebrations, and everyday cravings.</p></div>
        <nav aria-label="Footer navigation"><a href="/AI-CAKE/">Home</a><a href="/AI-CAKE/view-all.php">Menu</a><a href="/AI-CAKE/ai_cake.php">AI cake design</a><a href="/AI-CAKE/login.php">Log in</a></nav>
        <p class="footer-copy">© <?= date('Y') ?> Aure Sanchez House of Cakes</p>
      </div>
    </footer>
    <script>
    (() => {
      const userId = <?= json_encode(user_id()) ?>;
      const key = userId ? `cart_user_${userId}` : 'cart_guest_v1';
      const badge = document.getElementById('storefrontCartCount');
      if (!badge) return;
      const render = () => {
        let items = [];
        try { items = JSON.parse(localStorage.getItem(key) || '[]'); } catch (error) { items = []; }
        const count = Array.isArray(items) ? items.reduce((total, item) => total + Math.max(0, Number(item && item.qty || 1)), 0) : 0;
        badge.hidden = count < 1;
        badge.textContent = count > 99 ? '99+' : String(count);
      };
      render();
      window.addEventListener('storage', render);
      window.addEventListener('cartUpdated', render);
    })();
    </script>
    <?php
}
