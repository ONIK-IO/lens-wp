<?php

namespace OnikImages\Updates;

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

/**
 * Wires the plugin into WordPress's native update flow using GitHub releases as
 * the source of truth.
 *
 * The public distribution repo (ONIK-IO/lens-wp) publishes a GitHub release per
 * version: build.sh --push opens the release PR, and the repo's release.yml tags
 * `v<version>` on merge and attaches a single installable asset, `onik-lens.zip`,
 * rooted at `onik-lens/`. Yahnis Elsts' plugin-update-checker polls those releases
 * and surfaces "update available" in wp-admin exactly like a WP.org plugin.
 *
 * Two release conventions matter here:
 *   - Releases are published as PRE-RELEASES and promoted by hand. The checker's
 *     GitHub API ignores drafts and prereleases by default, so only a promoted
 *     ("latest") release is ever offered as an update — no unvetted build reaches
 *     users. This is the same guarantee the /releases/latest/ download URL gives.
 *   - We MUST download the attached asset, not GitHub's auto-generated source zip,
 *     which is rooted at `lens-wp-<tag>/` and would install under the wrong folder.
 *     enableReleaseAssets() switches the checker to the attached onik-lens.zip.
 */
class UpdateChecker
{
    private const REPO_URL   = 'https://github.com/ONIK-IO/lens-wp/';
    private const ASSET_REGEX = '/onik-lens\.zip$/';

    /**
     * @param string $pluginFile Absolute path to the main plugin file
     *                           (its basename is what WordPress identifies the
     *                           plugin by, and its header carries the version the
     *                           checker compares against each release tag).
     */
    public static function register(string $pluginFile): void
    {
        // Defensive: the library ships in the built vendor tree, but never assume
        // it — a broken/partial install must not fatal the whole plugin.
        if (!class_exists(PucFactory::class)) {
            return;
        }

        $checker = PucFactory::buildUpdateChecker(
            self::REPO_URL,
            $pluginFile,
            'onik-lens'
        );

        $api = $checker->getVcsApi();
        if (method_exists($api, 'enableReleaseAssets')) {
            $api->enableReleaseAssets(self::ASSET_REGEX);
        }
    }
}
