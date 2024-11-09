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

                // Ausführliches Log der API-Antwort zur Überprüfung
                error_log("GitHub API Full Response: " . print_r($this->githubAPIResult, true));
            }
            return $this->githubAPIResult;
        }

        // Transient für Plugin-Update setzen
        public function setPluginTransient($transient) {
            if (empty($transient->checked)) {
                return $transient;
            }
            $releaseInfo = $this->getRepoReleaseInfo();

            // Sicherstellen, dass die tag_name vorhanden ist
            if (isset($releaseInfo['tag_name'])) {
                $latestVersion = $releaseInfo['tag_name'];
            } else {
                error_log("Fehler: tag_name fehlt in der GitHub-Antwort.");
                return $transient;
            }

            $currentVersion = $transient->checked[$this->pluginFile] ?? '';
            error_log("Aktuelle Version: " . $currentVersion);
            error_log("Neueste Version auf GitHub: " . $latestVersion);

            // Prüfen und Transient setzen, wenn Update verfügbar
            if (version_compare($currentVersion, $latestVersion, '<')) {
                $transient->response[$this->pluginFile] = (object) [
                    'slug' => 'omonsch_customer_backend', // Hardcoded slug for consistent directory naming
                    'new_version' => $latestVersion,
                    'package' => $releaseInfo['zipball_url']
                ];
                error_log("Update für Plugin verfügbar: " . print_r($transient->response[$this->pluginFile], true));
            } else {
                error_log("Kein Update verfügbar.");
            }

            return $transient;
        }

        // Plugin-Informationen für den WordPress-Updater bereitstellen
        public function setPluginInfo($result, $action, $args) {
            if ($action !== 'plugin_information' || $args->slug !== 'omonsch_customer_backend') {
                return $result;
            }

            $releaseInfo = $this->getRepoReleaseInfo();

            // Sicherstellen, dass tag_name und zipball_url existieren
            if (!isset($releaseInfo['tag_name']) || !isset($releaseInfo['zipball_url'])) {
                error_log("Fehler: tag_name oder zipball_url fehlen in der GitHub-Antwort.");
                return $result;
            }

            $pluginInfo = (object) [
                'name' => 'Oliver Monschau - Customer Backend',
                'slug' => 'omonsch_customer_backend', // Hardcoded slug
                'version' => $releaseInfo['tag_name'],
                'download_link' => $releaseInfo['zipball_url'],
            ];

            error_log("Plugin-Info erfolgreich bereitgestellt: " . print_r($pluginInfo, true));
            return $pluginInfo;
        }
    }
}
?>
