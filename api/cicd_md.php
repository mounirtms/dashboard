<?php
/**
 * Shared markdown -> HTML helpers + docs catalog for the dashboard "Reports & Docs" hub.
 * Required on demand by api/cicd.php actions `docs` and `doc`.
 */

function _md_inline(string $text): string {
    $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text);
    $text = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $text);
    $text = preg_replace('/(?<!\*)\*([^*]+)\*(?!\*)/', '<em>$1</em>', $text);
    $text = preg_replace('/\~\~([^~]+)\~\~/', '<del>$1</del>', $text);
    $text = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2" target="_blank" rel="noopener">$1</a>', $text);
    return $text;
}

function _md_to_html(string $md): string {
    $md = str_replace(["\r\n", "\r"], "\n", $md);
    $blocks = [];
    while (preg_match('/```(\w*)\n(.*?)```/s', $md, $m)) {
        $ph = "\n__CB" . count($blocks) . "__\n";
        $lang = $m[1] ? " class=\"language-{$m[1]}\"" : '';
        $blocks[$ph] = '<pre><code' . $lang . '>' . htmlspecialchars($m[2], ENT_QUOTES, 'UTF-8') . '</code></pre>';
        $md = preg_replace('/```(\w*)\n(.*?)```/s', $ph, $md, 1);
    }
    $md = preg_replace_callback('/^\|(.+)\|\s*$\n^\|[\s\-|:]+\|\s*$\n((?:^\|.+\|\s*$\n?)+)/m', function ($m) {
        $header = array_map('trim', explode('|', $m[1]));
        $header = array_filter($header, fn($h) => $h !== '');
        preg_match_all('/^\|.+\|\s*$/', $m[2], $dm);
        $rows = [];
        foreach ($dm[0] as $line) {
            $cells = array_map('trim', explode('|', $line));
            $cells = array_filter($cells, fn($c) => $c !== '');
            if (count($cells) === count($header)) $rows[] = $cells;
        }
        $out = "<table>\n<thead><tr>";
        foreach ($header as $h) $out .= "<th>" . _md_inline($h) . "</th>";
        $out .= "</tr></thead>\n<tbody>\n";
        foreach ($rows as $row) {
            $out .= "<tr>";
            foreach ($row as $c) $out .= "<td>" . _md_inline($c) . "</td>";
            $out .= "</tr>\n";
        }
        return $out . "</tbody>\n</table>\n";
    }, $md);
    $md = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $md);
    $md = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $md);
    $md = preg_replace('/^# (.+)$/m', '<h1>$1</h1>', $md);
    $md = preg_replace('/^(?:---|\*\*\*|___)$/m', '<hr>', $md);
    // Blockquotes: collapse consecutive "> …" lines into <blockquote><p>…</p></blockquote>.
    $md = preg_replace_callback('/(?:^>[^\n]*\n?)+/m', function ($m) {
        $inner = preg_replace('/^>[ ]?/m', '', rtrim($m[0]));
        $out = "<blockquote>\n";
        foreach (array_filter(explode("\n\n", $inner)) as $p) {
            $out .= "<p>" . _md_inline(trim(str_replace("\n", ' ', $p))) . "</p>\n";
        }
        return $out . "</blockquote>\n";
    }, $md);
    $md = preg_replace_callback('/^(?:   )?(?:[-*]) (.+)$/m', fn($m) => "<li>" . _md_inline($m[1]) . "</li>", $md);
    $md = preg_replace('/((?:<li>.*<\/li>\n?)+)/', '<ul>$1</ul>', $md);
    $md = preg_replace_callback('/^(?:   )?(\d+)\. (.+)$/m', fn($m) => "<li>" . _md_inline($m[2]) . "</li>", $md);
    $md = preg_replace('/((?:<li>.*<\/li>\n?)+)/', '<ol>$1</ol>', $md);
    $lines = explode("\n", $md);
    $out = '';
    $buf = '';
    foreach ($lines as $line) {
        $t = trim($line);
        if ($t === '') {
            if ($buf !== '') { $out .= "<p>" . _md_inline(trim($buf)) . "</p>\n"; $buf = ''; }
            $out .= "\n";
        } elseif (preg_match('/^<(h[1-3]|ul|ol|li|pre|table|thead|tbody|tr|th|td|hr)/', $t)) {
            if ($buf !== '') { $out .= "<p>" . _md_inline(trim($buf)) . "</p>\n"; $buf = ''; }
            $out .= $line . "\n";
        } else {
            $buf .= ($buf ? ' ' : '') . $line;
        }
    }
    if ($buf !== '') $out .= "<p>" . _md_inline(trim($buf)) . "</p>\n";
    $md = $out;
    foreach ($blocks as $ph => $html) $md = str_replace($ph, $html, $md);
    return $md;
}

/** Catalog every markdown report/doc reachable from the dashboard docs hub. */
function _cicd_doc_catalog(): array {
    $base = realpath(__DIR__ . '/..');
    if ($base === false) return [];
    $patterns = [
        __DIR__ . '/../docs/*.md',        // docs/ root
        __DIR__ . '/../docs/*/*.md',      // docs/CI_CD, docs/archive …
        __DIR__ . '/../docs/*/*/*.md',    // deeper nesting
        __DIR__ . '/../*.md',             // root-level report copies (CICD-FULL-REPORT.md …)
    ];
    $paths = [];
    foreach ($patterns as $g) foreach ((array) glob($g) as $p) $paths[] = $p;
    $rules = [
        'CI/CD & Deployment'    => '/cicd|ci[\/_-]?cd|cd_july|cd\.md|deploy|pipeline|runner|release|promote|gitlab/i',
        'Security & Audit'      => '/secur|audit|ssh|hardening|vulnerab|csp/i',
        'Backup & Recovery'     => '/backup|rollback|restore/i',
        'Email & Notifications' => '/email|mail|notif|telegram|push/i',
        'Performance & Cache'   => '/varnish|cache|load|performance|optimiz|mariadb|queue/i',
        'Sessions & Summaries'  => '/session|summary|finalized|beta|quick_wins|plan/i',
    ];
    $docs = [];
    foreach (array_unique($paths) as $p) {
        $real = realpath($p);
        if (!$real || !is_file($real) || substr($real, -3) !== '.md') continue;
        if (strpos($real, $base . DIRECTORY_SEPARATOR) !== 0) continue;
        $rel = str_replace('\\', '/', substr($real, strlen($base) + 1));
        $title = basename($rel, '.md');
        $fh = @fopen($real, 'r');
        if ($fh) {
            $n = 0;
            while (($line = fgets($fh)) !== false && $n < 60) {
                $n++;
                if (preg_match('/^#\s+(.+)$/', rtrim($line), $m)) { $title = trim($m[1]); break; }
            }
            fclose($fh);
        }
        $cat = 'General';
        foreach ($rules as $name => $rx) if (preg_match($rx, $rel)) { $cat = $name; break; }
        $docs[] = [
            'file'     => $rel,
            'title'    => $title,
            'category' => $cat,
            'bytes'    => (int) filesize($real),
            'mtime'    => date('Y-m-d', (int) filemtime($real)),
            'featured' => $rel === 'docs/CD_JULY1_VS_TODAY.md',
        ];
    }
    usort($docs, function ($a, $b) {
        if ($a['featured'] !== $b['featured']) return $a['featured'] ? -1 : 1;
        return [$a['category'], $a['title']] <=> [$b['category'], $b['title']];
    });
    return $docs;
}
