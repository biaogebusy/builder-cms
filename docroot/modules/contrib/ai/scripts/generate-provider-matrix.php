<?php

/**
 * @file
 * Regenerates docs/providers/matris.md from live git.drupalcode.org data.
 *
 * Discovers every AI provider plugin published on git.drupalcode.org, works
 * out which operation types each supports, looks up project + release info,
 * and rewrites the provider/operation-type matrix.
 *
 * The script is DETERMINISTIC: given unchanged remote data it produces a
 * byte-identical file (stable sort order, no timestamps, fixed column set).
 *
 * Discovery (see https://git.drupalcode.org/project/ai/-/work_items/3586488):
 *   1. Blobs-search the "project" group (id 2) for the three provider base
 *      types: `extends OpenAiBasedProviderClientBase`,
 *      `extends AiProviderClientBase`, `implements AiProviderInterface`.
 *   2. Page through all results, merge and de-duplicate.
 *   3. Keep only files whose path contains `Plugin/AiProvider`, excluding test
 *      code (`tests/`, `Tests/`) and mirrored checkouts (`/contrib/`).
 *   4. Read each plugin file and extract the operation types it declares in
 *      getSupportedOperationTypes().
 *   5. Look up each owning project: GitLab /projects/{id} for the machine name,
 *      drupal.org node.json for the human title and release status (stable
 *      release? any release at all?).
 *   6. Render the matrix.
 *
 * Usage:
 *   GITLAB_TOKEN=xxxxx php scripts/generate-provider-matrix.php [options]
 *
 * Options:
 *   --write        Overwrite docs/providers/matris.md (default: print to stdout).
 *   --check        Exit 1 if the committed file is out of date (for CI). Implies
 *                  no write. Prints a unified-diff-ish summary on mismatch.
 *   --help         Show this help.
 *
 * A token is required because group blob search needs authentication. Create a
 * read_api token at https://git.drupalcode.org/-/user_settings/personal_access_tokens
 * and export it as GITLAB_TOKEN (or pass GITLAB_PRIVATE_TOKEN).
 */

declare(strict_types=1);

// Security.
if (php_sapi_name() !== 'cli') {
 echo 'This can only be run via command line.';
 die;
}

// -----------------------------------------------------------------------------
// Configuration. Everything that affects output ordering is fixed here.
// -----------------------------------------------------------------------------

const GITLAB_BASE = 'https://git.drupalcode.org/api/v4';
const GITLAB_GROUP_ID = 2;
const DRUPAL_ORG_BASE = 'https://www.drupal.org/api-d7';
const HTTP_PER_PAGE = 100;
const HTTP_TIMEOUT = 30;
const USER_AGENT = 'ai-module-provider-matrix-generator/1.0';

// The searches that identify a provider plugin, each tagged with the kind of
// provider it finds. Order only affects discovery order; results are sorted
// before use, so it does not affect output.
//   - 'ai_provider' files live under Plugin/AiProvider and declare their
//     supported operation types in getSupportedOperationTypes().
//   - 'vdb' files live under Plugin/VdbProvider and serve the Vector Database
//     column (they do not declare matrix operation types).
const SEARCHES = [
  ['extends OpenAiBasedProviderClientBase', 'ai_provider'],
  ['extends AiProviderClientBase', 'ai_provider'],
  ['implements AiProviderInterface', 'ai_provider'],
  ['extends AiVdbProviderClientBase', 'vdb'],
  ['implements VdbProviderInterface', 'vdb'],
];

/**
 * Projects to exclude from the matrix by GitLab project id.
 *
 * 106525 is project/ai itself — the core module ships provider plugins in its
 * submodules, but it is not a "provider" project and should not be a row.
 */
const EXCLUDED_PROJECT_IDS = [106525];

/**
 * Synthetic operation type representing the Vector Database column.
 *
 * VDB providers do not declare matrix operation types; instead every VDB
 * plugin file contributes this token directly.
 */
const VDB_OPERATION_TYPE = 'vector_database';

/**
 * Matrix columns, in the exact order they appear in the published file.
 *
 * Each entry: [heading-with-doc-link, operation_type_machine_name|null].
 * A null machine name means the column is not derivable from an AiProvider
 * plugin (Vector Database is served by a separate VDB provider plugin type
 * that these three searches do not match) — it is kept for layout parity and
 * always left blank by this script.
 */
const COLUMNS = [
  ['[Chat](../developers/call_chat.md)', 'chat'],
  ['[Vector Database](../modules/ai_search/index.md)', VDB_OPERATION_TYPE],
  ['[Embeddings](../developers/call_embeddings.md)', 'embeddings'],
  ['[Moderation](../developers/call_moderation.md)', 'moderation'],
  ['[TextToImage](../developers/call_text_to_image.md)', 'text_to_image'],
  ['[TextToSpeech](../developers/call_text_to_speech.md)', 'text_to_speech'],
  ['[SpeechToText](../developers/call_speech_to_text.md)', 'speech_to_text'],
  ['[SpeechToSpeech](../developers/call_speech_to_speech.md)', 'speech_to_speech'],
  ['[AudioToAudio](../developers/call_audio_to_audio.md)', 'audio_to_audio'],
  ['[TranslateText](../developers/call_translate_text.md)', 'translate_text'],
  ['[ImageClassification](../developers/call_image_classification.md)', 'image_classification'],
  ['[ImageToImage](../developers/call_image_to_image.md)', 'image_to_image'],
  ['[ObjectDetection](../developers/call_object_detection.md)', 'object_detection'],
];

const OUTPUT_RELATIVE_PATH = 'docs/providers/matris.md';

// -----------------------------------------------------------------------------
// Entry point.
// -----------------------------------------------------------------------------

exit(main($argv));

/**
 * Main routine.
 *
 * @param string[] $argv
 *   CLI arguments.
 *
 * @return int
 *   Process exit code.
 */
function main(array $argv): int {
  $opts = parse_args($argv);
  if ($opts['help']) {
    fwrite(STDOUT, extract_file_header(__FILE__));
    return 0;
  }

  $token = getenv('GITLAB_TOKEN') ?: getenv('GITLAB_PRIVATE_TOKEN') ?: '';
  if ($token === '') {
    fwrite(STDERR, "ERROR: set GITLAB_TOKEN (a git.drupalcode.org read_api token).\n");
    return 2;
  }

  try {
    $blobs = discover_provider_blobs($token);
    log_progress(sprintf('Discovered %d candidate plugin file(s) after filtering.', count($blobs)));

    // Group surviving plugin files by owning project.
    $projects = [];
    foreach ($blobs as $blob) {
      $projects[$blob['project_id']]['files'][] = $blob;
    }

    $providers = [];
    foreach ($projects as $project_id => $data) {
      $provider = build_provider_row($token, (int) $project_id, $data['files']);
      if ($provider !== NULL) {
        $providers[] = $provider;
      }
    }

    // Deterministic order: case-insensitive by display name, machine name as
    // tiebreaker.
    usort($providers, static function (array $a, array $b): int {
      return [strtolower($a['name']), $a['machine_name']]
        <=> [strtolower($b['name']), $b['machine_name']];
    });

    $markdown = render_matrix($providers);
  }
  catch (\Throwable $e) {
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . "\n");
    return 1;
  }

  $output_path = dirname(__DIR__) . '/' . OUTPUT_RELATIVE_PATH;

  if ($opts['check']) {
    $current = is_file($output_path) ? file_get_contents($output_path) : '';
    if ($current === $markdown) {
      log_progress('Matrix is up to date.');
      return 0;
    }
    fwrite(STDERR, "Matrix is OUT OF DATE. Re-run with --write to update.\n");
    fwrite(STDERR, diff_summary($current, $markdown));
    return 1;
  }

  if ($opts['write']) {
    file_put_contents($output_path, $markdown);
    log_progress('Wrote ' . OUTPUT_RELATIVE_PATH);
    return 0;
  }

  fwrite(STDOUT, $markdown);
  return 0;
}

// -----------------------------------------------------------------------------
// Discovery.
// -----------------------------------------------------------------------------

/**
 * Runs the three searches, merges, de-duplicates and filters the results.
 *
 * @param string $token
 *   GitLab API token.
 *
 * @return array<int, array{project_id:int, path:string, ref:string}>
 *   Surviving provider plugin blobs, keyed by "project_id:path", sorted.
 */
function discover_provider_blobs(string $token): array {
  $blobs = [];
  foreach (SEARCHES as [$term, $kind]) {
    $results = gitlab_get_all(
      $token,
      sprintf('/groups/%d/search', GITLAB_GROUP_ID),
      ['scope' => 'blobs', 'search' => '"' . $term . '"'],
    );
    log_progress(sprintf('Search %-40s -> %d raw hit(s).', '"' . $term . '"', count($results)));
    foreach ($results as $hit) {
      // Some hits expose only `filename`; older payloads use `path`.
      $path = $hit['path'] ?? $hit['filename'] ?? '';
      $project_id = (int) ($hit['project_id'] ?? 0);
      if ($path === '' || $project_id === 0) {
        continue;
      }
      if (in_array($project_id, EXCLUDED_PROJECT_IDS, TRUE)) {
        continue;
      }
      if (!is_provider_plugin_path($path, $kind)) {
        continue;
      }
      $key = $project_id . ':' . $path;
      $blobs[$key] = [
        'project_id' => $project_id,
        'path' => $path,
        'ref' => $hit['ref'] ?? '',
        'kind' => $kind,
      ];

      // List the raw interface implementers (AiProviderInterface /
      // VdbProviderInterface) as they are discovered, so they are visible in
      // the terminal during a run. Identified by raw project id + file path.
      if (str_starts_with($term, 'implements ')) {
        log_progress(sprintf('  using %s: project %d -> %s', $term, $project_id, $path));
      }
    }
  }

  // Supplementary VDB discovery. Group blob search only indexes each project's
  // DEFAULT branch, so a VDB provider whose default branch has moved to a 2.x
  // base class no longer matches any SEARCHES term and is missed entirely (e.g.
  // ai_vdb_provider_milvus defaults to 2.0.x, where MilvusProvider extends
  // SearchApiAiVdbProviderBase instead of AiVdbProviderClientBase). Re-discover
  // these from their 1.x branch, where the plugin still matches the VDB pattern.
  foreach (discover_vdb_blobs_on_one_x_branches($token) as $blob) {
    // Keyed by project_id:path, so a provider already found on its default
    // branch is de-duplicated rather than added twice.
    $key = $blob['project_id'] . ':' . $blob['path'];
    $blobs[$key] = $blob;
  }

  // Deterministic order so paging/throttling can never reorder output.
  ksort($blobs);
  return array_values($blobs);
}

/**
 * Discovers VDB provider plugins from each VDB project's 1.x branch.
 *
 * Enumerates projects by the `ai_vdb_provider_*` naming convention (group blob
 * search cannot search non-default branches, so name enumeration is the only
 * way to reach a 1.x branch on a project that defaults to 2.x), then looks for
 * a Plugin/VdbProvider plugin matching the VDB pattern on the highest available
 * 1.x / 1.x.x branch.
 *
 * @param string $token
 *   GitLab API token.
 *
 * @return array<int, array{project_id:int, path:string, ref:string, kind:string}>
 *   VDB provider blobs discovered on 1.x branches.
 */
function discover_vdb_blobs_on_one_x_branches(string $token): array {
  // The content patterns that identify a VDB provider plugin, taken from the
  // VDB SEARCHES so there is a single source of truth.
  $vdb_patterns = [];
  foreach (SEARCHES as [$term, $kind]) {
    if ($kind === 'vdb') {
      $vdb_patterns[] = $term;
    }
  }

  $projects = gitlab_get_all($token, sprintf('/groups/%d/projects', GITLAB_GROUP_ID), [
    'search' => 'ai_vdb_provider',
    'simple' => 'true',
    'include_subgroups' => 'true',
  ]);

  $blobs = [];
  foreach ($projects as $project) {
    $project_id = (int) ($project['id'] ?? 0);
    $path = (string) ($project['path'] ?? '');
    if ($project_id === 0 || in_array($project_id, EXCLUDED_PROJECT_IDS, TRUE)) {
      continue;
    }
    // Guard against incidental name matches (e.g. "my_ai_vdb_provider_demo").
    if (!str_starts_with($path, 'ai_vdb_provider')) {
      continue;
    }

    try {
      foreach (one_x_branches($token, $project_id) as $ref) {
        $files = find_vdb_plugin_files($token, $project_id, $ref, $vdb_patterns);
        if ($files === []) {
          continue;
        }
        foreach ($files as $file_path) {
          $blobs[] = [
            'project_id' => $project_id,
            'path' => $file_path,
            'ref' => $ref,
            'kind' => 'vdb',
          ];
        }
        log_progress(sprintf('VDB %-32s -> matched on %s.', $path, $ref));
        // The highest 1.x branch with a match is enough; stop here.
        break;
      }
    }
    catch (\RuntimeException $e) {
      log_progress(sprintf('WARNING: VDB 1.x lookup for %s failed: %s', $path, $e->getMessage()));
    }
  }

  return $blobs;
}

/**
 * Lists a project's 1.x / 1.x.x branches, highest first.
 *
 * Matches branch names like `1.x`, `1.0.x`, `1.1.x` (development branches), not
 * 2.x branches or release tags.
 *
 * @param string $token
 *   GitLab API token.
 * @param int $project_id
 *   GitLab project id.
 *
 * @return string[]
 *   Matching branch names, ordered highest version first.
 */
function one_x_branches(string $token, int $project_id): array {
  $branches = gitlab_get_all($token, sprintf('/projects/%d/repository/branches', $project_id));
  $names = [];
  foreach ($branches as $branch) {
    $name = (string) ($branch['name'] ?? '');
    if (preg_match('/^1\.(\d+\.)?x$/', $name) === 1) {
      $names[] = $name;
    }
  }
  // Prefer the newest 1.x line (1.1.x before 1.0.x before 1.x).
  usort($names, static fn(string $a, string $b): int => version_compare($b, $a));
  return $names;
}

/**
 * Finds Plugin/VdbProvider plugin files matching the VDB pattern on a branch.
 *
 * @param string $token
 *   GitLab API token.
 * @param int $project_id
 *   GitLab project id.
 * @param string $ref
 *   Branch (or other ref) to inspect.
 * @param string[] $vdb_patterns
 *   Content patterns that identify a VDB provider plugin.
 *
 * @return string[]
 *   Repository-relative paths of matching plugin files.
 */
function find_vdb_plugin_files(string $token, int $project_id, string $ref, array $vdb_patterns): array {
  $tree = gitlab_get_all($token, sprintf('/projects/%d/repository/tree', $project_id), [
    'ref' => $ref,
    'recursive' => 'true',
  ]);

  $found = [];
  foreach ($tree as $entry) {
    if (($entry['type'] ?? '') !== 'blob') {
      continue;
    }
    $path = (string) ($entry['path'] ?? '');
    if (!str_ends_with($path, '.php') || !is_provider_plugin_path($path, 'vdb')) {
      continue;
    }
    // Confirm the file actually matches the VDB pattern on this branch.
    $source = gitlab_get_raw_file($token, $project_id, $path, $ref);
    if ($source === NULL) {
      continue;
    }
    foreach ($vdb_patterns as $pattern) {
      if (str_contains($source, $pattern)) {
        $found[] = $path;
        break;
      }
    }
  }

  return $found;
}

/**
 * Decides whether a blob path is a real (non-test, non-mirrored) provider.
 *
 * @param string $path
 *   Repository-relative file path.
 * @param string $kind
 *   Provider kind: 'ai_provider' or 'vdb'.
 *
 * @return bool
 *   TRUE if the path should be treated as a provider plugin.
 */
function is_provider_plugin_path(string $path, string $kind): bool {
  // Must be a provider plugin of the expected kind.
  $needle = $kind === 'vdb' ? 'Plugin/VdbProvider' : 'Plugin/AiProvider';
  if (!str_contains($path, $needle)) {
    return FALSE;
  }
  // Exclude test code. Match path segments so we do not trip on substrings.
  if (preg_match('#(^|/)(tests|Tests)/#', $path) === 1) {
    return FALSE;
  }
  // Exclude mirrored full-site checkouts (the ai module vendored under a site).
  if (str_contains($path, '/contrib/')) {
    return FALSE;
  }
  return TRUE;
}

// -----------------------------------------------------------------------------
// Per-provider assembly.
// -----------------------------------------------------------------------------

/**
 * Builds a single provider row from its plugin file(s) and project metadata.
 *
 * @param string $token
 *   GitLab API token.
 * @param int $project_id
 *   GitLab project id.
 * @param array<int, array{project_id:int, path:string, ref:string}> $files
 *   The provider plugin blobs found in this project.
 *
 * @return array|null
 *   The provider row, or NULL if the project could not be resolved.
 */
function build_provider_row(string $token, int $project_id, array $files): ?array {
  // 1. GitLab project metadata -> machine name + default branch + fallback name.
  $project = gitlab_get($token, '/projects/' . $project_id);
  if ($project === NULL) {
    log_progress(sprintf('WARNING: could not load project %d, skipping.', $project_id));
    return NULL;
  }
  $machine_name = (string) ($project['path'] ?? '');
  $default_branch = (string) ($project['default_branch'] ?? '');
  $fallback_name = (string) ($project['name'] ?? $machine_name);

  // 2. Union the operation types declared across this project's plugin files.
  //    VDB plugins contribute the synthetic Vector Database type directly;
  //    AiProvider plugins are parsed for getSupportedOperationTypes().
  $operation_types = [];
  foreach ($files as $file) {
    if (($file['kind'] ?? 'ai_provider') === 'vdb') {
      $operation_types[VDB_OPERATION_TYPE] = TRUE;
      continue;
    }
    $ref = $file['ref'] !== '' ? $file['ref'] : $default_branch;
    $source = gitlab_get_raw_file($token, $project_id, $file['path'], $ref);
    if ($source === NULL) {
      continue;
    }
    foreach (extract_operation_types($source) as $type) {
      $operation_types[$type] = TRUE;
    }
  }

  // 3. drupal.org project node: human title + release status.
  $release = lookup_drupal_org_release_status($machine_name);
  $name = $release['title'] !== '' ? $release['title'] : $fallback_name;

  return [
    'machine_name' => $machine_name,
    'name' => normalize_provider_name($name),
    'url' => 'https://www.drupal.org/project/' . $machine_name,
    'operation_types' => $operation_types,
    'release_status' => $release['status'],
  ];
}

/**
 * Strips a leading "AI Provider " prefix from a human-readable project name.
 *
 * "AI Provider Baidu" -> "Baidu", "AI Provider moonshot (Kimi)" ->
 * "moonshot (Kimi)". Matches case-insensitively. Names like "AI Providers API"
 * (plural) and "{X} Provider" are intentionally left untouched.
 *
 * @param string $name
 *   The human-readable provider name.
 *
 * @return string
 *   The normalized name.
 */
function normalize_provider_name(string $name): string {
  $stripped = preg_replace('/^ai\s+provider\s+/i', '', $name, 1);
  $stripped = trim((string) $stripped);
  // Never collapse a name to empty (e.g. a project literally named
  // "AI Provider").
  return $stripped !== '' ? $stripped : trim($name);
}

/**
 * The operation types the matrix knows how to detect.
 *
 * Derived from COLUMNS (the single source of truth for the matrix layout) so a
 * column can never be added without extraction also learning about it. The
 * synthetic Vector Database type is excluded: it is contributed directly by VDB
 * plugin files, not parsed out of getSupportedOperationTypes().
 *
 * @return string[]
 *   Operation type machine names.
 */
function known_operation_types(): array {
  static $types = NULL;
  if ($types === NULL) {
    $types = array_values(array_filter(
      array_column(COLUMNS, 1),
      static fn(?string $type): bool => $type !== NULL && $type !== VDB_OPERATION_TYPE,
    ));
  }
  return $types;
}

/**
 * Extracts operation type machine names from a provider's source code.
 *
 * Pulls the body of getSupportedOperationTypes() (brace-balanced) and collects
 * every quoted token that matches a known operation type. This is robust to
 * array_merge(), multiple return statements and multi-line arrays.
 *
 * @param string $source
 *   PHP source of the provider plugin.
 *
 * @return string[]
 *   Sorted, unique list of operation type machine names.
 */
function extract_operation_types(string $source): array {
  $body = extract_method_body($source, 'getSupportedOperationTypes');
  if ($body === NULL) {
    return [];
  }
  $known = known_operation_types();
  $found = [];
  if (preg_match_all('/[\'"]([a-z_]+)[\'"]/', $body, $matches) > 0) {
    foreach ($matches[1] as $token) {
      if (in_array($token, $known, TRUE)) {
        $found[$token] = TRUE;
      }
    }
  }
  $found = array_keys($found);
  sort($found);
  return $found;
}

/**
 * Returns the brace-balanced body of a method, or NULL if not found.
 *
 * Uses PHP's tokenizer rather than scanning raw characters, so that braces
 * appearing inside string literals or comments in the method body cannot
 * prematurely close the balance.
 *
 * @param string $source
 *   PHP source.
 * @param string $method
 *   Method name.
 *
 * @return string|null
 *   The text between the method's outermost { and }, or NULL.
 */
function extract_method_body(string $source, string $method): ?string {
  $tokens = token_get_all($source);
  $count = count($tokens);

  // Locate `function <method>`: a T_FUNCTION followed (past whitespace) by a
  // T_STRING matching the method name.
  $start = NULL;
  for ($i = 0; $i < $count; $i++) {
    if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) {
      continue;
    }
    for ($j = $i + 1; $j < $count; $j++) {
      if (is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
        continue;
      }
      if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING && $tokens[$j][1] === $method) {
        $start = $j;
      }
      break;
    }
    if ($start !== NULL) {
      break;
    }
  }
  if ($start === NULL) {
    return NULL;
  }

  // Balance braces from the body's opening "{", counting only real brace
  // tokens. T_CURLY_OPEN / T_DOLLAR_OPEN_CURLY_BRACES are string-interpolation
  // openers whose matching close is a plain "}" token, so they stay balanced;
  // braces inside string/comment token text are never separate tokens.
  $depth = 0;
  $in_body = FALSE;
  $body = '';
  for ($i = $start; $i < $count; $i++) {
    $token = $tokens[$i];
    $is_open = $token === '{'
      || (is_array($token) && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], TRUE));
    $is_close = $token === '}';

    if ($is_open) {
      $depth++;
      if (!$in_body) {
        // The body's opening brace — start capturing after it.
        $in_body = TRUE;
        continue;
      }
    }
    elseif ($is_close) {
      $depth--;
      if ($depth === 0) {
        return $body;
      }
    }

    if ($in_body) {
      $body .= is_array($token) ? $token[1] : $token;
    }
  }
  return NULL;
}

/**
 * Looks up a project's human title and release status on drupal.org.
 *
 * @param string $machine_name
 *   drupal.org project machine name.
 *
 * @return array{title:string, status:string}
 *   status is one of: 'stable', 'prerelease', 'none', 'unknown'.
 */
function lookup_drupal_org_release_status(string $machine_name): array {
  $default = ['title' => '', 'status' => 'unknown'];
  if ($machine_name === '') {
    return $default;
  }

  // drupal.org is a best-effort enrichment: a transient outage, rate limit or
  // unexpected status must never abort the whole run (GitLab data alone is
  // enough to render a row). Any failure falls back to the default.
  try {
    $project = drupal_org_get('/node.json', ['field_project_machine_name' => $machine_name]);
    $node = $project['list'][0] ?? NULL;
    if ($node === NULL) {
      return $default;
    }

    $title = (string) ($node['title'] ?? '');
    $has_releases = !empty($node['field_project_has_releases']);
    if (!$has_releases) {
      return ['title' => $title, 'status' => 'none'];
    }

    // Inspect the release nodes (referenced by project node id) to tell a
    // stable release from pre-release/dev-only. A stable release has an empty
    // field_release_version_extra and is published (status === "1").
    $nid = (string) ($node['nid'] ?? '');
    $status = 'prerelease';
    if ($nid !== '') {
      $releases = drupal_org_get('/node.json', [
        'field_release_project' => $nid,
        'type' => 'project_release',
        'limit' => 100,
      ]);
      $any = FALSE;
      foreach ($releases['list'] ?? [] as $release) {
        if ((string) ($release['status'] ?? '') !== '1') {
          continue;
        }
        $any = TRUE;
        $extra = $release['field_release_version_extra'] ?? NULL;
        if ($extra === NULL || $extra === '') {
          return ['title' => $title, 'status' => 'stable'];
        }
      }
      if (!$any) {
        // has_releases flagged but no published release node visible.
        $status = 'none';
      }
    }
    return ['title' => $title, 'status' => $status];
  }
  catch (\RuntimeException $e) {
    log_progress(sprintf(
      'WARNING: drupal.org lookup for "%s" failed (%s); treating release status as unknown.',
      $machine_name,
      $e->getMessage(),
    ));
    return $default;
  }
}

// -----------------------------------------------------------------------------
// Rendering.
// -----------------------------------------------------------------------------

/**
 * Renders the full matrix markdown document.
 *
 * @param array $providers
 *   Sorted provider rows.
 *
 * @return string
 *   The markdown document.
 */
function render_matrix(array $providers): string {
  $headings = array_map(static fn(array $c): string => $c[0], COLUMNS);

  $lines = [];
  $lines[] = '# Table of Providers and Operation Types';
  $lines[] = '';
  $lines[] = 'The full list of providers available.';
  $lines[] = '';
  $lines[] = '| Provider Name | ' . implode(' | ', $headings) . ' | Release |';
  $lines[] = '| --- ' . str_repeat('| --- ', count($headings) + 1) . '|';

  foreach ($providers as $provider) {
    $cells = [];
    $cells[] = sprintf('[%s](%s)', $provider['name'], $provider['url']);
    foreach (COLUMNS as [$heading, $type]) {
      $supported = $type !== NULL && !empty($provider['operation_types'][$type]);
      $cells[] = $supported ? '☑' : '';
    }
    $cells[] = release_label($provider['release_status']);
    $lines[] = '| ' . implode(' | ', $cells) . ' |';
  }

  $lines[] = '';
  $lines[] = 'This file is generated by `scripts/generate-provider-matrix.php`.';
  $lines[] = 'Do not edit it by hand; run the script to regenerate it instead.';
  $lines[] = '';

  return implode("\n", $lines);
}

/**
 * Maps a release status to its display label.
 */
function release_label(string $status): string {
  return match ($status) {
    'stable' => 'Stable',
    'prerelease' => 'Pre-release only',
    'none' => 'No release',
    default => 'Unknown',
  };
}

// -----------------------------------------------------------------------------
// HTTP helpers.
// -----------------------------------------------------------------------------

/**
 * GETs a single GitLab API resource as decoded JSON.
 *
 * @return array|null
 *   Decoded body, or NULL on a 404.
 */
function gitlab_get(string $token, string $path, array $query = []): ?array {
  [$status, $body] = http_request(
    GITLAB_BASE . $path . ($query ? '?' . http_build_query($query) : ''),
    ['PRIVATE-TOKEN: ' . $token],
  );
  if ($status === 404) {
    return NULL;
  }
  if ($status < 200 || $status >= 300) {
    throw new \RuntimeException("GitLab GET $path failed with HTTP $status.");
  }
  return json_decode($body, TRUE) ?? [];
}

/**
 * GETs every page of a paginated GitLab list endpoint.
 *
 * @return array
 *   All items across all pages.
 */
function gitlab_get_all(string $token, string $path, array $query = []): array {
  $items = [];
  $page = 1;
  do {
    $url = GITLAB_BASE . $path . '?' . http_build_query($query + [
      'per_page' => HTTP_PER_PAGE,
      'page' => $page,
    ]);
    [$status, $body, $headers] = http_request($url, ['PRIVATE-TOKEN: ' . $token]);
    if ($status < 200 || $status >= 300) {
      throw new \RuntimeException("GitLab GET $path (page $page) failed with HTTP $status.");
    }
    $decoded = json_decode($body, TRUE);
    if (is_array($decoded)) {
      foreach ($decoded as $item) {
        $items[] = $item;
      }
    }
    $next = trim($headers['x-next-page'] ?? '');
    $page = $next !== '' ? (int) $next : 0;
  } while ($page > 0);

  return $items;
}

/**
 * Fetches a raw repository file. Returns NULL if it cannot be read.
 */
function gitlab_get_raw_file(string $token, int $project_id, string $path, string $ref): ?string {
  $url = sprintf(
    '%s/projects/%d/repository/files/%s/raw?ref=%s',
    GITLAB_BASE,
    $project_id,
    rawurlencode($path),
    rawurlencode($ref),
  );
  [$status, $body] = http_request($url, ['PRIVATE-TOKEN: ' . $token]);
  return ($status >= 200 && $status < 300) ? $body : NULL;
}

/**
 * GETs a drupal.org API resource as decoded JSON.
 */
function drupal_org_get(string $path, array $query = []): array {
  [$status, $body] = http_request(
    DRUPAL_ORG_BASE . $path . ($query ? '?' . http_build_query($query) : ''),
  );
  if ($status < 200 || $status >= 300) {
    throw new \RuntimeException("drupal.org GET $path failed with HTTP $status.");
  }
  return json_decode($body, TRUE) ?? [];
}

/**
 * Performs an HTTP GET, retrying on transient failures.
 *
 * @return array{0:int, 1:string, 2:array<string,string>}
 *   [status code, body, lowercased response headers].
 */
function http_request(string $url, array $headers = []): array {
  $attempts = 0;
  while (TRUE) {
    $attempts++;
    $ch = curl_init($url);
    $response_headers = [];
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => TRUE,
      CURLOPT_FOLLOWLOCATION => TRUE,
      CURLOPT_TIMEOUT => HTTP_TIMEOUT,
      CURLOPT_USERAGENT => USER_AGENT,
      CURLOPT_HTTPHEADER => array_merge(['Accept: application/json'], $headers),
      CURLOPT_HEADERFUNCTION => function ($curl, string $line) use (&$response_headers): int {
        $parts = explode(':', $line, 2);
        if (count($parts) === 2) {
          $response_headers[strtolower(trim($parts[0]))] = trim($parts[1]);
        }
        return strlen($line);
      },
    ]);
    $body = curl_exec($ch);
    $errno = curl_errno($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Retry transient network errors and 429/5xx, with linear backoff.
    $transient = $errno !== 0 || $status === 429 || $status >= 500;
    if ($transient && $attempts < 4) {
      usleep(500000 * $attempts);
      continue;
    }
    if ($errno !== 0) {
      throw new \RuntimeException("HTTP request to $url failed: curl errno $errno.");
    }
    return [$status, (string) $body, $response_headers];
  }
}

// -----------------------------------------------------------------------------
// Small utilities.
// -----------------------------------------------------------------------------

/**
 * Parses recognised CLI flags.
 *
 * @return array{write:bool, check:bool, help:bool}
 *   Parsed options.
 */
function parse_args(array $argv): array {
  $flags = array_slice($argv, 1);
  return [
    'write' => in_array('--write', $flags, TRUE),
    'check' => in_array('--check', $flags, TRUE),
    'help' => in_array('--help', $flags, TRUE) || in_array('-h', $flags, TRUE),
  ];
}

/**
 * Builds a line-by-line diff of two documents for the --check CI log.
 *
 * Not a full LCS diff — it compares positionally, prefixing removed lines with
 * "-" and added lines with "+" — but it is enough to show CI exactly which rows
 * changed when the matrix is out of date.
 *
 * @param string $old
 *   The committed document.
 * @param string $new
 *   The freshly generated document.
 *
 * @return string
 *   The diff summary, terminated with a newline (empty string if identical).
 */
function diff_summary(string $old, string $new): string {
  $old_lines = explode("\n", $old);
  $new_lines = explode("\n", $new);
  $max = max(count($old_lines), count($new_lines));

  $out = [];
  for ($i = 0; $i < $max; $i++) {
    $o = $old_lines[$i] ?? NULL;
    $n = $new_lines[$i] ?? NULL;
    if ($o === $n) {
      continue;
    }
    if ($o !== NULL) {
      $out[] = '- ' . $o;
    }
    if ($n !== NULL) {
      $out[] = '+ ' . $n;
    }
  }
  return $out === [] ? '' : implode("\n", $out) . "\n";
}

/**
 * Writes a progress line to stderr (keeps stdout clean for the matrix).
 */
function log_progress(string $message): void {
  fwrite(STDERR, $message . "\n");
}

/**
 * Returns the file-level docblock text for --help.
 */
function extract_file_header(string $file): string {
  $source = (string) file_get_contents($file);
  if (preg_match('#/\*\*.*?\*/#s', $source, $m) === 1) {
    $text = preg_replace('#^\s*/?\*+/?#m', '', $m[0]);
    return trim((string) $text) . "\n";
  }
  return "See the docblock at the top of this file.\n";
}
