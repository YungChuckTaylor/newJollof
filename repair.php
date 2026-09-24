<?php
/**
 * Jollof Living — one-file self-repair tool.
 *
 * Paste THIS ONE FILE into the site folder (public_html/jollof/), then open
 * it in the browser with the secret key. It:
 *   1. prints environment diagnostics (folder, PHP, opcache, disk, odd filenames),
 *   2. downloads every code file straight from GitHub (pinned to a known-good
 *      commit) and writes it into place — byte-verified with SHA-1,
 *   3. NEVER touches includes/config.php or jollof-automations/config.php,
 *   4. writes health.php AND status.php (identical page, two names) so a WAF
 *      rule blocking the word "health" cannot hide the diagnosis,
 *   5. clears opcache so stale bytecode cannot keep serving old files.
 *
 * USE:  https://<your-host>/jollof/repair.php?key=jl-fix-2026
 * DELETE THIS FILE FROM THE SERVER once the site works.
 */
declare(strict_types=1);

if (!isset($_GET['key']) || $_GET['key'] !== 'jl-fix-2026') {
    http_response_code(404);
    exit('Not found');
}
header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');
@set_time_limit(300);

const COMMIT = 'a4fc2b14e59924c849e8e15ae4292bb4f54c8686';
const BASE   = 'https://raw.githubusercontent.com/YungChuckTaylor/newJollof/a4fc2b14e59924c849e8e15ae4292bb4f54c8686/';
$MANIFEST = [
    'includes/auth.php' => '1d6f9e611a0fdc5963d6035791f789525b9a7f50',
    'includes/bootstrap.php' => '3873d8e5c01b56cc64daf2e1652cc52d32f05bf5',
    'includes/cancellations.php' => '9255169172238860ff2b237d89b84e37184f7ea0',
    'includes/concierge.php' => '20fa7b3021ccc0164b7d7ed5f4ad9b8ec638fca2',
    'includes/config.sample.php' => '794cd3951218d8d3ead034daebf7a17ee4741c08',
    'includes/db.php' => '2cf0c5fc34b8fc01cd6011c1ef73beee116f7251',
    'includes/disputes.php' => 'f91063aef077729c828c99863daa7ea6005aea68',
    'includes/helpers.php' => '82b73df45a02594d52ce218a7b1934956e87c89b',
    'includes/ledger.php' => '2da46061c8e7324e24f10d16fec42eefa19ce4c6',
    'includes/livechat.php' => 'd72c03cc9b2e962eb3ba6b7194e8c2f7c6bf3d1f',
    'includes/mailer.php' => '8e426cfe6b3312fe5150aed940ddf53ea3933330',
    'includes/payments.php' => '3eafd8f30850737299f24485b20e69fb11d1c03b',
    'includes/pricing.php' => 'c3ae0d6138d4f5ec384965dc65508fcc909f6b79',
    'includes/repo.php' => 'c72547d403e54370ea40853b3c44e997e3abfc1b',
    'includes/ssr.php' => '1e76163890c7fb42f8b33475168abdd804053d9b',
    'includes/verify.php' => '4787cd58060fc95112f76cd9a88fe7ffdeed3cfe',
    'includes/view.php' => '4f042cb03e4f024d10471ccebec30efa7e0d0f82',
    'assets/css/site.css' => '5af6ceccab583e40fe4830f164996178aaff8d27',
    'assets/css/site.min.css' => '9e8b7bcc30ad60aaea12a199c23e0f3f7f293cfc',
    'assets/js/chat.js' => '8c335096ad4f34f47f26909b583f9bc332cbb209',
    'assets/js/chat.min.js' => '96bff8a7a8b96623201e85ee9e88fc8e4b045643',
    'assets/js/site.js' => '0d3eec7769ea5534d9623b9eba08c8fb9d645357',
    'assets/js/site.min.js' => '59c41928308f93393c6e65c312609bc5632112bc',
    '.htaccess' => 'bc8d7a2ccb1fa8ca6c45b577b072a26cb11376a7',
    '404.php' => 'e68d3c867b5716ffb039f83d5050facd30ce26af',
    'DEPLOY_DISPUTES_LIVECHAT.md' => '7f1b1525ac63974964d644910e591f452d9017c3',
    'about.php' => 'f3d5c447ea708d4bbcf3776b548eb9f9db3736b2',
    'account.php' => '51970d9c109adfa664476432b4c6c6115c35e0c8',
    'admin-login.php' => '623dbd02ac1d2a610989c7c6e74f64353e3dc6cd',
    'admin.php' => 'ee0fcb90927624d267524afcb383bf927ffbaad0',
    'agent.php' => '0bf2329b1ad1ff7dda2d6a89bb5d40c51440748b',
    'app.php' => '64f3ad6b60d84a0c832343d736e1645e8ed5880e',
    'auth.php' => '4c5882024e632431ef053090b4ba678d69aa2b14',
    'automations.php' => '966eba063a8f3ae8d4785df60af4b64940c2bb19',
    'blog-post.php' => '44f097531a3387b732915fb5c1a48de236a8678d',
    'blog.php' => '3153f0039c55c0134fe86f214deec006e8959f49',
    'booking.php' => 'a071ccac01b06db4a1c88624016673c55cbddd0f',
    'business.php' => 'de4c514edf7d749b0ca37f55b153644f3a0def94',
    'collections.php' => 'af5243393c1236444c185c0826d8171da4447cea',
    'compare.php' => 'a20e304fdffe281af1d5d055e754d10476499c94',
    'concierge.php' => 'c5d28867fbad7940662ebf5d45142120c6819115',
    'confirm.php' => '44c8159d40d0fcdaff3d44953ded5c890064e51d',
    'experiences.php' => 'e4f5bcec0a0f0f32af64f6bf7fa20ef235e94303',
    'future.php' => '35dcc24c15952c150b7967f5863e1d61e174ce22',
    'giftcards.php' => '5bea2582303ac91378a698c4250d22a35a92efe0',
    'help.php' => '55dc988333f8920b66764db7fc3ca4f9fe3b2023',
    'host-dashboard.php' => '8cdbfeb40280afbf9fd8e91f072e35f432235179',
    'host-onboarding.php' => '3147b9f9052aa1cf49ea4dbdcd48ae910e6b5f82',
    'host.php' => '8bd7d30e2b7437d472da2a0c28e29ee3c156d0cc',
    'index.php' => '6e20af2cd34ada0f189cad2bcd90df3817d40a6e',
    'logout.php' => '1e7781af4988cbf494af661e62eb048bd4faea3e',
    'map.php' => '3070daa651b8f1a103885850d5303ec0af973da1',
    'membership.php' => '93fbf5af4e1c2a2cfb615c4793eb480fc67c921d',
    'messages.php' => '9f1c4d0ff848ebb2abce112d6f805694aa4cac0c',
    'neighborhood.php' => 'd61f537a4cd8c5177cb34db9ae8a78e50bd40dbf',
    'neighborhoods.php' => 'e49e1dfc69ea7f4b66d1ea626bfe7e27a62d502e',
    'notifications.php' => '0a738b52c9cc07fc59537b1a7c9848958fe36c2b',
    'payments.php' => '965e7516875ef56467d752c95475a3646e16b64d',
    'referral.php' => '4a67d8a1bfbc0aba048ed4a3309ab2cfee655f30',
    'reset.php' => '3d7b45866d06f47201285c65e211e543ed1e1f98',
    'reviews.php' => '1d04f2db798220242703da36f6cfb484be590808',
    'robots.txt' => '9df6629a895ffe13b8b1d341e28b49fbecc4084c',
    'sitemap.php' => '5291b9b45582df4358d37267620a2c9ee44398e3',
    'stay.php' => '2c1b39645bc2e4c6ea369d123eabab825b7ec863',
    'stays.php' => 'ae66781ec6ca33b83adcfc293183d0f778b24f69',
    'trips.php' => '5cf2c9fe9c524773df7d2dbeff096495723786c3',
    'verify-email.php' => '55c41a52d417c9715cadf6be482706299df2bccc',
    'wishlist.php' => 'f91a5e4298dbd1cd0fd90fffe8a049bb0c46fab0',
    'install/diagnose.php' => 'eb39c509b4c85bfba007465a6df7c3f5e2ca0c26',
    'install/index.php' => '1f38f72ad8aadb7f9e99d57600da29d43ba25279',
    'install/installed.lock' => 'af3236200f77197b61b1bc2df74b94087aac08ee',
    'install/migrate.php' => 'eaca517eae7f5d17e2f005f939f61e6b68e18799',
    'install/schema/2026_09_12_disputes_and_live_chat.sql' => '369c40dc002b7d9bceb5f9cba704923c819d41ab',
    'install/schema/2026_09_22_phase0_hardening.sql' => '5093814e00f966af7512367a7276e3eeedb0ff25',
    'install/schema/2026_09_23_automations_smart_homes.sql' => '2d88bce10078c4bba0db9f4b94a6c0ef64c95712',
    'install/schema/2026_09_23_phase1_money_machine.sql' => '378b33bf7cf2cd2cdec226edbe642ae445f9b56f',
    'install/schema/2026_09_23_phase2_booking_integrity.sql' => '926e1d8c1ec00d2901b5d40cf8e6979588a763c8',
    'install/schema/2026_09_23_phase3_trust_identity.sql' => 'b1f731a543559464126f34b07f83c3a8f4be07b2',
    'install/schema/2026_09_23_phase5_owner_workspace.sql' => '1f0fd32c27a33a755d286c935331353b702aac26',
    'install/schema/2026_09_23_phase6_back_office.sql' => 'e7f601476ff4c3d1b6b71d927e73dc5916baef07',
    'install/schema/2026_09_23_phase7_support_modules.sql' => '55cc5545d9855380419769220d68e7ecb47ad9a0',
    'install/schema/2026_09_23_phase8_platform_reach.sql' => '62630e13eaa2eef7b3414dba97b89bd68fc41a58',
    'install/schema/2026_09_24_seo_metadata.sql' => 'fe357419611e971b99a0f01a27c34cb8928127b9',
    'install/why.php' => '0791f8f405fd60c343c7973d1ebcac1e14a543fc',
    'api/_api.php' => 'f636e8db8482f05ee7a89220b76faf1cfe0016a3',
    'api/account.php' => '7e5eb274572715b14aaca25b09f435bc3aaa7fea',
    'api/admin-action.php' => '71577247dbbbf366068fe8aaa3086b11fbcf35e5',
    'api/admin-auth.php' => '6b5b0e9baf8ab9c631dbcfcf5b9a5c85dc97be15',
    'api/auth.php' => '56ca6ce74b5e43a07a5e46bcfd0f60da9f3e28fc',
    'api/booking-action.php' => '1684846640a1e719532863f7198ae5496e73f343',
    'api/booking-create.php' => 'b7cf2327632e23ccfa4ccd4cabac9d0625c7d6ba',
    'api/business.php' => '10fe711621c8a18e37ffce8bb96b70ed6450ad8e',
    'api/calendar.php' => '64ac4570b237eaafff297347015cb5885c3dbdc4',
    'api/chat.php' => 'bfdb5176d3a52b50fce31ebf1c9011455576e38e',
    'api/compare.php' => 'd5dc4a1e92e1a6e93ae4cf1164fe29c23633d722',
    'api/concierge.php' => 'fee20b295466fc479ec33ce7f80f7f202db51da6',
    'api/dispute.php' => '188de227efb58f29f16f015a05d17f653c77bff3',
    'api/experience-book.php' => '609cc2b3e5b29e1d1026495f621c6e51d02b9ff9',
    'api/giftcard.php' => 'a1121bb701cde0a83c883531e4d297ab8bff893f',
    'api/host-action.php' => 'bdeac2fb07df012c21d80e6518e73ea58d0802cd',
    'api/host-report.php' => 'fb31e47debf36ebae6ceda78d6920c7245229bfd',
    'api/invoice.php' => 'fa2c9c974a21a8bf749b03cea0015a0245c1f8f1',
    'api/listing-create.php' => 'b4fe0752ebfe3fea435e75723b9f5396a304a449',
    'api/listing-draft.php' => 'da2f375e8645155eafcb58fe68b0d0d8f0378e01',
    'api/message-send.php' => '21aa78070a3871cf7f574533fac55b9f6f2e0e3e',
    'api/mobile/_mobile.php' => '0de273d63b376a1c85f6e23a45cad3d6c7e7375e',
    'api/mobile/action.php' => '096156671fd6ccecadd5ab62b8776333edc70b8b',
    'api/mobile/auth.php' => 'dadb4be8acf2c359e03612cd21514adeb01b36fb',
    'api/mobile/sync.php' => '3db9bfd6be0238be49050e233b6bbdf7861ad173',
    'api/newsletter.php' => '6dce900e2fc7c1f7d199f790ee3307b8f5ce721b',
    'api/notifications.php' => '5dbb59b10f229d26362817f6fe8ae1ce7462adf9',
    'api/pay.php' => '60e55733ec082d94e8bc867b656c223e1a2f68f1',
    'api/quote.php' => '91691b66a476ca96b69204417bff2c74edc1d21c',
    'api/report.php' => 'dc58ad05120425a05c3c34c268577c559e4095e4',
    'api/review-create.php' => '37d43f398068fd4a47f24cdef5cc1a8832392c57',
    'api/state.php' => '46fd097785374d37840a9a3ee7b23b7c2f8835b9',
    'api/test-mail.php' => 'a1a77e77923647a823d91c9814119fc2a338c733',
    'api/verify-payment.php' => 'f7be154a20168df1d3023810e55a2aee556bae6b',
    'api/verify-schema.php' => '093e8164cb17d6f6a4e4806ca75b4aa506e07e41',
    'api/waitlist.php' => '2772f23581b13ec63b0adc12cdbb18aa6399d39e',
    'api/webhook.php' => '3ded8b84b128802ae462cb3a27e8333c9104bcd5',
    'api/wishlist.php' => 'a5110e568e35a1e40875a29d940fe47fd3a91092',
    'automations/api/leads.php' => '796cd9f42b685138bb3fb4fd478641b92a64969e',
    'automations/index.html' => '0f578e98b8429d33ba4f26b06369253d0c21d639',
    'automations/index.php' => '71f0eaf3b8ed7f497d64fe860de55d2dbd99d2a5',
    'jollof-automations/api/leads.php' => 'a9ee10bbb22d7ccdb7129b75b7804511219e86d8',
    'jollof-automations/assets/css/style.css' => '887f68c6a83c28148ef9f58535df277846796f93',
    'jollof-automations/assets/fonts/cg-normal.woff2' => '74db058fe06da79b5dbf5b33d12041ddd45de510',
    'jollof-automations/assets/img/automations-logo-light.png' => '123568ded61ed5dc7593fd27778943c90b7dd0ae',
    'jollof-automations/assets/img/automations-logo-light.svg' => 'f3fb0503b929cd537c7c94b5f55c67f3ed79e8dc',
    'jollof-automations/assets/img/automations-logo-white.png' => '123568ded61ed5dc7593fd27778943c90b7dd0ae',
    'jollof-automations/assets/img/automations-logo-white.svg' => '65ec433234762f2475e54daf1534c9425ba16583',
    'jollof-automations/assets/img/automations-logo.png' => 'f51c6b19188c0bdb08b55f3e6d8e2e1631738677',
    'jollof-automations/assets/img/automations-logo.svg' => '3f3f1e70d618265ab190c9954c01d71948d9178f',
    'jollof-automations/assets/js/app.js' => 'bfb363aa46885030879abcb983fde2690acc5b19',
    'jollof-automations/book-survey.php' => '2ecb6d4e51baf636bb7cff0a0617954022bde405',
    'jollof-automations/case-studies.php' => 'd3d96ab4f174ebc2a71700d4c61090d4d7adbc2e',
    'jollof-automations/config.sample.php' => '33e71cba270751c03e51a04e0151a7d6f9418cdb',
    'jollof-automations/estimator.php' => '2ecaf9879e716788f068d1740054236e991d2872',
    'jollof-automations/includes/bootstrap.php' => '522bacd7367043d6a6c0944a51948eccbcf8d4ff',
    'jollof-automations/includes/db.php' => '23ddc2a8ec250e917b7852a73aef3ca0d2ca53f7',
    'jollof-automations/includes/footer.php' => '8b4427cf6c05426c53aa988bf82a9251f2f14561',
    'jollof-automations/includes/header.php' => 'df9144c8d4812b215894a19083d17df764e4ab4d',
    'jollof-automations/includes/models.php' => '392e4b2fd66efa0746fde7fec1b7575ad5e1b828',
    'jollof-automations/index.html' => 'f7f4ac99982ded5bd2632bdb9882c1c4e33a94ba',
    'jollof-automations/index.php' => '5ab810146358e14ff60c67b504467ff9a9dc8d4a',
    'jollof-automations/lab.php' => '2f6506fe244b18357b740535d61b0c9471e92495',
    'jollof-automations/leads.php' => 'd1c12e501827d6175972f38b3c716844c368e5ab',
    'jollof-automations/process.php' => 'a921267c87e2b5a10ab9dcff46e7f59be4528fd4',
    'jollof-automations/solutions.php' => '1366169dec974861669da68ef6aba89db4b9c3e2',
    'tools/build-assets.mjs' => 'f82ecc72ee0395dbb3d47f4ba6b06792f407ad60',
];

echo "=== Jollof Living self-repair @ " . COMMIT . " ===\n\n";

echo "--- environment ---\n";
echo 'folder      : ' . __DIR__ . "\n";
echo 'PHP         : ' . PHP_VERSION . "\n";
echo 'opcache     : ' . (function_exists('opcache_get_status') ? 'loaded' : 'absent') . "\n";
echo 'disk free   : ' . round((float) @disk_free_space(__DIR__) / 1048576) . " MB\n";
echo 'config.php  : ' . (is_file(__DIR__ . '/includes/config.php') ? "present (kept untouched)\n" : "MISSING - restore it from your backup first!\n");
$weird = [];
foreach (scandir(__DIR__) ?: [] as $name) {
    if (preg_match('/[^\x20-\x7e]/', $name)) {
        $weird[] = bin2hex($name);
    }
}
if ($weird) {
    echo "!! filenames with invisible characters (rename these): " . implode(', ', $weird) . "\n";
}

function jl_fetch(string $url): ?string
{
    $ctx = stream_context_create(['http' => ['timeout' => 25, 'follow_location' => 1, 'ignore_errors' => true]]);
    $body = @file_get_contents($url, false, $ctx);
    if (is_string($body) && $body !== '') {
        return $body;
    }
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 25, CURLOPT_FOLLOWLOCATION => true]);
        $body = curl_exec($ch);
        curl_close($ch);
        return is_string($body) && $body !== '' ? $body : null;
    }
    return null;
}

echo "\n--- repairing " . count($MANIFEST) . " files ---\n";
$ok = 0;
$failed = [];
foreach ($MANIFEST as $path => $expectedSha) {
    $target = __DIR__ . '/' . $path;
    $body = jl_fetch(BASE . $path);
    if ($body === null) {
        echo "FETCH-FAIL   $path\n";
        $failed[] = $path;
        continue;
    }
    if (sha1($body) !== $expectedSha) {
        echo "SHA-MISMATCH $path\n";
        $failed[] = $path;
        continue;
    }
    $dir = dirname($target);
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        echo "MKDIR-FAIL   $path\n";
        $failed[] = $path;
        continue;
    }
    $old = is_file($target) ? (string) filesize($target) : '-';
    if (@file_put_contents($target, $body, LOCK_EX) === false) {
        echo "WRITE-FAIL   $path (permissions?)\n";
        $failed[] = $path;
        continue;
    }
    @chmod($target, 0644);
    @touch($target);
    $ok++;
    echo sprintf("OK  %-52s %8s -> %6s bytes\n", $path, $old, strlen($body));
}

$health = jl_fetch(BASE . 'health.php');
foreach (['health.php', 'status.php'] as $name) {
    if ($health === null) {
        echo "FETCH-FAIL   $name\n";
        continue;
    }
    if (@file_put_contents(__DIR__ . '/' . $name, $health, LOCK_EX) !== false) {
        @chmod(__DIR__ . '/' . $name, 0644);
        @touch(__DIR__ . '/' . $name);
        $ok++;
        echo "OK  $name written (" . strlen($health) . " bytes)\n";
    } else {
        echo "WRITE-FAIL   $name\n";
        $failed[] = $name;
    }
}

if (function_exists('opcache_reset')) {
    @opcache_reset();
    echo "\nopcache: cleared\n";
}

echo "\n=== RESULT: $ok files written, " . count($failed) . " failed ===\n";
if ($failed) {
    echo "Failed: " . implode(', ', $failed) . "\n";
    echo "Re-run this page once. If the same files fail again, the reason\n(FETCH/WRITE/MKDIR) tells you whether it is network or permissions.\n";
}

echo "\n--- now test, in this order ---\n";
echo " 1. hard-refresh the homepage (Ctrl+Shift+R)\n";
echo " 2. diagnostics: health.php - if it 404s, open status.php (same page)\n";
echo " 3. sign in as admin, run install/migrate.php\n";
echo " 4. sitemap: ../sitemap.xml\n";
echo "\nIf anything still fails, the exact cause is in:\n  ../storage/logs/php-error.log  (newest lines at the bottom)\n";
echo "\nDELETE repair.php + health.php + status.php from the server once done.\n";
