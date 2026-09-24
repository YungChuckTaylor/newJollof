<?php
/**
 * Jollof Automations — legacy root entry point.
 * The subsidiary site is canonically served under /automations/
 * (see .htaccess); this stub keeps the old URL working with a
 * permanent redirect so search engines consolidate the two.
 */
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

/* redirect() resolves through base_path(), so this 301 works both from the
   document root and from a sub-folder install. */
redirect('automations/', 301);
