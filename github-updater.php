<?php
// GitHub Plugin Updater Class einfügen
if (!class_exists('WP_GitHub_Updater')) {
    error_log("GitHub Updater geladen");
    class WP_GitHub_Updater {
        private $pluginFile;
        private $githubRepo;
        private $pluginData;
        private $githubAPIResult;

        public function __construct($pluginFile) {
            $this->pluginFile = $pluginFile;
            $this->slug = plugin_basename($pluginFile); // Sicherstellen, dass der Slug richtig gesetzt ist
            $this->githubRepo = 'Me4th/omonsch-customer-backend'; // Repository-Name

            add_filter("pre_set_site_transient_update_plugins", [$this, "setPluginTransient"]);
            add_filter("plugins_api", [$this, "setPluginInfo"], 10, 3);
        }

        // Plugin-Update-Information abrufen
        private function getRepoReleaseInfo() {
            if (is_null($this->githubAPIResult)) {
                $requestUri = "https://api.github.com/repos/{$this->githubRepo}/releases/latest";
                $token = defined('GITHUB_API_TOKEN') ? GITHUB_API_TOKEN : '';
                $args = [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $token,
                        'User-Agent' => 'WordPress-Updater'
                    ]
                ];
                $response = wp_remote_get($requestUri, $args);
                $this->githubAPIResult = json_decode(wp_remote_retrieve_body($response), true);

                // Log für vollständige Antwort zur Überprüfung
                error_log("GitHub API Full Response with Token: " . print_r($this->githubAPIResult, true));
            }
            return $this->githubAPIResult;
        }

        // Transienten für Plugin-Update setzen
        public function setPluginTransient($transient) {
            if (empty($transient->checked)) {
                return $transient;
            }
            $releaseInfo = $this->getRepoReleaseInfo();
            $latestVersion = $releaseInfo['tag_name'];
            $currentVersion = $transient->checked[$this->pluginFile];
            error_log("Aktuelle Version: " . $currentVersion);
            error_log("Neueste Version auf GitHub: " . $latestVersion);
            if (version_compare($currentVersion, $latestVersion, '<')) {
                $transient->response[$this->pluginFile] = (object) [
                    'slug' => $this->pluginFile,
                    'new_version' => $latestVersion,
                    'package' => $releaseInfo['zipball_url']
                ];
            }
            if (!empty($transient->response[$this->pluginFile])) {
                error_log("Update für Plugin verfügbar: " . print_r($transient->response[$this->pluginFile], true));
            } else {
                error_log("Kein Update verfügbar.");
            }
            return $transient;
        }

        // Plugin-Informationen für den WordPress-Updater bereitstellen
        public function setPluginInfo($result, $action, $args) {
            if ($args->slug !== $this->slug) return $result; // Korrektur beim Slug-Vergleich

            $releaseInfo = $this->getRepoReleaseInfo();
            return (object) [
                'name' => 'Oliver Monschau - Customer Backend',
                'slug' => $this->slug,
                'version' => $releaseInfo['tag_name'],
                'download_link' => $releaseInfo['zipball_url'],
            ];
        }
    }
}
// Temporären Upgrade-Pfad umbenennen
add_filter('upgrader_source_selection', function($source, $remote_source, $upgrader) {
    $plugin_slug = 'omonsch-customer-backend';
    $corrected_path = trailingslashit(dirname($source)) . $plugin_slug;

    error_log("Original-Ordnerpfad im Upgrade-Verzeichnis: {$source}");
    error_log("Umbenennung im temporären Verzeichnis nach: {$corrected_path}");

    if (basename($source) !== $plugin_slug) {
        if (rename($source, $corrected_path)) {
            error_log("Temporärer Ordner erfolgreich in {$corrected_path} umbenannt.");
            return $corrected_path;
        } else {
            error_log("Fehler beim Umbenennen des temporären Ordners.");
            return new WP_Error('rename_failed', __('Umbenennen des temporären Plugin-Ordners fehlgeschlagen.'));
        }
    }
    return $source;
}, 10, 3);

// Finalen Plugin-Ordnerpfad überprüfen und ggf. korrigieren
add_filter('upgrader_post_install', function ($response, $hook_extra, $result) {
    $plugin_slug = 'omonsch-customer-backend';
    $corrected_path = trailingslashit(WP_PLUGIN_DIR) . $plugin_slug;

    error_log("Entpackter Zielordner im Plugins-Verzeichnis: " . $result['destination']);
    error_log("Erwarteter finaler Ordnerpfad: {$corrected_path}");

    // Überprüfen und Umbenennen im finalen Plugin-Verzeichnis
    if (basename($result['destination']) !== $plugin_slug) {
        if (rename($result['destination'], $corrected_path)) {
            error_log("Finaler Ordnername erfolgreich in {$corrected_path} geändert.");
            $result['destination'] = $corrected_path;
        } else {
            error_log("Fehler beim Umbenennen des finalen Plugin-Ordners.");
            return new WP_Error('rename_failed', __('Umbenennen des finalen Plugin-Ordners fehlgeschlagen.'));
        }
    } else {
        error_log("Finaler Ordnername korrekt, keine Umbenennung erforderlich.");
    }

    return $response;
}, 10, 3);
