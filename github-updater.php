<?php
if (!class_exists('WP_GitHub_Updater')) {
    error_log("[WP_GitHub_Updater] GitHub Updater geladen");

    class WP_GitHub_Updater {
        private $pluginFile;
        private $githubRepo;
        private $githubAPIResult;
        private $slug;

        public function __construct($pluginFile) {
            $this->pluginFile = $pluginFile;
            $this->slug = 'omonsch_customer_backend'; // Hardcoded slug
            $this->githubRepo = 'Me4th/omonsch-customer-backend';

            add_filter("pre_set_site_transient_update_plugins", [$this, "setPluginTransient"]);
            add_filter("plugins_api", [$this, "setPluginInfo"], 10, 3);
            add_filter("upgrader_source_selection", [$this, "renameAndMoveFolder"], 10, 3);
        }

        private function getRepoReleaseInfo() {
            if (is_null($this->githubAPIResult)) {
                error_log("[getRepoReleaseInfo] Versuche, GitHub-Release-Info abzurufen...");

                $requestUri = "https://api.github.com/repos/{$this->githubRepo}/releases/latest";
                $token = defined('GITHUB_API_TOKEN') ? GITHUB_API_TOKEN : '';
                $args = [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $token,
                        'User-Agent' => 'WordPress-Updater'
                    ]
                ];
                $response = wp_remote_get($requestUri, $args);

                if (is_wp_error($response)) {
                    error_log("[getRepoReleaseInfo] Fehler: API-Anfrage fehlgeschlagen - " . $response->get_error_message());
                    return null;
                }

                $body = wp_remote_retrieve_body($response);
                $this->githubAPIResult = json_decode($body, true);

                if (empty($this->githubAPIResult)) {
                    error_log("[getRepoReleaseInfo] Fehler: Leere oder ungültige API-Antwort erhalten.");
                } else {
                    error_log("[getRepoReleaseInfo] GitHub API Full Response: " . print_r($this->githubAPIResult, true));
                }
            } else {
                error_log("[getRepoReleaseInfo] GitHub-Release-Info wurde bereits abgerufen.");
            }
            return $this->githubAPIResult;
        }

        public function setPluginTransient($transient) {
            error_log("[setPluginTransient] Starte setPluginTransient...");

            if (empty($transient->checked)) {
                error_log("[setPluginTransient] Kein Plugin-Check in diesem Transient-Durchlauf.");
                return $transient;
            }

            $releaseInfo = $this->getRepoReleaseInfo();

            if (!isset($releaseInfo['tag_name']) || !isset($releaseInfo['zipball_url'])) {
                error_log("[setPluginTransient] Fehler: tag_name oder zipball_url fehlen in der GitHub-Antwort.");
                return $transient;
            }

            $latestVersion = $releaseInfo['tag_name'];
            $currentVersion = $transient->checked[$this->pluginFile] ?? '';

            if (version_compare($currentVersion, $latestVersion, '<')) {
                $transient->response[$this->pluginFile] = (object) [
                    'slug' => 'omonsch_customer_backend',
                    'new_version' => $latestVersion,
                    'package' => $releaseInfo['zipball_url'],
                    'plugin' => 'omonsch_customer_backend/omonsch_customer_backend.php'
                ];
                error_log("[setPluginTransient] Update für Plugin verfügbar: " . print_r($transient->response[$this->pluginFile], true));
            } else {
                error_log("[setPluginTransient] Kein Update für Plugin erforderlich.");
            }

            return $transient;
        }

        public function setPluginInfo($result, $action, $args) {
            error_log("[setPluginInfo] Rufe setPluginInfo auf...");

            if ($action !== 'plugin_information' || $args->slug !== 'omonsch_customer_backend') {
                error_log("[setPluginInfo] Aktion oder Slug stimmen nicht überein. Aktion: $action, Slug: " . $args->slug);
                return $result;
            }

            $releaseInfo = $this->getRepoReleaseInfo();

            if (!isset($releaseInfo['tag_name']) || !isset($releaseInfo['zipball_url'])) {
                error_log("[setPluginInfo] Fehler: tag_name oder zipball_url fehlen in der GitHub-Antwort.");
                return $result;
            }

            $pluginInfo = (object) [
                'name' => 'Oliver Monschau - Customer Backend',
                'slug' => 'omonsch_customer_backend',
                'version' => $releaseInfo['tag_name'],
                'download_link' => $releaseInfo['zipball_url'],
                'plugin' => 'omonsch_customer_backend/omonsch_customer_backend.php'
            ];

            error_log("[setPluginInfo] Plugin-Informationen erfolgreich bereitgestellt: " . print_r($pluginInfo, true));
            return $pluginInfo;
        }

        public function renameAndMoveFolder($source, $remote_source, $upgrader) {
            global $wp_filesystem;

            $finalDest = WP_PLUGIN_DIR . '/omonsch_customer_backend';
            error_log("[renameAndMoveFolder] Original extrahiertes Verzeichnis: $source");

            if (strpos(basename($source), 'omonsch-customer-backend') !== false) {
                // Lösche das alte Verzeichnis, falls vorhanden
                if ($wp_filesystem->is_dir($finalDest)) {
                    $wp_filesystem->delete($finalDest, true);
                    error_log("[renameAndMoveFolder] Altes Plugin-Verzeichnis gelöscht: $finalDest");
                }

                if ($wp_filesystem->move($source, $finalDest)) {
                    error_log("[renameAndMoveFolder] Verzeichnis erfolgreich an Zielort '$finalDest' verschoben.");
                    return $finalDest;
                } else {
                    error_log("[renameAndMoveFolder] Fehler beim Verschieben des Verzeichnisses.");
                    return new WP_Error('move_failed', 'Das Plugin-Verzeichnis konnte nicht an den Zielort verschoben werden.');
                }
            }

            error_log("[renameAndMoveFolder] Kein Umbenennen/Verschieben erforderlich.");
            return $source;
        }
    }
}
?>
