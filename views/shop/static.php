<?php
declare(strict_types=1);
/** @var string $page */
?>
<?php if ($page === 'about'): ?>
  <div class="stack-lg">
    <section class="print-panel harvest" aria-labelledby="about-title" style="grid-template-columns:minmax(0,1.2fr) minmax(0,1fr);">
      <div style="position:relative;z-index:1;" class="stack-sm">
        <h1 id="about-title" class="display display-red harvest-title">From the bukid<br>to your door</h1>
        <p class="harvest-lede" style="margin:8px 0 0;">Bukid Basket is a small online grocer for households in Dasmariñas City. We sell vegetables, fruit, meat, eggs and rice from farms around Cavite, and deliver the next morning.</p>
      </div>
      <figure class="story-figure" style="position:relative;z-index:1;">
        <img src="/scenes/rice-terraces.jpg" alt="Farmers working on terraced rice fields" width="800" height="600">
        <figcaption>Rice terraces at planting time. Stock photo · <a href="/credits">photo credits</a></figcaption>
      </figure>
    </section>

    <section class="grid-2" style="align-items:center;">
      <figure class="story-figure">
        <img src="/scenes/market-stall.jpg" alt="A market stall stacked with fresh fruit and vegetables" width="800" height="600" loading="lazy">
        <figcaption>A produce stall at the market. Stock photo · <a href="/credits">photo credits</a></figcaption>
      </figure>
      <div class="stack-sm measure">
        <h2 class="display section-title">Every count is on the record</h2>
        <p class="ink-2" style="margin:0;">Prices are kept in whole centavos, and every bunch, kilo or tray that comes in or goes out is written to a stock ledger with the time and the reason. Nothing in it is edited or erased. A mistake is fixed with a new entry that reverses it.</p>
        <p class="ink-2" style="margin:0;">So the stock you see on a product is the real count, and we can't sell you something we don't have.</p>
      </div>
    </section>

    <section class="stack" aria-labelledby="makers-title">
      <div class="cat-head" style="margin-bottom:0;">
        <h2 id="makers-title" class="display section-title">Made by</h2>
        <span class="small muted">Academic capstone (SIA)</span>
      </div>
      <div class="makers-row">
        <div class="maker-card">
          <div class="maker-name">EJ</div>
          <p class="small ink-2" style="margin:12px 0 0;">Co-maker of Bukid Basket.</p>
        </div>
        <div class="maker-card">
          <div class="maker-name">Kiel</div>
          <p class="small ink-2" style="margin:12px 0 0;">Co-maker of Bukid Basket.</p>
        </div>
      </div>
      <p class="small muted" style="margin:0;">Bukid Basket is a school project. The business, the people in the demo data and every order are made up, and no real payment is ever taken.</p>
    </section>
  </div>
<?php elseif ($page === 'contact'): ?>
  <div class="stack-lg">
    <h1 class="display page-title">Contact &amp; hours</h1>
    <div class="grid-2" style="align-items:start;">
      <div class="print-panel stack-sm">
        <h2 class="display section-title">Visit or message us</h2>
        <dl class="stack-sm" style="margin:0;">
          <div><dt class="label">Pick-up counter</dt><dd style="margin:0;">Stall 14, Zone I, Dasmariñas City, Cavite (fictional)</dd></div>
          <div><dt class="label">Hours</dt><dd style="margin:0;" class="tabular">Mon–Sat, 6:00 AM – 6:00 PM · closed Sundays</dd></div>
          <div><dt class="label">Delivery slots</dt><dd style="margin:0;" class="tabular"><?= e(implode(' · ', DELIVERY_SLOTS)) ?></dd></div>
          <div><dt class="label">Phone &amp; GCash (demo)</dt><dd style="margin:0;" class="tabular">0917 888 0000</dd></div>
        </dl>
        <p class="small muted" style="margin:8px 0 0;">This is a school project, so this number and address are made up.</p>
      </div>
      <figure class="story-figure">
        <img src="/scenes/rice-sacks.jpg" alt="Sacks of rice stacked in a store" width="800" height="600" loading="lazy">
        <figcaption>Rice by the sack. Stock photo · <a href="/credits">photo credits</a></figcaption>
      </figure>
    </div>
  </div>
<?php elseif ($page === 'credits'): ?>
  <div class="stack" style="max-width:860px;">
    <div>
      <h1 class="display page-title">Photo credits</h1>
      <p class="ink-2 measure" style="margin:10px 0 0;">Every photo on Bukid Basket is a stock photo from <a href="https://unsplash.com">Unsplash</a>, used under the <a href="https://unsplash.com/license">Unsplash License</a>. They show each product in general, not our own stock, because the shop is fictional.</p>
    </div>
    <div class="card table-wrap" style="padding:0;">
      <table class="data">
        <thead><tr><th>Photo</th><th>Used for</th><th>Source</th></tr></thead>
        <tbody>
          <?php foreach (PHOTO_SOURCES as $file => $id):
            $label = ucwords(str_replace('-', ' ', pathinfo($file, PATHINFO_FILENAME)));
          ?>
            <tr>
              <td><img src="<?= e($file) ?>" alt="" width="64" height="48" loading="lazy" style="width:64px;height:48px;object-fit:cover;border-radius:4px;border:1.5px solid var(--ink);"></td>
              <td><?= e($label) ?></td>
              <td><a href="https://unsplash.com/photos/<?= e($id) ?>" rel="noopener">unsplash.com/photos/<?= e($id) ?></a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php else: ?>
  <div class="print-panel stack-sm" style="max-width:640px;margin:24px auto;text-align:center;align-items:center;">
    <h1 class="display display-red harvest-title">Not on<br>the shelf</h1>
    <p class="ink-2" style="margin:8px 0 12px;">We couldn't find that page. It may have moved, or the product is no longer sold.</p>
    <a href="/" class="btn btn-accent btn-lg">Back to today's harvest</a>
  </div>
<?php endif; ?>
