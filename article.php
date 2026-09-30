<?php
/**
 * =============================================================================
 *  Contra Hotel - public article page
 * =============================================================================
 *  Serves an article written in the admin panel:
 *
 *      /article.php?slug=your-article-slug
 *
 *  The page uses the site's own header, footer and stylesheet, so an article
 *  looks like the rest of the website without anyone touching a template.
 *
 *  Only articles with status 'published' are visible. A draft returns 404 so
 *  an unfinished piece cannot be read by guessing its address.
 *
 * @package ContraHotel
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

use Contra\ContentRepository;
use Contra\Security;

$content = new ContentRepository(db(), new Security(db()));

// -----------------------------------------------------------------------------
// Resolve the requested article
// -----------------------------------------------------------------------------
$slug = contra_slugify((string) ($_GET['slug'] ?? ''));

if ($slug === 'item') {
    // contra_slugify() returns 'item' for empty input.
    $slug = '';
}

$article = $slug === '' ? null : $content->articleBySlug($slug, true);

if ($article === null) {
    // Return a proper 404 with the site's own error page.
    if (!headers_sent()) {
        http_response_code(404);
    }

    $notFound = CONTRA_ROOT . '/404.html';

    if (is_file($notFound)) {
        readfile($notFound);
    } else {
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Article not found';
    }

    exit;
}

// -----------------------------------------------------------------------------
// Count the view (best effort, never fatal)
// -----------------------------------------------------------------------------
try {
    db()->run('UPDATE articles SET view_count = view_count + 1 WHERE id = ?', [(int) $article['id']]);
} catch (Throwable) {
    // A failed counter must never break the page.
}

$title = (string) ($article['meta_title'] ?? '') !== ''
    ? (string) $article['meta_title']
    : (string) $article['title'];

$metaDescription = (string) ($article['meta_description'] ?? '') !== ''
    ? (string) $article['meta_description']
    : mb_substr(trim(strip_tags((string) ($article['excerpt'] ?? $article['body'] ?? ''))), 0, 160);

// Distinguish "no publication date" from a real one, for display only.
$hasDate = !empty($article['published_at']);
$publishedTs = $hasDate ? (int) strtotime((string) $article['published_at']) : 0;

$bannerImage = !empty($article['image'])
    ? (string) $article['image']
    : 'contra/web/DSC04793.jpg';

$pageTitle = $title . ' - Contra Hotel';
$metaDescription = $metaDescription;
$canonical = contra_origin() . '/article.php?slug=' . rawurlencode((string) $article['slug']);
$bannerKicker = (string) ($article['category'] ?? 'Hotel news');
$bannerHeading = (string) $article['title'];

require __DIR__ . '/includes/site-header.php';
?>

<!-- Article -->
<section class="section-padding">
<div class="container">
<div class="row">

    <!-- Main content -->
    <div class="col-md-8">
        <article>

            <?php if (!empty($article['image'])): ?>
                <img src="<?= e((string) $article['image']) ?>"
                     alt="<?= e((string) $article['title']) ?>"
                     style="width:100%;height:auto;margin-bottom:26px;">
            <?php endif; ?>

            <div class="post-meta" style="margin-bottom:18px;color:#8a8378;font-size:13px;">
                <?php if ($hasDate): ?>
                    <span><?= e(date('d F Y', $publishedTs)) ?></span>
                <?php endif; ?>

                <?php if (!empty($article['author_name'])): ?>
                    &middot; <span><?= e((string) $article['author_name']) ?></span>
                <?php endif; ?>

                <?php if (!empty($article['category'])): ?>
                    &middot; <span><?= e((string) $article['category']) ?></span>
                <?php endif; ?>
            </div>

            <?php if (!empty($article['excerpt'])): ?>
                <p style="font-size:18px;line-height:1.7;color:#555;margin-bottom:24px;">
                    <em><?= e((string) $article['excerpt']) ?></em>
                </p>
            <?php endif; ?>

            <?php
            /*
             * The body is admin-authored HTML. It was passed through
             * Security::sanitizeHtml() when it was saved, which strips scripts,
             * event handlers and javascript: URLs, so printing it here is safe.
             */
            ?>
            <div class="article-body" style="font-size:16px;line-height:1.8;color:#444;">
                <?= (string) ($article['body'] ?? '') ?>
            </div>

            <?php if (!empty($article['tags'])): ?>
                <div style="margin-top:30px;padding-top:18px;border-top:1px solid #eee;">
                    <strong style="font-size:13px;letter-spacing:1px;text-transform:uppercase;">Tags:</strong>
                    <?php
                    $tags = array_filter(array_map('trim', explode(',', (string) $article['tags'])));
                    foreach ($tags as $tag):
                        ?>
                        <span style="display:inline-block;background:#f4f1ec;border:1px solid #e6e0d6;border-radius:3px;padding:3px 10px;margin:3px 4px 0 0;font-size:13px;">
                            <?= e($tag) ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </article>

        <div style="margin-top:36px;">
            <a href="news.html" class="butn-dark2"><span>&larr; Back to all news</span></a>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="col-md-4">
        <aside>

            <div style="border:1px solid #e6e0d6;border-radius:4px;padding:24px;margin-bottom:26px;background:#faf8f5;">
                <h5 style="margin-top:0;">Questions?</h5>
                <p style="font-size:15px;line-height:1.7;">
                    Our front desk is happy to help with rooms, rates and bookings.
                </p>

                <div class="reservations mb-30">
                    <div class="icon"></div>
                    <div class="text">
                        <p>Reservation</p>
                        <a href="tel:0701497083">0701497083</a> /
                        <a href="tel:0772497083">0772497083</a>
                    </div>
                </div>

                <a href="rooms2.html" class="butn-dark2" style="display:inline-block;">
                    <span>Book a room</span>
                </a>
            </div>

            <?php
            // Other recent articles, excluding the one being read.
            $related = [];

            try {
                foreach ($content->publishedArticles(6) as $candidate) {
                    if ((int) $candidate['id'] !== (int) $article['id']) {
                        $related[] = $candidate;
                    }
                }
            } catch (Throwable) {
                $related = [];
            }

            $related = array_slice($related, 0, 4);
            ?>

            <?php if ($related !== []): ?>
                <div style="border:1px solid #e6e0d6;border-radius:4px;padding:24px;background:#fff;">
                    <h5 style="margin-top:0;">More from the hotel</h5>

                    <?php foreach ($related as $item): ?>
                        <div style="display:flex;gap:12px;margin-bottom:16px;padding-bottom:16px;border-bottom:1px solid #f0ece5;">
                            <?php if (!empty($item['image'])): ?>
                                <img src="<?= e((string) $item['image']) ?>" alt="" loading="lazy"
                                     style="width:72px;height:56px;object-fit:cover;border-radius:3px;flex:0 0 72px;">
                            <?php endif; ?>
                            <div>
                                <a href="article.php?slug=<?= e(rawurlencode((string) $item['slug'])) ?>"
                                   style="font-size:15px;line-height:1.4;font-weight:500;">
                                    <?= e((string) $item['title']) ?>
                                </a>
                                <div style="font-size:12px;color:#8a8378;margin-top:4px;">
                                    <?= e(contra_date((string) $item['published_at'], 'd M Y')) ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <a href="news.html" style="font-size:14px;">See all news &rarr;</a>
                </div>
            <?php endif; ?>
        </aside>
    </div>

</div>
</div>
</section>

<?php require __DIR__ . '/includes/site-footer.php'; ?>
