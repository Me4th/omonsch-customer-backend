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
            $this->slug = 'omonsch_customer_backend';
            $this->githubRepo = 'Me4th/omonsch-customer-backend';

            add_filter("pre_set_site_transient_update_plugins", [$this, "setPluginTransient"]);
            add_filter("plugins_api", [$this, "setPluginInfo"], 10, 3);
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

            if (!isset($releaseInfo['assets'][0]['browser_download_url'])) {
                error_log("[setPluginTransient] Fehler: Release-Asset fehlt.");
                return $transient;
            }

            $latestVersion = $releaseInfo['tag_name'];
            $currentVersion = $transient->checked[$this->pluginFile] ?? '';

            if (version_compare($currentVersion, $latestVersion, '<')) {
                $downloadUrl = $releaseInfo['assets'][0]['browser_download_url'];
                $transient->response[$this->pluginFile] = (object) [
                    'slug' => 'omonsch_customer_backend',
                    'new_version' => $latestVersion,
                    'package' => $downloadUrl,
                    'plugin' => 'omonsch_customer_backend/omonsch_customer_backend.php'
                ];
                error_log("[setPluginTransient] Update für Plugin verfügbar mit direktem Download-Link: $downloadUrl");
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

            if (!isset($releaseInfo['tag_name']) || !isset($releaseInfo['assets'][0]['browser_download_url'])) {
                error_log("[setPluginInfo] Fehler: tag_name oder Download-Asset fehlen in der GitHub-Antwort.");
                return $result;
            }

            $pluginInfo = (object) [
                'name' => 'Oliver Monschau - Customer Backend',
                'slug' => 'omonsch_customer_backend',
                'version' => $releaseInfo['tag_name'],
                'download_link' => $releaseInfo['assets'][0]['browser_download_url'],
                'plugin' => 'omonsch_customer_backend/omonsch_customer_backend.php'
            ];

            error_log("[setPluginInfo] Plugin-Informationen erfolgreich bereitgestellt: " . print_r($pluginInfo, true));
            return $pluginInfo;
        }
    }
}
?>
