<?php
// GitHub Plugin Updater Class einfügen
if (!class_exists('WP_GitHub_Updater')) {
    error_log("GitHub Updater geladen");

    class WP_GitHub_Updater {
        private $pluginFile;
        private $githubRepo;
        private $pluginData;
        private $githubAPIResult;
        private $slug;

        public function __construct($pluginFile) {
            $this->pluginFile = $pluginFile;
            $this->slug = 'omonsch_customer_backend'; // Hardcoded slug to ensure the directory name
            $this->githubRepo = 'Me4th/omonsch-customer-backend'; // GitHub-Repository

            add_filter("pre_set_site_transient_update_plugins", [$this, "setPluginTransient"]);
            add_filter("plugins_api", [$this, "setPluginInfo"], 10, 3);
        }

        // GitHub Release-Info abrufen
        private function getRepoReleaseInfo() {
            if (is_null($this->githubAPIResult)) {
                error_log("Versuche, GitHub-Release-Info abzurufen...");

                $requestUri = "https://api.github.com/repos/{$this->githubRepo}/releases/latest";
                $token = defined('GITHUB_API_TOKEN') ? GITHUB_API_TOKEN : '';
                $args = [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $token,
                        'User-Agent' => 'WordPress-Updater'
                    ]
                ];
                $response = wp_remote_get($requestUri, $args);

                // Überprüfen, ob die API-Antwort erfolgreich war
                if (is_wp_error($response)) {
                    error_log("Fehler: API-Anfrage fehlgeschlagen - " . $response->get_error_message());
                    return null;
                }

                $body = wp_remote_retrieve_body($response);
                $this->githubAPIResult = json_decode($body, true);

                if (empty($this->githubAPIResult)) {
                    error_log("Fehler: Leere oder ungültige API-Antwort erhalten.");
                } else {
                    error_log("GitHub API Full Response: " . print_r($this->githubAPIResult, true));
                }
            } else {
                error_log("GitHub-Release-Info wurde bereits abgerufen.");
            }
            return $this->githubAPIResult;
        }

        // Transient für Plugin-Update setzen
        public function setPluginTransient($transient) {
            error_log("Starte setPluginTransient...");

            if (empty($transient->checked)) {
                error_log("Kein Plugin-Check in diesem Transient-Durchlauf.");
                return $transient;
            }

            $releaseInfo = $this->getRepoReleaseInfo();

            // Prüfen, ob tag_name vorhanden ist und loggen, falls nicht
            if (!isset($releaseInfo['tag_name']) || !isset($releaseInfo['zipball_url'])) {
                error_log("Fehler: tag_name oder zipball_url fehlen in der GitHub-Antwort.");
                return $transient;
            }

            $latestVersion = $releaseInfo['tag_name'];
            $currentVersion = $transient->checked[$this->pluginFile] ?? '';
            error_log("Aktuelle Plugin-Version: " . $currentVersion);
            error_log("Neueste Version auf GitHub: " . $latestVersion);

            // Prüfen und Transient setzen, wenn Update verfügbar
            if (version_compare($currentVersion, $latestVersion, '<')) {
                $transient->response[$this->pluginFile] = (object) [
                    'slug' => 'omonsch_customer_backend', // Harte Kodierung des Plugin-Ordners
                    'new_version' => $latestVersion,
                    'package' => $releaseInfo['zipball_url'],
                    'plugin' => 'omonsch_customer_backend/omonsch_customer_backend.php' // Vollständiger Pfad zur Haupt-Plugin-Datei
                ];
                error_log("Update für Plugin verfügbar: " . print_r($transient->response[$this->pluginFile], true));
            } else {
                error_log("Kein Update für Plugin erforderlich.");
            }

            return $transient;
        }

        // Plugin-Informationen für den WordPress-Updater bereitstellen
        public function setPluginInfo($result, $action, $args) {
            error_log("Rufe setPluginInfo auf...");

            if ($action !== 'plugin_information' || $args->slug !== 'omonsch_customer_backend') {
                error_log("Aktion oder Slug stimmen nicht überein. Aktion: $action, Slug: " . $args->slug);
                return $result;
            }

            $releaseInfo = $this->getRepoReleaseInfo();

            // Überprüfen, ob tag_name und zipball_url vorhanden sind
            if (!isset($releaseInfo['tag_name']) || !isset($releaseInfo['zipball_url'])) {
                error_log("Fehler: tag_name oder zipball_url fehlen in der GitHub-Antwort.");
                return $result;
            }

            $pluginInfo = (object) [
                'name' => 'Oliver Monschau - Customer Backend',
                'slug' => 'omonsch_customer_backend', // Harte Kodierung des Plugin-Ordners
                'version' => $releaseInfo['tag_name'],
                'download_link' => $releaseInfo['zipball_url'],
                'plugin' => 'omonsch_customer_backend/omonsch_customer_backend.php' // Vollständiger Pfad zur Haupt-Plugin-Datei
            ];

            error_log("Plugin-Informationen erfolgreich bereitgestellt: " . print_r($pluginInfo, true));
            return $pluginInfo;
        }
    }
}
?>
